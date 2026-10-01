<?php

namespace App\Agent;

use App\Agent\Guardrails\GroundingLedger;
use App\Agent\Guardrails\GuardVerdict;
use App\Agent\Guardrails\OutputGuard;
use App\Agent\Guardrails\RedFlagScreener;
use App\Agent\Llm\LlmException;
use App\Agent\Llm\LlmManager;
use App\Agent\Llm\LlmResponse;
use App\Agent\Tools\ToolContext;
use App\Agent\Tools\ToolRegistry;
use App\Enums\MessageKind;
use App\Http\Resources\RecommendationResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolCall;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs one patient turn: screen the message, then loop model -> tools ->
 * model until the model answers in text, checking that answer before it is
 * shown.
 *
 * The transcript in the database is the single source of truth. It is
 * append-only and is re-read and sent verbatim on every model call, which is
 * what keeps the prompt cache warm and the model's signed reasoning blocks
 * valid. Nothing here edits or reorders history.
 */
class AgentRunner
{
    public function __construct(
        private readonly LlmManager $llms,
        private readonly ToolRegistry $tools,
        private readonly RedFlagScreener $screener,
        private readonly OutputGuard $guard,
    ) {}

    /**
     * @return Generator<int, AgentEvent>
     */
    public function run(Conversation $conversation, string $text): Generator
    {
        $turn = $conversation->turns + 1;
        $conversation->update(['turns' => $turn]);
        $this->append($conversation, $turn, 'user', MessageKind::UserText, $text, visibleText: $text);

        yield new AgentEvent(AgentEvent::TURN_STARTED, ['turn' => $turn]);

        yield from $this->screen($conversation, $turn, $text);

        $context = new ToolContext($conversation, $turn, GroundingLedger::forConversation($conversation));
        $maxIterations = (int) config('healtrip.agent.max_iterations');
        $usage = [];
        $redrafted = false;

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            yield new AgentEvent(AgentEvent::THINKING, ['iteration' => $iteration]);

            try {
                $response = $this->llms->for($conversation->provider)->respond(
                    SystemPrompt::text(),
                    $this->tools->definitions(),
                    $this->transcript($conversation),
                    // The last allowed call may not use tools, so the turn always
                    // ends with an answer instead of an unfinished tool request.
                    allowTools: $iteration < $maxIterations,
                );
            } catch (LlmException $e) {
                // The provider is not configured, or failed after the driver's
                // own retries. Close the turn with a stored reply so the
                // transcript stays well formed (every patient message
                // answered) and can be continued.
                yield new AgentEvent(AgentEvent::ERROR, ['code' => $e->reason, 'retryable' => $e->retryable]);

                return yield from $this->fallback($conversation, $turn, 'unavailable', $usage);
            }

            $usage = $this->addUsage($usage, $response->usage);

            if ($response->isRefusal()) {
                return yield from $this->fallback($conversation, $turn, 'refusal', $usage);
            }

            if ($response->stopReason === 'tool_use' && $response->toolUses() !== []) {
                yield from $this->runTools($response, $context);

                continue;
            }

            $reply = $response->text();

            if ($response->stopReason === 'max_tokens' || $reply === '') {
                return yield from $this->fallback($conversation, $turn, 'incomplete', $usage);
            }

            $verdict = $this->guard->inspect($reply, $context->ledger);

            if ($verdict->passed()) {
                $this->append($conversation, $turn, 'assistant', MessageKind::Assistant, $response->content, visibleText: $reply, meta: $this->meta($response));

                yield new AgentEvent(AgentEvent::MESSAGE, ['text' => $reply, 'final' => true]);

                return yield $this->done($conversation, $turn, $usage);
            }

            // The draft named something that is not in the directory results.
            // It is discarded unseen: never shown, and never added to the
            // transcript.
            $this->reportUngrounded($conversation, $verdict);

            if ($redrafted) {
                yield new AgentEvent(AgentEvent::GUARD, ['action' => 'blocked', 'violations' => $verdict->types()]);

                return yield from $this->fallback($conversation, $turn, 'ungrounded', $usage);
            }

            yield new AgentEvent(AgentEvent::GUARD, ['action' => 'redraft', 'violations' => $verdict->types()]);

            $redrafted = true;
            $this->requestRedraft($conversation, $turn, $verdict);
        }

        return yield from $this->fallback($conversation, $turn, 'incomplete', $usage);
    }

    /**
     * @return Generator<int, AgentEvent>
     */
    private function screen(Conversation $conversation, int $turn, string $text): Generator
    {
        $screen = $this->screener->screen($text);

        if ($screen->level === null) {
            return;
        }

        $conversation->escalateTo($screen->level);

        // Sent as a system message so it carries operator authority and
        // cannot be forged from inside a patient message.
        $this->append($conversation, $turn, 'system', MessageKind::SafetyNote, $screen->operatorNote(), meta: [
            'level' => $screen->level->value,
            'categories' => $screen->categories,
        ]);

        yield new AgentEvent(AgentEvent::SAFETY, [
            'level' => $screen->level->value,
            'categories' => $screen->categories,
        ]);
    }

    /**
     * Execute every tool call in a model response, then store the response,
     * the audit records and the results together: the transcript never holds
     * a tool request without its answer.
     *
     * @return Generator<int, AgentEvent>
     */
    private function runTools(LlmResponse $response, ToolContext $context): Generator
    {
        $results = [];
        $calls = [];

        // Anything the model said alongside its tool calls is addressed to
        // the patient too, so it goes through the same check as a final reply.
        $aside = $response->text();
        $asideShown = $aside !== '' && $this->guard->inspect($aside, $context->ledger)->passed();

        if ($asideShown) {
            yield new AgentEvent(AgentEvent::MESSAGE, ['text' => $aside, 'final' => false]);
        }

        foreach ($response->toolUses() as $use) {
            yield new AgentEvent(AgentEvent::TOOL_STARTED, ['id' => $use['id'], 'name' => $use['name'], 'input' => $use['input']]);

            $startedAt = hrtime(true);
            $outcome = $this->tools->execute($use['name'], $use['input'], $context);
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            $context->ledger->record($outcome->doctorIds, $outcome->hospitalIds);

            $results[] = [
                'type' => 'tool_result',
                'tool_use_id' => $use['id'],
                'content' => json_encode($outcome->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'is_error' => $outcome->isError,
            ];

            $calls[] = [
                'conversation_id' => $context->conversation->id,
                'turn' => $context->turn,
                'tool_use_id' => $use['id'],
                'name' => $use['name'],
                'input' => $use['input'],
                'output' => $outcome->payload,
                'is_error' => $outcome->isError,
                'doctor_ids' => $outcome->doctorIds,
                'hospital_ids' => $outcome->hospitalIds,
                'duration_ms' => $durationMs,
            ];

            yield new AgentEvent(AgentEvent::TOOL_FINISHED, [
                'id' => $use['id'],
                'name' => $use['name'],
                'is_error' => $outcome->isError,
                'error' => $outcome->isError ? ($outcome->payload['error'] ?? 'error') : null,
                'stats' => $outcome->stats,
                'duration_ms' => $durationMs,
            ]);

            if ($outcome->recommendation !== null) {
                yield new AgentEvent(AgentEvent::RECOMMENDATION, RecommendationResource::make($outcome->recommendation)->resolve());
            }
        }

        DB::transaction(function () use ($response, $context, $results, $calls, $aside, $asideShown) {
            $this->append($context->conversation, $context->turn, 'assistant', MessageKind::Assistant, $response->content, visibleText: $asideShown ? $aside : null, meta: $this->meta($response));

            foreach ($calls as $call) {
                ToolCall::create($call);
            }

            $this->append($context->conversation, $context->turn, 'user', MessageKind::ToolResults, $results);
        });
    }

    /**
     * Ask for another draft. When the transcript allows an operator note at
     * this point (it must follow a user-role message) the model is told what
     * was wrong; otherwise the request is simply repeated.
     */
    private function requestRedraft(Conversation $conversation, int $turn, GuardVerdict $verdict): void
    {
        $last = $conversation->messages()->reorder('id', 'desc')->first();

        if ($last?->role !== 'user') {
            return;
        }

        $this->append($conversation, $turn, 'system', MessageKind::GuardCorrection,
            'Output check: your draft reply was withheld from the patient because it contained details that could not be verified '
            ."({$verdict->describe()}). You may name a provider only after showing them with present_recommendation, exactly as the "
            .'search results name them, and you may not write phone numbers or links. Present the providers first, or reply without them.',
            meta: ['violations' => $verdict->types()],
        );
    }

    /**
     * End the turn with a safe, pre-written reply when the model's own could
     * not be used.
     *
     * @param  array<string, int>  $usage
     * @return Generator<int, AgentEvent>
     */
    private function fallback(Conversation $conversation, int $turn, string $reason, array $usage): Generator
    {
        $text = trans("agent.fallback.{$reason}", [], $conversation->locale);

        $this->append($conversation, $turn, 'assistant', MessageKind::Fallback, [['type' => 'text', 'text' => $text]], visibleText: $text, meta: ['reason' => $reason]);

        yield new AgentEvent(AgentEvent::MESSAGE, ['text' => $text, 'final' => true, 'fallback' => $reason]);

        yield $this->done($conversation, $turn, $usage);
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function done(Conversation $conversation, int $turn, array $usage): AgentEvent
    {
        return new AgentEvent(AgentEvent::DONE, [
            'turn' => $turn,
            'urgency' => $conversation->urgency?->value,
            'usage' => (object) $usage,
        ]);
    }

    /**
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function transcript(Conversation $conversation): array
    {
        return array_values($conversation->messages()->get()->map(fn (Message $message) => $message->toApi())->all());
    }

    /**
     * @param  string|list<array<string, mixed>>  $content
     * @param  array<string, mixed>|null  $meta
     */
    private function append(Conversation $conversation, int $turn, string $role, MessageKind $kind, string|array $content, ?string $visibleText = null, ?array $meta = null): void
    {
        $conversation->messages()->create([
            'turn' => $turn,
            'role' => $role,
            'kind' => $kind,
            'content' => $content,
            'visible_text' => $visibleText,
            'meta' => $meta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(LlmResponse $response): array
    {
        return [
            'model' => $response->model,
            'stop_reason' => $response->stopReason,
            'usage' => $response->usage,
            'prompt_version' => SystemPrompt::VERSION,
        ];
    }

    /**
     * @param  array<string, int>  $total
     * @param  array<string, int>  $usage
     * @return array<string, int>
     */
    private function addUsage(array $total, array $usage): array
    {
        foreach ($usage as $key => $value) {
            $total[$key] = ($total[$key] ?? 0) + $value;
        }

        return $total;
    }

    /**
     * Violation types only: the draft itself may quote the patient.
     */
    private function reportUngrounded(Conversation $conversation, GuardVerdict $verdict): void
    {
        Log::warning('Agent draft failed the grounding check', [
            'conversation_id' => $conversation->id,
            'violations' => $verdict->types(),
        ]);
    }
}
