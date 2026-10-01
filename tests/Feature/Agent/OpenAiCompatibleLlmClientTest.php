<?php

use App\Agent\Llm\LlmException;
use App\Agent\Llm\OpenAiCompatibleLlmClient;
use App\Agent\Tools\ToolRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * The driver is tested at the HTTP boundary: what it puts on the wire for a
 * canonical transcript, and what it makes of a Chat Completions reply.
 */
function openAiDriver(array $config = []): OpenAiCompatibleLlmClient
{
    return new OpenAiCompatibleLlmClient('openai', [
        'max_tokens' => 8000,
        'timeout' => 30,
        'max_retries' => 0,
        ...config('healtrip.llm.providers.openai'),
        'api_key' => 'sk-test',
        'model' => 'gpt-test',
        ...$config,
    ]);
}

function completion(array $message, string $finishReason = 'stop'): array
{
    return [
        'id' => 'chatcmpl-1',
        'model' => 'gpt-test-2026',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', ...$message], 'finish_reason' => $finishReason]],
        'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 40, 'prompt_tokens_details' => ['cached_tokens' => 700]],
    ];
}

/** The body of the one request the driver sent. */
function sentBody(): array
{
    return Http::recorded()[0][0]->data();
}

// A conversation as the agent stores it: a patient message, an operator
// note, a model turn with a tool call, and our tool result.
$transcript = [
    ['role' => 'user', 'content' => 'I need a cardiologist in Cairo'],
    ['role' => 'system', 'content' => 'Safety screen: ...'],
    ['role' => 'assistant', 'content' => [
        ['type' => 'text', 'text' => 'Let me look.'],
        ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'search_doctors', 'input' => ['specialty' => 'cardiology', 'city' => 'القاهرة']],
    ]],
    ['role' => 'user', 'content' => [
        ['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => '{"total_matches":2}', 'is_error' => false],
    ]],
];

it('translates the canonical transcript into chat completion messages', function () use ($transcript) {
    Http::fake(['*' => Http::response(completion(['content' => 'Here they are.']))]);

    openAiDriver()->respond('SYSTEM', [], $transcript);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer sk-test'));

    expect(sentBody()['messages'])->toBe([
        ['role' => 'system', 'content' => 'SYSTEM'],
        ['role' => 'user', 'content' => 'I need a cardiologist in Cairo'],
        ['role' => 'system', 'content' => 'Safety screen: ...'],
        ['role' => 'assistant', 'content' => 'Let me look.', 'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'search_doctors', 'arguments' => '{"specialty":"cardiology","city":"القاهرة"}'],
        ]]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"total_matches":2}'],
    ])
        ->and(sentBody())->toMatchArray(['model' => 'gpt-test', 'max_completion_tokens' => 8000])
        ->and(sentBody())->not->toHaveKeys(['tools', 'tool_choice', 'reasoning_effort']);
});

it('sends an argument-less tool call as an empty object', function () {
    Http::fake(['*' => Http::response(completion(['content' => 'ok']))]);

    openAiDriver()->respond('SYSTEM', [], [
        ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'call_1', 'name' => 'noop', 'input' => []]]],
        ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => '{}', 'is_error' => false]]],
    ]);

    expect(sentBody()['messages'][1])->toMatchArray(['content' => null])
        ->and(sentBody()['messages'][1]['tool_calls'][0]['function']['arguments'])->toBe('{}');
});

it('sends the agent\'s tools as strict functions to a provider that enforces schemas', function () {
    Http::fake(['*' => Http::response(completion(['content' => 'ok']))]);
    $tools = app(ToolRegistry::class)->definitions();

    openAiDriver()->respond('SYSTEM', $tools, [['role' => 'user', 'content' => 'hi']]);

    $sent = sentBody()['tools'];

    expect(sentBody()['tool_choice'])->toBe('auto')
        ->and(array_column(array_column($sent, 'function'), 'name'))->toBe(array_column($tools, 'name'))
        ->and($sent[1])->toBe(['type' => 'function', 'function' => [
            'name' => 'search_doctors',
            'description' => $tools[1]['description'],
            'parameters' => $tools[1]['input_schema'],
            'strict' => true,
        ]]);
});

it('restates the schema in plain JSON Schema for a provider without strict mode', function () {
    Http::fake(['*' => Http::response(completion(['content' => 'ok']))]);

    openAiDriver(['strict_tools' => false, 'token_limit_param' => 'max_tokens'])
        ->respond('SYSTEM', app(ToolRegistry::class)->definitions(), [['role' => 'user', 'content' => 'hi']]);

    $search = sentBody()['tools'][1]['function'];

    expect($search)->not->toHaveKey('strict')
        ->and($search['parameters'])->not->toHaveKey('additionalProperties')
        // Optional filters are simply optional, with a single plain type...
        ->and($search['parameters']['required'])->toBe(['second_opinion_only', 'telemedicine_only'])
        ->and($search['parameters']['properties']['city']['type'])->toBe('string')
        ->and($search['parameters']['properties']['max_fee_usd']['type'])->toBe('integer')
        // ...and a nullable enum keeps its enum and its description.
        ->and($search['parameters']['properties']['specialty'])->toHaveKeys(['type', 'enum', 'description'])
        ->and($search['parameters']['properties']['specialty'])->not->toHaveKey('anyOf')
        ->and(sentBody())->toHaveKey('max_tokens', 8000);
});

it('forbids tools on the last step of a turn', function () {
    Http::fake(['*' => Http::response(completion(['content' => 'ok']))]);

    openAiDriver()->respond('SYSTEM', app(ToolRegistry::class)->definitions(), [['role' => 'user', 'content' => 'hi']], allowTools: false);

    expect(sentBody()['tool_choice'])->toBe('none');
});

it('turns a text reply into the canonical response', function () {
    Http::fake(['*' => Http::response(completion(['content' => ' Which city are you in? ']))]);

    $response = openAiDriver()->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);

    expect($response->stopReason)->toBe('end_turn')
        ->and($response->content)->toBe([['type' => 'text', 'text' => 'Which city are you in?']])
        ->and($response->usage)->toBe(['input_tokens' => 900, 'output_tokens' => 40, 'cache_read_input_tokens' => 700])
        ->and($response->model)->toBe('gpt-test-2026');
});

it('turns tool calls into canonical tool_use blocks', function () {
    Http::fake(['*' => Http::response(completion([
        'content' => null,
        'tool_calls' => [
            ['id' => 'call_a', 'type' => 'function', 'function' => ['name' => 'search_doctors', 'arguments' => '{"specialty":"cardiology","city":null}']],
            ['id' => 'call_b', 'type' => 'function', 'function' => ['name' => 'search_hospitals', 'arguments' => '{not json']],
        ],
    ], 'tool_calls'))]);

    $response = openAiDriver()->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);

    expect($response->stopReason)->toBe('tool_use')
        ->and($response->toolUses())->toBe([
            ['id' => 'call_a', 'name' => 'search_doctors', 'input' => ['specialty' => 'cardiology', 'city' => null]],
            // Malformed arguments reach validation as "nothing supplied".
            ['id' => 'call_b', 'name' => 'search_hospitals', 'input' => []],
        ]);
});

it('maps the ways a completion can stop', function (array $message, string $finishReason, string $expected) {
    Http::fake(['*' => Http::response(completion($message, $finishReason))]);

    expect(openAiDriver()->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']])->stopReason)->toBe($expected);
})->with([
    'cut off' => [['content' => 'Half a sent'], 'length', 'max_tokens'],
    'content filter' => [['content' => null], 'content_filter', 'refusal'],
    'refusal message' => [['content' => null, 'refusal' => 'I cannot help with that.'], 'stop', 'refusal'],
]);

it('maps provider failures to a reason and whether a retry can help', function (int $status, string $reason, bool $retryable) {
    Http::fake(['*' => Http::response(['error' => ['message' => 'nope', 'type' => 'some_error']], $status)]);

    try {
        openAiDriver()->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);
        $this->fail('Expected an LlmException.');
    } catch (LlmException $e) {
        expect($e->reason)->toBe($reason)->and($e->retryable)->toBe($retryable);
    }
})->with([
    'bad key' => [401, LlmException::AUTH, false],
    'rate limit' => [429, LlmException::RATE_LIMITED, true],
    'server error' => [503, LlmException::UNAVAILABLE, true],
    'malformed request' => [400, LlmException::BAD_REQUEST, false],
    'unknown model' => [404, LlmException::BAD_REQUEST, false],
]);

it('reports an unreachable provider as unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(fn () => openAiDriver()->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]))
        ->toThrow(fn (LlmException $e) => expect($e->reason)->toBe(LlmException::UNAVAILABLE));
});

it('retries a transient failure before giving up', function () {
    Sleep::fake();
    Http::fake(['*' => Http::sequence()
        ->push(['error' => ['message' => 'busy']], 503)
        ->push(completion(['content' => 'Recovered.']))]);

    $response = openAiDriver(['max_retries' => 2])->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);

    expect($response->text())->toBe('Recovered.');
    Http::assertSentCount(2);
});

it('talks to a keyless local server without an Authorization header', function () {
    Http::fake(['*' => Http::response(completion(['content' => 'ok']))]);

    (new OpenAiCompatibleLlmClient('ollama', [
        'max_tokens' => 8000, 'timeout' => 30, 'max_retries' => 0,
        ...config('healtrip.llm.providers.ollama'),
        'model' => 'local-model',
    ]))->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:11434/v1/chat/completions'
        && ! $request->hasHeader('Authorization'));
});
