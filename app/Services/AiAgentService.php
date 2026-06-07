<?php
/* GENERATED: snipe-it-ai RAG - Copilot */
namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Services\PythonApiService;
use App\Models\Asset;

class AiAgentService
{
    protected PythonApiService $python;

    public function __construct(PythonApiService $python)
    {
        $this->python = $python;
    }

    /** Main entry for handling a user message as an agent */
    public function handle(string $question, $user = null): array
    {
        $question = trim($question);
        $steps = [];

        // 1) Planner: ask LLM which tool to use and with which query
        $plannerResp = $this->plannerDecision($question);
        $decision = $plannerResp['decision'] ?? null;
        $plannerRaw = $plannerResp['raw'] ?? null;

        $steps[] = [
            'phase' => 'planner',
            'decision' => $decision,
            'raw' => $plannerRaw,
            'ts' => now()->toIsoString(),
        ];

        // If planner failed, fallback to llm_only
        if (!in_array($decision, ['mysql_asset_search','vector_search','llm_only'])) {
            $decision = 'llm_only';
        }

        // simulate thinking delay
        usleep(random_int(300, 1200) * 1000);

        // 2) Execute chosen tool
        $toolResult = null;
        $toolName = $decision;

        try {
            if ($decision === 'mysql_asset_search') {
                $query = $plannerResp['query'] ?? $question;
                $rows = $this->fetchFromMysql($query);
                $count = is_countable($rows) ? count($rows) : 0;
                $summary = "found {$count} rows";
                $toolResult = ['tool' => 'mysql_asset_search', 'query' => $query, 'summary' => $summary, 'rows' => $rows];
            } elseif ($decision === 'vector_search') {
                $query = $plannerResp['query'] ?? $question;
                $vec = $this->fetchFromVector($query);
                $count = is_countable($vec) ? count($vec) : 0;
                $summary = "found {$count} docs";
                $toolResult = ['tool' => 'vector_search', 'query' => $query, 'summary' => $summary, 'results' => $vec];
            } else { // llm_only
                $toolResult = ['tool' => 'llm_only', 'query' => $plannerResp['query'] ?? $question, 'summary' => 'no tools executed'];
            }
        } catch (\Exception $e) {
            $toolResult = ['tool' => $toolName, 'error' => $e->getMessage()];
        }

        $steps[] = [
            'phase' => 'execution',
            'tool' => $toolResult['tool'] ?? $toolName,
            'summary' => $toolResult['summary'] ?? null,
            'ts' => now()->toIsoString(),
        ];

        // 3) Final synthesis: provide original query, planner decision and tool output to LLM
        $finalAnswer = $this->finalSynthesis($question, $plannerResp, $toolResult, $steps);

        $steps[] = [
            'phase' => 'synthesis',
            'ts' => now()->toIsoString(),
        ];

        $sourceMap = [
            'mysql_asset_search' => 'mysql',
            'vector_search' => 'vector',
            'llm_only' => 'llm',
        ];

        $source = $sourceMap[$decision] ?? 'llm';

        return [
            'answer' => $finalAnswer ?? '',
            'source' => $source,
            'steps' => $steps,
        ];
    }

    /** Classify intent: MYSQL | VECTOR | LLM_ONLY | UNKNOWN
     * Defaults to VECTOR when ambiguous per policy
     */
    protected function classifySource(string $q): string
    {
        $ql = mb_strtolower($q);

        $assetKeywords = ['asset','assets','serial','tag','barcode','model','assigned','owner','assigned to','who owns','where is','available','count','how many','inventory','location'];
        foreach ($assetKeywords as $kw) {
            if (mb_stripos($ql, $kw) !== false) return 'MYSQL';
        }

        $vectorKeywords = ['policy','manual','guide','troubleshoot','troubleshooting','procedure','how do i','how to','uploaded','document','readme','instruction','installation','steps','step'];
        foreach ($vectorKeywords as $kw) {
            if (mb_stripos($ql, $kw) !== false) return 'VECTOR';
        }

        // ambiguous -> prefer VECTOR per constraints
        return 'VECTOR';
    }

    protected function fetchFromMysql(string $q)
    {
        // conservative search: try name, serial, tag, model
        $assets = Asset::where('name', 'LIKE', "%{$q}%")
            ->orWhere('serial', 'LIKE', "%{$q}%")
            ->orWhere('tag', 'LIKE', "%{$q}%")
            ->orWhere('model', 'LIKE', "%{$q}%")
            ->limit(50)
            ->get(['id','name','serial','tag','model','assigned_to']);

        return $assets;
    }

    protected function fetchFromVector(string $q)
    {
        try {
            $resp = $this->python->query($q, 5);
            return $resp['results'] ?? [];
        } catch (\Exception $e) {
            return ['_error' => $e->getMessage()];
        }
    }

    protected function callOpenAI(array $messages, $temperature = 0.2, $max_tokens = 800)
    {
        $apiKey = env('OPENAI_API_KEY');
        $model = env('OPENAI_CHAT_MODEL', 'gpt-4o-mini');

        $resp = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $max_tokens,
            ]);

        if (! $resp->successful()) {
            return null;
        }

        return data_get($resp->json(), 'choices.0.message.content');
    }

    protected function respondFromMysql(string $q, $assets): array
    {
        if ($assets->isEmpty()) {
            $reply = "I couldn't find matching assets in the inventory. You can search the Snipe-IT UI or run a report for more precise results.";
            return ['source' => 'mysql', 'reply' => $reply, 'results' => []];
        }

        // format concise bullet list
        $lines = [];
        foreach ($assets as $a) {
            $lines[] = "- {$a->name} (Tag: {$a->tag}, Serial: {$a->serial}, Model: {$a->model})";
        }
        $reply = "Here are the matching assets I found:\n" . implode("\n", $lines);

        return ['source' => 'mysql', 'reply' => $reply, 'results' => $assets];
    }

    protected function respondFromVector(string $q, array $results): array
    {
        if (isset($results['_error'])) {
            return ['source' => 'vector', 'reply' => 'There was an error querying the knowledge base: ' . $results['_error']];
        }

        if (empty($results)) {
            return ['source' => 'vector', 'reply' => 'I could not find relevant information in the knowledge base.'];
        }

        $contexts = array_map(function ($r) { return $r['document'] ?? ''; }, $results);

        $system = 'You are a helpful assistant. Answer using ONLY the provided context. If the context does not contain the answer, say: "I could not find relevant information in the knowledge base." Be concise and friendly.';
        $user = "Context:\n" . implode("\n---\n", $contexts) . "\n\nQuestion: {$q}";

        $message = $this->callOpenAI([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 0.0, 800);

        if ($message === null) {
            return ['source' => 'vector', 'reply' => 'Upstream LLM error while synthesizing an answer.'];
        }

        return ['source' => 'vector', 'reply' => trim($message), 'contexts' => $results];
    }

    protected function respondFromLlm(string $q): array
    {
        // Ask clarifying question if ambiguous
        $system = 'You are a friendly assistant. When the user question is ambiguous about whether they mean inventory data or documents, ask one short clarifying question. Otherwise answer concisely.';

        $message = $this->callOpenAI([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $q],
        ], 0.2, 400);

        if ($message === null) {
            return ['source' => 'llm', 'reply' => 'Upstream LLM error.'];
        }

        return ['source' => 'llm', 'reply' => trim($message)];
    }

    /**
     * Ask LLM to return a JSON decision about which tool to use.
     * Expected JSON: {"tool":"mysql_asset_search|vector_search|llm_only","query":"...","reason":"...","confidence":0.0}
     */
    protected function plannerDecision(string $question): array
    {
        $system = "You are an agent planner.\nRespond ONLY with a single JSON object and nothing else.\nSchema: {\n  \"tool\": \"mysql_asset_search|vector_search|llm_only\",\n  \"query\": \"the exact query to send to the tool\",\n  \"reason\": \"one-sentence rationale\",\n  \"confidence\": 0.0\n}\nPick one tool only. Do not include extra fields.";

        $user = "User question: {$question}\nDecide which single tool to use. If question is about inventory, choose mysql_asset_search. If it's about uploaded docs, guides, policies, choose vector_search. If neither or you must answer without tools, choose llm_only.";

        $resp = $this->callOpenAI([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 0.0, 300);

        $raw = $resp ?? '';

        // Attempt to extract JSON object from response
        $json = null;
        if ($raw) {
            $first = strpos($raw, '{');
            $last = strrpos($raw, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $substr = substr($raw, $first, $last - $first + 1);
                $parsed = json_decode($substr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    $json = $parsed;
                }
            }
        }

        if (!is_array($json)) {
            // fallback heuristic
            $lower = mb_strtolower($question);
            $tool = 'llm_only';
            if (preg_match('/asset|serial|tag|barcode|model|assigned|owner|where is|which asset/', $lower)) {
                $tool = 'mysql_asset_search';
            } elseif (preg_match('/manual|policy|guide|troubleshoot|how to|installation|readme|document/', $lower)) {
                $tool = 'vector_search';
            }
            $json = ['tool' => $tool, 'query' => $question, 'reason' => 'fallback heuristic', 'confidence' => 0.25];
        }

        // Normalize tool key name to decision format
        $decision = $json['tool'] ?? ($json['tool'] = 'llm_only');

        return ['decision' => $decision, 'query' => $json['query'] ?? $question, 'reason' => $json['reason'] ?? '', 'confidence' => $json['confidence'] ?? 0.0, 'raw' => $raw];
    }

    /** Compose final answer using planner + tool outputs. */
    protected function finalSynthesis(string $userQuestion, array $plannerResp, array $toolResult, array $steps)
    {
        $toolText = '';
        if (isset($toolResult['error'])) {
            $toolText = "Tool execution error: " . $toolResult['error'];
        } else {
            if (($toolResult['tool'] ?? '') === 'mysql_asset_search') {
                $rows = $toolResult['rows'] ?? [];
                if (is_iterable($rows) && count($rows) > 0) {
                    $lines = [];
                    foreach ($rows as $r) {
                        $lines[] = "- {$r->name} (Tag: {$r->tag}, Serial: {$r->serial}, Model: {$r->model})";
                    }
                    $toolText = "Inventory matches:\n" . implode("\n", array_slice($lines, 0, 10));
                } else {
                    $toolText = "Inventory search returned no matches.";
                }
            } elseif (($toolResult['tool'] ?? '') === 'vector_search') {
                $results = $toolResult['results'] ?? [];
                if (!empty($results)) {
                    $contexts = array_map(function ($r) { return ($r['document'] ?? '') . "\n(Source: " . ($r['source'] ?? 'kb') . ")"; }, $results);
                    $toolText = "Retrieved contexts:\n" . implode("\n---\n", array_slice($contexts, 0, 5));
                } else {
                    $toolText = "Vector search returned no documents.";
                }
            } else {
                $toolText = "No external tools executed.";
            }
        }

        $system = "You are a concise assistant. Use the user question, planner decision and tool outputs below to produce a single final answer. Do NOT hallucinate facts not present in the tools. If inventory rows exist, present the top matches and exact tag/serial/model values. If document contexts exist, answer only from them and say when you cannot find an answer. Keep answer concise (3-6 sentences).";

        $user = "User question: {$userQuestion}\n\nPlanner decision: " . json_encode($plannerResp) . "\n\nTool outputs:\n" . $toolText . "\n\nProvide a final natural-language answer and nothing else.";

        $resp = $this->callOpenAI([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 0.2, 700);

        return $resp ? trim($resp) : '';
    }
}
