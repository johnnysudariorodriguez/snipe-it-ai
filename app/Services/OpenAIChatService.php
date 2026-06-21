<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAIChatService
{
    protected string $apiKey;
    protected string $endpoint;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('ai_chat.openai_key', env('OPENAI_API_KEY', ''));
        if (is_string($this->apiKey) && $this->apiKey !== '') {
            $this->apiKey = trim($this->apiKey);
            $this->apiKey = str_replace(['"', "'"], '', $this->apiKey);
            $this->apiKey = ltrim($this->apiKey, "= ");
        }
        $this->endpoint = config('ai_chat.openai_url', 'https://api.openai.com/v1/chat/completions');
        $this->model = config('ai_chat.openai_model', 'gpt-4o-mini');
    }

    public function chat(array $messages, array $options = []): array
    {
        if ($this->apiKey === '') {
            return ['reply' => '', 'error' => 'OpenAI API key not configured'];
        }

        $payload = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 800,
        ], $options['overrides'] ?? []);

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(90)
            ->post($this->endpoint, $payload);

        if (! $response->successful()) {
            $err = data_get($response->json(), 'error.message') ?: 'Upstream error';
            return ['reply' => '', 'error' => $err, 'status' => $response->status()];
        }

        $json = $response->json();
        $content = data_get($json, 'choices.0.message.content') ?? '';

        // Strip assistant-format headers (INTENT / FORMAT) if present and remove
        // any context citation markers like [Context #3] that are only useful
        // for internal prompting. Keep the main text.
        if (is_string($content) && $content !== '') {
            $content = preg_replace('/\A\s*INTENT:.*\R\s*FORMAT:.*\R\s*/i', '', $content);
            $content = preg_replace('/\[Context\s*#\d+\]/i', '', $content);
            $content = trim((string) $content);
        }

        $tokens = data_get($json, 'usage.total_tokens') ?: data_get($json, 'usage.total', 0);
        $model = data_get($json, 'model') ?: $this->model;

        return [
            'reply' => (string) $content,
            'raw' => $json,
            'tokens' => (int) $tokens,
            'model' => (string) $model,
        ];
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
