<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class EntrepriseController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::entreprises()->lister($user, $request->query('statut'))]);
    }

    /** @param array<string,mixed> $user */
    public function proposer(Request $request, array $user): never
    {
        $entreprise = Kernel::entreprises()->proposer($user, $this->fiche($request->json()));
        Response::json(['data' => $entreprise], 201);
    }

    /** @param array<string,mixed> $user */
    public function store(Request $request, array $user): never
    {
        $payload = $request->json();
        $entreprise = Kernel::entreprises()->creer(
            $user,
            $this->fiche($payload),
            Validator::oneOf(
                $payload,
                'statut',
                ['PARTENAIRE', 'A_VALIDER', 'INTERDITE'],
                'Le statut'
            )
        );
        Response::json(['data' => $entreprise], 201);
    }

    /** @param array<string,mixed> $user */
    public function decider(Request $request, array $user): never
    {
        $payload = $request->json();
        $entreprise = Kernel::entreprises()->decider(
            $user,
            $request->integerParam('id'),
            Validator::oneOf($payload, 'statut', ['PARTENAIRE', 'INTERDITE'], 'Le statut'),
            Validator::optionalString($payload, 'motif', 255)
        );
        Response::json(['data' => $entreprise]);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    private function fiche(array $payload): array
    {
        return [
            'raisonSociale' => Validator::requiredString($payload, 'raisonSociale', 'La raison sociale', 120),
            'ville'         => Validator::requiredString($payload, 'ville', 'La ville', 80),
            'emailContact'  => Validator::optionalEmail($payload, 'emailContact', 'L’e-mail de contact'),
            'secteur'       => Validator::optionalString($payload, 'secteur', 80),
            'contactNom'    => Validator::optionalString($payload, 'contactNom', 100),
            'telephone'     => Validator::optionalString($payload, 'telephone', 30),
            'adresse'       => Validator::optionalString($payload, 'adresse', 255),
        ];
    }
}
