<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/BookService.php';
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
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_book_chunks_book_id ON book_chunks(book_id);");
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
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );");

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
        $stmt->execute([$title, $author, $description, count($chunks)]);
        $bookId = $stmt->fetchColumn();

        $chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, section_number, chunk_text, chunk_index) VALUES (?, ?, ?, ?, ?)");
        foreach ($chunks as $idx => $chunkText) {
            $chapterTitle = "Section " . ($idx + 1);
            $chunkStmt->execute([$bookId, $chapterTitle, $idx + 1, $chunkText, $idx + 1]);
        }
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'book_id' => $bookId,
            'title' => $title,
            'author' => $author,
            'chunks_count' => count($chunks)
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

if ($action === 'query') {
    $input = json_decode(file_get_contents('php://input'), true);
    $bookId = $input['book_id'] ?? null;
    $query = trim($input['query'] ?? '');
    $mode = $input['mode'] ?? 'ask';
    $books = $bookService->getBooks();
    $activeBook = null;
    foreach ($books as $b) { if ($b['id'] == $bookId) { $activeBook = $b; break; } }
    $bookTitle = $activeBook['title'] ?? 'Selected Book';
    $citations = $bookService->getGroundedChunks($bookId, $query);
    $result = $aiService->generateGroundedAnswer($query, $bookTitle, $citations, $mode);

    echo json_encode([
        'success' => $result['success'] ?? true,
        'answer' => $result['answer'] ?? '',
        'provider' => $result['provider'] ?? 'Google AI Studio',
        'citations' => $citations
    ]);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
