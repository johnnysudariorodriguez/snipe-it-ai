<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Services\OpenAIChatService;

class RagService
{
    protected OpenAIChatService $openai;
    protected string $pythonBase;

    public function __construct(OpenAIChatService $openai)
    {
        $this->openai = $openai;
        $this->pythonBase = rtrim(config('app.python_api_url', env('PYTHON_API_URL', 'http://127.0.0.1:8001')), '/');
    }

    /**
     * Main entry: attempt to answer from KB using a multi-step RAG loop.
     * Returns array with keys:
     * - use_kb: bool
     * - reply: string (when use_kb true)
     * - kb_results: array of chunks used
     */
    public function answer(string $question, array $history = [], array $opts = []): array
    {
        $top_k = $opts['top_k'] ?? 5;

        // Step 1: quick heuristic to decide if external data makes sense
        if (! $this->needsKb($question, $history)) {
            return ['use_kb' => false];
        }

        // Step 2: initial retrieval with original query
        $initial = $this->queryPython($question, min(5, $top_k));

        // Step 3: relevance grading (self-critic)
        $graded = $this->gradeResults($question, $initial);
        if ($this->hasRelevant($graded)) {
            $replyPacket = $this->generateFromContext($question, $graded['relevant']);
            return array_merge(['use_kb' => true], $replyPacket, ['kb_results' => $graded['relevant']]);
        }

        // Step 4: query expansion -> rewrite into 3 variations
        $variants = $this->expandQueries($question, $history);

        // execute retrieval across variants and dedupe
        $agg = [];
        foreach ($variants as $v) {
            $res = $this->queryPython($v, min(5, $top_k));
            foreach ($res as $chunk) {
                $key = md5(($chunk['meta']['file_id'] ?? '') . '|' . ($chunk['meta']['chunk'] ?? '') . '|' . ($chunk['text'] ?? ''));
                if (! isset($agg[$key]) || ($chunk['distance'] ?? 999) < ($agg[$key]['distance'] ?? 999)) {
                    $agg[$key] = $chunk;
                }
            }
        }
        $aggResults = array_values($agg);

        // grade aggregated results
        $graded2 = $this->gradeResults($question, $aggResults);
        if ($this->hasRelevant($graded2)) {
            $replyPacket = $this->generateFromContext($question, $graded2['relevant']);
            return array_merge(['use_kb' => true], $replyPacket, ['kb_results' => $graded2['relevant']]);
        }

        // No KB answer found after self-correction
        // Build closest matches list (top N) with simple reasons derived from vector distance
        $candidates = $aggResults ?: $initial;
        $closest = [];

        if (! empty($candidates)) {
            // sort by distance when available (ascending)
            usort($candidates, function ($a, $b) {
                $da = isset($a['distance']) ? (float) $a['distance'] : INF;
                $db = isset($b['distance']) ? (float) $b['distance'] : INF;
                if ($da === $db) return 0;
                return ($da < $db) ? -1 : 1;
            });

            $count = 0;
            foreach ($candidates as $chunk) {
                if ($count >= 5) break;
                $source = data_get($chunk, 'meta.source', data_get($chunk, 'meta.file_id', 'unknown'));
                $distance = isset($chunk['distance']) ? (float) $chunk['distance'] : null;

                // Return only source and distance for closest matches (no excerpts or explanations)
                $closest[] = [
                    'source' => (string) $source,
                    'distance' => $distance,
                ];

                $count++;
            }
        }

        return [
            'use_kb' => false,
            'closest_matches' => $closest,
            'searched' => 'knowledge base',
        ];
    }

    protected function needsKb(string $question, array $history = []): bool
    {
        // Conservative heuristic: favor KB for explicit questions and technical/problem queries
        if (preg_match('/\?|\b(how|what|why|when|where|who|error|install|setup|configure|steps|manual|guide|problem|fix|troubleshoot|best practice)\b/i', $question)) {
            return true;
        }

        // If conversation contains messages from assistant with KB metadata, prefer KB
        foreach ($history as $m) {
            if (is_array($m) && isset($m['meta']) && (! empty($m['meta']['kb'] ?? false))) {
                return true;
            }
        }

        // Default: don't use KB for very short chit-chat
        if (mb_strlen(trim($question)) < 20) return false;

        return true;
    }

    protected function queryPython(string $q, int $top_k = 5): array
    {
        try {
            $url = $this->pythonBase . '/query';
            $res = Http::timeout(8)->post($url, ['query' => $q, 'top_k' => $top_k]);
            if ($res->successful()) {
                $json = $res->json();
                return data_get($json, 'results', []);
            }
        } catch (\Throwable $e) {
            // ignore and return empty
        }

        return [];
    }

    protected function expandQueries(string $question, array $history = []): array
    {
        $system = "You are a utility that rewrites user questions into short, focused search queries optimized for document retrieval.\nReturn a JSON array of up to three query strings only.";

        $context = "";
        if (! empty($history)) {
            $last = array_slice($history, -6);
            $parts = [];
            foreach ($last as $m) {
                $role = $m['role'] ?? 'user';
                $parts[] = strtoupper($role) . ": " . ($m['content'] ?? '');
            }
            $context = implode("\n", $parts);
        }

        $user = "Question: {$question}\n\nConversation context:\n{$context}\n\nProvide up to three short queries as a JSON array. Example: [\"how to install x\", \"x install error code\", \"x configuration options\"]";

        $resp = $this->openai->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user]
        ], ['temperature' => 0.0, 'max_tokens' => 300]);

        $reply = trim((string) data_get($resp, 'reply', ''));
        // try JSON decode first
        $decoded = json_decode($reply, true);
        if (is_array($decoded) && count($decoded) > 0) {
            return array_slice(array_values($decoded), 0, 3);
        }

        // fallback: split lines
        $lines = preg_split('/\r?\n/', $reply);
        $out = [];
        foreach ($lines as $ln) {
            $ln = trim($ln, " \t\n\r\"'-:");
            if ($ln !== '') $out[] = $ln;
            if (count($out) >= 3) break;
        }

        if (empty($out)) {
            return [substr($question, 0, 200)];
        }

        return $out;
    }

    protected function gradeResults(string $question, array $chunks): array
    {
        $relevant = [];

        foreach ($chunks as $c) {
            $distance = isset($c['distance']) ? (float) $c['distance'] : null;

            // fast path: very close vectors
            if ($distance !== null && $distance < 0.12) {
                $c['eval'] = ['relevant' => true, 'confidence' => 0.95, 'reason' => 'low vector distance'];
                $relevant[] = $c;
                continue;
            }

            // Use LLM to judge whether the chunk answers the question
            $sys = "You are an unbiased evaluator. Given a user question and one text chunk, answer whether the chunk contains the answer. Respond ONLY with a JSON object: {\"relevant\": true|false, \"confidence\": 0-1, \"explanation\": \"...\"}. Do not hallucinate.";

            $chunkText = (string) ($c['text'] ?? '');

            $userPrompt = "Question: {$question}\n\nText chunk:\n" . substr($chunkText, 0, 3000) . "\n\nRespond in JSON.";

            $res = $this->openai->chat([
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user', 'content' => $userPrompt]
            ], ['temperature' => 0.0, 'max_tokens' => 200]);

            $j = json_decode(trim((string) data_get($res, 'reply', '')), true);

            if (is_array($j) && ! empty($j['relevant'])) {
                $c['eval'] = $j;
                $relevant[] = $c;
            } else {
                // keep eval metadata for debugging
                $c['eval'] = is_array($j) ? $j : ['relevant' => false, 'confidence' => 0.0, 'explanation' => 'LLM did not return valid JSON'];
            }
        }

        return ['all' => $chunks, 'relevant' => $relevant];
    }

    protected function hasRelevant(array $graded): bool
    {
        return ! empty($graded['relevant']);
    }

    protected function generateFromContext(string $question, array $chunks): array
    {
        // Build compact context (top N)
        $top = array_slice($chunks, 0, 6);
        $ctxParts = [];
        foreach ($top as $i => $c) {
            $idx = $i + 1;
            $src = data_get($c, 'meta.source', data_get($c, 'meta.file_id', 'unknown'));
            $text = trim(str_replace("\n\n", " \n ", mb_substr($c['text'] ?? '', 0, 4000)));
            $ctxParts[] = "[Context #{$idx}] Source: {$src}\n{$text}";
        }

        $context = implode("\n\n---\n\n", $ctxParts);

        $system = "You are a helpful assistant. Use ONLY the facts provided in the CONTEXT sections below to answer the user's question. If the answer cannot be derived from the context, respond exactly: 'I don't know'. Do not add information not present in the context. Provide concise answer and cite contexts like [Context #1].";

        $user = "CONTEXT:\n{$context}\n\nQuestion: {$question}\n\nAnswer using only the context. If not found, respond 'I don't know'. Include brief citations in square brackets referencing Context numbers.";

        $resp = $this->openai->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user]
        ], ['temperature' => 0.0, 'max_tokens' => 600]);

        $reply = trim((string) data_get($resp, 'reply', ''));

        return ['reply' => $reply, 'raw' => $resp];
    }
}
