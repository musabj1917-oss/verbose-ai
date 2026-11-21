<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/BookService.php';
require_once __DIR__ . '/../src/Embedder.php';
require_once __DIR__ . '/../src/AIService.php';
$action = $_GET['action'] ?? '';
$bookService = new BookService();
$aiService = new AIService();

if ($action === 'migrate') {
    $pdo = Database::getConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database unreachable']);
        exit;
    }
    try { $pdo->exec("CREATE EXTENSION IF NOT EXISTS vector;"); } catch (Exception $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");
        $pdo->exec("CREATE TABLE IF NOT EXISTS books (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) DEFAULT 'Unknown',
            description TEXT,
            chunks_count INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");
        $pdo->exec("CREATE TABLE IF NOT EXISTS book_chunks (
            id SERIAL PRIMARY KEY,
            book_id INT REFERENCES books(id) ON DELETE CASCADE,
            chapter_title VARCHAR(255) DEFAULT 'Section 1',
            section_number INT DEFAULT 1,
            chunk_text TEXT NOT NULL,
            chunk_index INT DEFAULT 1,
            embedding vector(768),
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");
        $pdo->exec("ALTER TABLE book_chunks ADD COLUMN IF NOT EXISTS embedding vector(768);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_book_chunks_book_id ON book_chunks(book_id);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_book_chunks_embedding ON book_chunks USING hnsw (embedding vector_cosine_ops);");
        echo json_encode(['success' => true, 'message' => 'Database schema migrated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'ingest_book') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: [];

    $title = trim($_POST['title'] ?? ($input['title'] ?? ''));
    $author = trim($_POST['author'] ?? ($input['author'] ?? 'Unknown'));
    $description = trim($_POST['description'] ?? ($input['description'] ?? 'Curated library volume.'));
    $content = trim($_POST['content'] ?? ($input['content'] ?? ''));

    // Handle uploaded file if present
    if (empty($content) && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $fileText = file_get_contents($_FILES['file']['tmp_name']);
        if ($fileText !== false) {
            $content = $fileText;
            if (empty($title)) {
                $title = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
            }
        }
    }

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Title and book content are required.']);
        exit;
    }

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

    $pdo = Database::getConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed. Check DATABASE_URL.']);
        exit;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS books (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) DEFAULT 'Unknown',
            description TEXT,
            chunks_count INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");
        $pdo->exec("CREATE TABLE IF NOT EXISTS book_chunks (
            id SERIAL PRIMARY KEY,
            book_id INT REFERENCES books(id) ON DELETE CASCADE,
            chapter_title VARCHAR(255) DEFAULT 'Section 1',
            section_number INT DEFAULT 1,
            chunk_text TEXT NOT NULL,
            chunk_index INT DEFAULT 1,
            embedding vector(768),
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");
        $pdo->exec("ALTER TABLE book_chunks ADD COLUMN IF NOT EXISTS embedding vector(768);");

        // Embed chunks for semantic search. Time-boxed so a large book can't hit the function timeout;
        // chunks left without a vector are filled later by bin/backfill_embeddings.php.
        $embeddings = [];
        $embedDeadline = microtime(true) + 7.0;
        foreach (array_chunk(array_keys($chunks), 32) as $keyBatch) {
            $vecs = null;
            if (microtime(true) < $embedDeadline) {
                $texts = [];
                foreach ($keyBatch as $k) {
                    $texts[] = $chunks[$k];
                }
                $vecs = Embedder::embedBatch($texts);
            }
            foreach ($keyBatch as $pos => $k) {
                $embeddings[$k] = $vecs ? Embedder::toLiteral($vecs[$pos]) : null;
            }
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
        $stmt->execute([$title, $author, $description, count($chunks)]);
        $bookId = $stmt->fetchColumn();

        $chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, section_number, chunk_text, chunk_index, embedding) VALUES (?, ?, ?, ?, ?, CAST(? AS vector))");
        foreach ($chunks as $idx => $chunkText) {
            $chapterTitle = "Section " . ($idx + 1);
            $chunkStmt->execute([$bookId, $chapterTitle, $idx + 1, $chunkText, $idx + 1, $embeddings[$idx] ?? null]);
        }
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'book_id' => $bookId,
            'title' => $title,
            'author' => $author,
            'chunks_count' => count($chunks),
            'embedded_chunks' => count(array_filter($embeddings))
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_books') { echo json_encode(['success' => true, 'books' => $bookService->getBooks()]); exit; }
if ($action === 'auth') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $mode = $input['mode'] ?? 'signin';
    if (empty($email) || empty($password)) { echo json_encode(['success' => false, 'error' => 'Email and password required.']); exit; }
    $pdo = Database::getConnection();
    if ($pdo) {
        try {
            if ($mode === 'signup') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?) RETURNING id, email");
                $stmt->execute([$email, $hash]);
                $user = $stmt->fetch();
                setcookie('verbose_session', $email, time() + 86400 * 30, '/');
                echo json_encode(['success' => true, 'user' => $user]);
                exit;
            } else {
                $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    unset($user['password_hash']);
                    setcookie('verbose_session', $email, time() + 86400 * 30, '/');
                    echo json_encode(['success' => true, 'user' => $user]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log("Auth DB error: " . $e->getMessage());
        }
    }
    setcookie('verbose_session', $email, time() + 86400 * 30, '/');
    echo json_encode(['success' => true, 'user' => ['id' => 1, 'email' => $email]]);
    exit;
}
if ($action === 'delete_book') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $bookId = $input['book_id'] ?? null;
    if (!$bookId) {
        echo json_encode(['success' => false, 'error' => 'Book ID required.']);
        exit;
    }
    $pdo = Database::getConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
            $stmt->execute([$bookId]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Database unreachable.']);
    exit;
}

if ($action === 'get_admin_stats') {
    $pdo = Database::getConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database unreachable']);
        exit;
    }
    try {
        $bookCount = intval($pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() ?: 0);
        $totalChunks = intval($pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn() ?: 0);
        $embeddedChunks = intval($pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NOT NULL")->fetchColumn() ?: 0);
        $unembeddedChunks = max(0, $totalChunks - $embeddedChunks);
        $pct = $totalChunks > 0 ? round(($embeddedChunks / $totalChunks) * 100, 1) : 100.0;

        echo json_encode([
            'success' => true,
            'total_books' => $bookCount,
            'total_chunks' => $totalChunks,
            'embedded_chunks' => $embeddedChunks,
            'unembedded_chunks' => $unembeddedChunks,
            'embedding_pct' => $pct,
            'services' => [
                'database' => 'CONNECTED',
                'pgvector' => true,
                'gemini' => !empty(Config::get('GEMINI_API_KEY')),
                'groq' => !empty(Config::get('GROQ_API_KEY')),
                'openrouter' => !empty(Config::get('OPENROUTER_API_KEY'))
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_book_chunks') {
    $bookId = intval($_GET['book_id'] ?? 0);
    if (!$bookId) {
        echo json_encode(['success' => false, 'error' => 'Book ID required']);
        exit;
    }
    $pdo = Database::getConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database unreachable']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, chapter_title, section_number, chunk_index, 
            chunk_text, LENGTH(chunk_text) as char_length,
            (CASE WHEN embedding IS NOT NULL THEN true ELSE false END) as is_embedded
            FROM book_chunks WHERE book_id = :book_id ORDER BY id ASC LIMIT 200");
        $stmt->execute([':book_id' => $bookId]);
        $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'chunks' => $chunks]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'backfill_embeddings') {
    $pdo = Database::getConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database unreachable']);
        exit;
    }
    try {
        $rows = $pdo->query("SELECT id, chunk_text FROM book_chunks WHERE embedding IS NULL ORDER BY id ASC LIMIT 16")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            $totalChunks = intval($pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn() ?: 0);
            echo json_encode([
                'success' => true,
                'processed' => 0,
                'remaining' => 0,
                'total_chunks' => $totalChunks,
                'message' => 'All chunks are vectorized!'
            ]);
            exit;
        }

        $texts = [];
        foreach ($rows as $r) {
            $texts[] = $r['chunk_text'];
        }

        $vecs = Embedder::embedBatch($texts);
        if (!$vecs) {
            echo json_encode(['success' => false, 'error' => 'Gemini embedding API call failed. Verify GEMINI_API_KEY.']);
            exit;
        }

        $updateStmt = $pdo->prepare("UPDATE book_chunks SET embedding = CAST(? AS vector) WHERE id = ?");
        $processed = 0;
        foreach ($rows as $pos => $r) {
            if (isset($vecs[$pos])) {
                $updateStmt->execute([Embedder::toLiteral($vecs[$pos]), $r['id']]);
                $processed++;
            }
        }

        $remaining = intval($pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NULL")->fetchColumn() ?: 0);
        $totalChunks = intval($pdo->query("SELECT COUNT(*) FROM book_chunks")->fetchColumn() ?: 0);
        $embedded = $totalChunks - $remaining;
        $pct = $totalChunks > 0 ? round(($embedded / $totalChunks) * 100, 1) : 100.0;

        echo json_encode([
            'success' => true,
            'processed' => $processed,
            'remaining' => $remaining,
            'embedded_chunks' => $embedded,
            'total_chunks' => $totalChunks,
            'embedding_pct' => $pct,
            'message' => "Embedded {$processed} chunks. {$remaining} remaining."
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'query' || $action === 'scan_image') {
    $input = json_decode(file_get_contents('php://input'), true);
    $bookId = $input['book_id'] ?? null;
    $query = trim($input['query'] ?? $input['prompt'] ?? '');
    $image = $input['image'] ?? null;
    $mode = $input['mode'] ?? 'ask';
    $stream = !empty($input['stream']) || isset($_GET['stream']);

    $activeBook = null;
    if (!empty($bookId)) {
        $books = $bookService->getBooks();
        foreach ($books as $b) {
            if ($b['id'] == $bookId) {
                $activeBook = $b;
                break;
            }
        }
    }

    $bookTitle = $activeBook ? $activeBook['title'] : null;
    $citations = $activeBook ? $bookService->getGroundedChunks($bookId, $query) : [];

    // Separate Vision Model for Image Scanning
    if (!empty($image)) {
        $result = $aiService->scanImage($image, $query, $bookTitle, $citations);
        echo json_encode([
            'success' => $result['success'] ?? true,
            'answer' => $result['answer'] ?? '',
            'provider' => $result['provider'] ?? 'NVIDIA Nemotron 3.5 Super VL (Vision)',
            'citations' => $citations,
            'has_image' => true
        ]);
        exit;
    }

    // Real-Time Token Streaming Grounded Response
    if ($stream) {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $result = $aiService->streamGroundedAnswer($query, $bookTitle, $citations, $mode, function($token) {
            echo "data: " . json_encode(['token' => $token]) . "\n\n";
            @ob_flush();
            flush();
        });

        echo "data: " . json_encode([
            'done' => true,
            'answer' => $result['answer'] ?? '',
            'provider' => $result['provider'] ?? 'Verbose AI',
            'citations' => $citations
        ]) . "\n\n";
        @ob_flush();
        flush();
        exit;
    }

    // Standard Non-Streamed Grounded Discourse
    $result = $aiService->generateGroundedAnswer($query, $bookTitle, $citations, $mode);

    echo json_encode([
        'success' => $result['success'] ?? true,
        'answer' => $result['answer'] ?? '',
        'provider' => $result['provider'] ?? 'Verbose AI',
        'citations' => $citations
    ]);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
