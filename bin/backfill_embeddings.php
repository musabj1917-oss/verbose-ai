<?php
/**
 * Verbose AI - Fill missing chunk embeddings.
 * Usage: php bin/backfill_embeddings.php
 * Embeds every book_chunks row whose embedding is NULL (e.g. chunks ingested while Gemini was unreachable).
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Embedder.php';

$pdo = Database::getConnection();
if (!$pdo) {
    echo "Database unreachable. Check DATABASE_URL.\n";
    exit(1);
}
$pdo->exec("ALTER TABLE book_chunks ADD COLUMN IF NOT EXISTS embedding vector(768)");

$update = $pdo->prepare("UPDATE book_chunks SET embedding = CAST(? AS vector) WHERE id = ?");
$done = 0;
$failures = 0;

while (true) {
    $rows = $pdo->query("SELECT id, chunk_text FROM book_chunks WHERE embedding IS NULL ORDER BY id LIMIT 24")->fetchAll();
    if (!$rows) {
        break;
    }
    $texts = [];
    foreach ($rows as $r) {
        $texts[] = $r['chunk_text'];
    }
    $vecs = Embedder::embedBatch($texts);
    if (!$vecs) {
        if (++$failures >= 15) {
            echo "Giving up after repeated embedding failures (check GEMINI_API_KEY / quota). Embedded so far: {$done}\n";
            exit(1);
        }
        echo "Embedding rate limit hit. Sleeping 25s (Attempt {$failures}/15)...\n";
        sleep(25);
        continue;
    }
    $failures = 0;
    foreach ($rows as $pos => $r) {
        $update->execute([Embedder::toLiteral($vecs[$pos]), $r['id']]);
        $done++;
    }
    echo "Embedded {$done} chunks...\n";
    sleep(2); // Pacing to remain safely under Gemini RPM quota
}

echo "Done. Embedded {$done} chunks.\n";
