<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Asset;
use App\Services\OpenAIChatService;
use App\Services\AssetContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function handle(Request $request, OpenAIChatService $openai)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        $user = $request->user();
        $message = trim((string) $request->input('message'));
        $lower = strtolower($message);

        /*
        |--------------------------------------------------------------------------
        | 1. INTENT ROUTING (DB FIRST - NO AI GUESSING)
        |--------------------------------------------------------------------------
        */

        // -------------------------
        // COUNT ASSETS
        // -------------------------
        if (preg_match('/(how many|count).*asset/', $lower)) {
            $count = Asset::count();

            return response()->json([
                'reply' => "There are {$count} assets in the system."
            ]);
        }

        // -------------------------
        // AVAILABLE ASSETS
        // -------------------------
        if (
            str_contains($lower, 'available asset') ||
            str_contains($lower, 'what assets are available')
        ) {
            $assets = Asset::whereNull('assigned_to')
                ->with(['model', 'assetstatus'])
                ->limit(10)
                ->get();

            $formatted = $assets->map(function ($a) {
                return "- {$a->asset_tag} | {$a->name} | " .
                    ($a->assetstatus->name ?? 'Unknown status');
            })->implode("\n");

            return response()->json([
                'reply' => "Available assets:\n" . $formatted
            ]);
        }

        // -------------------------
        // ASSET OWNER LOOKUP (FIX YOUR ABC123 ISSUE)
        // -------------------------
        if (preg_match('/who.*asset|asset.*[a-z0-9]|who has/i', $lower)) {

            preg_match('/[A-Z0-9\-]{3,}/', $message, $matches);
            $tag = $matches[0] ?? null;

            if ($tag) {
                $asset = Asset::where('asset_tag', $tag)
                    ->with(['assignedTo', 'assetstatus'])
                    ->first();

                if ($asset) {
                    $owner = $asset->assignedTo
                        ? ($asset->assignedTo->first_name . ' ' . $asset->assignedTo->last_name)
                        : 'Unassigned';

                    return response()->json([
                        'reply' => "Asset {$asset->asset_tag} ({$asset->name}) is assigned to: {$owner}"
                    ]);
                }

                return response()->json([
                    'reply' => "Asset {$tag} not found in the system."
                ]);
            }

            return response()->json([
                'reply' => "Please provide a valid asset tag (e.g. ABC123)."
            ]);
        }

        // -------------------------
        // SEARCH ASSETS
        // -------------------------
        if (preg_match('/find|search|dell|laptop|asset/', $lower)) {

            $keywords = collect(explode(' ', $lower))
                ->filter(fn ($w) => strlen($w) > 3)
                ->implode('%');

            $assets = Asset::where('name', 'LIKE', "%{$keywords}%")
                ->orWhere('asset_tag', 'LIKE', "%{$keywords}%")
                ->with(['model', 'assetstatus'])
                ->limit(10)
                ->get();

            if ($assets->isNotEmpty()) {
                $formatted = $assets->map(function ($a) {
                    return "- {$a->asset_tag} | {$a->name} | " .
                        ($a->assetstatus->name ?? 'Unknown');
                })->implode("\n");

                return response()->json([
                    'reply' => "Search results:\n" . $formatted
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. CHAT HISTORY
        |--------------------------------------------------------------------------
        */

        $conversation = Conversation::firstOrCreate(
            [
                'id' => $request->input('conversation_id')
            ],
            [
                'user_id' => $user->id,
                'title' => Str::limit($message, 120),
            ]
        );

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        $history = ChatMessage::where('conversation_id', $conversation->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 3. SYSTEM PROMPT (STRICT ANTI-HALLUCINATION)
        |--------------------------------------------------------------------------
        */

        $systemPrompt = "
    You are a friendly, helpful IT asset assistant for Snipe-IT. Be concise, polite, and professional.

    GUIDELINES:
    - NEVER guess or fabricate database values.
    - If the user asks for data from the system and you don't have it, reply: 'I don't have access to that information.'
    - If you need clarification, ask one focused question.
    - When appropriate, suggest next steps (e.g., tell the user how to locate an asset tag).
    ";

        $messagesForOpenAI = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        /*
        |--------------------------------------------------------------------------
        | 4. OPTIONAL CONTEXT
        |--------------------------------------------------------------------------
        */

        try {
            if (app()->bound(AssetContextService::class)) {
                $assetSvc = app(AssetContextService::class);
                $ctx = $assetSvc->buildContext($message, $user, 10);

                if (!empty($ctx['lines'])) {
                    $messagesForOpenAI[] = [
                        'role' => 'system',
                        'content' => "ASSET CONTEXT:\n" . implode("\n", $ctx['lines'])
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Asset context failed: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | 5. HISTORY
        |--------------------------------------------------------------------------
        */

        foreach ($history as $h) {
            $messagesForOpenAI[] = [
                'role' => $h->role === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $h->content,
            ];
        }

        $messagesForOpenAI[] = [
            'role' => 'user',
            'content' => $message,
        ];

        /*
        |--------------------------------------------------------------------------
        | 6. PRE-CALL DB HEURISTIC
        |--------------------------------------------------------------------------
        | Quick heuristic to determine whether this question likely requires a
        | database lookup. If so, attempt the lookup and return a concrete
        | DB-driven answer before invoking the AI. This reduces hallucination
        | and improves the user experience for factual queries.
        */

        $isLikelyDbRequest = false;
        // Detect asset tag like ABC123 or ABC-123
        if (preg_match('/[A-Z0-9\-]{3,}/', $message)) {
            $isLikelyDbRequest = true;
        }

        // Keywords that often require DB answers
        if (preg_match('/\b(how many|count|who has|who is|where is|location|find|search|available asset|what assets)\b/i', $message)) {
            $isLikelyDbRequest = true;
        }

        if ($isLikelyDbRequest) {
            // Try an asset tag lookup first
            if (preg_match('/[A-Z0-9\-]{3,}/', $message, $matches)) {
                $tag = $matches[0] ?? null;
                if ($tag) {
                    $asset = Asset::where('asset_tag', $tag)
                        ->with(['assignedTo', 'assetstatus', 'model'])
                        ->first();

                    if ($asset) {
                        $location = $asset->rtd_location ?? null;
                        $status = $asset->assetstatus->name ?? 'Unknown';
                        $owner = $asset->assignedTo
                            ? $asset->assignedTo->first_name . ' ' . $asset->assignedTo->last_name
                            : 'Unassigned';

                        return response()->json([
                            'reply' =>
                                "Asset {$asset->asset_tag} ({$asset->name})\n" .
                                "Status: {$status}\n" .
                                "Assigned to: {$owner}\n" .
                                "Location: " . ($location ?? 'Not set')
                        ]);
                    }

                    return response()->json([
                        'reply' => "Asset {$tag} not found in system."
                    ]);
                }
            }

            // Count assets shortcut
            if (preg_match('/\b(how many|count).*asset/i', $lower)) {
                $count = Asset::count();

                return response()->json([
                    'reply' => "There are {$count} assets in the system."
                ]);
            }

            // Available assets shortcut
            if (str_contains($lower, 'available asset') || str_contains($lower, 'what assets are available')) {
                $assets = Asset::whereNull('assigned_to')
                    ->with(['model', 'assetstatus'])
                    ->limit(10)
                    ->get();

                $formatted = $assets->map(function ($a) {
                    return "- {$a->asset_tag} | {$a->name} | " .
                        ($a->assetstatus->name ?? 'Unknown status');
                })->implode("\n");

                return response()->json([
                    'reply' => "Available assets:\n" . $formatted
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 7. OPENAI CALL
        |--------------------------------------------------------------------------
        */


// -------------------------
// ASSET LOCATION / DETAILS LOOKUP
// -------------------------
if (preg_match('/where is|location|status|asset/i', $lower)) {

    preg_match('/[A-Z0-9\-]{3,}/', $message, $matches);
    $tag = $matches[0] ?? null;

    if ($tag) {
        $asset = Asset::where('asset_tag', $tag)
            ->with(['assignedTo', 'assetstatus', 'model'])
            ->first();

        if ($asset) {

            $location = $asset->rtd_location ?? null; // or your location field
            $status = $asset->assetstatus->name ?? 'Unknown';
            $owner = $asset->assignedTo
                ? $asset->assignedTo->first_name . ' ' . $asset->assignedTo->last_name
                : 'Unassigned';

            return response()->json([
                'reply' =>
                    "Asset {$asset->asset_tag} ({$asset->name})\n" .
                    "Status: {$status}\n" .
                    "Assigned to: {$owner}\n" .
                    "Location: " . ($location ?? 'Not set')
            ]);
        }

        return response()->json([
            'reply' => "Asset {$tag} not found in system."
        ]);
    }
}



        /*
        |--------------------------------------------------------------------------
        | 7. OPENAI CALL
        |--------------------------------------------------------------------------
        */

        $start = microtime(true);
        $resp = $openai->chat($messagesForOpenAI, [
            'max_tokens' => 800,
            'temperature' => 0.2
        ]);
        $duration = round(microtime(true) - $start, 2);

        if (isset($resp['error'])) {
            return response()->json([
                'error' => $resp['error']
            ], $resp['status'] ?? 502);
        }

        $reply = trim((string) data_get($resp, 'reply', ''));
        $reply = strip_tags($reply);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return response()->json([
            'reply' => $reply,
            'conversation_id' => $conversation->id,
            'thinking_time_seconds' => $duration
        ]);
    }
}