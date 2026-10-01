<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model providers
    |--------------------------------------------------------------------------
    |
    | The agent talks to a model through App\Agent\Llm\LlmClient, and
    | App\Agent\Llm\LlmManager builds a client for each provider below. Two
    | drivers cover them all:
    |
    |   anthropic  Claude, through the official Anthropic SDK.
    |   openai     The OpenAI Chat Completions wire format, which OpenAI,
    |              Gemini, Ollama, Groq, OpenRouter and others all speak. A
    |              new provider of that kind is one more block here.
    |
    | A provider is offered only when it is configured (a model and, unless
    | it needs none, a key). Keys are read on the server and never sent to
    | the browser. Each conversation stays on the provider it started with.
    |
    */

    'llm' => [
        // Used for new conversations. If it is not configured, the first
        // provider that is takes its place.
        'default' => env('LLM_PROVIDER', 'anthropic'),

        // Limits shared by every provider.
        'max_tokens' => (int) env('LLM_MAX_TOKENS', 8000),
        'timeout' => (float) env('LLM_TIMEOUT', 120),
        'max_retries' => (int) env('LLM_MAX_RETRIES', 2),

        'providers' => [

            'anthropic' => [
                'driver' => 'anthropic',
                'label' => 'Claude',
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model' => env('ANTHROPIC_MODEL', 'claude-fable-5-1'),
                // Thinking depth / latency trade-off: low, medium, high, xhigh, max.
                'effort' => env('ANTHROPIC_EFFORT', 'medium'),
                // If the model's safety classifiers decline a request, let the
                // API retry it on its default fallback model instead of
                // failing the turn.
                'refusal_fallback' => (bool) env('ANTHROPIC_REFUSAL_FALLBACK', true),
            ],

            'openai' => [
                'driver' => 'openai',
                'label' => 'GPT',
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                // OpenAI enforces tool schemas server-side when asked to.
                'strict_tools' => true,
                'token_limit_param' => 'max_completion_tokens',
                // Only for reasoning models: minimal, low, medium, high.
                'reasoning_effort' => env('OPENAI_REASONING_EFFORT'),
            ],

            'gemini' => [
                'driver' => 'openai',
                'label' => 'Gemini',
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_MODEL'),
                'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
                'strict_tools' => false,
                'token_limit_param' => 'max_tokens',
            ],

            'ollama' => [
                'driver' => 'openai',
                'label' => 'Ollama (local)',
                'api_key' => null,
                'requires_key' => false,
                // Must be a model with tool-calling support.
                'model' => env('OLLAMA_MODEL'),
                'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434/v1'),
                'strict_tools' => false,
                'token_limit_param' => 'max_tokens',
            ],

        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent limits
    |--------------------------------------------------------------------------
    |
    | Hard ceilings that hold no matter what the model or the patient does.
    |
    */

    'agent' => [
        // Model round-trips allowed inside one patient turn.
        'max_iterations' => 6,
        // Rows returned by one directory search.
        'max_search_results' => 5,
        // Providers shown on one recommendation card.
        'max_recommended' => 3,
        // Patient turns per conversation before a new one must be started.
        'max_turns' => (int) env('HEALTRIP_MAX_TURNS', 30),
        'max_message_length' => 2000,
    ],

    'rate_limits' => [
        'messages_per_minute' => (int) env('HEALTRIP_MESSAGES_PER_MINUTE', 10),
        'messages_per_day' => (int) env('HEALTRIP_MESSAGES_PER_DAY', 150),
    ],

    'locales' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Countries the directory covers
    |--------------------------------------------------------------------------
    */

    'countries' => [
        'TR' => ['en' => 'Türkiye', 'ar' => 'تركيا', 'aliases' => ['turkey', 'turkiye']],
        'AE' => ['en' => 'United Arab Emirates', 'ar' => 'الإمارات', 'aliases' => ['uae', 'emirates', 'الامارات', 'الإمارات العربية المتحدة']],
        'EG' => ['en' => 'Egypt', 'ar' => 'مصر', 'aliases' => []],
        'SA' => ['en' => 'Saudi Arabia', 'ar' => 'السعودية', 'aliases' => ['ksa', 'saudi', 'المملكة العربية السعودية']],
        'DE' => ['en' => 'Germany', 'ar' => 'ألمانيا', 'aliases' => ['deutschland', 'المانيا']],
    ],

    'languages' => ['ar', 'en', 'tr', 'de', 'fr'],

];
