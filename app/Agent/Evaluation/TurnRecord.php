<?php

namespace App\Agent\Evaluation;

use App\Agent\AgentEvent;

/**
 * What one turn produced, as a patient and an auditor would see it. Built
 * from the same events the browser receives, so a scenario is graded on
 * observable behaviour and not on internals.
 */
final readonly class TurnRecord
{
    /**
     * @param  string  $reply  Everything the assistant said this turn.
     * @param  list<array{name: string, input: array<string, mixed>, is_error: bool, error: ?string}>  $toolCalls
     * @param  array<string, mixed>|null  $recommendation  The card shown, if any.
     * @param  array{level: string, categories: list<string>}|null  $safety
     * @param  string|null  $guard  "redraft" or "blocked" when the output guard intervened.
     * @param  string|null  $fallback  Set when a system-written reply replaced the model's.
     * @param  string|null  $error  Provider failure code, if the provider failed.
     * @param  array<string, int>  $usage
     */
    public function __construct(
        public string $reply,
        public array $toolCalls,
        public ?array $recommendation,
        public ?array $safety,
        public ?string $guard,
        public ?string $fallback,
        public ?string $error,
        public float $seconds,
        public array $usage,
    ) {}

    /**
     * @param  list<AgentEvent>  $events
     */
    public static function fromEvents(array $events, float $seconds): self
    {
        $reply = [];
        $toolCalls = [];
        $recommendation = $safety = $guard = $fallback = $error = null;
        $usage = [];

        foreach ($events as $event) {
            $data = $event->data;

            switch ($event->type) {
                case AgentEvent::MESSAGE:
                    $reply[] = $data['text'];
                    $fallback = $data['fallback'] ?? $fallback;
                    break;
                case AgentEvent::TOOL_STARTED:
                    $toolCalls[(string) $data['id']] = [
                        'name' => (string) $data['name'],
                        'input' => (array) $data['input'],
                        'is_error' => false,
                        'error' => null,
                    ];
                    break;
                case AgentEvent::TOOL_FINISHED:
                    $id = (string) $data['id'];

                    if (isset($toolCalls[$id])) {
                        $toolCalls[$id]['is_error'] = (bool) $data['is_error'];
                        $toolCalls[$id]['error'] = isset($data['error']) ? (string) $data['error'] : null;
                    }
                    break;
                case AgentEvent::RECOMMENDATION:
                    $recommendation = $data;
                    break;
                case AgentEvent::SAFETY:
                    $safety = [
                        'level' => (string) $data['level'],
                        'categories' => array_values(array_map(strval(...), (array) $data['categories'])),
                    ];
                    break;
                case AgentEvent::GUARD:
                    $guard = $data['action'];
                    break;
                case AgentEvent::ERROR:
                    $error = $data['code'];
                    break;
                case AgentEvent::DONE:
                    $usage = (array) $data['usage'];
                    break;
            }
        }

        return new self(implode("\n\n", $reply), array_values($toolCalls), $recommendation, $safety, $guard, $fallback, $error, $seconds, $usage);
    }

    /**
     * Whether a tool was called successfully with input the test accepts.
     *
     * @param  (callable(array<string, mixed>): bool)|null  $accepts
     */
    public function called(string $tool, ?callable $accepts = null): bool
    {
        foreach ($this->toolCalls as $call) {
            if ($call['name'] === $tool && ! $call['is_error'] && ($accepts === null || $accepts($call['input']))) {
                return true;
            }
        }

        return false;
    }

    public function says(string $pattern): bool
    {
        return preg_match($pattern, $this->reply) === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recommendedDoctors(): array
    {
        return $this->recommendation['doctors'] ?? [];
    }
}
