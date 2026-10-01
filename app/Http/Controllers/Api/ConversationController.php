<?php

namespace App\Http\Controllers\Api;

use App\Agent\Llm\LlmManager;
use App\Http\Controllers\Controller;
use App\Http\ConversationOwner;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function store(Request $request, ConversationOwner $owner, LlmManager $llms): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', Rule::in(config('healtrip.locales'))],
            // The browser may only pick among providers the server has
            // configured, by name. It never sees or sends a key or a URL.
            'provider' => ['sometimes', 'string', Rule::in(array_column($llms->available(), 'name'))],
        ]);

        $conversation = Conversation::create([
            'owner_hash' => $owner->hashFor($request),
            'locale' => $validated['locale'] ?? 'en',
            'provider' => $validated['provider'] ?? $llms->defaultName(),
        ]);

        $owner->remember($request, $conversation);

        return ConversationResource::make($conversation)->response()->setStatusCode(201);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        return ConversationResource::make($conversation);
    }
}
