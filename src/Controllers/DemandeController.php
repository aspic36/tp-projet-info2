<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

/**
 * Points d'entrée HTTP des demandes : aucune règle métier ni SQL ici.
 */
final class DemandeController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        $demandes = Kernel::demandes()->lister(
            $user,
            $request->query('statut'),
            $request->query('q')
        );
        Response::json(['data' => $demandes]);
    }

    /** @param array<string,mixed> $user */
    public function show(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::demandes()->detail($user, $request->integerParam('id'))]);
    }

    /** @param array<string,mixed> $user */
    public function store(Request $request, array $user): never
    {
        $payload = $request->json();
        $entrepriseId = Validator::positiveInt($payload, 'entrepriseId', 'L’entreprise');
        $soumettre = (bool) ($payload['soumettre'] ?? true);

        $demande = Kernel::demandes()->creer($user, $entrepriseId, $soumettre);
        Response::json(['data' => $demande], 201);
    }

    /** @param array<string,mixed> $user */
    public function changerStatut(Request $request, array $user): never
    {
        $payload = $request->json();
        $cible = Validator::oneOf($payload, 'statut', ['BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE'], 'Le statut');
        $motif = Validator::optionalString($payload, 'motif', 255);

        $demande = Kernel::demandes()->changerStatut($user, $request->integerParam('id'), $cible, $motif);
        Response::json(['data' => $demande]);
    }

    /** @param array<string,mixed> $user */
    public function affecterEncadreur(Request $request, array $user): never
    {
        $payload = $request->json();
        $encadreurId = Validator::positiveInt($payload, 'encadreurId', 'L’encadreur');

        $demande = Kernel::demandes()->affecterEncadreur($user, $request->integerParam('id'), $encadreurId);
        Response::json(['data' => $demande]);
    }

    /** @param array<string,mixed> $user */
    public function historique(Request $request, array $user): never
    {
        $lignes = Kernel::demandes()->historiqueDe($user, $request->integerParam('id'));
        Response::json(['data' => $lignes]);
    }
}
