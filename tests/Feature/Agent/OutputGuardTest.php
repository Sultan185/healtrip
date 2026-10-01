<?php

use App\Agent\Guardrails\GroundingLedger;
use App\Agent\Guardrails\OutputGuard;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->guard = new OutputGuard;

    // Two cardiologists in Istanbul, found by a search and then shown to the
    // patient on a recommendation card.
    $doctors = Doctor::whereIn('name_en', ['Elif Kaya', 'Kerem Yildiz'])->get();
    $this->ledger = new GroundingLedger;
    $this->ledger->record($doctors->modelKeys(), $doctors->pluck('hospital_id')->unique()->all());
    $this->ledger->markPresented($doctors, new Collection);
});

it('passes text that only names providers shown on a card', function (string $text) {
    expect($this->guard->inspect($text, $this->ledger)->passed())->toBeTrue();
})->with([
    'full name' => ['Dr. Elif Kaya focuses on chest pain evaluation and is available on 2026-10-03.'],
    'surname only' => ['Dr. Kaya or Dr. Yildiz would both be a good fit.'],
    'possessive' => ["Dr. Kaya's focus is chest pain; Dr. Elif Kaya’s next slot is this week."],
    'hospital' => ['Both work at Bosphorus Heart & Vascular Institute in Istanbul.'],
    'arabic name' => ['أنسب خيار هو د. إليف كايا في إسطنبول.'],
    'arabic title written out' => ['الدكتورة إليف كايا متاحة هذا الأسبوع.'],
    'arabic descriptive use of the title' => ['يُفضّل أن تراجع الدكتور المعالج أو دكتور قلب قريبًا.'],
    'no providers at all' => ['How long have you had the pain, and does it get worse when you walk?'],
    'emergency numbers' => ['Call 112 now, or 911 if you are in North America.'],
    'generic hospital' => ['Please go to the nearest hospital. The Hospital staff will assess you.'],
]);

it('fails text that names a provider the patient has not been shown', function (string $text, string $type) {
    $verdict = $this->guard->inspect($text, $this->ledger);

    expect($verdict->passed())->toBeFalse()->and($verdict->types())->toContain($type);
})->with([
    'invented doctor' => ['I recommend Dr. John Smith, a leading cardiologist.', 'doctor'],
    'right first name, wrong surname' => ['Dr. Elif Smith can see you tomorrow.', 'doctor'],
    'real doctor from the directory that was not retrieved' => ['Dr. Katharina Vogel in Frankfurt is excellent.', 'doctor'],
    'invented arabic doctor' => ['أنصحك بزيارة د. محمود السيد.', 'doctor'],
    'arabic title written out, doctor not shown' => ['هناك الدكتور أحمد الشرقاوي في القاهرة.', 'doctor'],
    'bare arabic title with a directory name' => ['يمكنك زيارة دكتور أحمد.', 'doctor'],
    'english title written out' => ['Doctor John Smith is available tomorrow.', 'doctor'],
    'invented hospital' => ['You could also try Acibadem Maslak Hospital.', 'hospital'],
    'phone number' => ['You can reach them on +90 212 555 01 10.', 'phone'],
    'arabic-digit phone number' => ['اتصل على ٠٠٩٠٢١٢٥٥٥٠١١٠', 'phone'],
    'link' => ['Book at https://example.com/book', 'link'],
]);

it('does not accept a provider that was only retrieved, not presented', function () {
    // The search also returned this doctor, but no card has shown her: prose
    // about her would have nothing verified beside it.
    $retrievedOnly = Doctor::where('name_en', 'Mona Abdel-Rahman')->firstOrFail();
    $this->ledger->record([$retrievedOnly->id], [$retrievedOnly->hospital_id]);

    $verdict = $this->guard->inspect('Dr. Mona Abdel-Rahman is an oncologist in Cairo.', $this->ledger);

    expect($verdict->types())->toBe(['doctor']);
});

it('recognises a retrieved provider by name alone, in either language', function (string $text, string $type) {
    // What the small model did in the live evaluation: list a doctor from its
    // search results, with no title and no card.
    $retrievedOnly = Doctor::where('name_en', 'Ahmed El-Sharkawy')->firstOrFail();
    $this->ledger->record([$retrievedOnly->id], [$retrievedOnly->hospital_id]);

    expect($this->guard->inspect($text, $this->ledger)->types())->toBe([$type]);
})->with([
    'arabic, no title' => ['١. أحمد الشرقاوي - له خبرة 30 سنة ويتحدث أربع لغات.', 'doctor'],
    'english, no title' => ['1. Ahmed El-Sharkawy has 30 years of experience.', 'doctor'],
    'arabic hospital name' => ['يعمل في مركز وادي النيل للقلب.', 'hospital'],
]);
