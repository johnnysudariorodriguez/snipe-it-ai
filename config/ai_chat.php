<?php

$httpUriRaw = trim((string) env('AI_CHAT_HTTP_URI', 'ai-chat/message'), '/');
$httpUriSanitized = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $httpUriRaw) ?: 'ai-chat/message';

return [

    'provider' => env('AI_PROVIDER', 'openai'),

    'openai_key' => env('OPENAI_API_KEY'),

    'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    'openai_url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),

    'gemini_key' => env('GEMINI_API_KEY'),

    'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),

    /*
     * Comma-separated fallbacks if GEMINI_MODEL is not available for your project (ListModels in AI Studio).
     * Example: gemini-2.0-flash,gemini-1.5-flash-002
     */
    'gemini_model_fallbacks' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_MODEL_FALLBACKS', 'gemini-2.0-flash,gemini-1.5-flash-002'))))),

    'gemini_url_template' => env(
        'GEMINI_URL_TEMPLATE',
        'https://generativelanguage.googleapis.com/{api_version}/models/{model}:generateContent'
    ),

    /*
     * Relative URI for POST (web middleware + session auth). Change for Azure ingress path rules, e.g.
     * api/v1/ai-chat/message — must stay in sync with reverse-proxy paths (no trailing slash).
     */
    'http_uri' => $httpUriSanitized,

    /*
     * Optional full URL override for the chat widget fetch() call when APP_URL / proxies are hard to align.
     * Example: https://snipe.company.com/api/v1/ai-chat/message
     */
    'endpoint_override' => env('AI_CHAT_ENDPOINT_URL'),

    'throttle_per_minute' => (int) env('AI_CHAT_THROTTLE_PER_MINUTE', 30),

    /*
     * When true, only superusers may call OpenAI/Gemini (built-in `ops` commands still obey normal permissions).
     */
    'llm_requires_superuser' => filter_var(env('AI_CHAT_LLM_REQUIRES_SUPERUSER', false), FILTER_VALIDATE_BOOL),

];
