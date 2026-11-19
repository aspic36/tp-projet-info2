<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Kernel;

final class NotificationController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::notifications()->lister(
            (int) $user['id'],
            min(200, $request->queryInt('limite', 50))
        )]);
    }

    /** @param array<string,mixed> $user */
    public function lire(Request $request, array $user): never
    {
        Kernel::notifications()->marquerLue((int) $user['id'], $request->integerParam('id'));
        Response::noContent();
    }

    /** @param array<string,mixed> $user */
    public function lireTout(Request $request, array $user): never
    {
        Kernel::notifications()->marquerToutesLues((int) $user['id']);
        Response::noContent();
    }
}
