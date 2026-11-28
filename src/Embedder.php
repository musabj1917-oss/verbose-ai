<?php
/**
 * Verbose AI - Enterprise Gemini Embedding Engine (768-dim)
 * Matches book_chunks.embedding vector(768).
 * Model: gemini-embedding-2 (multilingual, 768 dimensions).
 */

require_once __DIR__ . '/Config.php';

class Embedder {
    const DIMS = 768;
    const MODEL = 'gemini-embedding-2';

    /**
     * Embed a list of texts using Google Gemini gemini-embedding-2.
     * Returns an array of unit-length float vectors (768-dim), or null on failure.
     */
    public static function embedBatch(array $texts) {
        if (empty($texts)) {
            return [];
        }

        $apiKey = Config::get('GEMINI_API_KEY');
        if (empty($apiKey)) {
            error_log("Embedder: GEMINI_API_KEY is not configured.");
            return null;
        }

        // Clean and prepare input texts (limit length to safe token bounds)
        $cleanTexts = [];
        foreach ($texts as $t) {
            $t = (string)$t;
            $cleanTexts[] = function_exists('mb_substr') ? mb_substr($t, 0, 8000, 'UTF-8') : substr($t, 0, 8000);
        }

        $requests = [];
        foreach ($cleanTexts as $t) {
            $requests[] = [
                'model' => 'models/' . self::MODEL,
                'content' => ['parts' => [['text' => $t]]],
                'outputDimensionality' => self::DIMS,
            ];
        }

        $payload = json_encode(['requests' => $requests], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':batchEmbedContents?key=' . urlencode($apiKey);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_POSTFIELDS => $payload,
            ]);

            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            @curl_close($ch);

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

                    if ($out !== null && count($out) === count($cleanTexts)) {
                        return $out;
                    }
                }
            }

            if ($code === 429) {
                // Rate limit: back off before retry
                usleep(1500000 * $attempt);
            } elseif ($attempt < 3) {
                usleep(500000 * $attempt);
            }
        }

        error_log("Embedder failed after 3 attempts. Last HTTP code: {$code}, error: {$err}");
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
