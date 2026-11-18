<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Jeton anti-CSRF lié à la session : toute écriture doit le présenter.
 */
final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
