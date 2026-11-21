<?php
/**
 * Verbose AI - Ingest Extracted Book Chunks into Neon Postgres
 * Usage: php bin/ingest_extracted_book.php /path/to/extracted.json
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Embedder.php';

$jsonPath = $argv[1] ?? null;
if (!$jsonPath || !file_exists($jsonPath)) {
    fwrite(STDERR, "Error: JSON file not provided or not found.\n");
    exit(1);
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!$data || empty($data['title']) || empty($data['chunks'])) {
    fwrite(STDERR, "Error: Invalid book data JSON.\n");
    exit(1);
}

$title = trim($data['title']);
$author = trim($data['author'] ?? 'Ministry of Education');
$description = trim($data['description'] ?? 'Curated primary curriculum textbook.');
$rawChunks = $data['chunks'];

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Error: Database unreachable.\n");
    exit(1);
}

// Check if book already exists
$stmt = $pdo->prepare("SELECT id FROM books WHERE title = ? AND is_active = true LIMIT 1");
$stmt->execute([$title]);
$existingId = $stmt->fetchColumn();
if ($existingId) {
    echo "Book \"{$title}\" already exists (ID: {$existingId}). Skipping.\n";
    exit(0);
}

// Deduplicate / sanitize chunks
$chunks = [];
foreach ($rawChunks as $c) {
    $text = trim($c['text'] ?? '');
    if (strlen($text) < 20) continue;
    $chunks[] = [
        'chapter' => $c['chapter'] ?? ('Section ' . (count($chunks) + 1)),
        'section' => intval($c['section'] ?? (count($chunks) + 1)),
        'text' => $text
    ];
}

if (empty($chunks)) {
    fwrite(STDERR, "Error: No valid text chunks extracted for \"{$title}\".\n");
    exit(1);
}

$totalChunks = count($chunks);
echo "Ingesting \"{$title}\" ({$totalChunks} chunks). Generating embeddings in batches...\n";

// Batch embed all chunks with Gemini
$embeddings = [];
$chunkBatches = array_chunk(array_keys($chunks), 32);
foreach ($chunkBatches as $batchIndex => $keys) {
    $texts = [];
    foreach ($keys as $k) {
        $texts[] = $chunks[$k]['text'];
    }
    $vecs = Embedder::embedBatch($texts);
    foreach ($keys as $pos => $k) {
        $embeddings[$k] = ($vecs && isset($vecs[$pos])) ? Embedder::toLiteral($vecs[$pos]) : null;
    }
    $progress = min($totalChunks, ($batchIndex + 1) * 32);
    echo "  Embedded {$progress}/{$totalChunks} chunks...\n";
}

// Persist to Neon Postgres in a single transaction
$pdo->beginTransaction();
try {
    $bookStmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
    $bookStmt->execute([$title, $author, $description, $totalChunks]);
    $bookId = $bookStmt->fetchColumn();

    $chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, section_number, chunk_text, chunk_index, embedding) VALUES (?, ?, ?, ?, ?, CAST(? AS vector))");
    foreach ($chunks as $idx => $c) {
        $chunkStmt->execute([
            $bookId,
            $c['chapter'],
            $c['section'],
            $c['text'],
            $idx + 1,
            $embeddings[$idx] ?? null
        ]);
    }
    $pdo->commit();
    echo "SUCCESS: Ingested \"{$title}\" (Book ID: {$bookId}, Chunks: {$totalChunks}).\n";
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}
