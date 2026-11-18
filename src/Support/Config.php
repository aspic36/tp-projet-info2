<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Accès en lecture à la configuration chargée au démarrage.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    /** @param array<string,mixed> $items */
    public static function set(array $items): void
    {
        self::$items = $items;
    }

    /** @return mixed */
    public static function get(string $path, mixed $default = null): mixed
    {
        $current = self::$items;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }
}
