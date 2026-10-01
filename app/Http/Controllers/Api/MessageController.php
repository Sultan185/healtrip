<?php

namespace App\Http\Controllers\Api;

use App\Agent\AgentEvent;
use App\Agent\AgentRunner;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MessageController extends Controller
{
    /**
     * Run one patient turn and stream the agent's progress as server-sent
     * events: the safety screen result, each tool call, any recommendation
     * card, and finally the reply.
     */
    public function store(SendMessageRequest $request, Conversation $conversation, AgentRunner $agent): StreamedResponse|JsonResponse
    {
        if ($conversation->turns >= config('healtrip.agent.max_turns')) {
            return response()->json(['code' => 'conversation_full', 'message' => 'This conversation has reached its length limit. Start a new one.'], 422);
        }

        // One turn at a time per conversation: two interleaved turns would
        // corrupt the transcript's request/response ordering.
        $lock = Cache::lock("conversation:{$conversation->id}:turn", 600);

        if (! $lock->get()) {
            return response()->json(['code' => 'turn_in_progress', 'message' => 'A reply is already being generated for this conversation.'], 409);
        }

        if ($request->has('locale')) {
            $conversation->update(['locale' => $request->validated('locale')]);
        }

        $text = (string) $request->validated('text');

        return response()->stream(function () use ($agent, $conversation, $text, $lock) {
            // If the patient closes the tab mid-turn, finish anyway: stopping
            // half way would leave the stored transcript without its reply.
            ignore_user_abort(true);
            set_time_limit(600);

            try {
                foreach ($agent->run($conversation, $text) as $event) {
                    $this->send($event);
                }
            } catch (Throwable $e) {
                report($e);

                $this->send(new AgentEvent(AgentEvent::ERROR, ['code' => 'internal', 'retryable' => true]));
            } finally {
                $lock->release();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function send(AgentEvent $event): void
    {
        if (connection_aborted()) {
            return;
        }

        echo "event: {$event->type}\n";
        echo 'data: '.json_encode($event->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
