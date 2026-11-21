<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/BookService.php';
require_once __DIR__ . '/../src/AIService.php';
$action = $_GET['action'] ?? '';
$bookService = new BookService();
$aiService = new AIService();
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
    echo json_encode(['success' => $result['success'] ?? true, 'answer' => $result['answer'] ?? '', 'provider' => $result['provider'] ?? 'Google AI Studio', 'citations' => $citations]); exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
