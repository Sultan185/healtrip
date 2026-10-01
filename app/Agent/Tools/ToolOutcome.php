<?php

namespace App\Agent\Tools;

use App\Models\Recommendation;

final readonly class ToolOutcome
{
    /**
     * @param  array<string, mixed>  $payload  What the model receives as the tool result.
     * @param  list<int>  $doctorIds  Doctors this call returned (they become recommendable).
     * @param  list<int>  $hospitalIds  Hospitals this call returned.
     * @param  array{returned: int, total: int}|null  $stats  Result counts for the UI trace.
     */
    public function __construct(
        public array $payload,
        public bool $isError = false,
        public array $doctorIds = [],
        public array $hospitalIds = [],
        public ?array $stats = null,
        public ?Recommendation $recommendation = null,
    ) {}

    /**
     * A failure the model can read and recover from. Tool errors are part of
     * the conversation, not exceptions: the model gets told what was wrong
     * and tries again.
     *
     * @param  array<string, mixed>  $details
     */
    public static function error(string $code, string $message, array $details = []): self
    {
        return new self(['error' => $code, 'message' => $message, ...$details], isError: true);
    }
}
