<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class PieceController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::pieces()->lister($user, $request->integerParam('id'))]);
    }

    /** @param array<string,mixed> $user */
    public function upload(Request $request, array $user): never
    {
        $type = Validator::requiredString($_POST, 'type', 'Le type de pièce', 40);
        $fichier = $request->file('fichier');
        if ($fichier === null) {
            throw new \App\Http\ApiException(400, 'Données invalides.', ['fichier' => 'Fichier requis.']);
        }
        $piece = Kernel::pieces()->televerser($user, $request->integerParam('id'), $type, $fichier);
        Response::json(['data' => $piece], 201);
    }

    /** @param array<string,mixed> $user */
    public function telecharger(Request $request, array $user): never
    {
        $piece = Kernel::pieces()->telecharger($user, $request->integerParam('id'));
        Response::fichier(
            (string) $piece['chemin_absolu'],
            (string) $piece['nom_original'],
            (string) $piece['mime']
        );
    }

    /** @param array<string,mixed> $user */
    public function statut(Request $request, array $user): never
    {
        $payload = $request->json();
        $piece = Kernel::pieces()->changerStatut(
            $user,
            $request->integerParam('id'),
            Validator::oneOf($payload, 'statut', ['EN_ATTENTE', 'VALIDE', 'REJETE'], 'Le statut'),
            Validator::optionalString($payload, 'commentaire', 255)
        );
        Response::json(['data' => $piece]);
    }

    /** @param array<string,mixed> $user */
    public function supprimer(Request $request, array $user): never
    {
        Kernel::pieces()->supprimer($user, $request->integerParam('id'));
        Response::noContent();
    }
}
