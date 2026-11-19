<?php

declare(strict_types=1);

use App\Support\Env;

/**
 * Configuration de l'application.
 * Les valeurs sensibles viennent de .env ; ce fichier ne contient aucun secret.
 */
return [
    'app' => [
        'env'   => Env::get('APP_ENV', 'dev'),
        'debug' => Env::bool('APP_DEBUG', false),
        'url'   => Env::get('APP_URL', 'http://127.0.0.1:8000'),
    ],
    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'port'    => (int) Env::get('DB_PORT', '3306'),
        'name'    => Env::get('DB_NAME', 'stages'),
        'user'    => Env::get('DB_USER', 'stages_app'),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],
    'session' => [
        'name'     => 'STAGESESSID',
        'lifetime' => 3600,
    ],
];
