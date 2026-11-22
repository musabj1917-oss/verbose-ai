<?php
/**
 * Verbose AI - Fast Multi-Provider Chunk Embedding Vectorizer.
 * Usage: php bin/backfill_embeddings.php [--reset]
 *
 * Uses OpenRouter (openai/text-embedding-3-small) with high throughput,
 * falling back to Gemini if needed.
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Embedder.php';

$pdo = Database::getConnection();
if (!$pdo) {
    echo "Database unreachable. Check DATABASE_URL.\n";
    exit(1);
}
$pdo->exec("ALTER TABLE book_chunks ADD COLUMN IF NOT EXISTS embedding vector(768)");

$reset = in_array('--reset', $argv) || in_array('-r', $argv);
if ($reset) {
    echo "Resetting all chunk embeddings for uniform model re-indexing...\n";
    $pdo->exec("UPDATE book_chunks SET embedding = NULL");
}

$update = $pdo->prepare("UPDATE book_chunks SET embedding = CAST(? AS vector) WHERE id = ?");
$done = 0;
$failures = 0;
$batchSize = 40;

$total = (int)$pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn();
$remaining = (int)$pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NULL")->fetchColumn();
echo "Total chunks: {$total} | Unembedded chunks: {$remaining}\n";

while (true) {
    $rows = $pdo->query("SELECT id, chunk_text FROM book_chunks WHERE embedding IS NULL ORDER BY id LIMIT {$batchSize}")->fetchAll();
    if (!$rows) {
        break;
    }
    $texts = [];
    foreach ($rows as $r) {
        $texts[] = $r['chunk_text'];
    }
    $t0 = microtime(true);
    $vecs = Embedder::embedBatch($texts);
    $duration = round(microtime(true) - $t0, 2);

    if (!$vecs) {
        if (++$failures >= 10) {
            echo "Giving up after repeated embedding failures (check OPENROUTER_API_KEY / GEMINI_API_KEY). Embedded so far: {$done}\n";
            exit(1);
        }
        echo "Embedding request failed. Sleeping 5s (Attempt {$failures}/10)...\n";
        sleep(5);
        continue;
    }
    $failures = 0;
    foreach ($rows as $pos => $r) {
        $update->execute([Embedder::toLiteral($vecs[$pos]), $r['id']]);
        $done++;
    }
    $pct = $total > 0 ? round(($done / $total) * 100, 1) : 100;
    echo "Embedded {$done} chunks ({$pct}%) in {$duration}s...\n";
    usleep(250000); // 250ms pacing
}

echo "Done! All chunks are successfully vectorized.\n";
