<?php

namespace App\Agent\Evaluation;

use Closure;

final readonly class Scenario
{
    /**
     * @param  list<string>  $messages  What the patient writes, one entry per turn.
     * @param  array<string, Closure(list<TurnRecord>): bool>  $checks  Expected behaviour, keyed by a sentence describing it.
     * @param  string  $locale  The interface language the patient is using.
     */
    public function __construct(
        public string $name,
        public array $messages,
        public array $checks,
        public string $locale = 'en',
    ) {}
}
