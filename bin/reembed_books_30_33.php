<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Embedder.php';

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

foreach ([30, 33] as $bookId) {
    $stmt = $pdo->prepare("SELECT id, chunk_text FROM book_chunks WHERE book_id = ? ORDER BY chunk_index ASC");
    $stmt->execute([$bookId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Re-embedding Book {$bookId} (" . count($rows) . " chunks) with " . Embedder::MODEL . "...\n";

    $upd = $pdo->prepare("UPDATE book_chunks SET embedding = CAST(? AS vector) WHERE id = ?");
    $batches = array_chunk($rows, 64);
    $pdo->beginTransaction();
    foreach ($batches as $bIdx => $batch) {
        $texts = array_map(function($r) { return $r['chunk_text']; }, $batch);
        $vecs = Embedder::embedBatch($texts);
        if (!$vecs || count($vecs) !== count($batch)) {
            $pdo->rollBack();
            fwrite(STDERR, "Failed embedding batch {$bIdx} for Book {$bookId}\n");
            exit(1);
        }
        foreach ($batch as $idx => $r) {
            $upd->execute([Embedder::toLiteral($vecs[$idx]), $r['id']]);
        }
        echo "  Batch " . ($bIdx + 1) . "/" . count($batches) . " done.\n";
    }
    $pdo->commit();
    echo "Book {$bookId} re-embedded successfully.\n";
}
