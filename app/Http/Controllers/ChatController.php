<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\ChatMessage;
use App\Services\OpenAIChatService;
use App\Services\RagService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // List conversations for the authenticated user
    public function index(Request $request)
    {
        $user = $request->user();
        $convos = Conversation::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($c) {
                $last = $c->messages()->latest()->first();
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'updated_at' => $c->updated_at,
                    'last_message' => $last ? ['role' => $last->role, 'content' => Str::limit($last->content, 200), 'created_at' => $last->created_at] : null,
                ];
            });

        return response()->json(['conversations' => $convos]);
    }

    // Create a new conversation
    public function store(Request $request)
    {
        $user = $request->user();
        $request->validate(['title' => 'nullable|string|max:255']);
        $c = Conversation::create(['user_id' => $user->id, 'title' => $request->input('title')]);
        return response()->json(['conversation_id' => $c->id, 'title' => $c->title]);
    }

    // Get messages for a conversation (paginated)
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $conversation = Conversation::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $perPage = (int) $request->query('per_page', 50);
        $page = (int) $request->query('page', 1);

        $query = ChatMessage::where('conversation_id', $conversation->id)->orderBy('created_at', 'asc');
        $messages = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->map(function ($m) {
            return ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'meta' => $m->meta, 'created_at' => $m->created_at];
        });

        return response()->json(['conversation_id' => $conversation->id, 'title' => $conversation->title, 'messages' => $messages]);
    }

    // Main chat endpoint: save user message, call OpenAI with history, save assistant reply
    public function handle(Request $request, OpenAIChatService $openai, RagService $rag)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
            'conversation_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $message = trim($request->input('message'));
        $convId = $request->input('conversation_id');

        // Validate or create conversation
        if ($convId) {
            $conversation = Conversation::where('id', $convId)->where('user_id', $user->id)->first();
            if (! $conversation) {
                return response()->json(['error' => 'conversation_not_found'], 404);
            }
        } else {
            $conversation = Conversation::create(['user_id' => $user->id, 'title' => Str::limit($message, 100)]);
        }

        // Persist user message
        $userMsg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        // Load last N messages for context (ascending order)
        $history = ChatMessage::where('conversation_id', $conversation->id)
            ->latest()
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        // KB-first RAG reasoning via RagService
        try {
            $historyArr = $history->map(function ($m) {
                return ['role' => $m->role, 'content' => $m->content, 'meta' => $m->meta];
            })->toArray();

            $ragResp = $rag->answer($message, $historyArr, ['top_k' => 5]);

            // If KB produced any results, ALWAYS use them to produce the answer.
            $kbResults = $ragResp['kb_results'] ?? [];
            if (! empty($kbResults)) {
                // Prefer the reply returned by the RAG pipeline, but ensure we never fall back
                // to a generic "I don't know" when kb_results exist. If the RAG reply is
                // empty or unhelpful, synthesize a KB-only answer using the OpenAI service.
                $kbReply = trim((string) ($ragResp['reply'] ?? ''));

                // If the reply is empty or clearly a refusal, synthesize from the KB chunks
                if ($kbReply === '' || stripos($kbReply, "i don't know") !== false || stripos($kbReply, 'i do not know') !== false) {
                    // Build compact context from top chunks (up to 6)
                    $top = array_slice($kbResults, 0, 6);
                    $ctxParts = [];
                    foreach ($top as $i => $c) {
                        $idx = $i + 1;
                        $src = data_get($c, 'meta.source', data_get($c, 'meta.file_id', 'unknown'));
                        $text = trim(str_replace("\n\n", " \n ", mb_substr($c['text'] ?? '', 0, 4000)));
                        $ctxParts[] = "[Context #{$idx}] Source: {$src}\n{$text}";
                    }
                    $context = implode("\n\n---\n\n", $ctxParts);

                    $system = "You are a helpful assistant. Use ONLY the facts provided in the CONTEXT sections below to answer the user's question. Do NOT make assumptions or add external information. If the answer cannot be derived from the context, respond exactly with 'I don't know'. Provide a concise answer that uses only the context.";

                    $userPrompt = "CONTEXT:\n{$context}\n\nQuestion: {$message}\n\nAnswer using only the context. Provide a concise answer without extra commentary.";

                    $resp2 = $openai->chat([
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userPrompt]
                    ], ['temperature' => 0.0, 'max_tokens' => 600]);

                    $kbReply = trim((string) data_get($resp2, 'reply', ''));

                    // If still empty or an "I don't know" after synthesis, produce a conservative
                    // extractive summary from the top chunks (deterministic fallback) so we never
                    // return a generic refusal when results exist.
                    if ($kbReply === '' || stripos($kbReply, "i don't know") !== false || stripos($kbReply, 'i do not know') !== false) {
                        $snips = [];
                        foreach (array_slice($kbResults, 0, 5) as $c) {
                            $src = data_get($c, 'meta.source', data_get($c, 'meta.file_id', 'unknown'));
                            $text = trim(mb_substr($c['text'] ?? '', 0, 300));
                            $snips[] = "{$src}: {$text}";
                        }
                        $kbReply = implode("\n\n", $snips);
                    }
                }

                // Collect up to 5 unique source names from the kb results
                $sources = [];
                foreach ($kbResults as $c) {
                    $src = data_get($c, 'meta.source', data_get($c, 'meta.file_id', 'unknown'));
                    if ($src && ! in_array($src, $sources, true)) {
                        $sources[] = (string) $src;
                    }
                    if (count($sources) >= 5) break;
                }

                // Append Source list below the assistant reply (user-facing)
                $kbReplyWithSources = $kbReply;
                if (! empty($sources)) {
                    $kbReplyWithSources .= "\n\nSource:\n";
                    foreach ($sources as $s) {
                        $kbReplyWithSources .= "- " . $s . "\n";
                    }
                }

                $assistantMsg = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $kbReplyWithSources,
                    'meta' => [
                        'kb' => true,
                        'kb_count' => count($kbResults),
                        'sources' => $sources,
                    ],
                ]);

                $conversation->touch();

                $messagesOut = ChatMessage::where('conversation_id', $conversation->id)
                    ->orderBy('created_at', 'asc')
                    ->limit(50)
                    ->get()
                    ->map(function ($m) {
                        return ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'meta' => $m->meta, 'created_at' => $m->created_at];
                    });

                return response()->json([
                    'conversation_id' => $conversation->id,
                    'reply' => $kbReplyWithSources,
                    'messages' => $messagesOut,
                    'kb' => true,
                    'kb_results' => $kbResults,
                    'sources' => $sources,
                ]);
            }

            // No KB answer — build a more natural (non-static) NO-MATCH response
            $closest = $ragResp['closest_matches'] ?? [];

            $intros = [
                "I couldn't find a matching result.",
                "I couldn't locate an exact match in the knowledge base.",
                "No exact match was found in the knowledge base.",
                "I didn't find a direct match in the knowledge base.",
            ];

            $explanations = [
                "This may be because the item isn't indexed yet, the index is incomplete, or the query was very specific.",
                "Possible reasons: the information hasn't been indexed, or the query didn't match available documents.",
                "It appears the requested information isn't present in the indexed documents or the search was too narrow.",
            ];

            $nextSteps = [
                "Try providing additional details, alternate terms, or upload related documents.",
                "You can broaden the query, provide different keywords, or upload relevant files.",
                "If you have a specific document or keyword, tell me and I can search for it.",
            ];

            try {
                $intro = $intros[random_int(0, count($intros) - 1)];
                $explanation = $explanations[random_int(0, count($explanations) - 1)];
                $next = $nextSteps[random_int(0, count($nextSteps) - 1)];
            } catch (\Throwable $e) {
                // fallback deterministic choice
                $intro = $intros[0];
                $explanation = $explanations[0];
                $next = $nextSteps[0];
            }

            $parts = [];
            $parts[] = $intro;
            $parts[] = "Explanation:\n" . $explanation;
            $parts[] = "Scope searched:\n- knowledge base";
            $parts[] = "Clarification:\nThis does NOT mean the item does not exist; it only means it was not present in the retrieved knowledge base.";

            if (! empty($closest)) {
                $cm = "Closest Matches:\n";
                foreach ($closest as $i => $c) {
                    $idx = $i + 1;
                    $src = $c['source'] ?? 'unknown';
                    $cm .= "- Match {$idx}: Source: {$src}\n";
                }
                $parts[] = $cm;
            } else {
                $parts[] = "Closest Matches: none found.";
            }

            $parts[] = "Next Steps:\n- " . $next;
            $reply = implode("\n\n", $parts);

            $assistantMsg = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply,
                'meta' => [
                    'kb' => false,
                    'closest_matches_count' => count($closest),
                ],
            ]);

            $conversation->touch();

            $messagesOut = ChatMessage::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'asc')
                ->limit(50)
                ->get()
                ->map(function ($m) {
                    return ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'meta' => $m->meta, 'created_at' => $m->created_at];
                });

            return response()->json([
                'conversation_id' => $conversation->id,
                'reply' => $reply,
                'messages' => $messagesOut,
                'kb' => false,
                'closest_matches' => $closest,
            ]);

        } catch (\Throwable $e) {
            // Retrieval error — return NO MATCH structured fallback
            $parts = [];
            $parts[] = "I couldn't find a matching result.";
            $parts[] = "Explanation:\nThe retrieval service encountered an error while searching the knowledge base. Please try again later or refine your query.";
            $parts[] = "Scope searched:\n- knowledge base";
            $parts[] = "Clarification:\nThis does NOT mean the item does not exist; it only means it could not be retrieved at this time.";
            $parts[] = "Next Steps:\n- Try again later\n- Provide more specific search terms\n- Upload or index relevant documents if available";
            $reply = implode("\n\n", $parts);

            $assistantMsg = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply,
                'meta' => [
                    'kb' => false,
                    'error' => substr($e->getMessage(), 0, 400)
                ],
            ]);

            $conversation->touch();

            $messagesOut = ChatMessage::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'asc')
                ->limit(50)
                ->get()
                ->map(function ($m) {
                    return ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'meta' => $m->meta, 'created_at' => $m->created_at];
                });

            return response()->json([
                'conversation_id' => $conversation->id,
                'reply' => $reply,
                'messages' => $messagesOut,
                'kb' => false,
            ]);
        }
    }
}