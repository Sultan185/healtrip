<?php

namespace App\Http\Resources;

use App\Agent\Llm\LlmManager;
use App\Enums\MessageKind;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Recommendation;
use App\Models\ToolCall;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation as the chat UI shows it: one entry per patient turn, built
 * from the transcript, the tool-call audit log and the recommendations. The
 * raw transcript (model reasoning blocks, tool payloads, operator notes) is
 * not exposed.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $messages = $this->messages()->get()->groupBy('turn');
        $toolCalls = $this->toolCalls()->get()->groupBy('turn');
        $recommendations = $this->recommendations()->get()->groupBy('turn');

        $turns = [];

        foreach ($messages as $turn => $turnMessages) {
            $safety = $turnMessages->firstWhere('kind', MessageKind::SafetyNote);
            /** @var Recommendation|null $recommendation */
            $recommendation = $recommendations->get($turn)?->last();

            $turns[] = [
                'turn' => (int) $turn,
                'user_text' => $turnMessages->firstWhere('kind', MessageKind::UserText)?->visible_text,
                'safety' => $safety instanceof Message ? [
                    'level' => $safety->meta['level'] ?? null,
                    'categories' => $safety->meta['categories'] ?? [],
                ] : null,
                'texts' => $turnMessages
                    ->filter(fn (Message $message) => $message->role === 'assistant' && $message->visible_text !== null)
                    ->map(fn (Message $message) => $message->visible_text)
                    ->values()
                    ->all(),
                'tools' => ($toolCalls->get($turn) ?? collect())->map(fn (ToolCall $call) => [
                    'id' => $call->tool_use_id,
                    'name' => $call->name,
                    'input' => $call->input,
                    'is_error' => $call->is_error,
                    'error' => $call->is_error ? ($call->output['error'] ?? 'error') : null,
                    'stats' => isset($call->output['total_matches']) ? [
                        'returned' => count($call->output['doctors'] ?? $call->output['hospitals'] ?? []),
                        'total' => (int) $call->output['total_matches'],
                    ] : null,
                    'duration_ms' => $call->duration_ms,
                ])->values()->all(),
                'recommendation' => $recommendation ? RecommendationResource::make($recommendation)->resolve($request) : null,
                'guard' => match (true) {
                    $turnMessages->contains(fn (Message $message) => $message->kind === MessageKind::Fallback && ($message->meta['reason'] ?? null) === 'ungrounded') => 'blocked',
                    $turnMessages->contains('kind', MessageKind::GuardCorrection) => 'redraft',
                    default => null,
                },
            ];
        }

        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'provider' => app(LlmManager::class)->describe($this->provider),
            'urgency' => $this->urgency?->value,
            'turns' => $turns,
        ];
    }
}
