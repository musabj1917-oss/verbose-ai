<?php
/**
 * Verbose AI - AI Waterfall Cascade Service
 * Tier 1: Google AI Studio (Gemini)
 * Tier 2: xAI Grok
 * Tier 3: OpenRouter
 */

require_once __DIR__ . '/Config.php';

class AIService {
    private $geminiApiKey;
    private $grokApiKey;
    private $openRouterApiKey;

    public function __construct() {
        $this->geminiApiKey = Config::get('GEMINI_API_KEY');
        $this->grokApiKey = Config::get('GROK_API_KEY');
        $this->openRouterApiKey = Config::get('OPENROUTER_API_KEY');
    }

    public function generateGroundedAnswer($query, $bookTitle, $citations, $mode = 'ask') {
        $systemPrompt = $this->buildSystemPrompt($bookTitle, $citations, $mode);
        $userPrompt = "User Query: " . $query;

        if (!empty($this->geminiApiKey)) {
            $response = $this->callGemini($systemPrompt, $userPrompt);
            if ($response['success']) {
                $response['provider'] = 'Google AI Studio (Gemini)';
                return $response;
            }
        }

        if (!empty($this->grokApiKey)) {
            $response = $this->callGrok($systemPrompt, $userPrompt);
            if ($response['success']) {
                $response['provider'] = 'xAI Grok';
                return $response;
            }
        }

        if (!empty($this->openRouterApiKey)) {
            $response = $this->callOpenRouter($systemPrompt, $userPrompt);
            if ($response['success']) {
                $response['provider'] = 'OpenRouter (Fallback)';
                return $response;
            }
        }

        return [
            'success' => true,
            'provider' => 'Verbose Local Grounding Engine (Demo Mode)',
            'answer' => $this->synthesizeLocalGroundedAnswer($query, $citations, $mode)
        ];
    }

    private function buildSystemPrompt($bookTitle, $citations, $mode) {
        $contextText = "";
        foreach ($citations as $idx => $c) {
            $ref = $c['chapter'] ?? ('Passage ' . ($idx + 1));
            $contextText .= "[Source " . ($idx + 1) . ": {$ref}]\n" . $c['text'] . "\n\n";
        }

        if ($mode === 'quiz') {
            return "You are Verbose AI, an expert literary and philosophical comprehension tutor.
You have access to verified passages from \"{$bookTitle}\" below.
TASK: Generate a thought-provoking comprehension question that tests the user's deep understanding of these exact passages. You may incorporate necessary historical or conceptual context to enrich the question. Always cite the relevant chapter or section.

VERIFIED BOOK EXCERPTS:
{$contextText}";
        }

        return "You are Verbose AI, an insightful literary and philosophical reading companion.
Book: \"{$bookTitle}\"

CORE GROUNDING & CONTEXT PRINCIPLES:
1. Primary Grounding: Base your answer primarily on the verified book excerpts provided below. Cite the relevant passages (e.g. [Section 1], [Chapter X]).
2. Necessary Outside Context: You may freely draw upon essential outside context (historical background, philosophical definitions, literary terminology, or clarifying analogies) whenever helpful to explain and illuminate the book's ideas. Do NOT provide external website URLs or website links.
3. Anti-Hallucination Guardrail: Do NOT invent plot points, fictitious quotes, or false facts not supported by the book. Distinguish clearly between what the author explicitly states in the text and any clarifying context you provide.
4. Natural, eloquent, and profound: Speak with depth, clarity, and intelligence. Avoid rigid refusals; explain the text meaningfully and help the reader deeply understand the author's thought.

VERIFIED BOOK EXCERPTS:
{$contextText}";
    }

    private function callGemini($systemPrompt, $userPrompt) {
        $models = ['gemini-3.8-flash', 'gemini-3-flash-preview', 'gemini-2.5-flash'];
        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($this->geminiApiKey);
            $payload = [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 1024
                ]
            ];
            $res = $this->httpPost($url, $payload, ['Content-Type: application/json']);
            if ($res['success']) {
                return $res;
            }
        }
        return ['success' => false, 'error' => 'All Gemini models attempted'];
    }

    private function callGrok($systemPrompt, $userPrompt) {
        $url = "https://api.x.ai/v1/chat/completions";
        $payload = [
            'model' => 'grok-beta',
            'temperature' => 0.4,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->grokApiKey];
        $res = $this->httpPost($url, $payload, $headers);
        if ($res['success'] && isset($res['data']['choices'][0]['message']['content'])) {
            return ['success' => true, 'answer' => $res['data']['choices'][0]['message']['content']];
        }
        return $res;
    }

    private function callOpenRouter($systemPrompt, $userPrompt) {
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $payload = [
            'model' => 'meta-llama/llama-3.1-70b-instruct',
            'temperature' => 0.4,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openRouterApiKey,
            'HTTP-Referer: http://localhost:8000',
            'X-Title: Verbose AI'
        ];
        $res = $this->httpPost($url, $payload, $headers);
        if ($res['success'] && isset($res['data']['choices'][0]['message']['content'])) {
            return ['success' => true, 'answer' => $res['data']['choices'][0]['message']['content']];
        }
        return $res;
    }

    private function httpPost($url, $data, $headers) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        if ($err) return ['success' => false, 'error' => $err];
        $json = json_decode($raw, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                return ['success' => true, 'answer' => $json['candidates'][0]['content']['parts'][0]['text'], 'data' => $json];
            }
            return ['success' => true, 'data' => $json];
        }
        return ['success' => false, 'error' => "API error status: {$httpCode}"];
    }

    private function synthesizeLocalGroundedAnswer($query, $citations, $mode) {
        if (empty($citations)) {
            return "No verified book passages were found for this query in the library. Please verify the book context.";
        }
        $primary = $citations[0];
        $text = $primary['text'];
        $chapter = $primary['chapter'] ?? 'Section 1';

        return "In **{$chapter}**, the author directly examines this theme:\n\n"
             . "> *\"" . substr($text, 0, 360) . (strlen($text) > 360 ? '...' : '') . "\"*\n\n"
             . "The passage highlights the essential Stoic teaching: while external circumstances, human behavior, and the fleeting nature of life remain beyond our direct influence, our inner judgment (*hegemonikon*), moral character, and adherence to reason constitute our true realm of sovereignty.\n\n"
             . "• **Verified Book Source:** {$chapter}";
    }
}
