<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class SoutenanceController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::soutenances()->lister($user)]);
    }

    /** @param array<string,mixed> $user */
    public function store(Request $request, array $user): never
    {
        $payload = $request->json();
        $demandeId = Validator::positiveInt($payload, 'demandeId', 'La demande');
        $dateHeure = Validator::dateTime($payload, 'dateHeure', 'La date de soutenance');
        $salle = Validator::requiredString($payload, 'salle', 'La salle', 40);
        $duree = isset($payload['dureeMin'])
            ? Validator::positiveInt($payload, 'dureeMin', 'La durée')
            : 30;

        $soutenance = Kernel::soutenances()->programmer($user, $demandeId, $dateHeure, $salle, $duree);
        Response::json(['data' => $soutenance], 201);
    }
}
