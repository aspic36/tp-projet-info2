<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Kernel;

final class DocumentController
{
    /** @param array<string,mixed> $user */
    public function generer(Request $request, array $user): never
    {
        $document = Kernel::documents()->generer(
            $user,
            $request->integerParam('id'),
            Validator::oneOf(
                ['type' => $request->query('type')],
                'type',
                ['convention', 'attestation', 'pv', 'convocation'],
                'Le document'
            ),
            Validator::oneOf(
                ['format' => $request->query('format', 'html')],
                'format',
                ['html', 'pdf'],
                'Le format'
            )
        );
        Response::download($document['contenu'], $document['nom'], $document['mime']);
    }
}
