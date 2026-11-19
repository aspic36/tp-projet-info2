<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Requête HTTP entrante : méthode, chemin, paramètres, corps JSON et en-têtes.
 */
final class Request
{
    /** @var array<string,string> */
    private array $params = [];

    /** @var array<string,mixed>|null */
    private ?array $json = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $raw = file_get_contents('php://input') ?: '';
        if (trim($raw) === '') {
            return $this->json = [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ApiException(400, 'Corps JSON invalide.');
        }

        return $this->json = $decoded;
    }

    /** @return mixed */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json()[$key] ?? $default;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** @return mixed */
    public function post(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;
        return is_scalar($value) || is_array($value) ? $value : $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    public function setParam(string $key, string $value): void
    {
        $this->params[$key] = $value;
    }

    public function integerParam(string $key): int
    {
        $value = $this->param($key);
        if ($value === null || !preg_match('/^\d+$/', $value) || (int) $value < 1) {
            throw new ApiException(400, 'Identifiant invalide.');
        }
        return (int) $value;
    }

    public function queryInt(string $key, int $default, int $min = 1, ?int $max = null): int
    {
        $value = $this->query($key);
        if ($value === null || !preg_match('/^\d+$/', $value)) {
            return $default;
        }
        $nombre = (int) $value;
        if ($nombre < $min) {
            return $min;
        }
        if ($max !== null && $nombre > $max) {
            return $max;
        }
        return $nombre;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public function ip(): ?string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($value) ? $value : null;
    }

    public function userAgent(): ?string
    {
        $value = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($value) ? mb_substr($value, 0, 255) : null;
    }
}
