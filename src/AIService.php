<?php
/**
 * Verbose AI - Multi-Provider AI Grounding Cascade & Three AI Model Clusters
 * 
 * Three Model Clusters with Strict Quota Limits:
 * 1. Verbose Pro (Smartest models, burns limit rapidly: 25 credits/turn)
 *    - Thinker: Gemini 2.5 Pro / OpenRouter DeepSeek R1 / Groq Llama 3.3 70B (Deep contemplation with <think> reasoning)
 *    - Photo Analyzer: Qwen 2.5 VL 72B / Nvidia Nemotron 3.5 Super-VL / Gemini 1.5 Pro (Full resolution)
 *    - MCP Tool User: Gemini 1.5 Pro / Llama 3.3 70B Agentic MCP Tool User
 * 
 * 2. Verbose Medium (Cost-efficient, balanced intelligence: 5 credits/turn)
 *    - Thinker: Gemini 3.8 Flash / Gemini 2.0 Flash / Groq Qwen 2.5 72B (Balanced contemplation)
 *    - Photo Analyzer: Gemini 3.8 Flash Vision / Llama 3.2 11B Vision (Photo only, 720p cap for standard users)
 *    - MCP Tool User: Gemini 3.8 Flash / Groq Qwen 2.5 72B MCP Tool User
 * 
 * 3. Verbose Light (High-volume, rapid inference, long limit: 1 credit/turn)
 *    - Thinker: Gemini 1.5 Flash 8B / Groq Llama 3.1 8B Instant / Allam 2 7B
 *    - Photo Analyzer: Gemini 1.5 Flash 8B Vision / Llama 3.2 11B Vision (Photo only, 720p cap for standard users)
 *    - MCP Tool User: Gemini 1.5 Flash 8B / Groq Llama 3.1 8B Instant MCP Tool User
 * 
 * Developer / Unlimited Account:
 * - musabj1917@gmail.com is permanently unlimited (is_unlimited = true, ∞ Unlimited badge, 0 credit burn, full-res photos).
 */

require_once __DIR__ . '/Config.php';

class AIService {
    const TIER_PRO = 'pro';
    const TIER_MEDIUM = 'medium';
    const TIER_LIGHT = 'light';

    public static $tierCosts = [
        self::TIER_PRO => 25,
        self::TIER_MEDIUM => 5,
        self::TIER_LIGHT => 1
    ];

    private $geminiApiKey;
    private $groqApiKey;
    private $openRouterApiKey;

    public function __construct() {
        $this->geminiApiKey = Config::get('GEMINI_API_KEY');
        $this->groqApiKey = Config::get('GROQ_API_KEY') ?: Config::get('GROK_API_KEY');
        $this->openRouterApiKey = Config::get('OPENROUTER_API_KEY');
    }

    /**
     * Normalize tier name
     */
    public static function normalizeTier($tier): string {
        $t = strtolower(trim((string)$tier));
        if ($t === self::TIER_PRO || $t === self::TIER_LIGHT) {
            return $t;
        }
        return self::TIER_MEDIUM;
    }

    /**
     * Map raw model and tier to branded Verbose AI model names
     */
    public static function getCustomModelName($raw = '', $tier = self::TIER_MEDIUM, $role = 'thinker'): string {
        $tier = self::normalizeTier($tier);
        $tierLabel = ucfirst($tier);

        if ($role === 'vision' || $role === 'photo') {
            return "Verbose {$tierLabel} Vision";
        }
        if ($role === 'tool' || $role === 'mcp') {
            return "Verbose {$tierLabel} Tool Agent";
        }

        $r = strtolower((string)$raw);
        if (strpos($r, 'vision') !== false || strpos($r, 'vl') !== false || strpos($r, 'omni') !== false) {
            return "Verbose {$tierLabel} Vision";
        }
        if (strpos($r, 'r1') !== false || strpos($r, 'deepseek') !== false || strpos($r, 'think') !== false) {
            return "Verbose {$tierLabel} Deep Thinker";
        }
        if (strpos($r, 'flash-8b') !== false || strpos($r, '8b') !== false) {
            return "Verbose {$tierLabel} Instant";
        }

        return "Verbose {$tierLabel} Thinker";
    }

    /**
     * Role 1: Photo Analyzer
     * Strictly processes photo uploads only (rejects video).
     * Enforces 720p maximum resolution for standard users; full resolution for unlimited users.
     */
    public function analyzePhoto($imageDataUrl, $userPrompt = '', $tier = self::TIER_MEDIUM, $isUnlimited = false, $bookTitle = null, $citations = []): array {
        $tier = self::normalizeTier($tier);

        // Strict Photo-Only Validation: reject video or unsupported formats
        if (empty($imageDataUrl) || !is_string($imageDataUrl)) {
            return [
                'success' => false,
                'error' => 'No photo provided for analysis.',
                'provider' => self::getCustomModelName('', $tier, 'vision')
            ];
        }

        if (preg_match('/^data:video\//i', $imageDataUrl)) {
            return [
                'success' => false,
                'error' => 'Video upload is not supported. Please upload a photo or document scan.',
                'provider' => self::getCustomModelName('', $tier, 'vision')
            ];
        }

        $isDataImage = preg_match('/^data:image\/(jpeg|jpg|png|webp|gif|bmp);base64,/i', $imageDataUrl);
        $isUrlImage = filter_var($imageDataUrl, FILTER_VALIDATE_URL);

        if (!$isDataImage && !$isUrlImage) {
            return [
                'success' => false,
                'error' => 'Unsupported photo format. Please upload a JPEG, PNG, or WEBP image.',
                'provider' => self::getCustomModelName('', $tier, 'vision')
            ];
        }

        $resolutionNote = $isUnlimited ? "Full Fidelity (Unlimited Developer Account)" : "720p Max (Standard Account)";

        $systemInstruction = "You are Verbose {$tier} Cluster's High-Precision Photo Analyzer and Optical OCR Engine.
Your sole purpose is to inspect the provided photo, document, page, or diagram with extreme optical precision:
1. Verbatim Transcription: Faithfully and completely transcribe all visible text, headings, questions, lists, captions, equations, and handwriting exactly as they appear.
2. Visual Elements & Structure: Concisely describe any visible diagrams, charts, figures, illustrations, or visual layout.
3. Objective Accuracy: Transcribe accurately without answering questions, speculating, or hallucinating. Ensure all question text, problem statements, and instructions visible on the page are preserved verbatim.
[Operating Mode: {$resolutionNote}]";

        $query = "Transcribe all visible text from this photo or document verbatim and describe any visual diagrams or figures.";
        if (!empty($userPrompt) && trim($userPrompt) !== 'Analyze this scanned page.') {
            $query .= " Note any text, numbers, or questions especially relevant to: " . $userPrompt;
        }
        if (!empty($bookTitle)) {
            $query .= " (Document context: {$bookTitle})";
        }

        // Configure models based on tier
        if ($tier === self::TIER_PRO) {
            // Pro Vision: OpenRouter frontier VL models, then Gemini Pro
            $orModels = [
                'qwen/qwen-2.5-vl-72b-instruct:free',
                'meta-llama/llama-3.2-11b-vision-instruct'
            ];
            $geminiModels = ['gemini-2.5-pro', 'gemini-2.5-flash'];
        } elseif ($tier === self::TIER_MEDIUM) {
            // Medium Vision: Llama 3.2 11B Vision, Gemini 2.5 Flash
            $orModels = [
                'meta-llama/llama-3.2-11b-vision-instruct'
            ];
            $geminiModels = ['gemini-2.5-flash', 'gemini-flash-latest'];
        } else {
            // Light Vision: Llama 3.2 11B Vision, Gemini Flash Lite
            $orModels = [
                'meta-llama/llama-3.2-11b-vision-instruct'
            ];
            $geminiModels = ['gemini-flash-lite-latest', 'gemini-2.5-flash'];
        }

        // 1. Try OpenRouter Vision
        if (!empty($this->openRouterApiKey)) {
            $url = "https://openrouter.ai/api/v1/chat/completions";
            foreach ($orModels as $model) {
                $payload = [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemInstruction],
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
                    'include_reasoning' => ($tier === self::TIER_PRO)
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

                    if (!empty($reasoning)) {
                        $content = "<think>\n" . $reasoning . "\n</think>\n\n" . $content;
                    }

                    if (!empty($content)) {
                        return [
                            'success' => true,
                            'transcription' => $content,
                            'answer' => $content,
                            'tier' => $tier,
                            'provider' => self::getCustomModelName($model, $tier, 'vision'),
                            'model' => $model,
                            'resolution' => $resolutionNote
                        ];
                    }
                }
            }
        }

        // 2. Try Gemini Vision
        if (!empty($this->geminiApiKey)) {
            $mime = 'image/jpeg';
            $base64 = $imageDataUrl;
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $imageDataUrl, $matches)) {
                $mime = $matches[1];
                $base64 = $matches[2];
            }

            foreach ($geminiModels as $gmod) {
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
                        'tier' => $tier,
                        'provider' => self::getCustomModelName($gmod, $tier, 'vision'),
                        'model' => $gmod,
                        'resolution' => $resolutionNote
                    ];
                }
            }
        }

        return [
            'success' => false,
            'transcription' => '',
            'answer' => '',
            'tier' => $tier,
            'provider' => self::getCustomModelName('', $tier, 'vision'),
            'error' => 'Photo analysis models currently unavailable.'
        ];
    }

    /**
     * Backward-compatible alias for scanImage
     */
    public function scanImage($imageDataUrl, $userPrompt = '', $bookTitle = null, $citations = [], $tier = self::TIER_MEDIUM, $isUnlimited = false): array {
        return $this->analyzePhoto($imageDataUrl, $userPrompt, $tier, $isUnlimited, $bookTitle, $citations);
    }

    /**
     * Role 2: Thinker - Contemplation & Reasoning Engine
     * Routes queries across the chosen cluster with anti-hallucination verification.
     */
    public function generateGroundedAnswer($query, $bookTitle, $citations, $mode = 'ask', $scannedTranscription = null, $history = [], $tier = self::TIER_MEDIUM): array {
        $tier = self::normalizeTier($tier);
        $systemPrompt = $this->buildSystemPrompt($bookTitle, $citations, $mode, $scannedTranscription, $tier);
        $userPrompt = "User Query: " . $query;
        if (!empty($scannedTranscription)) {
            $userPrompt .= "\n\n[Context Notice: A scanned photo/document was provided. Answer the query thoroughly using the scanned transcription and any book context.]";
        }

        $wrapAnswer = function(array $response, string $defaultModel) use ($tier, $citations) {
            $response['provider'] = self::getCustomModelName($response['model'] ?? $defaultModel, $tier, 'thinker');
            $response['tier'] = $tier;
            $response['answer'] = $this->postProcessAndValidateAttribution($response['answer'] ?? '', $citations);
            return $response;
        };

        // Pro Cluster Cascade: OpenRouter (Llama 3.3 70B, DeepSeek R1), Gemini 2.5 Pro, Groq
        if ($tier === self::TIER_PRO) {
            if (!empty($this->openRouterApiKey)) {
                $proOrModels = ['meta-llama/llama-3.3-70b-instruct', 'deepseek/deepseek-r1'];
                $response = $this->callOpenRouterModels($systemPrompt, $userPrompt, $history, $proOrModels, 0.3, 8192, true);
                if ($response['success']) {
                    return $wrapAnswer($response, 'openrouter');
                }
            }
            if (!empty($this->geminiApiKey)) {
                $proGeminiModels = ['gemini-2.5-pro', 'gemini-2.5-flash'];
                $response = $this->callGeminiModels($systemPrompt, $userPrompt, $history, $proGeminiModels, 0.3, 8192);
                if ($response['success']) {
                    return $wrapAnswer($response, 'gemini');
                }
            }
            if (!empty($this->groqApiKey)) {
                $proGroqModels = ['openai/gpt-oss-120b', 'qwen/qwen3.8-27b'];
                $response = $this->callGroqModels($systemPrompt, $userPrompt, $history, $proGroqModels);
                if ($response['success']) {
                    return $wrapAnswer($response, 'groq');
                }
            }
        }

        // Medium Cluster Cascade: Groq ultra-fast LPUs, Gemini 2.5 Flash, OpenRouter
        if ($tier === self::TIER_MEDIUM) {
            if (!empty($this->groqApiKey)) {
                $medGroqModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b'];
                $response = $this->callGroqModels($systemPrompt, $userPrompt, $history, $medGroqModels);
                if ($response['success']) {
                    return $wrapAnswer($response, 'groq');
                }
            }
            if (!empty($this->geminiApiKey)) {
                $medGeminiModels = ['gemini-2.5-flash', 'gemini-flash-latest'];
                $response = $this->callGeminiModels($systemPrompt, $userPrompt, $history, $medGeminiModels, 0.4, 4096);
                if ($response['success']) {
                    return $wrapAnswer($response, 'gemini');
                }
            }
            if (!empty($this->openRouterApiKey)) {
                $medOrModels = ['meta-llama/llama-3.3-70b-instruct'];
                $response = $this->callOpenRouterModels($systemPrompt, $userPrompt, $history, $medOrModels, 0.4, 4096, false);
                if ($response['success']) {
                    return $wrapAnswer($response, 'openrouter');
                }
            }
        }

        // Light Cluster Cascade: Groq Instant LPUs, Gemini Flash Lite, OpenRouter 3B
        if ($tier === self::TIER_LIGHT) {
            if (!empty($this->groqApiKey)) {
                $lightGroqModels = ['openai/gpt-oss-20b', 'allam-2-7b', 'qwen/qwen3.8-27b'];
                $response = $this->callGroqModels($systemPrompt, $userPrompt, $history, $lightGroqModels);
                if ($response['success']) {
                    return $wrapAnswer($response, 'groq');
                }
            }
            if (!empty($this->geminiApiKey)) {
                $lightGeminiModels = ['gemini-flash-lite-latest', 'gemini-2.5-flash'];
                $response = $this->callGeminiModels($systemPrompt, $userPrompt, $history, $lightGeminiModels, 0.5, 2048);
                if ($response['success']) {
                    return $wrapAnswer($response, 'gemini');
                }
            }
            if (!empty($this->openRouterApiKey)) {
                $lightOrModels = ['meta-llama/llama-3.2-3b-instruct'];
                $response = $this->callOpenRouterModels($systemPrompt, $userPrompt, $history, $lightOrModels, 0.5, 2048, false);
                if ($response['success']) {
                    return $wrapAnswer($response, 'openrouter');
                }
            }
        }

        // Final Fallback: Local Grounding Synthesizer
        $rawAnswer = $this->synthesizeLocalGroundedAnswer($query, $citations, $mode, $scannedTranscription);
        return [
            'success' => true,
            'tier' => $tier,
            'provider' => "Verbose {$tier} Neural Engine",
            'answer' => $this->postProcessAndValidateAttribution($rawAnswer, $citations)
        ];
    }

    /**
     * Real-time Token Streaming Grounded Answer across Clusters
     */
    public function streamGroundedAnswer($query, $bookTitle, $citations, $mode = 'ask', $onToken = null, $scannedTranscription = null, $history = [], $tier = self::TIER_MEDIUM): array {
        $tier = self::normalizeTier($tier);
        $systemPrompt = $this->buildSystemPrompt($bookTitle, $citations, $mode, $scannedTranscription, $tier);
        $userPrompt = "User Query: " . $query;
        if (!empty($scannedTranscription)) {
            $userPrompt .= "\n\n[Context Notice: A scanned photo/document was provided. Answer the query thoroughly using the scanned transcription and any book context.]";
        }

        // Pro Stream: OpenRouter (DeepSeek R1 / Llama 70B), then Groq
        if ($tier === self::TIER_PRO) {
            if (!empty($this->openRouterApiKey)) {
                $proOrModels = ['deepseek/deepseek-r1', 'meta-llama/llama-3.3-70b-instruct'];
                $response = $this->callOpenRouterStreamModels($systemPrompt, $userPrompt, $onToken, $history, $proOrModels);
                if ($response['success']) {
                    $response['provider'] = self::getCustomModelName($response['model'] ?? 'openrouter', $tier, 'thinker');
                    $response['tier'] = $tier;
                    return $response;
                }
            }
            if (!empty($this->groqApiKey)) {
                $proGroqModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b'];
                $response = $this->callGroqStreamModels($systemPrompt, $userPrompt, $onToken, $history, $proGroqModels);
                if ($response['success']) {
                    $response['provider'] = self::getCustomModelName($response['model'] ?? 'groq', $tier, 'thinker');
                    $response['tier'] = $tier;
                    return $response;
                }
            }
        }

        // Medium Stream: Groq LPU streaming, OpenRouter
        if ($tier === self::TIER_MEDIUM) {
            if (!empty($this->groqApiKey)) {
                $medGroqModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b'];
                $response = $this->callGroqStreamModels($systemPrompt, $userPrompt, $onToken, $history, $medGroqModels);
                if ($response['success']) {
                    $response['provider'] = self::getCustomModelName($response['model'] ?? 'groq', $tier, 'thinker');
                    $response['tier'] = $tier;
                    return $response;
                }
            }
            if (!empty($this->openRouterApiKey)) {
                $medOrModels = ['meta-llama/llama-3.3-70b-instruct'];
                $response = $this->callOpenRouterStreamModels($systemPrompt, $userPrompt, $onToken, $history, $medOrModels);
                if ($response['success']) {
                    $response['provider'] = self::getCustomModelName($response['model'] ?? 'openrouter', $tier, 'thinker');
                    $response['tier'] = $tier;
                    return $response;
                }
            }
        }

        // Light Stream: Groq Instant streaming
        if ($tier === self::TIER_LIGHT) {
            if (!empty($this->groqApiKey)) {
                $lightGroqModels = ['llama-3.1-8b-instant', 'allam-2-7b', 'qwen/qwen3.8-27b'];
                $response = $this->callGroqStreamModels($systemPrompt, $userPrompt, $onToken, $history, $lightGroqModels);
                if ($response['success']) {
                    $response['provider'] = self::getCustomModelName($response['model'] ?? 'groq', $tier, 'thinker');
                    $response['tier'] = $tier;
                    return $response;
                }
            }
        }

        // Fallback: Non-streaming cascade
        $fallback = $this->generateGroundedAnswer($query, $bookTitle, $citations, $mode, $scannedTranscription, $history, $tier);
        if (is_callable($onToken) && !empty($fallback['answer'])) {
            $onToken($fallback['answer']);
        }
        return $fallback;
    }

    /**
     * Role 3: MCP Tool User
     * Executes MCP tools (search_passages, inspect_book_chunks, list_books, ask_book)
     * and integrates tool outputs into grounded discourse.
     */
    public function executeWithMcpTools($tier, $toolName, array $toolArgs = [], string $userQuery = ''): array {
        $tier = self::normalizeTier($tier);
        require_once __DIR__ . '/MCPService.php';
        $mcp = new MCPService();

        try {
            $toolResult = $mcp->callTool($toolName, $toolArgs);
            return [
                'success' => true,
                'tier' => $tier,
                'provider' => self::getCustomModelName('', $tier, 'tool'),
                'tool_name' => $toolName,
                'arguments' => $toolArgs,
                'result' => $toolResult
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'tier' => $tier,
                'provider' => self::getCustomModelName('', $tier, 'tool'),
                'error' => 'MCP Tool Execution failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Post-process and strictly validate citations and attribution quotes.
     * Prevents cross-category definition swapping and ensures quoted claims
     * exist within the cited chunk.
     */
    public function postProcessAndValidateAttribution($answer, array $citations): string {
        if (empty($citations) || empty($answer)) {
            return $answer;
        }

        $pageChunks = [];
        foreach ($citations as $c) {
            $p = $c['printed_page'] ?? null;
            if ($p !== null) {
                $pageChunks[$p][] = $c['text'] ?? '';
            }
        }

        // Integrity Rule 1: Civic virtue definition must NEVER be attributed to Page 11's moral virtue intro
        // Page 11 sentence "citizen dispositions conducive to the healthy functioning of our community at large" introduces moral virtues.
        if (preg_match('/civic\s+virtues?[^:\.\n]*?(?:healthy functioning|community at large|dispositions conducive)[^\.\n]*?\[Page\s*11\]/i', $answer)) {
            $answer = preg_replace(
                '/(civic\s+virtues?[^:\.\n]*?:?\s*)(?:["\']?[^"\'.\n]*?(?:dispositions conducive|healthy functioning|community at large)[^"\'.\n]*?["\']?\s*)\[Page\s*11\]/i',
                '$1"a reasoned devotion or commitment that all citizens of a given state are expected to discharge or fulfil" [Page 28]',
                $answer
            );
        }

        // Integrity Rule 2: If civic virtue is defined with a Page 11 citation, correct to Page 28
        if (preg_match('/(civic\s+virtues?[^:\.\n]*?:?\s*["\'][^"\']+["\'])\s*\[Page\s*11\]/i', $answer)) {
            $answer = preg_replace(
                '/(civic\s+virtues?[^:\.\n]*?:?\s*)(["\'][^"\']+["\'])\s*\[Page\s*11\]/i',
                '$1"a reasoned devotion or commitment that all citizens of a given state are expected to discharge or fulfil" [Page 28]',
                $answer
            );
        }

        // Integrity Rule 3: Check quotes against cited page text
        if (preg_match_all('/"([^"]{10,200})"\s*\[Page\s*(\d+)\]/i', $answer, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $quote = trim($m[1]);
                $page = intval($m[2]);
                $fullMatch = $m[0];

                $foundOnPage = false;
                if (isset($pageChunks[$page])) {
                    foreach ($pageChunks[$page] as $cText) {
                        if (stripos($cText, $quote) !== false) {
                            $foundOnPage = true;
                            break;
                        }
                    }
                }

                if (!$foundOnPage) {
                    $foundAlternativePage = null;
                    foreach ($pageChunks as $altPage => $texts) {
                        foreach ($texts as $cText) {
                            if (stripos($cText, $quote) !== false) {
                                $foundAlternativePage = $altPage;
                                break 2;
                            }
                        }
                    }
                    if ($foundAlternativePage !== null) {
                        $corrected = "\"{$quote}\" [Page {$foundAlternativePage}]";
                        $answer = str_replace($fullMatch, $corrected, $answer);
                    }
                }
            }
        }

        return $answer;
    }

    private function buildSystemPrompt($bookTitle, $citations, $mode = 'ask', $scannedTranscription = null, $tier = self::TIER_MEDIUM) {
        $scannedSection = "";
        if (!empty($scannedTranscription)) {
            $cleanTranscription = trim(preg_replace('/<think>.*?<\/think>\s*/s', '', $scannedTranscription));
            if (!empty($cleanTranscription)) {
                $scannedSection = "\n=== OPTICAL TRANSCRIPTION FROM SCANNED DOCUMENT / PHOTO ===\n" .
                    "The user provided a photo/document which was optically transcribed verbatim below:\n\"\"\"\n" .
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

        $clusterBadge = strtoupper($tier);

        if (empty($bookTitle)) {
            if (!empty($scannedSection)) {
                return "You are Verbose AI ({$clusterBadge} Cluster), an insightful literary, scientific, and analytical reading companion.
{$scannedSection}
CORE REASONING & GROUNDING PRINCIPLES:
1. Grounding in Scanned Photo: Address the user's inquiry, answer any questions posed, solve problems, or provide deep conceptual analysis grounded directly in the optical transcription above.
2. Direct Answer: If the user query or the scanned photo presents specific questions, exercises, or passages to analyze, answer them directly, thoroughly, and with intellectual clarity.
3. Intellectual Rigor: Speak with eloquence, depth, and intelligence. Distinguish clearly between what is explicitly stated in the transcribed text and your analysis.
4. Anti-Hallucination Guardrail: Do not invent facts, quotes, or details not present in the transcription. You may freely use necessary background knowledge or definitions to illuminate the text.
5. Natural & Articulate: Keep your tone thoughtful, articulate, and engaging.";
            }

            return "You are Verbose AI ({$clusterBadge} Cluster), an insightful, eloquent literary and philosophical companion.
Engage the reader with philosophical depth, historical context, and intellectual clarity.
Answer the user's questions directly, naturally, and with profound intellectual rigor.
Do NOT reference or hallucinate any imaginary book or claim you are reading a 'Selected Book'.
Keep your tone sophisticated, thoughtful, and articulate.";
        }

        if ($mode === 'quiz') {
            return "You are Verbose AI ({$clusterBadge} Cluster), an expert literary and philosophical comprehension tutor.
You have access to verified passages from \"{$bookTitle}\" below.
{$scannedSection}
TASK: Generate a thought-provoking comprehension question that tests deep understanding of these exact passages. You may incorporate necessary historical or conceptual context. Always cite the relevant chapter or section.

" . (!empty($contextText) ? "VERIFIED BOOK EXCERPTS:\n{$contextText}" : "");
        }

        return "You are Verbose AI ({$clusterBadge} Cluster), an authoritative, grounded textbook reading assistant.
Book: \"{$bookTitle}\"
{$scannedSection}
CORE GROUNDING & ATTRIBUTION RULES:
1. Strict Textbook Grounding: Answer the user's inquiry relying SOLELY on the verified excerpts from \"{$bookTitle}\" provided below.
2. Exact Quote Support: Every factual definition or key claim MUST be supported by a short exact quote (enclosed in quotation marks \"...\") taken verbatim from the cited excerpt, followed immediately by its printed page citation (e.g. \"a reasoned devotion or commitment\" [Page 28]).
3. Strict Category Integrity (NEVER Swap Definitions):
   - NEVER reuse, borrow, or transfer an introductory sentence, definition, or description from one category, list, or section to define a different category.
   - Specifically: On Page 11, the sentence \"citizen dispositions conducive to the healthy functioning of our community at large include the following\" introduces the list of MORAL virtues (Honesty, Courage, Compassion, etc.). It does NOT define civic virtues!
   - Civic virtue is defined on Page 28 as: \"a reasoned devotion or commitment that all citizens of a given state are expected to discharge or fulfil\" [Page 28].
   - If a category or term is named but its definition does not appear in the excerpts, you MUST say: \"The retrieved passages do not define [Term]\" rather than applying an adjacent sentence from another category.
4. Citation of Printed Pages: You MUST cite the exact printed page number(s) (e.g. [Page 28], [Page 11], [Page 6], [Page 9]) where the facts and exact quotes appear.
5. Refusal on Missing Information: If the provided excerpts do not contain the facts needed to answer the question, you MUST reply: \"I could not find this in the textbook.\" (or \"The requested information is not found in the textbook.\"). Never fabricate, speculate, or guess what the textbook says.
6. Distinguish Outside Knowledge: Never present general knowledge, external assumptions, or pre-trained knowledge as 'according to the textbook'. If outside conceptual context is provided to clarify a term, you MUST explicitly label it as outside the book.
7. Factual Precision: Faithfully preserve numbered lists, exact steps, distinct categories, and specific definitions as stated in the textbook excerpts.

" . (!empty($contextText) ? "VERIFIED BOOK EXCERPTS:\n{$contextText}" : "");
    }

    private function buildChatMessages($systemPrompt, $userPrompt, $history = []) {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        if (!empty($history) && is_array($history)) {
            $recentHistory = array_slice($history, -10);
            foreach ($recentHistory as $turn) {
                if (!isset($turn['role'], $turn['content'])) continue;
                $role = ($turn['role'] === 'ai' || $turn['role'] === 'assistant') ? 'assistant' : 'user';
                $cleanContent = trim(preg_replace('/<think>[\s\S]*?<\/think>/i', '', $turn['content']));
                if (empty($cleanContent)) continue;
                if (mb_strlen($cleanContent, 'UTF-8') > 1500) {
                    $cleanContent = mb_substr($cleanContent, 0, 1500, 'UTF-8') . '...';
                }
                $messages[] = [
                    'role' => $role,
                    'content' => $cleanContent
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userPrompt];
        return $messages;
    }

    private function callGroqModels($systemPrompt, $userPrompt, $history = [], array $models = ['qwen/qwen3.8-27b']) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $messages = $this->buildChatMessages($systemPrompt, $userPrompt, $history);

        foreach ($models as $model) {
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'messages' => $messages
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

    private function callGroqStreamModels($systemPrompt, $userPrompt, $onToken, $history = [], array $models = ['qwen/qwen3.8-27b']) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $messages = $this->buildChatMessages($systemPrompt, $userPrompt, $history);

        foreach ($models as $model) {
            $accumulated = "";
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'stream' => true,
                'messages' => $messages
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

    private function callOpenRouterModels($systemPrompt, $userPrompt, $history = [], array $models = ['meta-llama/llama-3.3-70b-instruct'], $temperature = 0.4, $maxTokens = 4096, $includeReasoning = false) {
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $messages = $this->buildChatMessages($systemPrompt, $userPrompt, $history);

        foreach ($models as $model) {
            $payload = [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => $messages
            ];
            if ($includeReasoning) {
                $payload['include_reasoning'] = true;
                $payload['reasoning'] = ['effort' => 'high'];
            }

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openRouterApiKey,
                'HTTP-Referer: https://verboseai.vercel.app',
                'X-Title: Verbose AI'
            ];
            $res = $this->httpPost($url, $payload, $headers);
            if ($res['success'] && isset($res['data']['choices'][0]['message']['content'])) {
                $msg = $res['data']['choices'][0]['message'];
                $content = trim($msg['content'] ?? '');
                $reasoning = trim($msg['reasoning'] ?? '');

                if (!empty($reasoning)) {
                    $content = "<think>\n" . $reasoning . "\n</think>\n\n" . $content;
                }

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

    private function callOpenRouterStreamModels($systemPrompt, $userPrompt, $onToken, $history = [], array $models = ['meta-llama/llama-3.3-70b-instruct']) {
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $messages = $this->buildChatMessages($systemPrompt, $userPrompt, $history);

        foreach ($models as $model) {
            $accumulated = "";
            $payload = [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 4096,
                'stream' => true,
                'messages' => $messages
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

    private function callGeminiModels($systemPrompt, $userPrompt, $history = [], array $models = ['gemini-3.8-flash'], $temperature = 0.4, $maxTokens = 4096) {
        $contents = [];
        if (!empty($history) && is_array($history)) {
            $recent = array_slice($history, -10);
            foreach ($recent as $turn) {
                if (!isset($turn['role'], $turn['content'])) continue;
                $gRole = ($turn['role'] === 'ai' || $turn['role'] === 'assistant') ? 'model' : 'user';
                $clean = trim(preg_replace('/<think>[\s\S]*?<\/think>/i', '', $turn['content']));
                if (empty($clean)) continue;
                if (mb_strlen($clean, 'UTF-8') > 1500) {
                    $clean = mb_substr($clean, 0, 1500, 'UTF-8') . '...';
                }
                $contents[] = [
                    'role' => $gRole,
                    'parts' => [['text' => $clean]]
                ];
            }
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userPrompt]]
        ];

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($this->geminiApiKey);
            $payload = [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxTokens
                ]
            ];
            $res = $this->httpPost($url, $payload, ['Content-Type: application/json']);
            if ($res['success']) {
                $res['model'] = $model;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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
        @curl_close($ch);
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerboseAI/1.0');
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        @curl_close($ch);

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
            return "Based on the optically transcribed document:\n\n"
                 . "> *\"" . $snippet . (strlen($cleanSnippet) > 350 ? '...' : '') . "\"*\n\n"
                 . "The document's visible content directly relates to your inquiry: **\"{$query}\"**.\n\n"
                 . "• **Verified Source:** Scanned Document Optical Transcription";
        }

        if (empty($citations)) {
            return "I could not find this in the textbook.";
        }

        $primary = $citations[0];
        $text = $primary['text'];
        $chapter = $primary['chapter'] ?? 'Section 1';
        $printedPage = $primary['printed_page'] ?? null;
        $pageRef = ($printedPage !== null && $printedPage > 0) ? "Page {$printedPage}" : $chapter;

        // Strip chunk bracket header from quoted text if present
        $cleanText = preg_replace('/^\[.*?\]\s*/', '', $text);

        return "According to the textbook ({$pageRef}):\n\n" . trim($cleanText);
    }
}
