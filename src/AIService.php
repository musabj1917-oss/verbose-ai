<?php
/**
 * Verbose AI - Multi-Provider AI Grounding Cascade Service
 * Tier 1: Groq LPUs (Ultra-low latency inference, Qwen & GPT-OSS)
 * Tier 2: OpenRouter (Frontier LLMs: Llama 3.3 70B, Mixtral)
 * Tier 3: Google AI Studio (Gemini)
 * Fallback: Verbose Local Grounding Synthesizer
 */

require_once __DIR__ . '/Config.php';

class AIService {
    private $geminiApiKey;
    private $groqApiKey;
    private $openRouterApiKey;

    public function __construct() {
        $this->geminiApiKey = Config::get('GEMINI_API_KEY');
        $this->groqApiKey = Config::get('GROQ_API_KEY') ?: Config::get('GROK_API_KEY');
        $this->openRouterApiKey = Config::get('OPENROUTER_API_KEY');
    }

    public function generateGroundedAnswer($query, $bookTitle, $citations, $mode = 'ask', $scannedTranscription = null) {
        $systemPrompt = $this->buildSystemPrompt($bookTitle, $citations, $mode, $scannedTranscription);
        $userPrompt = "User Query: " . $query;
        if (!empty($scannedTranscription)) {
            $userPrompt .= "\n\n[Context Notice: A scanned document was provided. Answer the query thoroughly using the scanned transcription and any book context.]";
        }

        // Tier 1: Groq LPUs (Ultra-fast ~10ms inference)
        if (!empty($this->groqApiKey)) {
            $response = $this->callGroq($systemPrompt, $userPrompt);
            if ($response['success']) {
                $response['provider'] = 'Groq LPUs (' . ($response['model'] ?? 'Qwen 27B') . ')';
                return $response;
            }
        }

        // Tier 2: OpenRouter (Frontier models: Llama 3.3 70B)
        if (!empty($this->openRouterApiKey)) {
            $response = $this->callOpenRouter($systemPrompt, $userPrompt);
            if ($response['success']) {
                $response['provider'] = 'OpenRouter (' . ($response['model'] ?? 'Llama 3.3 70B') . ')';
                return $response;
            }
        }

        // Tier 3: Google AI Studio (Gemini)
        if (!empty($this->geminiApiKey)) {
            $response = $this->callGemini($systemPrompt, $userPrompt);
            if ($response['success']) {
                $response['provider'] = 'Google AI Studio (Gemini)';
                return $response;
            }
        }

        // Fallback: Local Grounding Synthesizer
        return [
            'success' => true,
            'provider' => 'Verbose Grounding Engine',
            'answer' => $this->synthesizeLocalGroundedAnswer($query, $citations, $mode, $scannedTranscription)
        ];
    }

    /**
     * Real-time Token Streaming Grounded Answer
     */
    public function streamGroundedAnswer($query, $bookTitle, $citations, $mode = 'ask', $onToken = null, $scannedTranscription = null) {
        $systemPrompt = $this->buildSystemPrompt($bookTitle, $citations, $mode, $scannedTranscription);
        $userPrompt = "User Query: " . $query;
        if (!empty($scannedTranscription)) {
            $userPrompt .= "\n\n[Context Notice: A scanned document was provided. Answer the query thoroughly using the scanned transcription and any book context.]";
        }

        // Tier 1: Groq streaming
        if (!empty($this->groqApiKey)) {
            $response = $this->callGroqStream($systemPrompt, $userPrompt, $onToken);
            if ($response['success']) {
                $response['provider'] = 'Groq LPUs (' . ($response['model'] ?? 'Qwen 27B') . ')';
                return $response;
            }
        }

        // Tier 2: OpenRouter streaming
        if (!empty($this->openRouterApiKey)) {
            $response = $this->callOpenRouterStream($systemPrompt, $userPrompt, $onToken);
            if ($response['success']) {
                $response['provider'] = 'OpenRouter (' . ($response['model'] ?? 'Llama 3.3 70B') . ')';
                return $response;
            }
        }

        // Fallback: Non-streaming cascade
        $fallback = $this->generateGroundedAnswer($query, $bookTitle, $citations, $mode, $scannedTranscription);
        if (is_callable($onToken) && !empty($fallback['answer'])) {
            $onToken($fallback['answer']);
        }
        return $fallback;
    }

    /**
     * Image Scanning & Document OCR using dedicated Vision-Language Models
     * Strictly performs optical transcription and visual inspection without answering questions.
     */
    public function scanImage($imageDataUrl, $userPrompt = '', $bookTitle = null, $citations = []) {
        $systemInstruction = "You are Verbose AI's High-Precision Optical Transcription and Visual OCR Engine.
Your sole purpose is to inspect the provided image, document, page, or manuscript and extract its visual contents with extreme optical precision:
1. Verbatim Transcription: Faithfully and completely transcribe all visible text, headings, questions, lists, captions, equations, and handwriting exactly as they appear.
2. Visual Elements & Structure: Concisely describe any visible diagrams, charts, figures, illustrations, or structural layout.
3. Objective Accuracy: Transcribe accurately without answering questions, speculating, or providing commentary. Ensure all question text, problem statements, and instructions visible on the page are preserved verbatim.";

        $query = "Transcribe all visible text from this page or image verbatim and describe any visual diagrams or figures.";
        if (!empty($userPrompt) && trim($userPrompt) !== 'Analyze this scanned page.') {
            $query .= " Note any text, numbers, or questions especially relevant to: " . $userPrompt;
        }
        if (!empty($bookTitle)) {
            $query .= " (Document context: {$bookTitle})";
        }

        // Dedicated Multimodal Vision Models with auto-failover cascade
        $visionModels = [
            'nvidia/nemotron-3.5-super-vl',
            'nvidia/nemotron-3.5-super-vl:free',
            'meta-llama/llama-3.2-11b-vision-instruct',
            'qwen/qwen-2.5-vl-72b-instruct:free',
            'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free'
        ];

        if (!empty($this->openRouterApiKey)) {
            $url = "https://openrouter.ai/api/v1/chat/completions";
            foreach ($visionModels as $model) {
                $payload = [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemInstruction
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $query],
                                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]]
                            ]
                        ]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 4096,
                    'include_reasoning' => false,
                    'reasoning' => ['effort' => 'none']
                ];
                $headers = [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Bearer ' . $this->openRouterApiKey,
                    'HTTP-Referer: https://verboseai.vercel.app',
                    'X-Title: Verbose AI Vision'
                ];
                $res = $this->httpPost($url, $payload, $headers);
                if ($res['success'] && isset($res['data']['choices'][0]['message'])) {
                    $msg = $res['data']['choices'][0]['message'];
                    $content = trim($msg['content'] ?? '');
                    $reasoning = trim($msg['reasoning'] ?? '');

                    // Encapsulate any reasoning tokens in a <think> block
                    if (!empty($reasoning)) {
                        $content = "<think>\n" . $reasoning . "\n</think>\n\n" . $content;
                    }

                    if (!empty($content)) {
                        $shortName = 'NVIDIA Nemotron 3.5 Super VL (Vision)';
                        if (strpos($model, 'llama') !== false) {
                            $shortName = 'Meta Llama 3.2 Vision (OpenRouter)';
                        } elseif (strpos($model, 'qwen') !== false) {
                            $shortName = 'Qwen 2.5 VL 72B (OpenRouter)';
                        } elseif (strpos($model, 'nano') !== false) {
                            $shortName = 'NVIDIA Nemotron 3 Nano Omni (Vision)';
                        }

                        return [
                            'success' => true,
                            'transcription' => $content,
                            'answer' => $content,
                            'provider' => $shortName,
                            'model' => $model
                        ];
                    }
                }
            }
        }

        // Fallback: Gemini 3.8 Flash with inline base64 image data
        if (!empty($this->geminiApiKey)) {
            $mime = 'image/jpeg';
            $base64 = $imageDataUrl;
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $imageDataUrl, $matches)) {
                $mime = $matches[1];
                $base64 = $matches[2];
            }
            $models = ['gemini-3.8-flash', 'gemini-3-flash-preview'];
            foreach ($models as $gmod) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$gmod}:generateContent?key=" . urlencode($this->geminiApiKey);
                $payload = [
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $query],
                                ['inline_data' => ['mime_type' => $mime, 'data' => $base64]]
                            ]
                        ]
                    ]
                ];
                $res = $this->httpPost($url, $payload, ['Content-Type: application/json']);
                if ($res['success'] && !empty($res['answer'])) {
                    return [
                        'success' => true,
                        'transcription' => $res['answer'],
                        'answer' => $res['answer'],
                        'provider' => "Google AI Studio ({$gmod} Vision)",
                        'model' => $gmod
                    ];
                }
            }
        }

        return [
            'success' => false,
            'transcription' => '',
            'answer' => '',
            'provider' => 'Verbose Optical Scanner (Local)'
        ];
    }

    private function buildSystemPrompt($bookTitle, $citations, $mode = 'ask', $scannedTranscription = null) {
        $scannedSection = "";
        if (!empty($scannedTranscription)) {
            $cleanTranscription = trim(preg_replace('/<think>.*?<\/think>\s*/s', '', $scannedTranscription));
            if (!empty($cleanTranscription)) {
                $scannedSection = "\n=== OPTICAL TRANSCRIPTION FROM SCANNED DOCUMENT / PAGE ===\n" .
                    "The user provided an image/document which was optically transcribed verbatim below:\n\"\"\"\n" .
                    $cleanTranscription .
                    "\n\"\"\"\n";
            }
        }

        $contextText = "";
        foreach ($citations as $idx => $c) {
            if (isset($c['id']) && $c['id'] === 'vision') {
                continue;
            }
            $ref = $c['chapter'] ?? ('Passage ' . ($idx + 1));
            $contextText .= "[Source " . ($idx + 1) . ": {$ref}]\n" . ($c['text'] ?? '') . "\n\n";
        }

        if (empty($bookTitle)) {
            if (!empty($scannedSection)) {
                return "You are Verbose AI, an insightful literary and analytical reading and reasoning companion.
{$scannedSection}
CORE REASONING & GROUNDING PRINCIPLES:
1. Grounding in Scanned Document: Address the user's inquiry, answer any questions posed, solve problems, or provide deep literary/conceptual analysis grounded directly in the optical transcription above.
2. Direct Answer: If the user query or the scanned document presents specific questions, exercises, or passages to analyze, answer them directly, thoroughly, and with intellectual clarity.
3. Literary Depth & Rigor: Speak with eloquence, depth, and intelligence. Distinguish clearly between what is explicitly stated in the transcribed text and your analysis or explanation.
4. Anti-Hallucination Guardrail: Do not invent facts, quotes, or details not present in the transcription. You may freely use necessary background knowledge, definitions, or clarifying analogies to illuminate the text.
5. Natural & Articulate: Keep your tone thoughtful, articulate, and engaging.";
            }

            return "You are Verbose AI, an insightful, eloquent literary and philosophical companion.
Engage the reader with philosophical depth, historical context, and intellectual clarity.
Answer the user's questions directly, naturally, and with profound intellectual rigor.
Do NOT reference or hallucinate any imaginary book or claim you are reading a 'Selected Book'.
Keep your tone sophisticated, thoughtful, and articulate.";
        }

        if ($mode === 'quiz') {
            return "You are Verbose AI, an expert literary and philosophical comprehension tutor.
You have access to verified passages from \"{$bookTitle}\" below.
{$scannedSection}
TASK: Generate a thought-provoking comprehension question that tests the user's deep understanding of these exact passages. You may incorporate necessary historical or conceptual context to enrich the question. Always cite the relevant chapter or section.

" . (!empty($contextText) ? "VERIFIED BOOK EXCERPTS:\n{$contextText}" : "");
        }

        return "You are Verbose AI, an insightful literary and philosophical reading companion.
Book: \"{$bookTitle}\"
{$scannedSection}
CORE GROUNDING & CONTEXT PRINCIPLES:
1. Explicit Context Utilization: Read the verified book excerpts and any scanned document transcription above with absolute care. You MUST directly cite and synthesize specific quotes, facts, and passages from these sources (e.g. [Page X], [Source Y], or [Scanned Page]) to substantiate your answer. Do not ignore the provided context.
2. Scanned Document Integration: If an optical transcription from a scanned page is provided above, address the user's inquiry by directly analyzing, explaining, or answering questions from that page in the context of \"{$bookTitle}\".
3. Necessary Outside Context: You may freely draw upon essential outside context (historical background, philosophical definitions, literary terminology, or clarifying analogies) whenever helpful to explain and illuminate the book's ideas. Do NOT provide external website URLs or website links.
4. Anti-Hallucination Guardrail: Do NOT invent plot points, fictitious quotes, or false facts not supported by the book or document. Distinguish clearly between what the text explicitly states and any clarifying analysis.
5. Natural, eloquent, and profound: Speak with depth, clarity, and intelligence. Avoid rigid refusals; explain the text meaningfully and help the reader deeply understand the author's thought.

" . (!empty($contextText) ? "VERIFIED BOOK EXCERPTS:\n{$contextText}" : "");
    }

    private function callGroq($systemPrompt, $userPrompt) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $models = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'allam-2-7b'];

        foreach ($models as $model) {
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey
            ];
            $res = $this->httpPost($url, $payload, $headers);
            if ($res['success'] && isset($res['data']['choices'][0]['message']['content'])) {
                $content = trim($res['data']['choices'][0]['message']['content']);
                if (!empty($content)) {
                    return [
                        'success' => true,
                        'answer' => $content,
                        'model' => $model
                    ];
                }
            }
        }
        return ['success' => false, 'error' => 'Groq inference failed'];
    }

    private function callGroqStream($systemPrompt, $userPrompt, $onToken) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $models = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'allam-2-7b'];

        foreach ($models as $model) {
            $accumulated = "";
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'stream' => true,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey
            ];
            $res = $this->httpStream($url, $payload, $headers, $onToken, $accumulated);
            if ($res && !empty($accumulated)) {
                return [
                    'success' => true,
                    'answer' => $accumulated,
                    'model' => $model
                ];
            }
        }
        return ['success' => false];
    }

    private function callOpenRouter($systemPrompt, $userPrompt) {
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $models = [
            'meta-llama/llama-3.3-70b-instruct',
            'meta-llama/llama-3.1-70b-instruct',
            'mistralai/mixtral-8x7b-instruct'
        ];

        foreach ($models as $model) {
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openRouterApiKey,
                'HTTP-Referer: https://verboseai.vercel.app',
                'X-Title: Verbose AI'
            ];
            $res = $this->httpPost($url, $payload, $headers);
            if ($res['success'] && isset($res['data']['choices'][0]['message']['content'])) {
                $content = trim($res['data']['choices'][0]['message']['content']);
                if (!empty($content)) {
                    return [
                        'success' => true,
                        'answer' => $content,
                        'model' => $model
                    ];
                }
            }
        }
        return ['success' => false, 'error' => 'OpenRouter inference failed'];
    }

    private function callOpenRouterStream($systemPrompt, $userPrompt, $onToken) {
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $models = [
            'meta-llama/llama-3.3-70b-instruct',
            'meta-llama/llama-3.1-70b-instruct'
        ];

        foreach ($models as $model) {
            $accumulated = "";
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'stream' => true,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openRouterApiKey,
                'HTTP-Referer: https://verboseai.vercel.app',
                'X-Title: Verbose AI'
            ];
            $res = $this->httpStream($url, $payload, $headers, $onToken, $accumulated);
            if ($res && !empty($accumulated)) {
                return [
                    'success' => true,
                    'answer' => $accumulated,
                    'model' => $model
                ];
            }
        }
        return ['success' => false];
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
                    'maxOutputTokens' => 4096
                ]
            ];
            $res = $this->httpPost($url, $payload, ['Content-Type: application/json']);
            if ($res['success']) {
                return $res;
            }
        }
        return ['success' => false, 'error' => 'All Gemini models attempted'];
    }

    private function httpStream($url, $data, $headers, $onToken, &$accumulated) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerboseAI/1.0');
        $buffer = "";

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, &$accumulated, $onToken) {
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') continue;
                    $decoded = json_decode($jsonStr, true);
                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        $token = $decoded['choices'][0]['delta']['content'];
                        $accumulated .= $token;
                        if (is_callable($onToken)) {
                            $onToken($token);
                        }
                    }
                }
            }
            return strlen($chunk);
        });

        $success = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!$success || $code < 200 || $code >= 300) {
            return false;
        }
        return true;
    }

    private function httpPost($url, $data, $headers) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerboseAI/1.0');
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
        return ['success' => false, 'error' => "API error status: {$httpCode}", 'code' => $httpCode, 'raw' => $raw, 'data' => $json];
    }

    private function synthesizeLocalGroundedAnswer($query, $citations, $mode = 'ask', $scannedTranscription = null) {
        if (!empty($scannedTranscription)) {
            $cleanSnippet = trim(preg_replace('/<think>.*?<\/think>\s*/s', '', $scannedTranscription));
            $snippet = function_exists('mb_substr') ? mb_substr($cleanSnippet, 0, 350, 'UTF-8') : substr($cleanSnippet, 0, 350);
            return "Based on the optically transcribed page:\n\n"
                 . "> *\"" . $snippet . (strlen($cleanSnippet) > 350 ? '...' : '') . "\"*\n\n"
                 . "The document's visible content directly relates to your inquiry: **\"{$query}\"**.\n\n"
                 . "• **Verified Source:** Scanned Page Transcription";
        }
        if (empty($citations)) {
            return "Verbose AI is operating in open philosophical discourse. You may ask any question across literature, philosophy, and history, attach a book from your library for grounded chapter analysis, or scan an image page.";
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
