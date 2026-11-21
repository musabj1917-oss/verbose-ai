<?php
/**
 * Verbose AI - Database Schema Migrations
 */

require_once __DIR__ . '/../src/Database.php';

echo "Running migrations on Neon Postgres...\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "Error: Database unreachable. Check DATABASE_URL.\n";
    exit(1);
}

$queries = [
    "CREATE EXTENSION IF NOT EXISTS vector;",
    "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );",
    "CREATE TABLE IF NOT EXISTS books (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) DEFAULT 'Unknown',
        description TEXT,
        chunks_count INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );",
    "CREATE TABLE IF NOT EXISTS book_chunks (
        id SERIAL PRIMARY KEY,
        book_id INT REFERENCES books(id) ON DELETE CASCADE,
        chapter_title VARCHAR(255) DEFAULT 'Section 1',
        section_number INT DEFAULT 1,
        chunk_text TEXT NOT NULL,
        chunk_index INT DEFAULT 1,
        embedding vector(768),
        created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );",
    "CREATE INDEX IF NOT EXISTS idx_book_chunks_book_id ON book_chunks(book_id);"
];

foreach ($queries as $idx => $sql) {
    try {
        $pdo->exec($sql);
        echo "Step " . ($idx + 1) . " executed successfully.\n";
    } catch (Exception $e) {
        echo "Step " . ($idx + 1) . " error: " . $e->getMessage() . "\n";
    }
}

echo "Database migrations completed successfully.\n";
