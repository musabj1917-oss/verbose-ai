<?php
<<<<<<< HEAD
/**
 * Verbose AI - Front Controller
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Static assets routing for PHP built-in server
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg)$/', $uri)) {
    return false;
}

if ($uri === '/api' || $uri === '/api.php') {
    require __DIR__ . '/api.php';
    exit;
}

if ($uri === '/login' || $uri === '/signup' || $uri === '/auth') {
    require __DIR__ . '/../templates/auth.php';
    exit;
}

// Render main reader page
=======
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg)$/', $uri)) { return false; }
if ($uri === '/api' || $uri === '/api.php') { require __DIR__ . '/api.php'; exit; }
if ($uri === '/login' || $uri === '/signup' || $uri === '/auth') { require __DIR__ . '/../templates/auth.php'; exit; }
>>>>>>> origin/main
require __DIR__ . '/../templates/reader.php';
