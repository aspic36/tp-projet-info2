<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Réponses JSON normalisées : une seule forme pour toute l'API.
 */
final class Response
{
    /** @param array<string,mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<string,mixed> $details */
    public static function error(int $status, string $message, array $details = []): never
    {
        $payload = ['message' => $message];
        if ($details !== []) {
            $payload['details'] = $details;
        }
        self::json($payload, $status);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function html(string $contenu, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $contenu;
        exit;
    }

    public static function download(string $contenu, string $nom, string $mime): never
    {
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nom) ?: 'export';
        http_response_code(200);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('Content-Length: ' . (string) strlen($contenu));
        header('Cache-Control: no-store');
        echo $contenu;
        exit;
    }

    public static function fichier(string $chemin, string $nom, string $mime): never
    {
        if (!is_file($chemin)) {
            self::error(404, 'Fichier introuvable.');
        }
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nom) ?: 'document';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $nom . '"');
        header('Content-Length: ' . (string) filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($chemin);
        exit;
    }
}
