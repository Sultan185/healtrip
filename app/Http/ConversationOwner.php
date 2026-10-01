<?php

namespace App\Http;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Ties conversations to the browser session that started them.
 *
 * The prototype has no accounts, but a conversation is health information
 * and must not be readable by anyone who learns its ID. Each session holds a
 * random token; a conversation stores only that token's hash.
 */
class ConversationOwner
{
    private const SESSION_KEY = 'healtrip.owner_token';

    private const CURRENT_KEY = 'healtrip.conversation_id';

    public function hashFor(Request $request): string
    {
        $session = $request->session();

        if (! $session->has(self::SESSION_KEY)) {
            $session->put(self::SESSION_KEY, Str::random(40));
        }

        return hash('sha256', (string) $session->get(self::SESSION_KEY));
    }

    public function owns(Request $request, Conversation $conversation): bool
    {
        return hash_equals($conversation->owner_hash, $this->hashFor($request));
    }

    public function remember(Request $request, Conversation $conversation): void
    {
        $request->session()->put(self::CURRENT_KEY, $conversation->id);
    }

    /**
     * The conversation this session was last in, so a page reload resumes it.
     */
    public function current(Request $request): ?Conversation
    {
        $id = $request->session()->get(self::CURRENT_KEY);

        if (! is_string($id)) {
            return null;
        }

        return Conversation::query()
            ->whereKey($id)
            ->where('owner_hash', $this->hashFor($request))
            ->first();
    }
}
