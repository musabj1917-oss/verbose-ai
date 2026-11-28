<?php
require_once __DIR__ . '/../src/Database.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/upsert_book.php <payload.json>\n");
    exit(1);
}

$payload = json_decode(file_get_contents($argv[1]), true);
if (!$payload || empty($payload['title']) || empty($payload['chunks'])) {
    fwrite(STDERR, "Invalid payload JSON\n");
    exit(1);
}

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}

// Check if a book with this exact title already exists (never touch books 23-32 or 33)
$stmt = $pdo->prepare("SELECT id FROM books WHERE title = ? LIMIT 1");
$stmt->execute([$payload['title']]);
$existingId = $stmt->fetchColumn();

if ($existingId) {
    echo "Book '{$payload['title']}' already exists with ID: {$existingId}. Updating chunks...\n";
    $pdo->prepare("DELETE FROM book_chunks WHERE book_id = ?")->execute([$existingId]);
    $bookId = intval($existingId);
    $pdo->prepare("UPDATE books SET author = ?, description = ?, chunks_count = ?, is_active = true WHERE id = ?")
        ->execute([$payload['author'], $payload['description'], count($payload['chunks']), $bookId]);
} else {
    $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
    $stmt->execute([$payload['title'], $payload['author'], $payload['description'], count($payload['chunks'])]);
    $bookId = intval($stmt->fetchColumn());
    echo "Created new book '{$payload['title']}' with ID: {$bookId}\n";
}

$chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, section_number, chunk_text, chunk_index, embedding, pdf_page, printed_page, unit_title, is_front_matter) VALUES (?, ?, ?, ?, ?, CAST(? AS vector), ?, ?, ?, ?)");

$pdo->beginTransaction();
foreach ($payload['chunks'] as $c) {
    $chunkStmt->execute([
        $bookId,
        $c['chapter_title'],
        $c['section_number'],
        $c['chunk_text'],
        $c['chunk_index'],
        $c['embedding'],
        $c['pdf_page'],
        $c['printed_page'],
        $c['unit_title'],
        $c['is_front_matter'] ? 1 : 0
    ]);
}
$pdo->commit();

echo "SUCCESS: Ingested '{$payload['title']}' (Book ID: {$bookId}) with " . count($payload['chunks']) . " chunks.\n";
