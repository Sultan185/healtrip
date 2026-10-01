<?php

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Agent\Llm\AnthropicLlmClient;
use App\Agent\Llm\LlmException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The driver is tested against a fake HTTP transport, so these assertions are
 * about the real bytes the SDK puts on the wire.
 */
function fakeTransport(Response ...$responses): ClientInterface
{
    return new class($responses) implements ClientInterface
    {
        /** @var list<RequestInterface> */
        public array $requests = [];

        /** @param list<Response> $responses */
        public function __construct(private array $responses) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->requests[] = $request;

            return array_shift($this->responses) ?? throw new LogicException('No more fake responses.');
        }

        /** @return array<string, mixed> */
        public function body(int $index = 0): array
        {
            return json_decode((string) $this->requests[$index]->getBody(), true);
        }
    };
}

function apiMessage(array $content, string $stopReason = 'end_turn'): Response
{
    return new Response(200, ['content-type' => 'application/json'], json_encode([
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-fable-5-1',
        'content' => $content,
        'stop_reason' => $stopReason, 'stop_sequence' => null,
        'usage' => ['input_tokens' => 120, 'output_tokens' => 30, 'cache_read_input_tokens' => 80, 'cache_creation_input_tokens' => 0],
    ]));
}

function apiError(int $status, string $type): Response
{
    return new Response($status, ['content-type' => 'application/json'], json_encode([
        'type' => 'error', 'error' => ['type' => $type, 'message' => 'nope'],
    ]));
}

function driver(ClientInterface $transport, array $config = []): AnthropicLlmClient
{
    return new AnthropicLlmClient(
        [...config('healtrip.llm'), ...config('healtrip.llm.providers.anthropic'), 'api_key' => 'sk-test', ...$config],
        new Client(apiKey: 'sk-test', requestOptions: RequestOptions::with(transporter: $transport, maxRetries: 0)),
    );
}

$transcript = [
    ['role' => 'user', 'content' => 'I need a cardiologist'],
    ['role' => 'system', 'content' => 'Safety screen: ...'],
    ['role' => 'assistant', 'content' => [
        ['type' => 'thinking', 'thinking' => '', 'signature' => 'SIG=='],
        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'search_doctors', 'input' => []],
    ]],
    ['role' => 'user', 'content' => [
        ['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => '{"doctors":[]}', 'is_error' => false],
    ]],
];

it('sends the transcript untouched, with caching, effort and the refusal fallback', function () use ($transcript) {
    $transport = fakeTransport(apiMessage([['type' => 'text', 'text' => 'Hello']]));

    driver($transport)->respond('SYSTEM', [['name' => 'search_doctors']], $transcript);

    $body = $transport->body();

    expect($body['model'])->toBe('claude-fable-5-1')
        ->and($body['system'])->toBe('SYSTEM')
        ->and($body['output_config'])->toBe(['effort' => 'medium'])
        ->and($body['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['fallbacks'])->toBe('default')
        ->and($transport->requests[0]->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01')
        ->and($body)->not->toHaveKeys(['thinking', 'temperature', 'tool_choice'])
        ->and($body['messages'][1])->toBe(['role' => 'system', 'content' => 'Safety screen: ...'])
        ->and($body['messages'][2]['content'][0])->toBe(['type' => 'thinking', 'thinking' => '', 'signature' => 'SIG=='])
        ->and($body['messages'][3])->toBe($transcript[3]);

    // An argument-less tool call must stay a JSON object, not become a list.
    expect((string) $transport->requests[0]->getBody())->toContain('"input":{}');
});

it('forbids tools on the last step of a turn', function () use ($transcript) {
    $transport = fakeTransport(apiMessage([['type' => 'text', 'text' => 'Hello']]));

    driver($transport)->respond('SYSTEM', [], $transcript, allowTools: false);

    expect($transport->body()['tool_choice'])->toBe(['type' => 'none']);
});

it('returns content blocks in wire format so they can be stored and replayed', function () {
    $content = [
        ['type' => 'thinking', 'thinking' => '', 'signature' => 'SIG=='],
        ['type' => 'tool_use', 'id' => 'toolu_9', 'name' => 'search_doctors', 'input' => ['specialty' => 'cardiology']],
    ];

    $response = driver(fakeTransport(apiMessage($content, 'tool_use')))->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);

    expect($response->stopReason)->toBe('tool_use')
        ->and($response->content[0])->toMatchArray(['type' => 'thinking', 'signature' => 'SIG=='])
        ->and($response->toolUses())->toBe([['id' => 'toolu_9', 'name' => 'search_doctors', 'input' => ['specialty' => 'cardiology']]])
        ->and($response->usage)->toMatchArray(['input_tokens' => 120, 'cache_read_input_tokens' => 80]);
});

it('reports a refusal instead of reading empty content', function () {
    $response = driver(fakeTransport(apiMessage([], 'refusal')))->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);

    expect($response->isRefusal())->toBeTrue()->and($response->text())->toBe('');
});

it('maps provider failures to a reason and whether a retry can help', function (int $status, string $type, string $reason, bool $retryable) {
    $driver = driver(fakeTransport(apiError($status, $type)));

    try {
        $driver->respond('SYSTEM', [], [['role' => 'user', 'content' => 'hi']]);
        $this->fail('Expected an LlmException.');
    } catch (LlmException $e) {
        expect($e->reason)->toBe($reason)->and($e->retryable)->toBe($retryable);
    }
})->with([
    'bad key' => [401, 'authentication_error', LlmException::AUTH, false],
    'rate limit' => [429, 'rate_limit_error', LlmException::RATE_LIMITED, true],
    'overloaded' => [529, 'overloaded_error', LlmException::UNAVAILABLE, true],
    'server error' => [500, 'api_error', LlmException::UNAVAILABLE, true],
    'malformed request' => [400, 'invalid_request_error', LlmException::BAD_REQUEST, false],
]);

it('fails clearly when no API key is configured', function () {
    $driver = new AnthropicLlmClient([...config('healtrip.llm'), ...config('healtrip.llm.providers.anthropic'), 'api_key' => null]);

    expect(fn () => $driver->respond('SYSTEM', [], []))->toThrow(LlmException::class, 'The model provider [anthropic] is not configured.');
});
