<?php

namespace App\Agent\Llm;

use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Core\Exceptions\UnprocessableEntityException;
use Anthropic\RequestOptions;
use Illuminate\Support\Facades\Log;

/**
 * Claude via the official Anthropic SDK.
 *
 * Requests are deliberately not streamed: the reply has to pass the output
 * guard as a whole before any of it reaches the patient, so there is nothing
 * to gain from receiving it token by token. Progress is streamed to the
 * browser by the agent loop instead.
 */
class AnthropicLlmClient implements LlmClient
{
    /**
     * Lets the API retry a request on its default fallback model when the
     * primary model's safety classifiers decline it.
     */
    private const REFUSAL_FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private ?Client $client;

    /**
     * @param  array<string, mixed>  $config  The provider's block from config/healtrip.php plus the shared limits.
     */
    public function __construct(
        private readonly array $config,
        ?Client $client = null,
    ) {
        $this->client = $client;
    }

    public function respond(string $system, array $tools, array $messages, bool $allowTools = true): LlmResponse
    {
        $client = $this->client();
        $fallback = (bool) ($this->config['refusal_fallback'] ?? false);

        try {
            $message = $client->beta->messages->create(
                maxTokens: (int) $this->config['max_tokens'],
                model: (string) $this->config['model'],
                system: $system,
                // Tools and the transcript are passed as plain wire-format arrays
                // on purpose (see forWire): the SDK forwards them untouched,
                // which static analysis cannot verify against its own shapes.
                tools: $tools, // @phpstan-ignore argument.type
                messages: $this->forWire($messages), // @phpstan-ignore argument.type
                // Forced tool use is not available on current models; "none" is
                // the one constraint we need, to end a turn that ran out of steps.
                toolChoice: $allowTools ? null : ['type' => 'none'],
                outputConfig: ['effort' => Effort::from((string) $this->config['effort'])],
                // Caches the longest stable prefix (tools, system, earlier
                // turns), which is why all three are kept byte-stable.
                cacheControl: ['type' => 'ephemeral'],
                fallbacks: $fallback ? 'default' : null,
                betas: $fallback ? [self::REFUSAL_FALLBACK_BETA] : null,
            );
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw LlmException::auth($this->logged($e));
        } catch (RateLimitException $e) {
            throw LlmException::rateLimited($this->logged($e));
        } catch (BadRequestException|NotFoundException|UnprocessableEntityException $e) {
            throw LlmException::badRequest($this->logged($e));
        } catch (APIStatusException|APIConnectionException $e) {
            // 5xx, overloaded, timeouts and network failures. The SDK has
            // already retried these with backoff before giving up.
            throw LlmException::unavailable($this->logged($e));
        } catch (APIException $e) {
            throw LlmException::unavailable($this->logged($e));
        }

        /** @var list<array<string, mixed>> $content */
        $content = json_decode((string) json_encode($message->content), true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $usage */
        $usage = json_decode((string) json_encode($message->usage), true, flags: JSON_THROW_ON_ERROR);

        return new LlmResponse(
            stopReason: (string) $message->stopReason,
            content: $content,
            usage: array_filter($usage, is_int(...)),
            model: (string) $message->model,
        );
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (blank($this->config['api_key'] ?? null)) {
            throw LlmException::notConfigured('anthropic');
        }

        return $this->client = new Client(
            apiKey: (string) $this->config['api_key'],
            requestOptions: RequestOptions::with(
                timeout: (float) $this->config['timeout'],
                maxRetries: (int) $this->config['max_retries'],
            ),
        );
    }

    /**
     * The stored transcript is sent as-is, with one serialisation fix: a tool
     * call with no arguments decodes from JSON to an empty PHP array, which
     * would re-encode as `[]` where the API requires an object.
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function forWire(array $messages): array
    {
        foreach ($messages as &$message) {
            if (! is_array($message['content'])) {
                continue;
            }

            foreach ($message['content'] as &$block) {
                if (($block['type'] ?? null) === 'tool_use' && ($block['input'] ?? null) === []) {
                    $block['input'] = (object) [];
                }
            }

            unset($block);
        }

        unset($message);

        return $messages;
    }

    /**
     * Log the failure without the request body: it contains what the patient
     * wrote, and that does not belong in application logs.
     */
    private function logged(APIException $e): APIException
    {
        Log::error('Model provider request failed', [
            'exception' => $e::class,
            'status' => $e instanceof APIStatusException ? $e->status : null,
            'type' => $e instanceof APIStatusException ? $e->type?->value : null,
        ]);

        return $e;
    }
}
