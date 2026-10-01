<?php

use App\Models\Conversation;
use App\Models\Doctor;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\Support\ScriptedLlmClient as Model;

/**
 * Parse a server-sent event stream into [event, data] pairs.
 *
 * @return list<array{string, array<string, mixed>}>
 */
function sse(string $stream): array
{
    $events = [];

    foreach (array_filter(explode("\n\n", trim($stream))) as $chunk) {
        preg_match('/^event: (.+)\ndata: (.+)$/s', $chunk, $match);
        $events[] = [$match[1], json_decode($match[2], true)];
    }

    return $events;
}

function startConversation(): string
{
    return test()->postJson('/api/conversations', ['locale' => 'en'])->assertCreated()->json('id');
}

it('serves the chat page', function () {
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('chat')
        ->where('conversation', null)
        ->where('providers', [])
        ->where('defaultProvider', 'anthropic')
        ->where('limits.maxMessageLength', 2000));
});

it('lets the visitor choose among configured providers, by name only', function () {
    config([
        'healtrip.llm.providers.anthropic.api_key' => 'sk-ant-secret',
        'healtrip.llm.providers.openai.api_key' => 'sk-openai-secret',
        'healtrip.llm.providers.openai.model' => 'gpt-test',
    ]);

    $page = $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('providers', [
            ['name' => 'anthropic', 'label' => 'Claude', 'model' => 'claude-fable-5-1'],
            ['name' => 'openai', 'label' => 'GPT', 'model' => 'gpt-test'],
        ]));

    expect($page->getContent())->not->toContain('secret');

    $this->postJson('/api/conversations', ['provider' => 'openai'])
        ->assertCreated()
        ->assertJsonPath('provider', ['name' => 'openai', 'label' => 'GPT', 'model' => 'gpt-test']);

    // Not configured, and not a provider at all.
    $this->postJson('/api/conversations', ['provider' => 'gemini'])->assertUnprocessable();
    $this->postJson('/api/conversations', ['provider' => 'https://evil.example/v1'])->assertUnprocessable();
});

it('keeps a conversation on its provider and answers through it', function () {
    config(['healtrip.llm.providers.openai.api_key' => 'k', 'healtrip.llm.providers.openai.model' => 'gpt-test']);
    $gpt = scriptProvider('openai', Model::says('Answered by the second provider.'));

    $id = $this->postJson('/api/conversations', ['provider' => 'openai'])->json('id');
    $events = sse($this->post("/api/conversations/{$id}/messages", ['text' => 'hello'])->streamedContent());

    expect(collect($events)->firstWhere(0, 'message')[1]['text'])->toBe('Answered by the second provider.')
        ->and($gpt->requests)->toHaveCount(1)
        ->and(Conversation::find($id)->provider)->toBe('openai');
});

it('streams a turn as server-sent events', function () {
    scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Cairo'])),
        Model::says('I found two cardiologists in Cairo.'),
    );

    $response = $this->post('/api/conversations/'.startConversation().'/messages', ['text' => 'cardiologist in Cairo']);

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = sse($response->streamedContent());

    expect(array_column($events, 0))->toBe(['turn.started', 'agent.thinking', 'tool.started', 'tool.finished', 'agent.thinking', 'message', 'done'])
        ->and($events[3][1])->toMatchArray(['name' => 'search_doctors', 'is_error' => false, 'stats' => ['returned' => 2, 'total' => 2]])
        ->and($events[5][1]['text'])->toBe('I found two cardiologists in Cairo.');
});

it('restores the conversation on reload without leaking the raw transcript', function () {
    scriptModel(
        Model::calls('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Cairo'])),
        fn (array $messages) => Model::calls('present_recommendation', [
            'urgency' => 'urgent',
            'care_path' => 'specialist_visit',
            'specialty' => 'cardiology',
            'summary' => 'See a cardiologist within a day.',
            'doctor_ids' => array_column(Model::lastToolResult($messages)['doctors'], 'doctor_id'),
            'hospital_ids' => [],
        ]),
        Model::says('Both are available this week.'),
    );

    $id = startConversation();
    $this->post("/api/conversations/{$id}/messages", ['text' => 'I have chest pain, cardiologist in Cairo'])->streamedContent();

    $snapshot = $this->getJson("/api/conversations/{$id}")->assertOk();

    $snapshot->assertJsonPath('urgency', 'urgent')
        ->assertJsonPath('turns.0.user_text', 'I have chest pain, cardiologist in Cairo')
        ->assertJsonPath('turns.0.safety.level', 'urgent')
        ->assertJsonPath('turns.0.texts', ['Both are available this week.'])
        ->assertJsonPath('turns.0.tools.0.stats', ['returned' => 2, 'total' => 2])
        ->assertJsonPath('turns.0.recommendation.doctors.0.name.en', Doctor::where('name_en', 'Ahmed El-Sharkawy')->value('name_en'));

    expect($snapshot->getContent())->not->toContain('signature')->not->toContain('Safety screen');

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('conversation.id', $id));
});

it('hides a conversation from every other session', function () {
    $id = startConversation();

    $this->flushSession();

    $this->getJson("/api/conversations/{$id}")->assertNotFound();
    $this->postJson("/api/conversations/{$id}/messages", ['text' => 'hello'])->assertNotFound();
});

it('validates the message', function (array $payload) {
    $this->postJson('/api/conversations/'.startConversation().'/messages', $payload)->assertUnprocessable();
})->with([
    'empty' => [['text' => '   ']],
    'too long' => [['text' => str_repeat('a', 2001)]],
    'unsupported locale' => [['text' => 'hi', 'locale' => 'fr']],
]);

it('caps the length of a conversation', function () {
    $id = startConversation();
    Conversation::whereKey($id)->update(['turns' => config('healtrip.agent.max_turns')]);

    $this->postJson("/api/conversations/{$id}/messages", ['text' => 'one more'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'conversation_full');
});

it('rate limits messages per client', function () {
    config(['healtrip.rate_limits.messages_per_minute' => 2]);
    scriptModel(Model::says('one'), Model::says('two'));
    $id = startConversation();

    $this->post("/api/conversations/{$id}/messages", ['text' => 'a'])->streamedContent();
    $this->post("/api/conversations/{$id}/messages", ['text' => 'b'])->streamedContent();

    $this->postJson("/api/conversations/{$id}/messages", ['text' => 'c'])->assertTooManyRequests();
});

it('refuses a second turn while one is still running', function () {
    $id = startConversation();
    $lock = Cache::lock("conversation:{$id}:turn", 60);
    $lock->get();

    $this->postJson("/api/conversations/{$id}/messages", ['text' => 'hello'])
        ->assertConflict()
        ->assertJsonPath('code', 'turn_in_progress');

    $lock->release();
});

it('exposes the directory with the same filters the agent uses', function () {
    $this->getJson('/api/directory/doctors?specialty=cardiology&country=Egypt&second_opinion_only=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name.en', 'Ahmed El-Sharkawy')
        ->assertJsonPath('data.0.hospital.country.code', 'EG');

    $this->getJson('/api/directory/hospitals?city=Dubai&emergency_only=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.emergency_phone', '+971 4 555 0441');

    $this->getJson('/api/directory/doctors?specialty=astrology')->assertUnprocessable();
});
