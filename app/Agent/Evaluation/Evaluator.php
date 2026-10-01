<?php

namespace App\Agent\Evaluation;

use App\Agent\AgentRunner;
use App\Models\Conversation;

/**
 * Runs scenarios through the real agent: the same loop, tools, guardrails
 * and provider driver a patient's message goes through.
 */
class Evaluator
{
    public function __construct(private readonly AgentRunner $agent) {}

    public function run(Scenario $scenario, string $provider): ScenarioResult
    {
        $conversation = Conversation::create([
            'owner_hash' => hash('sha256', 'evaluation'),
            'locale' => $scenario->locale,
            'provider' => $provider,
        ]);

        try {
            $turns = [];

            foreach ($scenario->messages as $message) {
                $startedAt = microtime(true);
                $events = iterator_to_array($this->agent->run($conversation, $message), false);

                $turns[] = TurnRecord::fromEvents($events, microtime(true) - $startedAt);
            }

            return new ScenarioResult(
                $scenario,
                $turns,
                array_map(fn (callable $check) => (bool) $check($turns), $scenario->checks),
            );
        } finally {
            // Evaluation runs leave nothing behind (messages, tool calls and
            // recommendations cascade).
            $conversation->delete();
        }
    }
}
