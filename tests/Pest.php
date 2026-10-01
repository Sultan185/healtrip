<?php

use App\Agent\AgentEvent;
use App\Agent\AgentRunner;
use App\Agent\Llm\LlmManager;
use App\Models\Conversation;
use Database\Seeders\DirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScriptedLlmClient;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutVite();
        $this->seed(DirectorySeeder::class);
    })
    ->in('Feature');

/**
 * Put a scripted model behind a provider name for the current test.
 */
function scriptModel(mixed ...$script): ScriptedLlmClient
{
    return scriptProvider('anthropic', ...$script);
}

function scriptProvider(string $provider, mixed ...$script): ScriptedLlmClient
{
    $model = new ScriptedLlmClient(...$script);

    app(LlmManager::class)->register($provider, $model);

    return $model;
}

function conversation(string $locale = 'en', string $provider = 'anthropic'): Conversation
{
    return Conversation::create(['owner_hash' => str_repeat('a', 64), 'locale' => $locale, 'provider' => $provider]);
}

/**
 * Run one agent turn to completion and return everything it emitted.
 *
 * @return list<AgentEvent>
 */
function runTurn(Conversation $conversation, string $text): array
{
    return iterator_to_array(app(AgentRunner::class)->run($conversation, $text), false);
}

/**
 * @param  list<AgentEvent>  $events
 * @return list<string>
 */
function eventTypes(array $events): array
{
    return array_map(fn (AgentEvent $event) => $event->type, $events);
}

/**
 * @param  list<AgentEvent>  $events
 */
function firstEvent(array $events, string $type): ?AgentEvent
{
    foreach ($events as $event) {
        if ($event->type === $type) {
            return $event;
        }
    }

    return null;
}

/**
 * The search_doctors input with every filter empty, overridden as needed.
 *
 * @param  array<string, mixed>  $filters
 * @return array<string, mixed>
 */
function doctorSearch(array $filters = []): array
{
    return [
        'specialty' => null,
        'country' => null,
        'city' => null,
        'language' => null,
        'second_opinion_only' => false,
        'telemedicine_only' => false,
        'max_fee_usd' => null,
        ...$filters,
    ];
}
