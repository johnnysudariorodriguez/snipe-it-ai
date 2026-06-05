<?php

namespace App\Http\Controllers;

use App\Services\AiOperationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    private const SYSTEM_PROMPT = 'You help Snipe-IT users with IT asset management concepts, navigation, and best practices. '
        .'Be concise. You cannot see this organization\'s live asset data; if they ask for counts or specific records, explain they should use the Snipe-IT UI or reports.';

    /**
     * Full system text for Gemini/OpenAI including built-in chat command catalog.
     */
    protected function llmPrompt(): string
    {
        return trim(self::SYSTEM_PROMPT.AiOperationsService::llmCommandsInstructions());
    }

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function message(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
        ]);

        $operationsReply = app(AiOperationsService::class)->handle((string) $request->input('message'), $request->user());
        if ($operationsReply !== null) {
            return response()->json([
                'reply' => $operationsReply['reply'],
                'links' => $operationsReply['links'] ?? [],
            ]);
        }

        if (config('ai_chat.llm_requires_superuser') && ! $request->user()->isSuperUser()) {
            return response()->json([
                'error' => __('Only superadmins may use the cloud AI assistant. Try built-in commands like `ops help`.'),
            ], 403);
        }

        $provider = Str::lower((string) config('ai_chat.provider', 'openai'));
        if ($provider === 'gemini') {
            return $this->messageWithGemini((string) $request->input('message'));
        }

        return $this->messageWithOpenAi((string) $request->input('message'));
    }

    protected function messageWithOpenAi(string $message)
    {
        $key = (string) config('ai_chat.openai_key');
        if ($key === '') {
            return response()->json(['error' => __('AI assistant is not configured (missing OPENAI_API_KEY).')], 503);
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout(90)
            ->post((string) config('ai_chat.openai_url'), [
                'model' => (string) config('ai_chat.openai_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->llmPrompt()],
                    ['role' => 'user', 'content' => $message],
                ],
            ]);

        if (! $response->successful()) {
            $upstreamMessage = data_get($response->json(), 'error.message');
            return response()->json([
                'error' => $upstreamMessage ?: __('The AI service returned an error.'),
            ], 502);
        }

        $text = data_get($response->json(), 'choices.0.message.content');
        return response()->json(['reply' => (string) ($text ?? '')]);
    }

    protected function messageWithGemini(string $message)
    {
        $key = (string) config('ai_chat.gemini_key');
        if ($key === '') {
            return response()->json(['error' => __('AI assistant is not configured (missing GEMINI_API_KEY).')], 503);
        }

        // Some Gemini REST versions reject `systemInstruction`. Embed instructions in the user turn for broad compatibility.
        $combined = $this->llmPrompt()."\n\n---\n\n".$message;
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $combined],
                    ],
                ],
            ],
        ];

        $response = null;

        foreach ($this->geminiModelCandidates() as $model) {
            // Prefer stable v1 first; v1beta model availability differs by project and often shows "model not found" for 1.5.
            foreach (['v1', 'v1beta'] as $version) {
                $response = $this->postGeminiGenerateContent($key, $model, $version, $payload);
                if ($response->successful()) {
                    $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

                    return response()->json(['reply' => (string) ($text ?? '')]);
                }
                if (! $this->shouldContinueGeminiAttempts($response)) {
                    $upstreamMessage = data_get($response->json(), 'error.message');

                    return response()->json([
                        'error' => $upstreamMessage ?: __('The AI service returned an error.'),
                    ], 502);
                }
            }
        }

        $upstreamMessage = data_get($response?->json(), 'error.message');

        return response()->json([
            'error' => $upstreamMessage ?: __('The AI service returned an error.'),
        ], 502);
    }

    /**
     * @return list<string>
     */
    protected function geminiModelCandidates(): array
    {
        $default = 'gemini-2.5-flash';
        $primary = preg_replace('/^models\//', '', trim((string) config('ai_chat.gemini_model', $default))) ?: $default;
        $fallbacks = config('ai_chat.gemini_model_fallbacks', []);
        if (! is_array($fallbacks)) {
            $fallbacks = [];
        }

        $models = array_merge([$primary], $fallbacks);
        $normalized = [];
        foreach ($models as $m) {
            $m = preg_replace('/^models\//', '', trim((string) $m));
            if ($m !== '') {
                $normalized[] = $m;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function shouldContinueGeminiAttempts(\Illuminate\Http\Client\Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        $status = $response->status();
        if (in_array($status, [401, 403], true)) {
            return false;
        }

        $msg = Str::lower((string) data_get($response->json(), 'error.message', ''));

        if (Str::contains($msg, ['api key', 'permission denied', 'blocked', 'billing'])) {
            return false;
        }

        if (Str::contains($msg, ['quota', 'rate limit', 'resource exhausted'])) {
            return false;
        }

        return Str::contains($msg, ['not found', 'not supported', 'unsupported'])
            || Str::contains($msg, ['404']);
    }

    protected function postGeminiGenerateContent(string $apiKey, string $model, string $apiVersion, array $payload)
    {
        $urlTemplate = (string) config('ai_chat.gemini_url_template');
        $baseUrl = str_replace(
            ['{api_version}', '{model}'],
            [rawurlencode($apiVersion), rawurlencode($model)],
            $urlTemplate
        );
        $url = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'key='.rawurlencode($apiKey);

        return Http::acceptJson()
            ->timeout(90)
            ->post($url, $payload);
    }

}
