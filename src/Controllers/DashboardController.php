<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Kernel;

final class DashboardController
{
    /** @param array<string,mixed> $user */
    public function index(Request $request, array $user): never
    {
        Response::json(['data' => Kernel::tableauBord()->pour($user)]);
    }
}
