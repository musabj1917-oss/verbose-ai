<?php
require_once __DIR__ . '/../src/Database.php';
$jsonFile = __DIR__ . '/../data/book30_reembed.json';
if (!file_exists($jsonFile)) {
    fwrite(STDERR, "Payload not found\n");
    exit(1);
}
$updates = json_decode(file_get_contents($jsonFile), true);
$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}
$stmt = $pdo->prepare("UPDATE book_chunks SET embedding = CAST(? AS vector) WHERE id = ?");
$pdo->beginTransaction();
foreach ($updates as $u) {
    $stmt->execute([$u['embedding'], $u['id']]);
}
$pdo->commit();
echo "SUCCESS: Updated " . count($updates) . " chunks for book 30 with gemini-embedding-2 vectors!\n";
