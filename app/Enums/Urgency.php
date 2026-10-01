<?php

namespace App\Enums;

enum Urgency: string
{
    case Routine = 'routine';
    case Urgent = 'urgent';
    case Emergency = 'emergency';

    public function rank(): int
    {
        return match ($this) {
            self::Routine => 0,
            self::Urgent => 1,
            self::Emergency => 2,
        };
    }

    /**
     * The safety floor: the more severe of two levels. The model may escalate
     * above what the deterministic screen found, but never de-escalate below it.
     */
    public static function max(self $a, ?self $b): self
    {
        return $b !== null && $b->rank() > $a->rank() ? $b : $a;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
