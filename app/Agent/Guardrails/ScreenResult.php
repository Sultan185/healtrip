<?php

namespace App\Agent\Guardrails;

use App\Enums\Urgency;

final readonly class ScreenResult
{
    /**
     * @param  Urgency|null  $level  Emergency, Urgent ("triage this first"), or null when nothing matched.
     * @param  list<string>  $categories
     */
    public function __construct(
        public ?Urgency $level,
        public array $categories = [],
    ) {}

    public static function clear(): self
    {
        return new self(null);
    }

    public function isEmergency(): bool
    {
        return $this->level === Urgency::Emergency;
    }

    /**
     * The operator note that tells the model what the screen found.
     */
    public function operatorNote(): string
    {
        $categories = implode(', ', $this->categories);

        if ($this->isEmergency()) {
            return "Safety screen (automatic keyword check of the patient's last message): possible emergency indicators - {$categories}. "
                .'The patient is already being shown an emergency banner. Unless their own words make clear this is not happening now '
                ."(a past event, or someone else's history), lead your reply with clear emergency guidance and call present_recommendation "
                .'with urgency "emergency" before anything else.';
        }

        return "Safety screen (automatic keyword check of the patient's last message): symptoms that can be an emergency - {$categories}. "
            .'Before discussing providers, check for red-flag signs, and tell the patient to seek emergency care immediately if any are present.';
    }
}
