<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Static assets handling (SVG, CSS, JS, Images, Favicon)
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg)$/', $uri)) {
    $staticFile = __DIR__ . $uri;
    if (file_exists($staticFile) && !is_dir($staticFile)) {
        $mimes = [
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'ico' => 'image/x-icon'
        ];
        $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=604800, immutable');
        readfile($staticFile);
        exit;
    }
    return false;
}

if ($uri === '/favicon.ico') {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=604800, immutable');
    readfile(__DIR__ . '/img/logo.svg');
    exit;
}

// API endpoints
if ($uri === '/api' || $uri === '/api.php') {
    require __DIR__ . '/api.php';
    exit;
}

require_once __DIR__ . '/../src/AuthService.php';

// MCP Server endpoints (Streamable HTTP / JSON-RPC 2.0)
if ($uri === '/mcp' || $uri === '/mcp.php' || $uri === '/api/mcp') {
    require __DIR__ . '/mcp.php';
    exit;
}

// Logout endpoint
if ($uri === '/logout') {
    AuthService::clearSession();
    header('Location: /login');
    exit;
}

// Dedicated Auth pages
if ($uri === '/login' || $uri === '/signup' || $uri === '/auth') {
    if (AuthService::getCurrentUser()) {
        header('Location: /');
        exit;
    }
    require __DIR__ . '/../templates/auth.php';
    exit;
}

$currentUser = AuthService::getCurrentUser();

// Admin Panel - requires verified session
if ($uri === '/admin' || $uri === '/admin.php') {
    if (!$currentUser) {
        header('Location: /login');
        exit;
    }
    require __DIR__ . '/../templates/admin.php';
    exit;
}

// Direct app/reader access - requires verified session
if ($uri === '/app' || $uri === '/reader') {
    if (!$currentUser) {
        header('Location: /login');
        exit;
    }
    require __DIR__ . '/../templates/reader.php';
    exit;
}

// Root entrypoint: FIRST page MUST be login if not authenticated
if (!$currentUser) {
    require __DIR__ . '/../templates/auth.php';
    exit;
}

// If authenticated, render reading canvas
require __DIR__ . '/../templates/reader.php';

