<?php

namespace App\Agent;

/**
 * Something that happened during a turn, as the browser will hear about it.
 * The agent yields these as it works; the HTTP layer writes each one out as
 * a server-sent event.
 */
final readonly class AgentEvent
{
    public const TURN_STARTED = 'turn.started';

    public const SAFETY = 'safety';

    public const THINKING = 'agent.thinking';

    public const TOOL_STARTED = 'tool.started';

    public const TOOL_FINISHED = 'tool.finished';

    public const RECOMMENDATION = 'recommendation';

    public const GUARD = 'guard';

    public const MESSAGE = 'message';

    public const ERROR = 'error';

    public const DONE = 'done';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $type,
        public array $data = [],
    ) {}
}
