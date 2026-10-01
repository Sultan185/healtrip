<?php

namespace App\Agent\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Any provider that speaks the OpenAI Chat Completions wire format: OpenAI
 * itself, Gemini's OpenAI endpoint, a local Ollama, Groq, OpenRouter. They
 * differ only in base URL, key, model and a few capability flags, all of
 * which come from config.
 *
 * The agent's transcript is kept in one canonical shape (see LlmClient).
 * This driver's whole job is translating that shape to Chat Completions on
 * the way out, and the reply back into it on the way in.
 */
class OpenAiCompatibleLlmClient implements LlmClient
{
    private const RETRYABLE_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $provider,
        private readonly array $config,
    ) {}

    public function respond(string $system, array $tools, array $messages, bool $allowTools = true): LlmResponse
    {
        $body = [
            'model' => (string) $this->config['model'],
            'messages' => $this->toChatMessages($system, $messages),
            (string) ($this->config['token_limit_param'] ?? 'max_tokens') => (int) $this->config['max_tokens'],
        ];

        if ($tools !== []) {
            $body['tools'] = array_map($this->toFunctionTool(...), $tools);
            // "none" ends a turn that ran out of steps with a text answer.
            $body['tool_choice'] = $allowTools ? 'auto' : 'none';
        }

        if (filled($this->config['reasoning_effort'] ?? null)) {
            $body['reasoning_effort'] = $this->config['reasoning_effort'];
        }

        try {
            $response = $this->http()->post('chat/completions', $body);
        } catch (ConnectionException $e) {
            throw LlmException::unavailable($this->logged($e));
        }

        if ($response->failed()) {
            throw $this->failure($response);
        }

        return $this->toResponse($response);
    }

    private function http(): PendingRequest
    {
        $attempts = (int) $this->config['max_retries'] + 1;

        $request = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) $this->config['timeout'])
            // Retry what can succeed on a second try, with growing pauses,
            // and hand back the last response instead of throwing so that
            // the status can be mapped below.
            ->retry(
                $attempts,
                fn (int $attempt) => $attempt * 500,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), self::RETRYABLE_STATUSES, true)),
                throw: false,
            );

        return filled($this->config['api_key'] ?? null)
            ? $request->withToken((string) $this->config['api_key'])
            : $request;
    }

    /**
     * Canonical transcript -> Chat Completions messages.
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @return list<array<string, mixed>>
     */
    private function toChatMessages(string $system, array $messages): array
    {
        $chat = [['role' => 'system', 'content' => $system]];

        foreach ($messages as $message) {
            $content = $message['content'];

            // Patient text, or an operator note (role "system").
            if (is_string($content)) {
                $chat[] = ['role' => $message['role'], 'content' => $content];

                continue;
            }

            if ($message['role'] === 'assistant') {
                $chat[] = $this->toAssistantMessage($content);

                continue;
            }

            // A user-role message made of blocks carries our tool results;
            // each becomes its own "tool" message.
            foreach ($content as $block) {
                $chat[] = match ($block['type'] ?? null) {
                    'tool_result' => [
                        'role' => 'tool',
                        'tool_call_id' => (string) ($block['tool_use_id'] ?? ''),
                        'content' => is_string($block['content'] ?? null) ? $block['content'] : json_encode($block['content'] ?? null),
                    ],
                    default => ['role' => 'user', 'content' => (string) ($block['text'] ?? '')],
                };
            }
        }

        return $chat;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function toAssistantMessage(array $blocks): array
    {
        $text = [];
        $toolCalls = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text[] = (string) ($block['text'] ?? '');
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'type' => 'function',
                    'function' => [
                        'name' => (string) ($block['name'] ?? ''),
                        // Arguments travel as a JSON string, and "no
                        // arguments" must be an object, not a list.
                        'arguments' => json_encode(($block['input'] ?? []) ?: (object) [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ],
                ];
            }
        }

        $message = ['role' => 'assistant', 'content' => $text === [] ? null : implode("\n\n", $text)];

        if ($toolCalls !== []) {
            $message['tool_calls'] = $toolCalls;
        }

        return $message;
    }

    /**
     * Canonical tool definition -> Chat Completions function tool.
     *
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private function toFunctionTool(array $tool): array
    {
        $strict = (bool) ($this->config['strict_tools'] ?? false);

        /** @var array<string, mixed> $schema */
        $schema = $tool['input_schema'] ?? ['type' => 'object', 'properties' => []];

        $function = [
            'name' => (string) $tool['name'],
            'description' => (string) ($tool['description'] ?? ''),
            'parameters' => $strict ? $schema : $this->relaxed($schema),
        ];

        if ($strict) {
            $function['strict'] = true;
        }

        return ['type' => 'function', 'function' => $function];
    }

    /**
     * Tool schemas are written for strict mode: every property required,
     * "no value" expressed as null. Providers without strict mode understand
     * a narrower schema dialect, so for them the same schema is restated the
     * plain way: optional properties are simply not required. The server
     * validates every tool call either way.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function relaxed(array $schema): array
    {
        /** @var list<string> $required */
        $required = $schema['required'] ?? [];

        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $name => $property) {
            $nullable = false;

            if (isset($property['anyOf']) && is_array($property['anyOf'])) {
                $variants = array_values(array_filter($property['anyOf'], fn ($variant) => ($variant['type'] ?? null) !== 'null'));

                if (count($variants) === 1 && count($property['anyOf']) === 2) {
                    unset($property['anyOf']);
                    $property = [...$variants[0], ...$property];
                    $nullable = true;
                }
            } elseif (is_array($property['type'] ?? null)) {
                $types = array_values(array_diff($property['type'], ['null']));

                if (count($types) === 1 && count($property['type']) === 2) {
                    $property['type'] = $types[0];
                    $nullable = true;
                }
            }

            if ($nullable) {
                $required = array_values(array_diff($required, [$name]));
            }

            $properties[$name] = $property;
        }

        unset($schema['additionalProperties']);

        return [...$schema, 'properties' => $properties, 'required' => $required];
    }

    /**
     * Chat Completions reply -> canonical response.
     */
    private function toResponse(Response $response): LlmResponse
    {
        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw LlmException::unavailable($this->logged($response->toException() ?? new RequestException($response)));
        }

        $content = [];
        $text = is_string($message['content'] ?? null) ? trim($message['content']) : '';

        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        $toolCalls = (array) ($message['tool_calls'] ?? []);

        foreach ($toolCalls as $call) {
            $arguments = json_decode((string) ($call['function']['arguments'] ?? ''), true);

            $content[] = [
                'type' => 'tool_use',
                'id' => filled($call['id'] ?? null) ? (string) $call['id'] : 'call_'.Str::random(16),
                'name' => (string) ($call['function']['name'] ?? ''),
                // Malformed arguments become "no arguments": validation then
                // tells the model exactly what is missing and it tries again.
                'input' => is_array($arguments) ? $arguments : [],
            ];
        }

        $stopReason = match (true) {
            $toolCalls !== [] => 'tool_use',
            $content === [] && filled($message['refusal'] ?? null) => 'refusal',
            default => match ($response->json('choices.0.finish_reason')) {
                'length' => 'max_tokens',
                'content_filter' => 'refusal',
                default => 'end_turn',
            },
        };

        return new LlmResponse(
            stopReason: $stopReason,
            content: $content,
            usage: array_filter([
                'input_tokens' => $response->json('usage.prompt_tokens'),
                'output_tokens' => $response->json('usage.completion_tokens'),
                'cache_read_input_tokens' => $response->json('usage.prompt_tokens_details.cached_tokens'),
            ], is_int(...)),
            model: (string) ($response->json('model') ?? $this->config['model']),
        );
    }

    private function failure(Response $response): LlmException
    {
        $exception = $this->logged($response->toException() ?? new RequestException($response), $response);

        return match (true) {
            in_array($response->status(), [401, 403], true) => LlmException::auth($exception),
            $response->status() === 429 => LlmException::rateLimited($exception),
            $response->status() >= 500, $response->status() === 408 => LlmException::unavailable($exception),
            default => LlmException::badRequest($exception),
        };
    }

    /**
     * Log the failure without the request body: it contains what the patient
     * wrote, and that does not belong in application logs.
     */
    private function logged(Throwable $e, ?Response $response = null): Throwable
    {
        Log::error('Model provider request failed', [
            'provider' => $this->provider,
            'exception' => $e::class,
            'status' => $response?->status(),
            'type' => $response?->json('error.type') ?? $response?->json('error.code'),
        ]);

        return $e;
    }
}
