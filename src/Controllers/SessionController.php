<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;
use App\Support\Csrf;

/**
 * Session : jeton CSRF, connexion, déconnexion, utilisateur courant.
 */
final class SessionController
{
    public function show(Request $request): never
    {
        $user = Kernel::auth()->current();
        Response::json([
            'authenticated' => $user !== null,
            'user'          => $user,
            'csrfToken'     => Csrf::token(),
        ]);
    }

    public function login(Request $request): never
    {
        $email = Validator::requiredString($request->json(), 'email', 'L’adresse e-mail', 100);
        $password = Validator::requiredString($request->json(), 'motDePasse', 'Le mot de passe', 200);

        $user = Kernel::auth()->login($email, $password);

        Response::json([
            'user'      => $user,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function logout(Request $request): never
    {
        Kernel::auth()->logout();
        Response::noContent();
    }
}
