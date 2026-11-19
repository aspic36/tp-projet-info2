<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class ProfileController
{
    /** @param array<string,mixed> $user */
    public function show(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::utilisateurs()->profil($user)]);
    }

    /** @param array<string,mixed> $user */
    public function update(Request $request, array $user): never
    {
        $payload = $request->json();
        $profil = Kernel::utilisateurs()->mettreAJourProfil(
            $user,
            Validator::requiredString($payload, 'prenom', 'Le prénom', 80),
            Validator::requiredString($payload, 'nom', 'Le nom', 80),
            Validator::email($payload, 'email', 'L’adresse e-mail'),
            Validator::optionalString($payload, 'telephone', 30)
        );
        Response::json(['data' => $profil]);
    }

    /** @param array<string,mixed> $user */
    public function motDePasse(Request $request, array $user): never
    {
        $payload = $request->json();
        Kernel::utilisateurs()->changerMotDePasse(
            $user,
            Validator::requiredString($payload, 'motDePasseActuel', 'Le mot de passe actuel', 200),
            Validator::requiredString($payload, 'nouveauMotDePasse', 'Le nouveau mot de passe', 200)
        );
        Response::noContent();
    }

    public function motDePasseOublie(Request $request): never
    {
        Kernel::utilisateurs()->demanderReinitialisation(
            Validator::email($request->json(), 'email', 'L’adresse e-mail')
        );
        Response::json(['message' => 'Si ce compte existe, un lien a été envoyé.']);
    }

    public function reinitialiser(Request $request): never
    {
        $payload = $request->json();
        Kernel::utilisateurs()->reinitialiser(
            Validator::requiredString($payload, 'token', 'Le jeton', 200),
            Validator::requiredString($payload, 'motDePasse', 'Le mot de passe', 200)
        );
        Response::json(['message' => 'Mot de passe mis à jour.']);
    }
}
