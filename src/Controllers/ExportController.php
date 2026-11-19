<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Kernel;

final class ExportController
{
    /** @param array<string,mixed> $user */
    public function demandesCsv(Request $request, array $user): never
    {
        $export = Kernel::exports()->demandesCsv($user);
        Response::download($export['contenu'], $export['nom'], $export['mime']);
    }

    /** @param array<string,mixed> $user */
    public function statistiquesCsv(Request $request, array $user): never
    {
        $export = Kernel::exports()->statistiquesCsv($user);
        Response::download($export['contenu'], $export['nom'], $export['mime']);
    }

    /** @param array<string,mixed> $user */
    public function demandesPdf(Request $request, array $user): never
    {
        $export = Kernel::exports()->demandesPdf($user);
        Response::download($export['contenu'], $export['nom'], $export['mime']);
    }

    /** @param array<string,mixed> $user */
    public function globalZip(Request $request, array $user): never
    {
        $export = Kernel::exports()->globalZip($user);
        Response::download($export['contenu'], $export['nom'], $export['mime']);
    }
}
