<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Kernel;

/**
 * Données de référence pour les formulaires (entreprises, encadreurs, comptes).
 */
final class ReferentielController
{
    /** @param array<string,mixed> $user */
    public function entreprises(Request $request, array $user): never
    {
        $repos = Kernel::referentiel();
        Response::json(['data' => $repos['entreprises']->findAll()]);
    }

    /** @param array<string,mixed> $user */
    public function encadreurs(Request $request, array $user): never
    {
        $repos = Kernel::referentiel();
        Response::json(['data' => $repos['utilisateurs']->listerEncadreurs()]);
    }

    /** @param array<string,mixed> $user */
    public function comptes(Request $request, array $user): never
    {
        $repos = Kernel::referentiel();
        Response::json(['data' => $repos['utilisateurs']->listerComptes()]);
    }
}
