<?php

declare(strict_types=1);

use App\Http\Request;

/**
 * Point d'entrée unique.
 * Avec le serveur intégré : `php -S 127.0.0.1:8000 -t public public/index.php`
 * Les fichiers statiques existants sont servis directement (return false).
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

if ($path === '/' || $path === '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/index.html');
    exit;
}

/** @var \App\Http\Router $router */
$router = require dirname(__DIR__) . '/src/bootstrap.php';
$router->dispatch(new Request());
