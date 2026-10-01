<?php

namespace App\Agent\Guardrails;

final readonly class GuardVerdict
{
    /**
     * @param  list<array{type: string, value: string}>  $violations
     */
    public function __construct(public array $violations = []) {}

    public function passed(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_values(array_unique(array_column($this->violations, 'type')));
    }

    public function describe(): string
    {
        return implode('; ', array_map(
            fn (array $violation) => "{$violation['type']}: \"{$violation['value']}\"",
            $this->violations,
        ));
    }
}
