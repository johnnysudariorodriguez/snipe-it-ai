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
