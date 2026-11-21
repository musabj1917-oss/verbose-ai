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

        return "You are Verbose AI, an AI system that uses books as context to understand and answer questions with MINIMUM HALLUCINATIONS.
Book: \"{$bookTitle}\"

CRITICAL RULES FOR MINIMUM HALLUCINATIONS:
1. Answer the user's question using ONLY the provided verified book passages below.
2. If the answer cannot be found in or directly deduced from the excerpts, state clearly: \"The book context does not mention this detail.\"
3. Never invent facts, characters, or outside theories not present in the excerpts.
4. Reference the source excerpts (e.g. [Source 1], [Chapter X]) when stating key points.

VERIFIED BOOK EXCERPTS:
{$contextText}";
    }

    private function callGemini($systemPrompt, $userPrompt) {
        $models = ['gemini-2.5-flash-lite', 'gemini-flash-latest', 'gemini-3.8-flash', 'gemini-1.5-flash'];
        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($this->geminiApiKey);
            $payload = [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1024]
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
            'temperature' => 0.2,
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
            'temperature' => 0.2,
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

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
            return "No verified book passages were found for this query in the library.";
        }
        $points = [];
        foreach (array_slice($citations, 0, 2) as $idx => $c) {
            $points[] = "• {$c['text']} (Ref: {$c['chapter']})";
        }
        return "Based on verified book passages in the context:\n\n" . implode("\n\n", $points) . "\n\n*Zero external sources or unverified hallucinations were used.*";
    }
}
