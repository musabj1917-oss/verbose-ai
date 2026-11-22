<?php
/**
 * Verbose AI - Enterprise Multi-Provider Embedding Engine (768-dim)
 * Matches book_chunks.embedding vector(768).
 *
 * Tier 1: OpenRouter (openai/text-embedding-3-small, openai/text-embedding-3-large, baai/bge-m3)
 *         High-throughput, enterprise rate limits, no 15 RPM throttling.
 * Tier 2: Google Gemini (text-embedding-004, gemini-embedding-2)
 */

require_once __DIR__ . '/Config.php';

class Embedder {
    const DIMS = 768;
    const MODEL = 'nvidia/nemotron-3.5-embed-1b:free';
    const OPENROUTER_MODELS = [
        'nvidia/nemotron-3.5-embed-1b:free',
        'nvidia/nemotron-3-embed-1b:free',
        'openai/text-embedding-3-small'
    ];
    const GEMINI_MODELS = [
        'text-embedding-004',
        'gemini-embedding-2'
    ];

    /**
     * Embed a list of texts. Returns an array of normalized float vectors in the same order,
     * or null if all providers fail.
     */
    public static function embedBatch(array $texts) {
        if (empty($texts)) {
            return [];
        }

        // Clean and prepare input texts
        $cleanTexts = [];
        foreach ($texts as $t) {
            $t = (string)$t;
            $cleanTexts[] = function_exists('mb_substr') ? mb_substr($t, 0, 8000, 'UTF-8') : substr($t, 0, 8000);
        }

        // Tier 1: OpenRouter
        $openRouterKey = Config::get('OPENROUTER_API_KEY');
        if (!empty($openRouterKey)) {
            $vecs = self::embedWithOpenRouter($cleanTexts, $openRouterKey);
            if ($vecs !== null) {
                return $vecs;
            }
        }

        // Tier 2: Gemini
        $geminiKey = Config::get('GEMINI_API_KEY');
        if (!empty($geminiKey)) {
            $vecs = self::embedWithGemini($cleanTexts, $geminiKey);
            if ($vecs !== null) {
                return $vecs;
            }
        }

        return null;
    }

    /**
     * Embed batch using OpenRouter OpenAI / BGE embedding endpoints with elastic 768 dimensions
     */
    private static function embedWithOpenRouter(array $texts, string $apiKey) {
        $url = 'https://openrouter.ai/api/v1/embeddings';

        foreach (self::OPENROUTER_MODELS as $model) {
            $payload = json_encode([
                'model' => $model,
                'input' => $texts,
                'dimensions' => self::DIMS
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Bearer ' . $apiKey,
                    'HTTP-Referer: https://verboseai.vercel.app',
                    'X-Title: Verbose AI Embedder'
                ],
                CURLOPT_POSTFIELDS => $payload
            ]);

            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($raw !== false && $code === 200) {
                $data = json_decode($raw, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    // Sort embeddings by index if present to guarantee identical order
                    usort($data['data'], function($a, $b) {
                        return ($a['index'] ?? 0) <=> ($b['index'] ?? 0);
                    });

                    $out = [];
                    foreach ($data['data'] as $item) {
                        $v = $item['embedding'] ?? null;
                        if (!is_array($v) || count($v) !== self::DIMS) {
                            $out = null;
                            break;
                        }
                        $out[] = self::normalizeVector($v);
                    }

                    if ($out !== null && count($out) === count($texts)) {
                        return $out;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Embed batch using Google Gemini API
     */
    private static function embedWithGemini(array $texts, string $apiKey) {
        foreach (self::GEMINI_MODELS as $model) {
            $requests = [];
            foreach ($texts as $t) {
                $requests[] = [
                    'model' => 'models/' . $model,
                    'content' => ['parts' => [['text' => $t]]],
                    'outputDimensionality' => self::DIMS,
                ];
            }

            $payload = json_encode(['requests' => $requests], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':batchEmbedContents');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'x-goog-api-key: ' . $apiKey
                ],
                CURLOPT_POSTFIELDS => $payload,
            ]);

            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($raw !== false && $code === 200) {
                $data = json_decode($raw, true);
                if (isset($data['embeddings']) && is_array($data['embeddings'])) {
                    $out = [];
                    foreach ($data['embeddings'] as $e) {
                        $v = $e['values'] ?? null;
                        if (!is_array($v) || count($v) !== self::DIMS) {
                            $out = null;
                            break;
                        }
                        $out[] = self::normalizeVector($v);
                    }

                    if ($out !== null && count($out) === count($texts)) {
                        return $out;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Mathematical L2 unit vector normalization for exact cosine similarity
     */
    private static function normalizeVector(array $v): array {
        $norm = 0.0;
        foreach ($v as $x) {
            $norm += $x * $x;
        }
        $norm = sqrt($norm);
        if ($norm <= 0) {
            $norm = 1.0;
        }
        return array_map(function ($x) use ($norm) {
            return $x / $norm;
        }, $v);
    }

    public static function embedOne($text) {
        $r = self::embedBatch([$text]);
        return $r ? $r[0] : null;
    }

    /** pgvector text literal, e.g. [0.1,0.2,...] */
    public static function toLiteral(array $v) {
        return '[' . implode(',', array_map(function ($x) {
            return sprintf('%.6f', $x);
        }, $v)) . ']';
    }
}
