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

// Google OAuth initiate
if ($uri === '/auth/google' || $uri === '/login/google') {
    if (AuthService::getCurrentUser()) {
        header('Location: /');
        exit;
    }
    if (!AuthService::isGoogleConfigured()) {
        header('Location: /login?google_unconfigured=1');
        exit;
    }
    $authUrl = AuthService::getGoogleAuthUrl();
    if (!empty($authUrl)) {
        header('Location: ' . $authUrl);
        exit;
    }
    header('Location: /login?error=' . urlencode('Could not initialize Google OAuth session.'));
    exit;
}

// Google OAuth callback
if ($uri === '/auth/google/callback') {
    if (isset($_GET['error'])) {
        header('Location: /login?error=' . urlencode('Google Sign-In canceled or access denied: ' . $_GET['error']));
        exit;
    }
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if (empty($code) || empty($state)) {
        header('Location: /login?error=' . urlencode('Missing Google OAuth code or state parameter.'));
        exit;
    }
    $res = AuthService::handleGoogleCallback($code, $state);
    if ($res['success']) {
        header('Location: /app');
        exit;
    } else {
        header('Location: /login?error=' . urlencode($res['error'] ?? 'Google authentication failed.'));
        exit;
    }
}

// Logout endpoint
if ($uri === '/logout') {
    AuthService::clearSession();
    header('Location: /login');
    exit;
}

// Landing page for AI Model & VGPU Showcase
if ($uri === '/landing' || $uri === '/model' || $uri === '/showcase' || $uri === '/vgpu') {
    require __DIR__ . '/../templates/landing.php';
    exit;
}

// Dedicated Auth pages
if ($uri === '/login' || $uri === '/signup' || $uri === '/auth') {
    if (AuthService::getCurrentUser()) {
        header('Location: /app');
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

// Root entrypoint: showcase landing page if unauthenticated, reader if authenticated
if (!$currentUser) {
    require __DIR__ . '/../templates/landing.php';
    exit;
}

// If authenticated, render reading canvas
require __DIR__ . '/../templates/reader.php';

