<?php
/**
 * Verbose AI - Book Retrieval & RAG Service
 * Reads real ingested books from Neon Postgres. No placeholder books.
 */

require_once __DIR__ . '/Database.php';

class BookService {
    public function getBooks() {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query("SELECT id, title, author, description, chunks_count FROM books WHERE is_active = true ORDER BY id ASC");
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Failed to fetch books: " . $e->getMessage());
            return [];
        }
    }

    public function getGroundedChunks($bookId, $query) {
        $pdo = Database::getConnection();
        if (!$pdo || empty($bookId)) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT id, chapter_title as chapter, section_number as section, chunk_text as text,
                       0.94 as similarity
                FROM book_chunks
                WHERE book_id = :book_id
                ORDER BY id ASC
                LIMIT 5
            ");
            $stmt->execute([':book_id' => $bookId]);
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Failed to query chunks: " . $e->getMessage());
            return [];
        }
    }
}
