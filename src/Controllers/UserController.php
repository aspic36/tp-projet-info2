<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class UserController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::utilisateurs()->listerComptes($user)]);
    }

    /** @param array<string,mixed> $user */
    public function relances(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::utilisateurs()->relances($user)]);
    }

    /** @param array<string,mixed> $user */
    public function store(Request $request, array $user): never
    {
        $payload = $request->json();
        $role = Validator::oneOf(
            $payload,
            'role',
            ['ETUDIANT', 'SECRETARIAT', 'RESPONSABLE', 'ENCADREUR', 'ADMIN'],
            'Le rôle'
        );
        $data = [
            'role'          => $role,
            'email'         => Validator::email($payload, 'email', 'L’adresse e-mail'),
            'prenom'        => Validator::requiredString($payload, 'prenom', 'Le prénom', 80),
            'nom'           => Validator::requiredString($payload, 'nom', 'Le nom', 80),
            'telephone'     => Validator::optionalString($payload, 'telephone', 30),
            'motDePasse'    => Validator::optionalString($payload, 'motDePasse', 200) ?? 'Stage2026!',
            'matricule'     => Validator::optionalString($payload, 'matricule', 40),
            'filiere'       => Validator::optionalString($payload, 'filiere', 80),
            'promotion'     => Validator::optionalString($payload, 'promotion', 20),
            'grade'         => Validator::optionalString($payload, 'grade', 80),
            'departement'   => Validator::optionalString($payload, 'departement', 80),
            'chargeMax'     => Validator::optionalPositiveInt($payload, 'chargeMax', 'La charge maximale') ?? 5,
        ];
        if ($role === 'ETUDIANT') {
            foreach (['matricule', 'filiere', 'promotion'] as $cle) {
                if ($data[$cle] === null) {
                    throw new ApiException(400, 'Données invalides.', [$cle => 'Champ obligatoire pour un étudiant.']);
                }
            }
        }
        if ($role === 'ENCADREUR') {
            foreach (['grade', 'departement'] as $cle) {
                if ($data[$cle] === null) {
                    throw new ApiException(400, 'Données invalides.', [$cle => 'Champ obligatoire pour un encadreur.']);
                }
            }
        }
        $compte = Kernel::utilisateurs()->creerCompte($user, $data);
        Response::json(['data' => $compte], 201);
    }

    /** @param array<string,mixed> $user */
    public function actif(Request $request, array $user): never
    {
        $payload = $request->json();
        if (!array_key_exists('actif', $payload) || !is_bool($payload['actif'])) {
            throw new ApiException(400, 'Données invalides.', ['actif' => 'Booléen attendu.']);
        }
        Kernel::utilisateurs()->changerActif($user, $request->integerParam('id'), $payload['actif']);
        Response::noContent();
    }

    /** @param array<string,mixed> $user */
    public function importer(Request $request, array $user): never
    {
        $fichier = $request->file('fichier');
        if ($fichier === null) {
            throw new ApiException(400, 'Données invalides.', ['fichier' => 'Fichier CSV requis.']);
        }
        Response::json(['data' => Kernel::utilisateurs()->importerEtudiants($user, $fichier)]);
    }
}
