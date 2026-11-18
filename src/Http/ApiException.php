<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Erreur métier ou HTTP portant un code de statut et, si besoin, le détail
 * des champs invalides. Le message est destiné au client, jamais au debug.
 */
final class ApiException extends RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(
        private readonly int $status,
        string $message,
        private readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string,mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
