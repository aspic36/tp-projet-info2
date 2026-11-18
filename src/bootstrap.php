<?php

declare(strict_types=1);

use App\Http\ApiException;
use App\Http\Response;
use App\Support\Config;
use App\Support\Env;
use App\Support\Log;

define('ROOT_PATH', dirname(__DIR__));

// 1. Autoload PSR-4 minimal (App\ → src/), sans Composer.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = ROOT_PATH . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 2. Configuration : .env local (ou fichier imposé par ENV_FILE pour les tests).
Env::load(getenv('ENV_FILE') ?: ROOT_PATH . '/.env');
Config::set(require ROOT_PATH . '/config/config.php');

// 3. Erreurs : jamais de détail technique dans la réponse HTTP.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('Africa/Porto-Novo');

set_exception_handler(static function (Throwable $e): never {
    if ($e instanceof ApiException) {
        Response::error($e->status(), $e->getMessage(), $e->details());
    }

    Log::error('Exception non gérée : ' . $e->getMessage(), [
        'type' => $e::class,
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);

    $debug = (bool) Config::get('app.debug', false);
    Response::error(500, 'Erreur interne du serveur.', $debug ? [
        'exception' => $e::class,
        'message'   => $e->getMessage(),
        'fichier'   => $e->getFile() . ':' . $e->getLine(),
    ] : []);
});

// 4. En-têtes de sécurité communs.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

// 5. Session : cookie HttpOnly, SameSite=Lax, identifiant strict.
$session = Config::get('session');
session_name((string) $session['name']);
session_set_cookie_params([
    'lifetime' => (int) $session['lifetime'],
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => false, // local uniquement ; à passer à true derrière HTTPS
]);
ini_set('session.use_strict_mode', '1');
session_start();

// 6. Routage.
return require ROOT_PATH . '/config/routes.php';
