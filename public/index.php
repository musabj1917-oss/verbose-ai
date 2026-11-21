<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Static assets routing for PHP built-in server
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg)$/', $uri)) {
    return false;
}

// API endpoints
if ($uri === '/api' || $uri === '/api.php') {
    require __DIR__ . '/api.php';
    exit;
}

// Logout endpoint
if ($uri === '/logout') {
    setcookie('verbose_session', '', time() - 3600, '/');
    header('Location: /login');
    exit;
}

// Dedicated Auth pages
if ($uri === '/login' || $uri === '/signup' || $uri === '/auth') {
    require __DIR__ . '/../templates/auth.php';
    exit;
}

// Direct app/reader access - requires login
if ($uri === '/app' || $uri === '/reader') {
    if (empty($_COOKIE['verbose_session'])) {
        header('Location: /login');
        exit;
    }
    require __DIR__ . '/../templates/reader.php';
    exit;
}

// Root entrypoint: FIRST page MUST be login if not authenticated
if (empty($_COOKIE['verbose_session'])) {
    require __DIR__ . '/../templates/auth.php';
    exit;
}

// If authenticated, render reading canvas
require __DIR__ . '/../templates/reader.php';

