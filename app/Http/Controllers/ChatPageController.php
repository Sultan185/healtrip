<?php

namespace App\Http\Controllers;

use App\Agent\Llm\LlmManager;
use App\Http\ConversationOwner;
use App\Http\Resources\ConversationResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatPageController extends Controller
{
    public function __invoke(Request $request, ConversationOwner $owner, LlmManager $llms): Response
    {
        $conversation = $owner->current($request);

        return Inertia::render('chat', [
            'conversation' => $conversation ? ConversationResource::make($conversation)->resolve($request) : null,
            // Name, label and model of each configured provider; no secrets.
            'providers' => $llms->available(),
            'defaultProvider' => $llms->defaultName(),
            'limits' => [
                'maxMessageLength' => config('healtrip.agent.max_message_length'),
            ],
        ]);
    }
}
