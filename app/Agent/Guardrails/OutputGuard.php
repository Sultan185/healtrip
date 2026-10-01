<?php

namespace App\Agent\Guardrails;

use App\Models\Doctor;
use App\Models\Hospital;

/**
 * Checks model-written prose against the grounding ledger before a patient
 * sees it.
 *
 * Provider cards are rendered from the database and cannot be wrong; free
 * text is the one place a made-up or misdescribed provider could still
 * surface. This guard looks for a named clinician or hospital, a phone
 * number or a link, and fails the text unless every provider it names has
 * been shown to the patient on a recommendation card. A name alone is not
 * enough: beside a card, the facts about that provider are on screen and
 * come from the database, whatever the prose says about them.
 *
 * It is a pattern check, so it is a backstop and not a proof. It knows the
 * names the model has been given (its search results) however they are
 * written, and the conventional ways of naming anyone else ("Dr. X",
 * "د. X", "X Hospital"). A name the model invented and wrote with no title
 * is beyond it.
 */
class OutputGuard
{
    private const LINK = '/https?:\/\/\S+|\bwww\.\S+|[\w.+-]+@[\w-]+\.[\w.-]+/iu';

    private const ISO_DATE = '/\b\d{4}-\d{2}-\d{2}\b/';

    private const PHONE = '/\+?\d[\d\s().\-]{7,}\d/';

    private const DOCTOR_EN = '/\b(?:Dr|Prof|Doctor|Professor)\.?\s+([A-Z][\p{L}\'’\-]+)(?:\s+([A-Z][\p{L}\'’\-]+))?/u';

    private const DOCTOR_AR = '/(?<![\p{L}])(?:ا\.?\s?)?د\s?[.\/]\s*([\p{Arabic}]+)(?:\s+([\p{Arabic}]+))?/u';

    /** The title written out: "الدكتور أحمد", "دكتورة منى". */
    private const DOCTOR_AR_TITLE = '/(?<![\p{L}])(ال)?دكتور[هة]?\s+([\p{Arabic}]+)/u';

    private const HOSPITAL_EN = '/\b((?:[A-Z][\p{L}\'’\-]+\s+){1,5})(Hospital|Clinic|Institute|Medical Cent(?:er|re)|Medical City|Heart Cent(?:er|re))\b/u';

    private const GENERIC_LEAD = '/^(?:(?:the|a|an|at|to|in|from|and|or|is|visit|contact|call|your|any|another|this|that|nearest|local|closest|private|public|emergency)(?:\s+|$))+/u';

    public function inspect(string $text, GroundingLedger $ledger): GuardVerdict
    {
        $violations = [];
        $doctors = $ledger->presentedDoctors();
        $hospitals = $ledger->presentedHospitals();

        foreach ($this->matches(self::LINK, $text) as $link) {
            $violations[] = ['type' => 'link', 'value' => $link[0]];
        }

        // The model is never given a provider's phone number (cards show
        // them), so any full-length number in its prose is unverified. Short
        // numbers (112, 911, 997) are emergency lines and are fine.
        $withoutDates = (string) preg_replace(self::ISO_DATE, ' ', $this->westernDigits($text));

        foreach ($this->matches(self::PHONE, $withoutDates) as $phone) {
            if (strlen($this->digits($phone[0])) >= 9) {
                $violations[] = ['type' => 'phone', 'value' => trim($phone[0])];
            }
        }

        $doctorNamesEn = array_values($doctors->map(fn (Doctor $doctor) => $this->tokens($doctor->name_en))->all());
        $doctorNamesAr = array_values($doctors->map(fn (Doctor $doctor) => $this->tokens($this->foldArabic($doctor->name_ar)))->all());

        foreach ($this->matches(self::DOCTOR_EN, $text) as $match) {
            if (! $this->isKnownName($match[1], $match[2] ?? null, $doctorNamesEn)) {
                $violations[] = ['type' => 'doctor', 'value' => trim($match[0])];
            }
        }

        $arabic = $this->foldArabic($text);

        foreach ($this->matches(self::DOCTOR_AR, $arabic) as $match) {
            // Arabic has no capital letters, so the word after the name cannot
            // be told apart from a surname: only the first name is checked.
            if (! $this->isKnownName($match[1], null, $doctorNamesAr)) {
                $violations[] = ['type' => 'doctor', 'value' => trim($match[0])];
            }
        }

        foreach ($this->matches(self::DOCTOR_AR_TITLE, $arabic) as $match) {
            if (! $this->isKnownName($match[2], null, $doctorNamesAr) && $this->namesSomeone($match[1] !== '', $match[2])) {
                $violations[] = ['type' => 'doctor', 'value' => trim($match[0])];
            }
        }

        // The model knows the names in its search results whether or not it
        // has shown them, and can write them with no title at all. Those are
        // matched by name, in either language.
        $lower = mb_strtolower($text);

        foreach ($ledger->unpresentedDoctors() as $doctor) {
            if (str_contains($lower, mb_strtolower($doctor->name_en)) || str_contains($arabic, $this->foldArabic($doctor->name_ar))) {
                $violations[] = ['type' => 'doctor', 'value' => $doctor->name_en];
            }
        }

        foreach ($ledger->unpresentedHospitals() as $hospital) {
            if (str_contains($lower, mb_strtolower($hospital->name_en)) || str_contains($arabic, $this->foldArabic($hospital->name_ar))) {
                $violations[] = ['type' => 'hospital', 'value' => $hospital->name_en];
            }
        }

        $hospitalNames = $hospitals->map(fn (Hospital $hospital) => mb_strtolower($hospital->name_en))->all();

        foreach ($this->matches(self::HOSPITAL_EN, $text) as $match) {
            // Drop capitalised words swept up with the name that are not part
            // of one ("The Nearest Hospital" names nothing).
            $name = (string) preg_replace(self::GENERIC_LEAD, '', mb_strtolower(trim($match[1])));

            if ($name === '') {
                continue;
            }

            $candidate = $name.' '.mb_strtolower($match[2]);
            $known = array_filter($hospitalNames, fn (string $known) => str_contains($known, $candidate) || str_contains($candidate, $known));

            if ($known === []) {
                $violations[] = ['type' => 'hospital', 'value' => trim($match[0])];
            }
        }

        return new GuardVerdict($violations);
    }

    /**
     * The written-out title is also used descriptively ("الدكتور المعالج",
     * the treating doctor; "دكتور قلب", a heart doctor), so the word after it
     * is not always a name. After the definite title, a word without the
     * article is a name. After the bare title it could be either, so only a
     * name that exists in the directory is treated as one.
     */
    private function namesSomeone(bool $definiteTitle, string $word): bool
    {
        if ($definiteTitle) {
            return ! str_starts_with($word, 'ال');
        }

        return Doctor::query()
            ->pluck('name_ar')
            ->flatMap(fn (string $name) => $this->tokens($this->foldArabic($name)))
            ->contains($word);
    }

    /**
     * A written name is grounded when it matches one presented doctor: the
     * first written word must be part of that doctor's name, and so must the
     * second when there is one ("Dr. Kaya" and "Dr. Elif Kaya" both pass,
     * "Dr. Elif Smith" does not).
     *
     * @param  list<list<string>>  $knownNames  Each known name as lower-case tokens.
     */
    private function isKnownName(string $first, ?string $second, array $knownNames): bool
    {
        $first = $this->bareName($first);
        $second = $second !== null ? $this->bareName($second) : null;

        foreach ($knownNames as $tokens) {
            if (in_array($first, $tokens, true) && ($second === null || in_array($second, $tokens, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lower-case, without a possessive ending ("Kaya's" is "kaya").
     */
    private function bareName(string $word): string
    {
        return (string) preg_replace('/[\'’]s?$/u', '', mb_strtolower($word));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $name): array
    {
        return array_values(array_filter((array) preg_split('/\s+/u', mb_strtolower(trim($name)))));
    }

    /**
     * @return list<array<int, string>>
     */
    private function matches(string $pattern, string $text): array
    {
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        return $matches;
    }

    private function digits(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function westernDigits(string $text): string
    {
        return strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    private function foldArabic(string $text): string
    {
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);
    }
}
