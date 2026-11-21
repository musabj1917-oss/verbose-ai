<?php
/**
 * Verbose AI - Gemini embedding helper (768-dim, matches book_chunks.embedding vector(768))
 */

require_once __DIR__ . '/Config.php';

class Embedder {
    const MODEL = 'gemini-embedding-2';
    const DIMS = 768;

    /**
     * Embed a list of texts. Returns a list of unit-length float arrays in the same order,
     * or null if the request fails (callers must degrade gracefully).
     */
    public static function embedBatch(array $texts) {
        $key = Config::get('GEMINI_API_KEY');
        if (!$key || empty($texts)) {
            return null;
        }
        $requests = [];
        foreach ($texts as $t) {
            $t = (string)$t;
            $t = function_exists('mb_substr') ? mb_substr($t, 0, 8000) : substr($t, 0, 8000);
            $requests[] = [
                'model' => 'models/' . self::MODEL,
                'content' => ['parts' => [['text' => $t]]],
                'outputDimensionality' => self::DIMS,
            ];
        }
        $payload = json_encode(['requests' => $requests]);
        if ($payload === false) {
            error_log('Embedding payload encode failed: ' . json_last_error_msg());
            return null;
        }

        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':batchEmbedContents');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code !== 200) {
            error_log('Embedding request failed: HTTP ' . $code);
            return null;
        }

        $data = json_decode($raw, true);
        $out = [];
        foreach (($data['embeddings'] ?? []) as $e) {
            $v = $e['values'] ?? null;
            if (!is_array($v) || count($v) !== self::DIMS) {
                return null;
            }
            $norm = 0.0;
            foreach ($v as $x) {
                $norm += $x * $x;
            }
            $norm = sqrt($norm);
            if ($norm <= 0) {
                $norm = 1.0;
            }
            $out[] = array_map(function ($x) use ($norm) {
                return $x / $norm;
            }, $v);
        }
        return count($out) === count($texts) ? $out : null;
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
