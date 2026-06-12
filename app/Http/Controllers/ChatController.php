<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenAIChatService;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Main entry: minimal OpenAI-only chat endpoint.
     */
    public function handle(Request $request, OpenAIChatService $openai)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
        ]);

        $message = trim($request->input('message'));

        $messages = [
            [
                'role' => 'system',
                'content' => "You are a helpful AI assistant. Be concise and accurate. If you don't know something, say so.",
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        $resp = $openai->chat($messages, [
            'temperature' => 0.2,
            'max_tokens' => 800,
        ]);

        return response()->json(['reply' => (string) data_get($resp, 'reply', '')]);
    }
}