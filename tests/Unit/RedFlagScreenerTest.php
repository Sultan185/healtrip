<?php

use App\Agent\Guardrails\RedFlagScreener;
use App\Enums\Urgency;

function screen(string $message): array
{
    $result = (new RedFlagScreener)->screen($message);

    return [$result->level, $result->categories];
}

it('flags emergency language as an emergency', function (string $message, string $category) {
    [$level, $categories] = screen($message);

    expect($level)->toBe(Urgency::Emergency)
        ->and($categories)->toContain($category);
})->with([
    'chest pain with breathlessness' => ['I have chest pain and shortness of breath', 'cardiac'],
    'chest pain spreading to the arm' => ['Crushing chest pain spreading to my left arm', 'cardiac'],
    'stroke signs' => ['My father has slurred speech and his face is drooping', 'stroke'],
    'cannot breathe' => ["I can't breathe", 'breathing'],
    'bleeding that will not stop' => ["The cut won't stop bleeding", 'bleeding'],
    'self harm' => ['I want to end my life', 'self_harm'],
    'arabic chest pain with breathlessness' => ['عندي ألم في الصدر وضيق في التنفس', 'cardiac'],
    'arabic chest pain with sweating, unaccented spelling' => ['الم بالصدر مع تعرق شديد', 'cardiac'],
    'arabic cannot breathe (egyptian)' => ['مش قادر اتنفس', 'breathing'],
    'arabic loss of consciousness' => ['والدي فقد الوعي فجأة', 'consciousness'],
    'arabic self harm' => ['أفكر في الانتحار', 'self_harm'],
]);

it('asks for triage, not an ambulance, for chest pain with no other sign', function (string $message) {
    [$level, $categories] = screen($message);

    expect($level)->toBe(Urgency::Urgent)
        ->and($categories)->toContain('chest_pain');
})->with([
    'the brief\'s example' => ["I have chest pain and I'm not sure whether I should see a cardiologist, go to the ER, or seek a second opinion."],
    'arabic' => ['عندي وجع في صدري من يومين'],
]);

it('does not treat symptoms the patient denies as present', function (string $message, ?Urgency $expected) {
    expect(screen($message)[0])->toBe($expected);
})->with([
    'answers to triage questions' => ['Chest pain when I press on it. No shortness of breath, no sweating, no pain in my arm.', Urgency::Urgent],
    'denied chest pain' => ['No chest pain, I just want a routine heart check-up.', null],
    'arabic denial' => ['ألم في الصدر، لا يوجد ضيق في التنفس ولا تعرق', Urgency::Urgent],
]);

it('still sees a symptom reported after a denial', function () {
    expect(screen('I have chest pain. No fever but I am sweating a lot.')[0])->toBe(Urgency::Emergency);
});

it('stays quiet for ordinary requests', function (string $message) {
    expect(screen($message)[0])->toBeNull();
})->with([
    'I need a knee replacement and want a second opinion in Istanbul',
    'أبحث عن طبيب عظام في القاهرة يتحدث العربية',
    'What does a cardiology consultation cost?',
]);
