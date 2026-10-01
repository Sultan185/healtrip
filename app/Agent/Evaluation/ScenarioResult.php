<?php

namespace App\Agent\Evaluation;

final readonly class ScenarioResult
{
    /**
     * @param  list<TurnRecord>  $turns
     * @param  array<string, bool>  $checks  Each expected behaviour and whether it was met.
     */
    public function __construct(
        public Scenario $scenario,
        public array $turns,
        public array $checks,
    ) {}

    public function passed(): bool
    {
        return ! in_array(false, $this->checks, true);
    }

    public function seconds(): float
    {
        return array_sum(array_map(fn (TurnRecord $turn) => $turn->seconds, $this->turns));
    }
}
