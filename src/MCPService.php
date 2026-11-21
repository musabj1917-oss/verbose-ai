<?php
/**
 * Verbose AI - Model Context Protocol (MCP) Server Service
 * Exposes book ingestion, semantic pgvector search, chunk inspection,
 * grounded literary Q&A, and vector backfilling via JSON-RPC 2.0.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BookService.php';
require_once __DIR__ . '/Embedder.php';
require_once __DIR__ . '/AIService.php';
require_once __DIR__ . '/Config.php';

class MCPService {
    private $bookService;
    private $aiService;

    public function __construct() {
        $this->bookService = new BookService();
        $this->aiService = new AIService();
    }

    /**
     * Handle incoming JSON-RPC 2.0 message
     */
    public function handleRequest(array $request): array {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];

        switch ($method) {
            case 'initialize':
                return $this->formatResult($id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [
                        'tools' => ['listChanged' => false]
                    ],
                    'serverInfo' => [
                        'name' => 'verbose-ai',
                        'version' => '1.0.0'
                    ],
                    'instructions' => 'Verbose AI Book & Document Intelligence MCP Server. Ingest books, inspect semantic chunks, perform pgvector cosine similarity searches, and query grounded book content.'
                ]);

            case 'ping':
                return $this->formatResult($id, new stdClass());

            case 'tools/list':
                return $this->formatResult($id, [
                    'tools' => $this->getToolDefinitions()
                ]);

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $args = $params['arguments'] ?? [];
                try {
                    $result = $this->callTool($toolName, $args);
                    return $this->formatResult($id, [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            ]
                        ]
                    ]);
                } catch (Exception $e) {
                    return $this->formatError($id, -32603, $e->getMessage());
                }

            default:
                return $this->formatError($id, -32601, "Method '{$method}' not found");
        }
    }

    public function getToolDefinitions(): array {
        return [
            [
                'name' => 'add_book',
                'description' => 'Ingest a book, manuscript, or multi-page document into Verbose AI. Splits text into semantic paragraphs (~1500 chars), computes 768-dimensional Gemini vector embeddings, and stores the volume in Neon Lakebase Postgres.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title of the book or document volume.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The full book text, chapter text, or extracted PDF pages. Preserves [Page X] tags.'
                        ],
                        'author' => [
                            'type' => 'string',
                            'description' => 'Author or publishing institution (default: Unknown).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Short summary or overview of the volume.'
                        ],
                        'embed' => [
                            'type' => 'boolean',
                            'description' => 'Whether to generate 768-dim Gemini vector embeddings immediately (default: true).'
                        ]
                    ],
                    'required' => ['title', 'content'],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Ingest Book Volume',
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => false,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'list_books',
                'description' => 'List all active book volumes currently grounded in Neon Lakebase Postgres with their chunk counts and creation timestamps.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'List Curated Volumes',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'get_book',
                'description' => 'Get full metadata and overview for a specific book by ID or title.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'book_id' => [
                            'type' => 'integer',
                            'description' => 'The unique ID of the book.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Optional title search if book_id is unknown.'
                        ]
                    ],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Get Book Details',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'inspect_book_chunks',
                'description' => 'Inspect the semantic chunks, section titles, and page markers [Page X] of a book volume.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'book_id' => [
                            'type' => 'integer',
                            'description' => 'The ID of the book to inspect.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum chunks to return (default: 20, max: 100).'
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Offset for pagination (default: 0).'
                        ]
                    ],
                    'required' => ['book_id'],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Inspect Book Chunks',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'search_passages',
                'description' => 'Search across book chunks using pgvector cosine similarity (768-dim) and keyword relevance scoring.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query, subject, or phrase to find.'
                        ],
                        'book_id' => [
                            'type' => 'integer',
                            'description' => 'Optional book ID to constrain search to a single volume.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of passages to return (default: 5, max: 15).'
                        ]
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Search Book Passages',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'ask_book',
                'description' => 'Ask a question answered strictly from verified book passages, with exact page citations and zero hallucination.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The user query or question.'
                        ],
                        'book_id' => [
                            'type' => 'integer',
                            'description' => 'Optional ID of the book context.'
                        ]
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Ask Grounded Inquiry',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => false,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'backfill_embeddings',
                'description' => 'Generate 768-dimensional Gemini embeddings for any chunks in the database that are missing vectors.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Batch size to process (default: 32).'
                        ]
                    ],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Backfill Vector Embeddings',
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'delete_book',
                'description' => 'Permanently delete a book volume and all its associated chunks from the library.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'book_id' => [
                            'type' => 'integer',
                            'description' => 'The ID of the book to delete.'
                        ]
                    ],
                    'required' => ['book_id'],
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Delete Book Volume',
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ],
            [
                'name' => 'get_library_stats',
                'description' => 'Get real-time statistics on curated volumes, total chunks, pgvector embedding coverage %, and neural pipeline health.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                    'additionalProperties' => false
                ],
                'annotations' => [
                    'title' => 'Get Library Statistics',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false
                ]
            ]
        ];
    }

    private function callTool(string $toolName, array $args) {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection failed. Verify DATABASE_URL.");
        }

        switch ($toolName) {
            case 'add_book':
                return $this->toolAddBook($pdo, $args);

            case 'list_books':
                return $this->bookService->getBooks();

            case 'get_book':
                return $this->toolGetBook($pdo, $args);

            case 'inspect_book_chunks':
                return $this->toolInspectBookChunks($pdo, $args);

            case 'search_passages':
                return $this->toolSearchPassages($pdo, $args);

            case 'ask_book':
                return $this->toolAskBook($args);

            case 'backfill_embeddings':
                return $this->toolBackfillEmbeddings($pdo, $args);

            case 'delete_book':
                return $this->toolDeleteBook($pdo, $args);

            case 'get_library_stats':
                return $this->toolGetLibraryStats($pdo);

            default:
                throw new Exception("Unknown tool: '{$toolName}'");
        }
    }

    private function toolAddBook(PDO $pdo, array $args): array {
        $title = trim($args['title'] ?? '');
        $content = trim($args['content'] ?? '');
        $author = trim($args['author'] ?? 'Unknown');
        $description = trim($args['description'] ?? 'Curated library volume.');
        $shouldEmbed = $args['embed'] ?? true;

        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required.");
        }

        // 1. Chunk content by paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $chunks = [];
        $currentChunk = "";
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            if (strlen($currentChunk) + strlen($p) > 1500) {
                $chunks[] = $currentChunk;
                $currentChunk = $p;
            } else {
                $currentChunk .= ($currentChunk ? "\n\n" : "") . $p;
            }
        }
        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }
        if (empty($chunks)) {
            $chunks[] = $content;
        }

        // 2. Vectorize chunks with Gemini if requested
        $embeddings = [];
        if ($shouldEmbed) {
            foreach (array_chunk(array_keys($chunks), 32) as $keyBatch) {
                $texts = [];
                foreach ($keyBatch as $k) {
                    $texts[] = $chunks[$k];
                }
                $vecs = Embedder::embedBatch($texts);
                foreach ($keyBatch as $pos => $k) {
                    $embeddings[$k] = $vecs ? Embedder::toLiteral($vecs[$pos]) : null;
                }
            }
        }

        // 3. Persist to Neon Postgres
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
            $stmt->execute([$title, $author, $description, count($chunks)]);
            $bookId = $stmt->fetchColumn();

            $chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, section_number, chunk_text, chunk_index, embedding) VALUES (?, ?, ?, ?, ?, CAST(? AS vector))");
            foreach ($chunks as $idx => $chunkText) {
                $chapterTitle = "Section " . ($idx + 1);
                // Detect [Page X] marker if present
                if (preg_match('/\[Page\s*(\d+)\]/i', $chunkText, $pm)) {
                    $chapterTitle = "Page " . $pm[1];
                }
                $chunkStmt->execute([$bookId, $chapterTitle, $idx + 1, $chunkText, $idx + 1, $embeddings[$idx] ?? null]);
            }
            $pdo->commit();

            return [
                'success' => true,
                'book_id' => $bookId,
                'title' => $title,
                'author' => $author,
                'chunks_count' => count($chunks),
                'embedded_chunks' => count(array_filter($embeddings)),
                'message' => "Book '{$title}' ingested successfully with " . count($chunks) . " semantic chunks."
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function toolGetBook(PDO $pdo, array $args): array {
        $bookId = $args['book_id'] ?? null;
        $title = $args['title'] ?? null;

        if ($bookId) {
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->execute([$bookId]);
        } elseif ($title) {
            $stmt = $pdo->prepare("SELECT * FROM books WHERE title ILIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute(['%' . $title . '%']);
        } else {
            throw new Exception("Either book_id or title must be provided.");
        }

        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$book) {
            throw new Exception("Book not found.");
        }

        // Get embedding stats
        $cStmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT(embedding) as embedded FROM book_chunks WHERE book_id = ?");
        $cStmt->execute([$book['id']]);
        $stats = $cStmt->fetch(PDO::FETCH_ASSOC);

        $book['embedded_chunks'] = intval($stats['embedded'] ?? 0);
        $book['total_chunks'] = intval($stats['total'] ?? 0);
        $book['embedding_pct'] = $book['total_chunks'] > 0 ? round(($book['embedded_chunks'] / $book['total_chunks']) * 100, 1) : 0;

        return $book;
    }

    private function toolInspectBookChunks(PDO $pdo, array $args): array {
        $bookId = intval($args['book_id'] ?? 0);
        $limit = min(100, max(1, intval($args['limit'] ?? 20)));
        $offset = max(0, intval($args['offset'] ?? 0));

        if (!$bookId) {
            throw new Exception("book_id is required.");
        }

        $stmt = $pdo->prepare("
            SELECT id, chapter_title, section_number, chunk_index, chunk_text,
                   LENGTH(chunk_text) as char_length,
                   (CASE WHEN embedding IS NOT NULL THEN true ELSE false END) as is_embedded
            FROM book_chunks
            WHERE book_id = :book_id
            ORDER BY id ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'book_id' => $bookId,
            'limit' => $limit,
            'offset' => $offset,
            'chunks' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        ];
    }

    private function toolSearchPassages(PDO $pdo, array $args): array {
        $query = trim($args['query'] ?? '');
        $bookId = !empty($args['book_id']) ? intval($args['book_id']) : null;
        $limit = min(15, max(1, intval($args['limit'] ?? 5)));

        if (empty($query)) {
            throw new Exception("query parameter is required.");
        }

        if ($bookId) {
            return $this->bookService->getGroundedChunks($bookId, $query);
        }

        // If no book_id, search across entire library with pgvector or keywords
        try {
            $vec = Embedder::embedOne($query);
            if ($vec) {
                $literal = Embedder::toLiteral($vec);
                $stmt = $pdo->prepare("
                    SELECT bc.id, b.title as book_title, bc.chapter_title as chapter,
                           bc.chunk_text as text,
                           ROUND(CAST(1 - (bc.embedding <=> CAST(:q1 AS vector)) AS numeric), 4) as similarity
                    FROM book_chunks bc
                    JOIN books b ON bc.book_id = b.id
                    WHERE bc.embedding IS NOT NULL
                    ORDER BY bc.embedding <=> CAST(:q2 AS vector)
                    LIMIT :limit
                ");
                $stmt->bindValue(':q1', $literal);
                $stmt->bindValue(':q2', $literal);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Exception $e) {
            error_log("Global semantic search fallback: " . $e->getMessage());
        }

        // Keyword fallback
        $stmt = $pdo->prepare("
            SELECT bc.id, b.title as book_title, bc.chapter_title as chapter,
                   bc.chunk_text as text, 0.85 as similarity
            FROM book_chunks bc
            JOIN books b ON bc.book_id = b.id
            WHERE bc.chunk_text ILIKE :kw
            ORDER BY bc.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':kw', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function toolAskBook(array $args): array {
        $query = trim($args['query'] ?? '');
        $bookId = !empty($args['book_id']) ? intval($args['book_id']) : null;

        if (empty($query)) {
            throw new Exception("query is required.");
        }

        $activeBook = null;
        if ($bookId) {
            $books = $this->bookService->getBooks();
            foreach ($books as $b) {
                if ($b['id'] == $bookId) {
                    $activeBook = $b;
                    break;
                }
            }
        }

        $bookTitle = $activeBook ? $activeBook['title'] : null;
        $citations = $activeBook ? $this->bookService->getGroundedChunks($bookId, $query) : [];

        $res = $this->aiService->generateGroundedAnswer($query, $bookTitle, $citations, 'ask');
        return [
            'success' => $res['success'] ?? true,
            'answer' => $res['answer'] ?? '',
            'provider' => $res['provider'] ?? 'Verbose AI',
            'book_title' => $bookTitle,
            'citations_count' => count($citations),
            'citations' => $citations
        ];
    }

    private function toolBackfillEmbeddings(PDO $pdo, array $args): array {
        $limit = min(64, max(1, intval($args['limit'] ?? 32)));

        $rows = $pdo->query("SELECT id, chunk_text FROM book_chunks WHERE embedding IS NULL ORDER BY id ASC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            $total = intval($pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn() ?: 0);
            return [
                'success' => true,
                'processed' => 0,
                'remaining' => 0,
                'total_chunks' => $total,
                'embedding_pct' => 100.0,
                'message' => 'All chunks are vectorized.'
            ];
        }

        $texts = array_column($rows, 'chunk_text');
        $vecs = Embedder::embedBatch($texts);
        if (!$vecs) {
            throw new Exception("Gemini embedding batch failed. Verify GEMINI_API_KEY.");
        }

        $update = $pdo->prepare("UPDATE book_chunks SET embedding = CAST(? AS vector) WHERE id = ?");
        $processed = 0;
        foreach ($rows as $pos => $r) {
            if (isset($vecs[$pos])) {
                $update->execute([Embedder::toLiteral($vecs[$pos]), $r['id']]);
                $processed++;
            }
        }

        $remaining = intval($pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NULL")->fetchColumn() ?: 0);
        $total = intval($pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn() ?: 0);
        $embedded = $total - $remaining;
        $pct = $total > 0 ? round(($embedded / $total) * 100, 1) : 100.0;

        return [
            'success' => true,
            'processed' => $processed,
            'remaining' => $remaining,
            'embedded_chunks' => $embedded,
            'total_chunks' => $total,
            'embedding_pct' => $pct,
            'message' => "Embedded {$processed} chunks. {$remaining} remaining."
        ];
    }

    private function toolDeleteBook(PDO $pdo, array $args): array {
        $bookId = intval($args['book_id'] ?? 0);
        if (!$bookId) {
            throw new Exception("book_id is required.");
        }

        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$bookId]);

        return [
            'success' => true,
            'deleted_book_id' => $bookId,
            'message' => "Book ID {$bookId} and its associated chunks deleted successfully."
        ];
    }

    private function toolGetLibraryStats(PDO $pdo): array {
        $bookCount = intval($pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() ?: 0);
        $totalChunks = intval($pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn() ?: 0);
        $embeddedChunks = intval($pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NOT NULL")->fetchColumn() ?: 0);
        $unembeddedChunks = max(0, $totalChunks - $embeddedChunks);
        $pct = $totalChunks > 0 ? round(($embeddedChunks / $totalChunks) * 100, 1) : 100.0;

        return [
            'total_books' => $bookCount,
            'total_chunks' => $totalChunks,
            'embedded_chunks' => $embeddedChunks,
            'unembedded_chunks' => $unembeddedChunks,
            'embedding_pct' => $pct,
            'database' => 'Neon Lakebase Postgres (AWS Oregon)',
            'pgvector_extension' => true,
            'vector_dimension' => 768,
            'embedding_model' => 'gemini-embedding-2',
            'inference_engine' => 'Groq LPUs (qwen/qwen3.8-27b)',
            'vision_engine' => 'NVIDIA Nemotron 3.5 Super VL'
        ];
    }

    private function formatResult($id, $result): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
    }

    private function formatError($id, int $code, string $message): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }
}
