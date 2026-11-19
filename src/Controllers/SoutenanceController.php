<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class SoutenanceController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::soutenances()->lister(
            $user,
            $request->queryInt('annee', 0) ?: null,
            $request->queryInt('mois', 0) ?: null
        )]);
    }

    /** @param array<string,mixed> $user */
    public function planning(Request $request, array $user): never
    {
        $annee = $request->queryInt('annee', (int) date('Y'));
        $mois = $request->queryInt('mois', (int) date('n'), 1, 12);
        Response::json(['data' => Kernel::soutenances()->planningMensuel($user, $annee, $mois)]);
    }

    /** @param array<string,mixed> $user */
    public function show(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::soutenances()->detail($user, $request->integerParam('id'))]);
    }

    /** @param array<string,mixed> $user */
    public function store(Request $request, array $user): never
    {
        $payload = $request->json();
        $soutenance = Kernel::soutenances()->programmer(
            $user,
            Validator::positiveInt($payload, 'demandeId', 'La demande'),
            Validator::dateTime($payload, 'dateHeure', 'La date de soutenance'),
            Validator::optionalPositiveInt($payload, 'salleId', 'La salle'),
            Validator::optionalString($payload, 'salle', 40) ?? '',
            isset($payload['dureeMin']) ? Validator::positiveInt($payload, 'dureeMin', 'La durée') : 30,
            $this->jury($payload)
        );
        Response::json(['data' => $soutenance], 201);
    }

    /** @param array<string,mixed> $user */
    public function update(Request $request, array $user): never
    {
        $payload = $request->json();
        $soutenance = Kernel::soutenances()->modifier(
            $user,
            $request->integerParam('id'),
            Validator::dateTime($payload, 'dateHeure', 'La date de soutenance'),
            Validator::optionalPositiveInt($payload, 'salleId', 'La salle'),
            Validator::optionalString($payload, 'salle', 40) ?? '',
            isset($payload['dureeMin']) ? Validator::positiveInt($payload, 'dureeMin', 'La durée') : 30,
            $this->jury($payload)
        );
        Response::json(['data' => $soutenance]);
    }

    /** @param array<string,mixed> $user */
    public function convoquer(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::soutenances()->convoquer($user, $request->integerParam('id'))]);
    }

    /** @param array<string,mixed> $user */
    public function convocation(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::soutenances()->convocationTexte($user, $request->integerParam('id'))]);
    }

    /** @param array<string,mixed> $payload
     *  @return array<int,array{utilisateurId:int,role:string}>
     */
    private function jury(array $payload): array
    {
        $jury = $payload['jury'] ?? [];
        if (!is_array($jury)) {
            throw new ApiException(400, 'Données invalides.', ['jury' => 'Liste de jury attendue.']);
        }
        $membres = [];
        foreach ($jury as $membre) {
            if (!is_array($membre)) {
                throw new ApiException(400, 'Données invalides.', ['jury' => 'Membre invalide.']);
            }
            $membres[] = [
                'utilisateurId' => Validator::positiveInt($membre, 'utilisateurId', 'Le membre du jury'),
                'role'          => Validator::oneOf($membre, 'role', ['PRESIDENT', 'RAPPORTEUR', 'MEMBRE', 'ENCADREUR'], 'Le rôle'),
            ];
        }
        return $membres;
    }
}
