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
    $rows = $pdo->query("SELECT id, chunk_text FROM book_chunks WHERE embedding IS NULL ORDER BY id LIMIT 32")->fetchAll();
    if (!$rows) {
        break;
    }
    $texts = [];
    foreach ($rows as $r) {
        $texts[] = $r['chunk_text'];
    }
    $vecs = Embedder::embedBatch($texts);
    if (!$vecs) {
        if (++$failures >= 10) {
            echo "Giving up after repeated embedding failures (check GEMINI_API_KEY / quota). Embedded so far: {$done}\n";
            exit(1);
        }
        echo "Embedding rate limit / pause hit. Sleeping 12s (Attempt {$failures}/10)...\n";
        sleep(12);
        continue;
    }
    $failures = 0;
    foreach ($rows as $pos => $r) {
        $update->execute([Embedder::toLiteral($vecs[$pos]), $r['id']]);
        $done++;
    }
    echo "Embedded {$done} chunks...\n";
    usleep(600000); // 0.6s breathing room to stay well under Gemini RPM
}

echo "Done. Embedded {$done} chunks.\n";
