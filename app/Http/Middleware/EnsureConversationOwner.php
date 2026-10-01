<?php

namespace App\Http\Middleware;

use App\Http\ConversationOwner;
use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConversationOwner
{
    public function __construct(private readonly ConversationOwner $owner) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $conversation = $request->route('conversation');

        // 404, not 403: someone else's conversation should be indistinguishable
        // from one that does not exist.
        abort_unless($conversation instanceof Conversation && $this->owner->owns($request, $conversation), 404);

        return $next($request);
    }
}
