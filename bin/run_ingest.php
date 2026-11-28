<?php

require 'src/Database.php';
$payload = json_decode(file_get_contents('data/english_textbook_ingest_payload.json'), true);
$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed
");
    exit(1);
}

// Check if book already exists
$stmt = $pdo->prepare("SELECT id FROM books WHERE title = ? LIMIT 1");
$stmt->execute([$payload['title']]);
$existingId = $stmt->fetchColumn();
if ($existingId) {
    echo "Book already exists with ID: {$existingId}. Updating chunks...
";
    $pdo->prepare("DELETE FROM book_chunks WHERE book_id = ?")->execute([$existingId]);
    $bookId = $existingId;
    $pdo->prepare("UPDATE books SET chunks_count = ? WHERE id = ?")->execute([count($payload['chunks']), $bookId]);
} else {
    $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
    $stmt->execute([$payload['title'], $payload['author'], $payload['description'], count($payload['chunks'])]);
    $bookId = $stmt->fetchColumn();
    echo "Created new book with ID: {$bookId}
";
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
echo "SUCCESS: Ingested {$payload['title']} (Book ID: {$bookId}) with " . count($payload['chunks']) . " chunks into Neon Postgres!
";
