<?php

namespace App\Agent\Guardrails;

use App\Enums\Urgency;

/**
 * A deterministic screen for emergency language, run on every patient
 * message before the model sees it.
 *
 * It exists so that showing emergency instructions never depends on the
 * model behaving: if these phrases appear, the banner is shown. It is a
 * keyword net, not a triage engine. It is tuned to over-trigger (a banner
 * shown needlessly costs little, one missed costs a lot) and the model, which
 * can read context, decides what to say next.
 */
class RedFlagScreener
{
    /**
     * Emergency phrases that are themselves negations ("can't breathe") and
     * so must be matched before negated clauses are stripped.
     *
     * @var array<string, string>
     */
    private const NEGATED_EMERGENCIES = [
        'breathing' => '/\b(can\'?t|cannot|can ?not|unable to|struggling to|hard to) breathe\b|\bnot breathing\b|مش قادر[هة]? ا?تنفس|لا استطيع التنفس|مش عارف[هة]? ا?تنفس|لا يتنفس/u',
        'bleeding' => '/\b(won\'?t|will not|doesn\'?t|does not) stop bleeding\b|نزيف (لا|مش|ما) (يتوقف|بيقف|بيوقف)/u',
        'consciousness' => '/\b(not responding|won\'?t wake|unresponsive)\b|لا يستجيب|مش بيرد|فاقد الوعي/u',
    ];

    /**
     * @var array<string, string>
     */
    private const EMERGENCIES = [
        'stroke' => '/\b(face (is )?droop\w*|slurred speech|sudden (weakness|numbness|confusion)|sudden(ly)? (lost|loss of) vision|worst headache of my life)\b|تنميل مفاجئ|ضعف مفاجئ|ثقل في الكلام|تلعثم مفاجئ|اعوجاج (في )?الوجه|ارتخاء (في )?الوجه|شلل مفاجئ/u',
        'breathing' => '/\b(choking|turning blue|gasping for (air|breath))\b|اختناق|ازرقاق/u',
        'bleeding' => '/\b(severe bleeding|bleeding heavily|coughing up blood|vomiting blood)\b|نزيف شديد|نزيف حاد|قيء دموي|سعال دموي|برجع دم|بكح دم/u',
        'consciousness' => '/\b(unconscious|passed out|fainted|seizure|convulsions?)\b|فقد(ت)? الوعي|فقدان الوعي|اغماء|اغمي علي|تشنج|تشنجات|نوبه صرع/u',
        'self_harm' => '/\b(suicid\w*|kill myself|end my life|want to die|overdosed?)\b|انتحار|انتحر|اقتل نفسي|انهي حياتي|اموت نفسي|جرعه زائده/u',
        'allergic_reaction' => '/\b(anaphyla\w*|throat is (closing|swelling)|swelling of (the |my )?(tongue|throat))\b|تورم (في )?(اللسان|الحلق)|حساسيه شديده/u',
    ];

    private const CHEST_PAIN = '/\bchest (pain|pressure|tightness|discomfort)\b|\b(pain|pressure|tightness) in (my|the|his|her) chest\b|(الم|وجع|ضغط|ضيق|ثقل) (في |ب|علي )?(ال)?صدر/u';

    /**
     * Chest pain is an emergency when it comes with any of these.
     */
    private const CARDIAC_COMPANIONS = '/\b(short(ness)? of breath|breathless|sweating|sweaty|cold sweat|left arm|jaw|radiat\w*|spread\w*|crushing|nausea|light-?headed|faint\w*)\b|ضيق (في )?(ال)?تنفس|صعوبه (في )?(ال)?تنفس|نهجان|تعرق|عرق|ذراع|دراع|كتف|الفك|غثيان|دوخه/u';

    /**
     * Symptoms that are not an emergency on their own but must be triaged
     * before anything else is discussed.
     *
     * @var array<string, string>
     */
    private const NEEDS_TRIAGE = [
        'palpitations' => '/\b(palpitations?|heart (is )?(racing|pounding)|irregular heartbeat)\b|خفقان|ضربات القلب (سريعه|غير منتظمه)/u',
        'severe_pain' => '/\b(severe|unbearable|excruciating) (pain|headache)\b|الم (شديد|حاد|لا يحتمل)|صداع (شديد|حاد)/u',
    ];

    /**
     * A negation word and everything up to the end of its clause: the next
     * punctuation mark, or "and" / "but", which usually start a new one
     * ("no sweating but my arm hurts").
     */
    private const NEGATED_CLAUSE = '/(\b(no|not|never|without|none|denies|don\'?t have|do not have|doesn\'?t have|does not have|haven\'?t|hasn\'?t)\b|(?<![\p{L}])(لا يوجد|لا اعاني|لا اشعر|مفيش|مافيش|ما في|ما عندي|معنديش|بدون|من غير|ليس|لم|لا|مش)(?![\p{L}]))'
        .'(?:(?!\b(?:and|but|however)\b|(?<![\p{L}])(?:لكن|بس)(?![\p{L}]))[^.,;:!?\n،؛؟])*/u';

    public function screen(string $message): ScreenResult
    {
        $text = $this->normalize($message);
        $emergencies = [];

        foreach (self::NEGATED_EMERGENCIES as $category => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $emergencies[] = $category;
            }
        }

        // "No shortness of breath, no sweating" is the patient answering a
        // triage question, not reporting those symptoms.
        $affirmed = (string) preg_replace(self::NEGATED_CLAUSE, ' ', $text);

        foreach (self::EMERGENCIES as $category => $pattern) {
            if (preg_match($pattern, $affirmed) === 1) {
                $emergencies[] = $category;
            }
        }

        $chestPain = preg_match(self::CHEST_PAIN, $affirmed) === 1;

        if ($chestPain && preg_match(self::CARDIAC_COMPANIONS, $affirmed) === 1) {
            $emergencies[] = 'cardiac';
        }

        if ($emergencies !== []) {
            return new ScreenResult(Urgency::Emergency, array_values(array_unique($emergencies)));
        }

        $triage = $chestPain ? ['chest_pain'] : [];

        foreach (self::NEEDS_TRIAGE as $category => $pattern) {
            if (preg_match($pattern, $affirmed) === 1) {
                $triage[] = $category;
            }
        }

        return $triage === []
            ? ScreenResult::clear()
            : new ScreenResult(Urgency::Urgent, $triage);
    }

    /**
     * Lower-case, and fold the Arabic spelling variants people type
     * interchangeably (hamza forms, taa marbuta, alef maqsura, diacritics) so
     * one pattern matches all of them.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['’', '‘'], "'", $text);
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
    }
}
