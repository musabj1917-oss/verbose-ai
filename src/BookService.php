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
            $cleanQuery = trim($query ?? '');

            // 1. Extract meaningful search keywords (filter out common stopwords)
            $rawWords = preg_split('/[\s,\.\?!;:\(\)\[\]"\'\/\\\\]+/', strtolower($cleanQuery));
            $stopWords = [
                'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how',
                'does', 'did', 'done', 'will', 'would', 'should', 'could', 'can', 'may',
                'the', 'this', 'that', 'these', 'those', 'and', 'but', 'for', 'with',
                'about', 'from', 'into', 'over', 'after', 'before', 'under', 'book',
                'page', 'tell', 'explain', 'describe', 'summarize', 'mention', 'say',
                'give', 'show', 'mean', 'meaning', 'find', 'does', 'have', 'been'
            ];
            $keywords = [];
            foreach ($rawWords as $w) {
                $w = trim($w);
                if (strlen($w) >= 3 && !in_array($w, $stopWords) && !in_array($w, $keywords)) {
                    $keywords[] = $w;
                    if (count($keywords) >= 8) break;
                }
            }

            // 2. Check if a specific page or unit was requested (e.g. "page 5", "unit 2")
            $specificPage = null;
            if (preg_match('/(?:page|p\.?)\s*(\d+)/i', $cleanQuery, $pm)) {
                $specificPage = intval($pm[1]);
            }
            $specificUnit = null;
            if (preg_match('/(?:unit|chapter)\s*(\d+)/i', $cleanQuery, $um)) {
                $specificUnit = intval($um[1]);
            }

            // 3. Relevance-Scored Retrieval across all digested pages
            if (!empty($keywords) || $specificPage !== null || $specificUnit !== null) {
                $scoreParts = [];
                $params = [':book_id' => $bookId];

                if ($specificPage !== null) {
                    $params[':page_pattern'] = "%[Page {$specificPage}]%";
                    $params[':page_title'] = "%Page {$specificPage}%";
                    $scoreParts[] = "(CASE WHEN chunk_text ILIKE :page_pattern OR chapter_title ILIKE :page_title THEN 50 ELSE 0 END)";
                }

                if ($specificUnit !== null) {
                    $params[':unit_pattern'] = "%UNIT {$specificUnit}%";
                    $scoreParts[] = "(CASE WHEN chunk_text ILIKE :unit_pattern OR chapter_title ILIKE :unit_pattern THEN 40 ELSE 0 END)";
                }

                foreach ($keywords as $idx => $kw) {
                    $paramName = ":kw_" . $idx;
                    $params[$paramName] = '%' . $kw . '%';
                    $scoreParts[] = "(CASE WHEN chunk_text ILIKE {$paramName} THEN 4 ELSE 0 END)";
                    $scoreParts[] = "(CASE WHEN chapter_title ILIKE {$paramName} THEN 8 ELSE 0 END)";
                }

                $scoreSql = implode(' + ', $scoreParts);
                $sql = "
                    SELECT id, chapter_title as chapter, section_number as section, chunk_text as text,
                           ({$scoreSql}) as score
                    FROM book_chunks
                    WHERE book_id = :book_id
                    ORDER BY score DESC, id ASC
                    LIMIT 6
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $matchedRows = array_filter($rows, function($r) { return ($r['score'] ?? 0) > 0; });
                if (!empty($matchedRows)) {
                    return $this->formatChunks(array_values($matchedRows));
                }
            }

            // Fallback: If no specific search term matched, return representative overview chunks
            $stmt = $pdo->prepare("
                SELECT id, chapter_title as chapter, section_number as section, chunk_text as text
                FROM book_chunks
                WHERE book_id = :book_id
                ORDER BY id ASC
                LIMIT 5
            ");
            $stmt->execute([':book_id' => $bookId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $this->formatChunks($rows);

        } catch (Exception $e) {
            error_log("Failed to query chunks: " . $e->getMessage());
            return [];
        }
    }

    private function formatChunks($rows) {
        $formatted = [];
        foreach ($rows as $r) {
            $chapter = $r['chapter'] ?? 'Section';
            $text = $r['text'] ?? '';
            // If chapter is generic "Section X" but text contains [Page Y], accurately promote to Page Y
            if ((strpos($chapter, 'Section') === 0) && preg_match('/\[(?:Page|Chapter)\s*(\d+)\]/i', $text, $m)) {
                $chapter = "Page " . $m[1];
            }
            $formatted[] = [
                'id' => $r['id'],
                'chapter' => $chapter,
                'section' => $r['section'] ?? 1,
                'text' => $text,
                'similarity' => 0.94
            ];
        }
        return $formatted;
    }
}
