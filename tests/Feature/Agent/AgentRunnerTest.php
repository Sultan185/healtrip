<?php

use App\Agent\AgentEvent;
use App\Agent\Llm\LlmException;
use App\Enums\MessageKind;
use App\Enums\Urgency;
use App\Models\Doctor;
use App\Models\Recommendation;
use App\Models\ToolCall;
use Tests\Support\ScriptedLlmClient as Model;

it('asks a clarifying question without touching the directory', function () {
    $model = scriptModel(Model::says('How long have you had the pain, and does it spread to your arm or jaw?'));
    $conversation = conversation();

    $events = runTurn($conversation, 'I want to see a heart doctor');

    expect(eventTypes($events))->toBe([AgentEvent::TURN_STARTED, AgentEvent::THINKING, AgentEvent::MESSAGE, AgentEvent::DONE])
        ->and(firstEvent($events, AgentEvent::MESSAGE)->data['text'])->toContain('How long')
        ->and($model->requests)->toHaveCount(1)
        ->and(ToolCall::count())->toBe(0);
});

it('searches the directory, then recommends by ID, and the card comes from the database', function () {
    scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Istanbul'])),
        fn (array $messages) => Model::calls('present_recommendation', [
            'urgency' => 'routine',
            'care_path' => 'specialist_visit',
            'specialty' => 'cardiology',
            'summary' => 'A cardiology consultation is the right next step.',
            'doctor_ids' => array_column(Model::lastToolResult($messages)['doctors'], 'doctor_id'),
            'hospital_ids' => [],
        ]),
        Model::says('These two cardiologists in Istanbul match what you described.'),
    );
    $conversation = conversation();

    $events = runTurn($conversation, 'I need a cardiologist in Istanbul');

    expect(eventTypes($events))->toBe([
        AgentEvent::TURN_STARTED,
        AgentEvent::THINKING, AgentEvent::TOOL_STARTED, AgentEvent::TOOL_FINISHED,
        AgentEvent::THINKING, AgentEvent::TOOL_STARTED, AgentEvent::TOOL_FINISHED, AgentEvent::RECOMMENDATION,
        AgentEvent::THINKING, AgentEvent::MESSAGE, AgentEvent::DONE,
    ]);

    $card = firstEvent($events, AgentEvent::RECOMMENDATION)->data;
    $elif = Doctor::where('name_en', 'Elif Kaya')->firstOrFail();

    expect(array_column($card['doctors'], 'id'))->toContain($elif->id)
        ->and($card['doctors'][0]['name'])->toBe(['en' => $elif->name_en, 'ar' => $elif->name_ar])
        ->and($card['doctors'][0]['consultation_fee_usd'])->toBe($elif->consultation_fee_usd)
        ->and($card['doctors'][0]['hospital']['name']['en'])->toBe('Bosphorus Heart & Vascular Institute');

    expect(ToolCall::pluck('name')->all())->toBe(['search_doctors', 'present_recommendation'])
        ->and(ToolCall::first()->doctor_ids)->toContain($elif->id);
});

it('keeps an append-only transcript and replays it verbatim', function () {
    $model = scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'orthopedics'])),
        Model::says('Here is what I found. Which country do you prefer?'),
        Model::says('Understood.'),
    );
    $conversation = conversation();

    runTurn($conversation, 'I need a knee specialist');
    runTurn($conversation, 'Egypt please');

    [$first, $second, $third] = array_column($model->requests, 'messages');

    // Each request is the previous one plus new messages at the end.
    expect(array_slice($second, 0, count($first)))->toBe($first)
        ->and(array_slice($third, 0, count($second)))->toBe($second);

    // The model's response (thinking block and signature included) goes back untouched.
    expect($second[1]['role'])->toBe('assistant')
        ->and($second[1]['content'][0])->toHaveKeys(['type', 'thinking', 'signature'])
        ->and($second[2]['content'][0]['type'])->toBe('tool_result')
        ->and($second[2]['content'][0]['tool_use_id'])->toBe($second[1]['content'][1]['id']);

    // The system prompt and tools never change between requests.
    expect(array_unique(array_column($model->requests, 'system')))->toHaveCount(1)
        ->and($model->requests[0]['tools'])->toBe($model->requests[2]['tools']);
});

it('tells the model when it recommends a provider it never retrieved, and shows nothing', function () {
    $model = scriptModel(
        Model::calls('present_recommendation', [
            'urgency' => 'routine',
            'care_path' => 'specialist_visit',
            'specialty' => 'cardiology',
            'summary' => 'See this cardiologist.',
            'doctor_ids' => [4242],
            'hospital_ids' => [],
        ]),
        Model::says('Let me check the directory first. Which city are you in?'),
    );

    $events = runTurn(conversation(), 'Recommend your best cardiologist');

    expect(firstEvent($events, AgentEvent::RECOMMENDATION))->toBeNull()
        ->and(firstEvent($events, AgentEvent::TOOL_FINISHED)->data)->toMatchArray(['is_error' => true, 'error' => 'ungrounded_ids'])
        ->and(Recommendation::count())->toBe(0);

    $toolResult = $model->requests[1]['messages'][2]['content'][0];

    expect($toolResult['is_error'])->toBeTrue()
        ->and($toolResult['content'])->toContain('ungrounded_ids');
});

it('withholds a reply that names an unverified provider and asks for a redraft', function () {
    $model = scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Istanbul'])),
        Model::says('I recommend Dr. John Smith at Acibadem Maslak Hospital, call +90 212 304 4444.'),
        Model::says('I found two cardiologists in Istanbul. Shall I show them to you?'),
    );
    $conversation = conversation();

    $events = runTurn($conversation, 'cardiologist in Istanbul');

    $shown = array_values(array_filter($events, fn (AgentEvent $event) => $event->type === AgentEvent::MESSAGE));

    expect($shown)->toHaveCount(1)
        ->and($shown[0]->data['text'])->toBe('I found two cardiologists in Istanbul. Shall I show them to you?')
        ->and(firstEvent($events, AgentEvent::GUARD)->data)->toMatchArray(['action' => 'redraft'])
        ->and(firstEvent($events, AgentEvent::GUARD)->data['violations'])->toEqualCanonicalizing(['doctor', 'hospital', 'phone']);

    // The rejected draft is never stored as something the assistant said;
    // the model is told why it was withheld.
    expect(json_encode($conversation->messages()->where('role', 'assistant')->pluck('content')))->not->toContain('John Smith')
        ->and($conversation->messages()->where('kind', MessageKind::GuardCorrection)->value('role'))->toBe('system')
        ->and(end($model->requests[2]['messages'])['content'])->toContain('withheld');
});

it('falls back to a safe reply when the redraft is still ungrounded', function () {
    scriptModel(
        Model::says('See Dr. John Smith.'),
        Model::says('Dr. Jane Doe is also excellent.'),
    );
    $conversation = conversation('ar');

    $events = runTurn($conversation, 'أريد طبيب قلب');
    $message = firstEvent($events, AgentEvent::MESSAGE);

    expect($message->data['fallback'])->toBe('ungrounded')
        ->and($message->data['text'])->toBe(trans('agent.fallback.ungrounded', [], 'ar'))
        ->and(json_encode($conversation->messages()->pluck('content'), JSON_UNESCAPED_UNICODE))->not->toContain('Jane Doe');
});

it('raises the emergency banner before the model is even called', function () {
    $model = scriptModel(Model::says('This could be an emergency. Call your local emergency number now.'));
    $conversation = conversation();

    $events = runTurn($conversation, 'I have chest pain and shortness of breath, should I book a cardiologist?');

    expect(array_slice(eventTypes($events), 0, 3))->toBe([AgentEvent::TURN_STARTED, AgentEvent::SAFETY, AgentEvent::THINKING])
        ->and(firstEvent($events, AgentEvent::SAFETY)->data)->toBe(['level' => 'emergency', 'categories' => ['cardiac']])
        ->and($conversation->fresh()->urgency)->toBe(Urgency::Emergency);

    // The model receives the finding as an operator (system) message, not as patient text.
    $note = $model->requests[0]['messages'][1];

    expect($note['role'])->toBe('system')->and($note['content'])->toContain('possible emergency indicators - cardiac');
});

it('never lowers a conversation\'s urgency once it has been raised', function () {
    scriptModel(Model::says('Call emergency services now.'), Model::says('Glad to hear it.'));
    $conversation = conversation();

    runTurn($conversation, "I can't breathe");
    runTurn($conversation, 'Thanks, I feel fine now. Can I book a routine check-up?');

    expect($conversation->fresh()->urgency)->toBe(Urgency::Emergency);
});

it('shows what the model says alongside a tool call', function () {
    scriptModel(
        Model::calls('search_hospitals', ['specialty' => null, 'country' => null, 'city' => 'Dubai', 'emergency_only' => true], aside: 'Please call 998 now. I am finding the nearest emergency department.'),
        Model::says('The hospital below has a 24/7 emergency department.'),
    );

    $events = runTurn(conversation(), 'My husband collapsed in Dubai');
    $messages = array_values(array_filter($events, fn (AgentEvent $event) => $event->type === AgentEvent::MESSAGE));

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->data)->toBe(['text' => 'Please call 998 now. I am finding the nearest emergency department.', 'final' => false])
        ->and($messages[1]->data['final'])->toBeTrue();
});

it('ends the turn with an answer when the step budget runs out', function () {
    config(['healtrip.agent.max_iterations' => 3]);

    $model = scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology'])),
        Model::calls('search_doctors', doctorSearch(['specialty' => 'neurology'])),
        Model::says('Here is a summary of what I found.'),
    );

    $events = runTurn(conversation(), 'search everything');

    expect(array_column($model->requests, 'allowTools'))->toBe([true, true, false])
        ->and(end($events)->type)->toBe(AgentEvent::DONE);
});

it('survives a provider outage with a stored, safe reply and a retryable error', function () {
    scriptModel(LlmException::unavailable(new RuntimeException('boom')), Model::says('Hello again.'));
    $conversation = conversation();

    $events = runTurn($conversation, 'hello');

    expect(firstEvent($events, AgentEvent::ERROR)->data)->toBe(['code' => 'unavailable', 'retryable' => true])
        ->and(firstEvent($events, AgentEvent::MESSAGE)->data['fallback'])->toBe('unavailable')
        ->and($conversation->messages()->pluck('role')->all())->toBe(['user', 'assistant']);

    // The transcript is still well formed, so the conversation simply continues.
    expect(eventTypes(runTurn($conversation, 'hello?')))->toContain(AgentEvent::MESSAGE);
});

it('replaces a refused request with a safe reply', function () {
    scriptModel(Model::refuses());

    $message = firstEvent(runTurn(conversation(), 'something the model declines'), AgentEvent::MESSAGE);

    expect($message->data['fallback'])->toBe('refusal');
});

it('lets the model name a doctor once the card is on screen, and not before', function () {
    $search = Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Istanbul']));
    $present = fn (array $messages) => Model::calls('present_recommendation', [
        'urgency' => 'routine',
        'care_path' => 'specialist_visit',
        'specialty' => 'cardiology',
        'summary' => 'A cardiology consultation is the right next step.',
        'doctor_ids' => array_column(Model::lastToolResult($messages)['doctors'], 'doctor_id'),
        'hospital_ids' => [],
    ]);

    scriptModel($search, $present, Model::says('Dr. Elif Kaya is the closest match for chest pain.'));
    $withCard = runTurn(conversation(), 'cardiologist in Istanbul');

    expect(firstEvent($withCard, AgentEvent::GUARD))->toBeNull()
        ->and(end($withCard)->type)->toBe(AgentEvent::DONE);

    // The same sentence without a card: the search found her, but the
    // patient has been shown nothing to check the claim against.
    scriptModel($search, Model::says('Dr. Elif Kaya is the closest match for chest pain.'), Model::says('Dr. Elif Kaya is the one to see.'));
    $withoutCard = runTurn(conversation(), 'cardiologist in Istanbul');

    expect(firstEvent($withoutCard, AgentEvent::GUARD)->data['action'])->toBe('redraft')
        ->and(collect($withoutCard)->last(fn (AgentEvent $event) => $event->type === AgentEvent::MESSAGE)->data['fallback'])->toBe('ungrounded');
});

it('keeps presented providers nameable in later turns', function () {
    scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Istanbul'])),
        fn (array $messages) => Model::calls('present_recommendation', [
            'urgency' => 'routine',
            'care_path' => 'specialist_visit',
            'specialty' => 'cardiology',
            'summary' => 'A cardiology consultation is the right next step.',
            'doctor_ids' => array_column(Model::lastToolResult($messages)['doctors'], 'doctor_id'),
            'hospital_ids' => [],
        ]),
        Model::says('These two match.'),
        Model::says('Dr. Kerem Yildiz also speaks Arabic, and works at Bosphorus Heart & Vascular Institute.'),
    );
    $conversation = conversation();

    runTurn($conversation, 'cardiologist in Istanbul');
    $second = runTurn($conversation, 'which one speaks Arabic?');

    expect(firstEvent($second, AgentEvent::GUARD))->toBeNull()
        ->and(firstEvent($second, AgentEvent::MESSAGE)->data['text'])->toContain('Kerem Yildiz');
});
