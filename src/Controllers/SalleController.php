<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class SalleController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::salles()->findAll()]);
    }

    /** @param array<string,mixed> $user */
    public function store(Request $request, array $user): never
    {
        $payload = $request->json();
        $id = Kernel::salles()->create(
            Validator::requiredString($payload, 'nom', 'Le nom de la salle', 40),
            Validator::optionalString($payload, 'batiment', 60),
            isset($payload['capacite']) ? Validator::positiveInt($payload, 'capacite', 'La capacité') : 30,
            (bool) ($payload['active'] ?? true)
        );
        Response::json(['data' => Kernel::salles()->findById($id)], 201);
    }

    /** @param array<string,mixed> $user */
    public function update(Request $request, array $user): never
    {
        $payload = $request->json();
        $id = $request->integerParam('id');
        if (Kernel::salles()->findById($id) === null) {
            Response::error(404, 'Salle introuvable.');
        }
        Kernel::salles()->update(
            $id,
            Validator::requiredString($payload, 'nom', 'Le nom de la salle', 40),
            Validator::optionalString($payload, 'batiment', 60),
            isset($payload['capacite']) ? Validator::positiveInt($payload, 'capacite', 'La capacité') : 30,
            (bool) ($payload['active'] ?? true)
        );
        Response::json(['data' => Kernel::salles()->findById($id)]);
    }
}
