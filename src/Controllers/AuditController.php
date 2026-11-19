<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Kernel;

final class AuditController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::auditRepository()->search(
            $request->query('action'),
            $request->query('dateDebut'),
            $request->query('dateFin'),
            $request->queryInt('page', 1),
            min(100, $request->queryInt('parPage', 25))
        )]);
    }
}
