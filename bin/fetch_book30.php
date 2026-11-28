<?php
require_once __DIR__ . '/../src/Database.php';
$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}
$stmt = $pdo->query("SELECT id, chunk_index, chunk_text FROM book_chunks WHERE book_id = 30 ORDER BY chunk_index ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
