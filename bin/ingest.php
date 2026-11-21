<?php
/**
 * Verbose AI - Admin CLI Book Ingestion Tool
 * Usage: php bin/ingest.php --title "Book Title" --author "Author" --file /path/to/book.txt
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Embedder.php';

echo "=== Verbose AI Admin Book Ingestion ===\n\n";

$options = getopt('', ['title:', 'author:', 'file:', 'description:']);
$title = $options['title'] ?? null;
$author = $options['author'] ?? 'Unknown';
$file = $options['file'] ?? null;
$description = $options['description'] ?? 'Admin curated volume.';

if (!$title || !$file || !file_exists($file)) {
    echo "Usage: php bin/ingest.php --title \"Title\" --author \"Author\" --file ./path/to/book.txt\n";
    exit(1);
}

$content = file_get_contents($file);
if (empty($content)) {
    echo "Error: Book file is empty.\n";
    exit(1);
}

echo "1. Reading and parsing '{$title}' by {$author}...\n";

$paragraphs = preg_split('/\n\s*\n/', $content);
$chunks = [];
$currentChunk = "";

foreach ($paragraphs as $p) {
    $p = trim($p);
    if (empty($p)) continue;

    if (strlen($currentChunk) + strlen($p) > 1800) {
        $chunks[] = $currentChunk;
        $currentChunk = $p;
    } else {
        $currentChunk .= ($currentChunk ? "\n\n" : "") . $p;
    }
}
if ($currentChunk) {
    $chunks[] = $currentChunk;
}

echo "2. Generated " . count($chunks) . " semantic chunks.\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "Database not configured or unreachable. Check DATABASE_URL in .env.\n";
    exit(0);
}

echo "3. Embedding chunks with Gemini (768-dim)...\n";
$pdo->exec("ALTER TABLE book_chunks ADD COLUMN IF NOT EXISTS embedding vector(768)");
$embeddings = [];
foreach (array_chunk(array_keys($chunks), 32) as $keyBatch) {
    $texts = [];
    foreach ($keyBatch as $k) {
        $texts[] = $chunks[$k];
    }
    $vecs = null;
    for ($attempt = 0; $attempt < 4 && !$vecs; $attempt++) {
        if ($attempt > 0) {
            sleep(10);
        }
        $vecs = Embedder::embedBatch($texts);
    }
    if (!$vecs) {
        echo "Embedding failed (check GEMINI_API_KEY / quota). Aborting so no unembedded book is saved.\n";
        exit(1);
    }
    foreach ($keyBatch as $pos => $k) {
        $embeddings[$k] = Embedder::toLiteral($vecs[$pos]);
    }
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
    $stmt->execute([$title, $author, $description, count($chunks)]);
    $bookId = $stmt->fetchColumn();

    $chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, chunk_text, chunk_index, embedding) VALUES (?, ?, ?, ?, CAST(? AS vector))");
    foreach ($chunks as $idx => $chunkText) {
        $chapterTitle = "Section " . ($idx + 1);
        $chunkStmt->execute([$bookId, $chapterTitle, $chunkText, $idx + 1, $embeddings[$idx]]);
    }

    $pdo->commit();
    echo "Successfully ingested '{$title}' (ID: {$bookId}) with " . count($chunks) . " verified chunks into Neon Postgres!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Ingestion failed: " . $e->getMessage() . "\n";
    exit(1);
}
