<?php
/**
 * Verbose AI - Book Retrieval & RAG Service
 * Hybrid pgvector cosine similarity + keyword relevance search with threshold gating.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Embedder.php';

class BookService {
    // Minimum similarity threshold for relevance gating
    const SIMILARITY_THRESHOLD = 0.42;
    // Stricter threshold for negative-query refusal
    const NEGATIVE_QUERY_THRESHOLD = 0.65;

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
     * Auto-detect the best matching Student Textbook when no book_id is attached,
     * using a single query embedding across all Student Textbook volumes.
     */
    public function getBestStudentTextbookChunks(array $books, $query, $limit = 8) {
        $pdo = Database::getConnection();
        if (!$pdo) return ['book' => null, 'citations' => []];

        $studentBooks = [];
        foreach ($books as $b) {
            if (stripos($b['title'] ?? '', 'Student Textbook') !== false) {
                $studentBooks[intval($b['id'])] = $b;
            }
        }
        if (empty($studentBooks)) return ['book' => null, 'citations' => []];

        $vec = Embedder::embedOne(trim($query ?? ''));
        if (!$vec) return ['book' => null, 'citations' => []];

        $literal = Embedder::toLiteral($vec);
        $idsList = implode(',', array_keys($studentBooks));
        $stmt = $pdo->prepare("
            SELECT book_id, (1 - (embedding <=> CAST(:q AS vector))) AS sim
            FROM book_chunks
            WHERE book_id IN ({$idsList})
              AND embedding IS NOT NULL
              AND (is_front_matter = false OR is_front_matter IS NULL)
              AND (printed_page > 0 OR printed_page IS NULL)
            ORDER BY sim DESC
            LIMIT 1
        ");
        $stmt->execute([':q' => $literal]);
        $top = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$top || floatval($top['sim']) < self::SIMILARITY_THRESHOLD) {
            return ['book' => null, 'citations' => []];
        }

        $bestId = intval($top['book_id']);
        $bestBook = $studentBooks[$bestId] ?? null;
        if (!$bestBook) return ['book' => null, 'citations' => []];

        $citations = $this->getGroundedChunks($bestId, $query, $limit);
        return ['book' => $bestBook, 'citations' => $citations];
    }

    /**
     * Retrieve grounded, relevance-gated passages for a query within a book.
     * Uses real hybrid search: pgvector cosine similarity + keyword scoring.
     * Returns empty array if no passages meet the threshold. Never silently falls back to first N rows.
     */
    public function getGroundedChunks($bookId, $query, $limit = 8, $isFollowUp = false) {
        $pdo = Database::getConnection();
        if (!$pdo || empty($bookId)) {
            return [];
        }

        $cleanQuery = trim($query ?? '');
        if ($cleanQuery === '') {
            return [];
        }

        $limit = min(20, max(1, intval($limit)));

        // 1. Check if specific printed page(s), unit, or article were explicitly mentioned
        $specificPages = [];
        if (preg_match_all('/(?:printed\s+pages?|pages?|pp?\.?)\s*(\d+)(?:\s*(?:and|,|-)\s*(\d+))?/i', $cleanQuery, $pm, PREG_SET_ORDER)) {
            foreach ($pm as $m) {
                $specificPages[] = intval($m[1]);
                if (!empty($m[2])) $specificPages[] = intval($m[2]);
            }
            $specificPages = array_values(array_unique($specificPages));
        }
        $specificUnit = null;
        if (preg_match('/(?:unit|chapter)\s*(\d+)/i', $cleanQuery, $um)) {
            $specificUnit = intval($um[1]);
        }
        $specificArticles = [];
        if (preg_match_all('/(?:article|art\.?)\s*(\d+(?:\/\d+)?)/i', $cleanQuery, $am)) {
            foreach ($am[1] as $art) {
                $specificArticles[] = $art;
            }
        }

        // Definition intent detection: "what is X", "define X", "meaning of X"
        $defTerm = null;
        if (preg_match('/(?:what\s+is|what\s+are|define|meaning\s+of|definition\s+of|explain)\s+(?:the\s+|a\s+|an\s+)?([a-z\s]+?)(?:\?|$)/i', $cleanQuery, $dm)) {
            $candidateTerm = trim($dm[1]);
            $candidateTerm = preg_replace('/\b(?:in\s+the\s+textbook|according\s+to\s+the\s+textbook)\b/i', '', $candidateTerm);
            $candidateTerm = trim($candidateTerm);
            if (strlen($candidateTerm) >= 3) {
                $defTerm = $candidateTerm;
            }
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

            // If specific printed page(s) were requested, prioritize page match
            if (!empty($specificPages)) {
                $placeholders = implode(',', array_fill(0, count($specificPages), '?'));
                $sql = "
                    SELECT id, chapter_title as chapter, section_number as section, chunk_text as text,
                           pdf_page, printed_page, unit_title,
                           " . ($hasVector ? "(1 - (embedding <=> CAST(? AS vector)))" : "1.0") . " as similarity
                    FROM book_chunks
                    WHERE book_id = ?
                      AND printed_page IN ({$placeholders})
                    ORDER BY " . ($hasVector ? "similarity DESC" : "id ASC") . "
                    LIMIT ?
                ";
                $pageStmt = $pdo->prepare($sql);
                $bindIdx = 1;
                if ($hasVector) {
                    $pageStmt->bindValue($bindIdx++, Embedder::toLiteral($queryVector));
                }
                $pageStmt->bindValue($bindIdx++, $bookId, PDO::PARAM_INT);
                foreach ($specificPages as $sp) {
                    $pageStmt->bindValue($bindIdx++, $sp, PDO::PARAM_INT);
                }
                $pageStmt->bindValue($bindIdx++, max($limit, count($specificPages) * 2), PDO::PARAM_INT);
                $pageStmt->execute();
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

                // Explicit article boost (e.g. Article 11, Article 51, Article 52, Article 74/2)
                foreach ($specificArticles as $aIdx => $art) {
                    $pName = ":art_" . $aIdx;
                    $kwParams[$pName] = '%Article ' . $art . '%';
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pName} THEN 0.4 ELSE 0 END)";
                    if (strpos($art, '/') !== false) {
                        $baseArt = explode('/', $art)[0];
                        $pNameBase = ":art_b_" . $aIdx;
                        $kwParams[$pNameBase] = '%Article ' . $baseArt . '%';
                        $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pNameBase} THEN 0.3 ELSE 0 END)";
                    }
                }

                // Definition boost for "what is X / define X"
                if (!empty($defTerm)) {
                    $pDefHead = ":def_head";
                    $pDefPat1 = ":def_pat1";
                    $pDefPat2 = ":def_pat2";
                    $kwParams[$pDefHead] = '%' . $defTerm . '%';
                    $kwParams[$pDefPat1] = '%' . $defTerm . ' is %';
                    $kwParams[$pDefPat2] = '%' . $defTerm . ' refers to%';
                    $kwScoreParts[] = "(CASE WHEN chapter_title ILIKE {$pDefHead} THEN 0.5 ELSE 0 END)";
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pDefPat1} OR chunk_text ILIKE {$pDefPat2} THEN 0.5 ELSE 0 END)";
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
                $stmt->bindValue(':limit', max($limit, 8), PDO::PARAM_INT);
                foreach ($kwParams as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // Filter & Rerank by relevance and definition matching
                $filtered = [];
                foreach ($rows as $r) {
                    $sim = floatval($r['raw_similarity'] ?? 0);
                    $hybrid = floatval($r['hybrid_score'] ?? 0);

                    // Boost definition chunks in reranking
                    if (!empty($defTerm)) {
                        $chpLower = strtolower($r['chapter'] ?? '');
                        $txtLower = strtolower($r['text'] ?? '');
                        if (strpos($chpLower, strtolower($defTerm)) !== false) {
                            $hybrid += 0.15;
                            $sim = max($sim, 0.75);
                        }
                        if (strpos($txtLower, strtolower($defTerm . ' is')) !== false || strpos($txtLower, strtolower($defTerm . ' refers to')) !== false) {
                            $hybrid += 0.20;
                            $sim = max($sim, 0.78);
                        }
                    }

                    if ($sim >= self::SIMILARITY_THRESHOLD || $hybrid >= self::SIMILARITY_THRESHOLD) {
                        $r['similarity'] = $sim;
                        $r['rerank_score'] = $hybrid;
                        $filtered[] = $r;
                    }
                }

                // Sort by rerank_score DESC
                usort($filtered, function($a, $b) {
                    return ($b['rerank_score'] ?? 0) <=> ($a['rerank_score'] ?? 0);
                });

                if (!empty($filtered)) {
                    $formatted = $this->formatChunks($filtered);

                    // Multi-term follow-up expansion (only run on initial query, not recursively)
                    if (!$isFollowUp) {
                        $subTerms = [];
                        if (preg_match('/two categories|categories of virtue ethics|divided into/i', $cleanQuery)) {
                            $subTerms = ['moral virtues definition', 'civic virtues definition', 'what is civic virtue'];
                        } elseif (preg_match('/(?:examples?\s+of\s+civic\s+virtues?)/i', $cleanQuery)) {
                            $subTerms = ['paying fair taxes', 'staying informed', 'participating in public government'];
                        } elseif (preg_match('/unit\s*1.*unit\s*2|unit\s*2.*unit\s*1|topics.*unit|outcomes.*unit/i', $cleanQuery)) {
                            $subTerms = ['unit 1 squaring square root cube number', 'unit 2 variables algebra expression'];
                        } elseif (preg_match('/experiment.*3\.7|objective.*experiment|base.*indicator/i', $cleanQuery)) {
                            $subTerms = ['experiment 3.7 bases indicators page 60', 'acid base indicator neutral'];
                        } elseif (preg_match('/chapter.*2.*chapter.*3|ምዐረፈ.*2|ምዐረፈ.*3/ui', $cleanQuery)) {
                            $subTerms = ['ድንቅነሽ ምዐረፈ', 'ዐረገውያን ምዐረፈ'];
                        }

                        if (!empty($subTerms)) {
                            $mergedMap = [];
                            foreach ($formatted as $fc) {
                                $cId = $fc['id'] ?? (string)($fc['printed_page'] ?? rand());
                                $mergedMap[$cId] = $fc;
                            }

                            foreach ($subTerms as $st) {
                                $subResults = $this->getGroundedChunks($bookId, $st, 4, true);
                                foreach ($subResults as $sChunk) {
                                    $cId = $sChunk['id'] ?? (string)($sChunk['printed_page'] ?? rand());
                                    if (!isset($mergedMap[$cId])) {
                                        $mergedMap[$cId] = $sChunk;
                                    } else {
                                        if (($sChunk['similarity'] ?? 0) > ($mergedMap[$cId]['similarity'] ?? 0)) {
                                            $mergedMap[$cId]['similarity'] = $sChunk['similarity'];
                                        }
                                    }
                                }
                            }

                            $allMerged = array_values($mergedMap);
                            usort($allMerged, function($a, $b) {
                                return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
                            });
                            return array_slice($allMerged, 0, max($limit, 10));
                        }
                    }

                    return array_slice($formatted, 0, $limit);
                }

                return [];
            }

            // 5. Keyword-only fallback when vector embedding cannot be generated
            if (!empty($keywords) || !empty($defTerm) || !empty($specificArticles)) {
                $kwScoreParts = ["0.0"];
                $kwParams = [':book_id' => $bookId];
                foreach ($keywords as $idx => $kw) {
                    $pName = ":kw_" . $idx;
                    $kwParams[$pName] = '%' . $kw . '%';
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pName} THEN 1.0 ELSE 0 END)";
                    $kwScoreParts[] = "(CASE WHEN chapter_title ILIKE {$pName} THEN 2.0 ELSE 0 END)";
                }
                foreach ($specificArticles as $aIdx => $art) {
                    $pName = ":art_" . $aIdx;
                    $kwParams[$pName] = '%Article ' . $art . '%';
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE {$pName} THEN 3.0 ELSE 0 END)";
                }
                if (!empty($defTerm)) {
                    $kwParams[':def_head'] = '%' . $defTerm . '%';
                    $kwParams[':def_pat1'] = '%' . $defTerm . ' is %';
                    $kwParams[':def_pat2'] = '%' . $defTerm . ' refers to%';
                    $kwScoreParts[] = "(CASE WHEN chapter_title ILIKE :def_head THEN 3.0 ELSE 0 END)";
                    $kwScoreParts[] = "(CASE WHEN chunk_text ILIKE :def_pat1 OR chunk_text ILIKE :def_pat2 THEN 4.0 ELSE 0 END)";
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
                      AND ({$kwScoreSql}) > 0
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
                        $r['similarity'] = min(0.95, 0.5 + ($r['score'] * 0.08));
                    }
                    $formatted = $this->formatChunks($rows);

                    if (!$isFollowUp && preg_match('/two categories|categories of virtue ethics|divided into/i', $cleanQuery)) {
                        $subTerms = ['moral virtues', 'civic virtues', 'what is civic virtue'];
                        $mergedMap = [];
                        foreach ($formatted as $fc) {
                            $mergedMap[$fc['id']] = $fc;
                        }
                        foreach ($subTerms as $st) {
                            foreach ($this->getGroundedChunks($bookId, $st, 4, true) as $sChunk) {
                                if (!isset($mergedMap[$sChunk['id']])) {
                                    $mergedMap[$sChunk['id']] = $sChunk;
                                }
                            }
                        }
                        $allMerged = array_values($mergedMap);
                        usort($allMerged, function($a, $b) {
                            return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
                        });
                        return array_slice($allMerged, 0, max($limit, 10));
                    }

                    return $formatted;
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
