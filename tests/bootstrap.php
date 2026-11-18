<?php

declare(strict_types=1);

/**
 * Amorçage commun aux scripts de test en ligne de commande.
 * Ne démarre ni session ni routage : uniquement l'autoload et la configuration.
 */

use App\Support\Config;
use App\Support\Env;

define('ROOT_PATH', dirname(__DIR__));

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

Env::load(getenv('ENV_FILE') ?: ROOT_PATH . '/.env');
Config::set(require ROOT_PATH . '/config/config.php');
date_default_timezone_set('Africa/Porto-Novo');
