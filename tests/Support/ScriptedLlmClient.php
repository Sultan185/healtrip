<?php

namespace Tests\Support;

use App\Agent\Llm\LlmClient;
use App\Agent\Llm\LlmException;
use App\Agent\Llm\LlmResponse;
use Closure;
use LogicException;

/**
 * A model that says exactly what the test tells it to, in order, and records
 * every request it was sent. It lets the agent loop, tools and guardrails be
 * tested deterministically, including against a model that misbehaves.
 */
class ScriptedLlmClient implements LlmClient
{
    /** @var list<LlmResponse|LlmException|Closure(list<array<string, mixed>>): LlmResponse> */
    private array $script;

    /** @var list<array{system: string, tools: list<array<string, mixed>>, messages: list<array<string, mixed>>, allowTools: bool}> */
    public array $requests = [];

    public function __construct(LlmResponse|LlmException|Closure ...$script)
    {
        $this->script = array_values($script);
    }

    public function respond(string $system, array $tools, array $messages, bool $allowTools = true): LlmResponse
    {
        $this->requests[] = compact('system', 'tools', 'messages', 'allowTools');

        $next = array_shift($this->script) ?? throw new LogicException('The scripted model was asked for more responses than the test provided.');

        if ($next instanceof Closure) {
            $next = $next($messages);
        }

        if ($next instanceof LlmException) {
            throw $next;
        }

        return $next;
    }

    public static function says(string $text): LlmResponse
    {
        return new LlmResponse('end_turn', [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-'.md5($text)],
            ['type' => 'text', 'text' => $text],
        ], ['input_tokens' => 100, 'output_tokens' => 20]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function calls(string $tool, array $input, string $aside = ''): LlmResponse
    {
        $content = [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-'.md5($tool.json_encode($input))]];

        if ($aside !== '') {
            $content[] = ['type' => 'text', 'text' => $aside];
        }

        $content[] = ['type' => 'tool_use', 'id' => 'toolu_'.substr(md5($tool.json_encode($input)), 0, 12), 'name' => $tool, 'input' => $input];

        return new LlmResponse('tool_use', $content, ['input_tokens' => 100, 'output_tokens' => 20]);
    }

    public static function refuses(): LlmResponse
    {
        return new LlmResponse('refusal', []);
    }

    /**
     * The decoded payload of the most recent tool result in a transcript.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public static function lastToolResult(array $messages): array
    {
        $last = end($messages);

        return json_decode($last['content'][0]['content'], true);
    }
}
