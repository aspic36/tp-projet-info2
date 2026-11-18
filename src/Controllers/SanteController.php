<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Request;
use App\Http\Response;
use App\Support\Log;

/**
 * Sonde de santé : 200 si MySQL répond, 503 sinon. Aucun secret exposé.
 */
final class SanteController
{
    public function index(Request $request): never
    {
        try {
            $pdo = Database::pdo();
            $pdo->query('SELECT 1');
            Response::json([
                'status'   => 'ok',
                'database' => 'ok',
                'php'      => PHP_VERSION,
                'heure'    => date('c'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sonde de santé : base injoignable.', ['erreur' => $e->getMessage()]);
            Response::json([
                'status'   => 'degraded',
                'database' => 'unreachable',
                'heure'    => date('c'),
            ], 503);
        }
    }
}
