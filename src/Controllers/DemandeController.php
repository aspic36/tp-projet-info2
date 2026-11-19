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
        $resultat = Kernel::demandes()->lister(
            $user,
            $request->query('statut'),
            $request->query('q'),
            $request->queryInt('entrepriseId', 0) ?: null,
            $request->query('filiere'),
            $request->queryInt('annee', 0) ?: null,
            $request->queryInt('page', 1),
            min(100, $request->queryInt('parPage', 25))
        );
        Response::json($resultat);
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

        $demande = Kernel::demandes()->creer($user, $entrepriseId, $soumettre, $this->fiche($payload));
        Response::json(['data' => $demande], 201);
    }

    /** @param array<string,mixed> $user */
    public function update(Request $request, array $user): never
    {
        $payload = $request->json();
        $demande = Kernel::demandes()->modifier(
            $user,
            $request->integerParam('id'),
            Validator::positiveInt($payload, 'entrepriseId', 'L’entreprise'),
            $this->fiche($payload)
        );
        Response::json(['data' => $demande]);
    }

    /** @param array<string,mixed> $user */
    public function annuler(Request $request, array $user): never
    {
        $payload = $request->json();
        $raison = Validator::optionalString($payload, 'raison', 255);
        Response::json(['data' => Kernel::demandes()->annuler($user, $request->integerParam('id'), $raison)]);
    }

    /** @param array<string,mixed> $user */
    public function changerStatut(Request $request, array $user): never
    {
        $payload = $request->json();
        $cible = Validator::oneOf(
            $payload,
            'statut',
            ['BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE', 'ANNULEE'],
            'Le statut'
        );
        $motif = Validator::optionalString($payload, 'motif', 255);
        $commentaire = Validator::optionalString($payload, 'commentaire', 500);

        $demande = Kernel::demandes()->changerStatut(
            $user,
            $request->integerParam('id'),
            $cible,
            $motif,
            $commentaire
        );
        Response::json(['data' => $demande]);
    }

    /** @param array<string,mixed> $user */
    public function traiterLot(Request $request, array $user): never
    {
        $payload = $request->json();
        $resultat = Kernel::demandes()->changerStatutLot(
            $user,
            Validator::positiveIntList($payload, 'ids', 'Les demandes'),
            Validator::oneOf($payload, 'statut', ['VALIDEE', 'REFUSEE'], 'Le statut'),
            Validator::optionalString($payload, 'motif', 255),
            Validator::optionalString($payload, 'commentaire', 500)
        );
        Response::json(['data' => $resultat]);
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
    public function confirmerEncadreur(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::demandes()->confirmerEncadreur($user, $request->integerParam('id'))]);
    }

    /** @param array<string,mixed> $user */
    public function rapport(Request $request, array $user): never
    {
        $payload = $request->json();
        $demande = Kernel::demandes()->rapport(
            $user,
            $request->integerParam('id'),
            (bool) ($payload['valide'] ?? false),
            Validator::optionalString($payload, 'commentaire', 500)
        );
        Response::json(['data' => $demande]);
    }

    /** @param array<string,mixed> $user */
    public function noter(Request $request, array $user): never
    {
        $payload = $request->json();
        $demande = Kernel::demandes()->noter(
            $user,
            $request->integerParam('id'),
            Validator::decimal($payload, 'note', 'La note', 0, 20),
            Validator::optionalString($payload, 'mention', 40),
            Validator::optionalString($payload, 'appreciation', 2000)
        );
        Response::json(['data' => $demande]);
    }

    /** @param array<string,mixed> $user */
    public function commenter(Request $request, array $user): never
    {
        $payload = $request->json();
        $demande = Kernel::demandes()->commenter(
            $user,
            $request->integerParam('id'),
            Validator::requiredString($payload, 'contenu', 'Le commentaire', 2000)
        );
        Response::json(['data' => $demande], 201);
    }

    /** @param array<string,mixed> $user */
    public function historique(Request $request, array $user): never
    {
        $lignes = Kernel::demandes()->historiqueDe($user, $request->integerParam('id'));
        Response::json(['data' => $lignes]);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    private function fiche(array $payload): array
    {
        return [
            'sujet'            => Validator::optionalString($payload, 'sujet', 255),
            'missions'         => Validator::optionalString($payload, 'missions', 5000),
            'dateDebut'        => Validator::date($payload, 'dateDebut', 'La date de début', false),
            'dateFin'          => Validator::date($payload, 'dateFin', 'La date de fin', false),
            'tuteurEntreprise' => Validator::optionalString($payload, 'tuteurEntreprise', 100),
            'tuteurEmail'      => Validator::optionalEmail($payload, 'tuteurEmail', 'L’e-mail du tuteur'),
        ];
    }
}
