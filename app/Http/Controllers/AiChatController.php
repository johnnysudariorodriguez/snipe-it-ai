<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Asset;
use App\Models\AiChatMessage;
use App\Models\AiDocument;

class AiChatController extends Controller
{
    public const SYSTEM_PROMPT = "
You are an enterprise AI agent for an IT asset management system.

MANDATORY OUTPUT RULES (Follow exactly):

1) Intent classification (MANDATORY FIRST LINE):
- Determine the user's intent and output exactly one of: definition, procedure, policy, data/reference, troubleshooting, overview, multi-domain.
- Emit a single line starting with 'INTENT: ' followed by the chosen intent (lowercase).

2) Format selection (MANDATORY SECOND LINE):
- Map the chosen intent to exactly one format A-G and emit a single line starting with 'FORMAT: ' followed by the format label and name (for example: 'FORMAT: B - PROCEDURE').

3) Content: After the two header lines, output the response using only the selected format's exact structure below. Do NOT mix formats, do NOT include extra preamble, and do NOT explain the classification or format choice.

FORMAT TEMPLATES (use exactly):
A. DEFINITION FORMAT
- Short direct definition (1 paragraph)
- Breakdown in bullet points
- Optional example

B. PROCEDURE FORMAT (SOP STYLE)
1. Overview
2. Requirements (if applicable)
3. Step-by-step instructions (numbered)
4. Validation / completion criteria
5. Exceptions or edge cases

C. POLICY FORMAT
- Purpose
- Scope
- Rules
- Responsibilities
- Enforcement / consequences
- Compliance notes

D. DATA / REFERENCE FORMAT
- Short explanation
- Table format (primary output)
- Key insights below table

E. TROUBLESHOOTING FORMAT
- Possible causes
- Diagnostic steps
- Solutions
- Prevention tips

F. OVERVIEW FORMAT
- High-level explanation paragraph
- Sectioned breakdown
- Key components list

G. HYBRID FORMAT
- Direct answer first
- Segmented sections per domain
- SOP or policy blocks if needed
- Summary at end

ADDITIONAL CONSTRAINTS:
- Begin response with the INTENT and FORMAT lines only, then the formatted content.
- Be concise, structured, and enterprise-friendly.
- If ambiguous, choose the closest intent and proceed; you may include a single clarification question in the 'Exceptions' or 'Diagnostic steps' section when needed.
- No filler, no apologies, no meta commentary.

You operate using tools:
- MYSQL_QUERY
- GENERAL_REASONING

RULES:
- Never guess database values
- Always use tools for factual questions
- Be concise and structured
- If unsure, ask clarification
";

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * =========================
     * MAIN AGENT ENTRYPOINT
     * =========================
     */
    public function message(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
        ]);

        $user = $request->user();
        $message = trim($request->input('message'));

        /**
         * STEP 1: Load memory
         */
        $memory = $this->loadMemory($user->id);

        /**
         * STEP 2: Agent decides action (tool selection)
         */
        $plan = $this->decideTool($message, $memory);

        /**
         * STEP 3: Execute tool (if any)
         * The router returns a tool name and optional params. Call the authorized tool wrapper.
         */
        $toolResult = $this->executeTool($plan['tool'] ?? null, $plan['params'] ?? null);

        /**
         * STEP 4: Final reasoning pass
         */
        $finalPrompt = $this->buildFinalPrompt($message, $memory, $plan, $toolResult);

        $reply = $this->callLLM($finalPrompt);

        /**
         * STEP 5: Save memory
         */
        $this->storeMemory($user->id, $message, $reply);

        return response()->json([
            'reply' => $reply,
            'plan' => $plan,
            'tool_result' => $toolResult
        ]);
    }

    /**
     * Accept a finalized file upload from the UI, send it to the Python vector service,
     * and return the number of inserted chunks.
     */
    public function upload(Request $request)
    {
        if (! $request->user() || (! $request->user()->isAdmin() && ! $request->user()->isSuperUser())) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if (! $request->hasFile('file')) {
            return response()->json(['status' => 'error', 'message' => 'No file provided'], 400);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $allowed = ['pdf', 'docx', 'doc', 'txt'];
        if (! in_array($ext, $allowed)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid file type'], 400);
        }

        $originalName = $file->getClientOriginalName();
        $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $ext;
        $path = $file->storeAs('ai_docs', $filename);

        $doc = AiDocument::create([
            'original_name' => $originalName,
            'filename' => $filename,
            'path' => $path,
            'size' => $file->getSize(),
            'status' => 'Queued',
            'created_by' => $request->user()->id ?? null,
        ]);

        // Dispatch background job to process the document and insert into ChromaDB
        try {
            \App\Jobs\ProcessAiDocument::dispatch($doc->id);
        } catch (\Throwable $e) {
            $doc->status = 'Error';
            $doc->error_message = 'Failed to queue processing: ' . $e->getMessage();
            $doc->save();
            return response()->json(['status' => 'error', 'message' => 'Failed to queue processing'], 500);
        }

        return response()->json(['status' => 'queued', 'doc_id' => $doc->id]);
    }

    /**
     * Return document status and metadata for frontend polling.
     */
    public function documentStatus($id)
    {
        $doc = AiDocument::find($id);
        if (! $doc) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'doc' => [
                'id' => $doc->id,
                'original_name' => $doc->original_name,
                'status' => $doc->status,
                'inserted_count' => $doc->inserted_count,
                'error_message' => $doc->error_message,
                'processed_at' => $doc->processed_at,
            ]
        ]);
    }

    /**
     * Delete a document record and remove the stored file.
     */
    public function destroy($id)
    {
        $doc = AiDocument::find($id);
        if (! $doc) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        // attempt to delete file using configured filesystem
        try {
            $disk = config('filesystems.default', env('PRIVATE_FILESYSTEM_DISK', 'local'));
            if ($doc->path && \Illuminate\Support\Facades\Storage::disk($disk)->exists($doc->path)) {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($doc->path);
            }
        } catch (\Throwable $e) {
            // non-fatal: continue to delete DB record but report partial failure
            $doc->error_message = 'Failed to delete file: ' . $e->getMessage();
            $doc->save();
            return response()->json(['status' => 'error', 'message' => 'Failed to delete file'], 500);
        }

        $doc->delete();

        return response()->json(['status' => 'ok', 'message' => 'Deleted']);
    }

    /**
     * Simple chat endpoint that queries the Python service `/query` endpoint and
     * returns a brief answer derived from the top result.
     */
    public function chat(Request $request)
    {
        $q = trim((string) $request->input('question', ''));
        if ($q === '') {
            return response()->json(['status' => 'error', 'message' => 'Empty question'], 400);
        }

        $pythonUrl = rtrim(env('PYTHON_API_URL', 'http://127.0.0.1:8001'), '/') . '/query';

        try {
            $res = Http::timeout(30)
                ->post($pythonUrl, ['query' => $q, 'top_k' => 5]);

            if (! $res->successful()) {
                return response()->json(['status' => 'error', 'message' => 'Python service error', 'detail' => $res->body()], 500);
            }

            $json = $res->json();
            $results = data_get($json, 'results', []);
            $answer = '';
            $source = '';
            if (! empty($results) && isset($results[0]['text'])) {
                $answer = $results[0]['text'];
                $source = data_get($results[0], 'meta.source', '');
            }

            return response()->json(['status' => 'ok', 'answer' => $answer, 'source' => $source, 'results' => $results]);

        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * =========================
     * TOOL ROUTER (LLM BRAIN)
     * =========================
     */
    protected function decideTool(string $message, array $memory): array
    {
        $system = "
You are a tool router for an enterprise database AI agent.
You MUST choose exactly ONE tool from the following list or return GENERAL_REASONING if no tool applies:

- get_asset_count
- get_assets_by_status
- find_asset_by_tag
- get_users
- get_suppliers
- get_categories
- get_locations
- get_departments

Return JSON ONLY:

{
  \"tool\": \"<tool name> | GENERAL_REASONING\",
  \"params\": { /* optional parameters */ }
}

Map user language (for example, 'deployable' -> status='available').
";

        $res = Http::withToken(config('ai_chat.openai_key'))
            ->post(config('ai_chat.openai_url'), [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode([
                        'message' => $message,
                        'memory' => $memory
                    ])]
                ],
                'temperature' => 0
            ]);

        $resJson = $res->json();
        $content = data_get($resJson, 'choices.0.message.content', '{}');

        // Remove INTENT/FORMAT header lines and any [Context #n] citations
        if (is_string($content) && $content !== '') {
            $content = preg_replace('/\A\s*INTENT:.*\R\s*FORMAT:.*\R\s*/i', '', $content);
            $content = preg_replace('/\[Context\s*#\d+\]/i', '', $content);
            $content = trim((string) $content);
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded) || !isset($decoded['tool'])) {
            return [
                'tool' => 'GENERAL_REASONING',
                'params' => []
            ];
        }

        if (!isset($decoded['params']) || !is_array($decoded['params'])) {
            $decoded['params'] = [];
        }

        return $decoded;
    }

    /**
     * =========================
     * MYSQL TOOL EXECUTOR
     * =========================
     */
    protected function executeMysqlTool(?string $query)
    {
        if (!$query) {
            return null;
        }

        $q = strtolower($query);

        // SAFE OPERATIONS ONLY (no raw SQL execution)
        if (str_contains($q, 'count')) {
            return [
                'type' => 'count',
                'value' => Asset::count()
            ];
        }

        if (str_contains($q, 'available')) {
            return [
                'type' => 'available_assets',
                'items' => Asset::where('status', 'available')
                    ->limit(20)
                    ->get()
                    ->toArray()
            ];
        }

        if (str_contains($q, 'asset')) {
            return [
                'type' => 'asset_search',
                'items' => Asset::limit(10)->get()->toArray()
            ];
        }

        return [
            'type' => 'unknown',
            'items' => []
        ];
    }

    /**
     * =========================
     * FINAL REASONING STAGE
     * =========================
     */
    protected function buildFinalPrompt($message, $memory, $plan, $toolResult)
    {
        return "
You are a high-precision AI agent.

REQUIREMENTS:
- Always choose a tool for factual questions and use its output.
- Never guess or hallucinate database values.
- If tool output is empty or indicates an error, ask for clarification.
- Be concise and structured in your response.

USER QUESTION:
{$message}

TOOL PLAN:
" . json_encode($plan, JSON_PRETTY_PRINT) . "

TOOL RESULT:
" . json_encode($toolResult, JSON_PRETTY_PRINT) . "

MEMORY:
" . json_encode($memory, JSON_PRETTY_PRINT) . "

INSTRUCTIONS:
- If tool_result exists, answer using only that data.
- If tool_result is null or contains an error, ask the user for clarification or state that a tool is required.
";
    }

    /**
     * =========================
     * LLM CALL
     * =========================
     */
    protected function callLLM(string $prompt): string
    {
        $res = Http::withToken(config('ai_chat.openai_key'))
            ->post(config('ai_chat.openai_url'), [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2
            ]);

        $resJson = $res->json();
        $content = data_get($resJson, 'choices.0.message.content', 'No response');
        if (is_string($content) && $content !== '') {
            $content = preg_replace('/\A\s*INTENT:.*\R\s*FORMAT:.*\R\s*/i', '', $content);
            $content = preg_replace('/\[Context\s*#\d+\]/i', '', $content);
            $content = trim((string) $content);
        }

        return $content ?: 'No response';
    }

    /**
     * =========================
     * MEMORY SYSTEM
     * =========================
     */
    protected function loadMemory($userId)
    {
        return AiChatMessage::where('user_id', $userId)
            ->latest()
            ->limit(6)
            ->get(['question', 'answer'])
            ->toArray();
    }

    protected function storeMemory($userId, $q, $a)
    {
        AiChatMessage::create([
            'user_id' => $userId,
            'question' => $q,
            'answer' => $a
        ]);
    }

    /**
     * Execute an allowed tool by name, passing normalized params.
     * Tools are expected to be provided externally (functions or services).
     */
    protected function executeTool(?string $tool, ?array $params = null)
    {
        if (!$tool || $tool === 'GENERAL_REASONING') {
            return null;
        }

        $allowed = [
            'get_asset_count',
            'get_assets_by_status',
            'find_asset_by_tag',
            'get_users',
            'get_suppliers',
            'get_categories',
            'get_locations',
            'get_departments',
        ];

        if (!in_array($tool, $allowed)) {
            return [
                'error' => 'unsupported_tool',
                'tool' => $tool
            ];
        }

        $params = $params ?? [];
        if (isset($params['status'])) {
            $params['status'] = $this->normalizeStatus($params['status']);
        }

        return $this->callExternalTool($tool, $params);
    }

    /**
     * Call the external/tool function if available. Fall back to a structured error.
     * Expected function signatures (examples):
     * - get_asset_count()
     * - get_assets_by_status(string $status)
     * - find_asset_by_tag(string $tag)
     */
    protected function callExternalTool(string $tool, array $params = [])
    {
        if (function_exists($tool)) {
            try {
                switch ($tool) {
                    case 'get_asset_count':
                        return call_user_func($tool);
                    case 'get_assets_by_status':
                        return call_user_func($tool, $params['status'] ?? null);
                    case 'find_asset_by_tag':
                        return call_user_func($tool, $params['tag'] ?? null);
                    case 'get_users':
                    case 'get_suppliers':
                    case 'get_categories':
                    case 'get_locations':
                    case 'get_departments':
                        return call_user_func($tool);
                    default:
                        return ['error' => 'unhandled_tool', 'tool' => $tool];
                }
            } catch (\Throwable $e) {
                return ['error' => 'tool_exception', 'message' => $e->getMessage()];
            }
        }

        return ['error' => 'tool_not_available', 'tool' => $tool];
    }

    /**
     * Normalize human-friendly status labels into canonical status values.
     */
    protected function normalizeStatus($status)
    {
        $s = strtolower(trim((string) $status));
        $map = [
            'deployable' => 'available',
            'available' => 'available',
            'in use' => 'in_use',
            'deployed' => 'in_use',
        ];

        return $map[$s] ?? $s;
    }
}