<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\ChatMessage;
use App\Services\OpenAIChatService;
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
    public function handle(Request $request, OpenAIChatService $openai)
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

        // Build system prompt and messages for OpenAI
        $system = "You are a helpful AI assistant. RULES:\n- Do not hallucinate data.\n- Use only the provided context.\n- Be concise and conversational.";

        $messages = [ ['role' => 'system', 'content' => $system] ];

        foreach ($history as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        // Call OpenAI
        $resp = $openai->chat($messages, ['temperature' => 0.2, 'max_tokens' => 600]);

        if (isset($resp['error'])) {
            return response()->json(['error' => $resp['error']], 502);
        }

        $reply = trim((string) data_get($resp, 'reply', ''));

        // Save assistant response
        $assistantMsg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
            'meta' => [
                'tokens' => (int) data_get($resp, 'tokens', 0),
                'model' => data_get($resp, 'model', ''),
            ],
        ]);

        // Update conversation timestamp
        $conversation->touch();

        // Return structured response
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
            'meta' => [
                'tokens' => (int) data_get($resp, 'tokens', 0),
                'model' => data_get($resp, 'model', ''),
            ],
        ]);
    }
}