<?php
/**
 * Verbose AI - Book Retrieval & RAG Service
 * Hybrid pgvector cosine similarity + keyword relevance search with threshold gating.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Embedder.php';

class BookService {
    // Minimum similarity threshold for relevance gating
    const SIMILARITY_THRESHOLD = 0.38;

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

    public function getBookById($bookId) {
        $pdo = Database::getConnection();
        if (!$pdo || empty($bookId)) return null;
        try {
            $stmt = $pdo->prepare("SELECT id, title, author, description, chunks_count FROM books WHERE id = ? LIMIT 1");
            $stmt->execute([$bookId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Retrieve grounded, relevance-gated passages for a query within a book.
     * Uses real hybrid search: pgvector cosine similarity + keyword scoring.
     * Returns empty array if no passages meet the threshold. Never silently falls back to first N rows.
     */
    public function getGroundedChunks($bookId, $query, $limit = 5) {
        $pdo = Database::getConnection();
        if (!$pdo || empty($bookId)) {
            return [];
        }

        $cleanQuery = trim($query ?? '');
        if ($cleanQuery === '') {
            return [];
        }

        $limit = min(15, max(1, intval($limit)));

        // 1. Check if a specific printed page or unit was explicitly requested (e.g. "page 9", "p. 54", "unit 2")
        $specificPage = null;
        if (preg_match('/(?:printed\s+page|page|p\.?)\s*(\d+)/i', $cleanQuery, $pm)) {
            $specificPage = intval($pm[1]);
        }
        $specificUnit = null;
        if (preg_match('/(?:unit|chapter)\s*(\d+)/i', $cleanQuery, $um)) {
            $specificUnit = intval($um[1]);
        }

        // 2. Extract meaningful search keywords (filter stopwords)
        $rawWords = preg_split('/[\s,\.\?!;:\(\)\[\]"\'\/\\\\]+/', strtolower($cleanQuery));
        $stopWords = [
            'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how',
            'does', 'did', 'done', 'will', 'would', 'should', 'could', 'can', 'may',
            'the', 'this', 'that', 'these', 'those', 'and', 'but', 'for', 'with',
            'about', 'from', 'into', 'over', 'after', 'before', 'under', 'book',
            'tell', 'explain', 'describe', 'summarize', 'mention', 'say',
            'give', 'show', 'mean', 'meaning', 'find', 'have', 'been', 'there',
            'is', 'are', 'was', 'were', 'according', 'textbook'
        ];
        $keywords = [];
        foreach ($rawWords as $w) {
            $w = trim($w);
            if (strlen($w) >= 3 && !in_array($w, $stopWords) && !in_array($w, $keywords)) {
                $keywords[] = $w;
                if (count($keywords) >= 8) break;
            }
        }

        try {
            // 3. Compute query embedding for semantic search
            $queryVector = Embedder::embedOne($cleanQuery);
            $hasVector = ($queryVector !== null);

            // If a specific printed page was requested, prioritize page match
            if ($specificPage !== null) {
                $pageStmt = $pdo->prepare("
                    SELECT id, chapter_title as chapter, section_number as section, chunk_text as text,
                           pdf_page, printed_page, unit_title,
                           " . ($hasVector ? "(1 - (embedding <=> CAST(:q AS vector)))" : "1.0") . " as similarity
                    FROM book_chunks
                    WHERE book_id = :book_id
                      AND (printed_page = :page_num OR (printed_page IS NULL AND chunk_text ILIKE :page_pat))
                    ORDER BY " . ($hasVector ? "similarity DESC" : "id ASC") . "
                    LIMIT :limit
                ");
                $params = [
                    ':book_id' => $bookId,
                    ':page_num' => $specificPage,
                    ':page_pat' => "%Page {$specificPage}%",
                    ':limit' => $limit
                ];
                if ($hasVector) {
                    $params[':q'] = Embedder::toLiteral($queryVector);
                }
                $pageStmt->execute($params);
                $pageRows = $pageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($pageRows)) {
                    return $this->formatChunks($pageRows);
                }
            }

            // 4. Real Hybrid Search (pgvector cosine + keyword relevance)
            if ($hasVector) {
                $literal = Embedder::toLiteral($queryVector);

                // Build keyword score SQL
                $kwScoreParts = ["0.0"];
                $kwParams = [];
                foreach ($keywords as $idx => $kw) {
                    $pName = ":kw_" . $idx;
                    $kwParams[$pName] = '%' . $kw . '%';
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pName} THEN 0.15 ELSE 0 END)";
                    $kwScoreParts[] = "(CASE WHEN chapter_title ILIKE {$pName} THEN 0.25 ELSE 0 END)";
                }
                $kwScoreSql = implode(' + ', $kwScoreParts);

                // Unit boost if requested
                $unitScoreSql = "0.0";
                if ($specificUnit !== null) {
                    $kwParams[':unit_pat'] = "%Unit {$specificUnit}%";
                    $unitScoreSql = "(CASE WHEN unit_title ILIKE :unit_pat OR chapter_title ILIKE :unit_pat THEN 0.2 ELSE 0 END)";
                }

                // Exclude front matter from general retrieval
                $sql = "
                    SELECT id, chapter_title as chapter, section_number as section, chunk_text as text,
                           pdf_page, printed_page, unit_title,
                           (1 - (embedding <=> CAST(:q AS vector))) as raw_similarity,
                           LEAST(1.0, ({$kwScoreSql}) + ({$unitScoreSql})) as keyword_score,
                           ((0.75 * (1 - (embedding <=> CAST(:q AS vector)))) + (0.25 * LEAST(1.0, ({$kwScoreSql}) + ({$unitScoreSql})))) as hybrid_score
                    FROM book_chunks
                    WHERE book_id = :book_id
                      AND embedding IS NOT NULL
                      AND (is_front_matter = false OR is_front_matter IS NULL)
                      AND (printed_page > 0 OR printed_page IS NULL)
                    ORDER BY hybrid_score DESC
                    LIMIT :limit
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':q', $literal);
                $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                foreach ($kwParams as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // Filter by threshold: at least one passage must meet the minimum relevance threshold
                $filtered = [];
                foreach ($rows as $r) {
                    $sim = floatval($r['raw_similarity'] ?? 0);
                    $hybrid = floatval($r['hybrid_score'] ?? 0);
                    // Accept if semantic similarity meets threshold or strong hybrid match
                    if ($sim >= self::SIMILARITY_THRESHOLD || $hybrid >= self::SIMILARITY_THRESHOLD) {
                        $r['similarity'] = $sim;
                        $filtered[] = $r;
                    }
                }

                if (!empty($filtered)) {
                    return $this->formatChunks($filtered);
                }

                // If nothing passed threshold, return empty! Never fall back to silent first N!
                return [];
            }

            // 5. Keyword-only fallback when vector embedding cannot be generated
            if (!empty($keywords)) {
                $kwScoreParts = ["0.0"];
                $kwParams = [':book_id' => $bookId];
                foreach ($keywords as $idx => $kw) {
                    $pName = ":kw_" . $idx;
                    $kwParams[$pName] = '%' . $kw . '%';
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pName} THEN 1.0 ELSE 0 END)";
                    $kwScoreParts[] = "(CASE WHEN chapter_title ILIKE {$pName} THEN 2.0 ELSE 0 END)";
                }
                $kwScoreSql = implode(' + ', $kwScoreParts);

                $sql = "
                    SELECT id, chapter_title as chapter, section_number as section, chunk_text as text,
                           pdf_page, printed_page, unit_title,
                           ({$kwScoreSql}) as score
                    FROM book_chunks
                    WHERE book_id = :book_id
                      AND (is_front_matter = false OR is_front_matter IS NULL)
                      AND (printed_page > 0 OR printed_page IS NULL)
                    HAVING ({$kwScoreSql}) > 0
                    ORDER BY score DESC, id ASC
                    LIMIT :limit
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                foreach ($kwParams as $k => $v) {
                    if ($k !== ':book_id') {
                        $stmt->bindValue($k, $v);
                    }
                }
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($rows)) {
                    foreach ($rows as &$r) {
                        $r['similarity'] = min(0.95, 0.5 + ($r['score'] * 0.1));
                    }
                    return $this->formatChunks($rows);
                }
            }

            // Strict no-result: return empty array
            return [];

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
            $pdfPage = isset($r['pdf_page']) ? intval($r['pdf_page']) : null;
            $printedPage = isset($r['printed_page']) ? intval($r['printed_page']) : null;

            // Normalize page references in chapter label
            if ($printedPage !== null && $printedPage > 0) {
                if ($pdfPage !== null && strpos($chapter, 'Printed Page') === false) {
                    $chapter = "Printed Page {$printedPage} (PDF p. {$pdfPage})";
                }
            } elseif (preg_match('/\[(?:Page|Chapter)\s*(\d+)\]/i', $text, $m)) {
                $chapter = "Page " . $m[1];
            }

            $sim = null;
            if (isset($r['similarity']) && $r['similarity'] !== null) {
                $sim = round((float)$r['similarity'], 4);
            } elseif (isset($r['raw_similarity']) && $r['raw_similarity'] !== null) {
                $sim = round((float)$r['raw_similarity'], 4);
            }

            $formatted[] = [
                'id' => $r['id'],
                'chapter' => $chapter,
                'section' => $r['section'] ?? 1,
                'text' => $text,
                'similarity' => $sim,
                'printed_page' => $printedPage,
                'pdf_page' => $pdfPage,
                'unit' => $r['unit_title'] ?? null
            ];
        }
        return $formatted;
    }
}
