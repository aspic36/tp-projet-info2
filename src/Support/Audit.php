<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

final class Audit
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string,mixed> $details */
    public function log(
        ?int $utilisateurId,
        string $action,
        string $entite,
        ?int $entiteId = null,
        array $details = []
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO journal_audit
                    (utilisateur_id, action, entite, entite_id, details, adresse_ip, agent)
                 VALUES
                    (:utilisateur_id, :action, :entite, :entite_id, :details, :ip, :agent)'
            );
            $stmt->execute([
                ':utilisateur_id' => $utilisateurId,
                ':action'         => $action,
                ':entite'         => $entite,
                ':entite_id'      => $entiteId,
                ':details'        => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                ':ip'             => isset($_SERVER['REMOTE_ADDR']) ? mb_substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
                ':agent'          => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Audit impossible : ' . $e->getMessage(), ['action' => $action, 'entite' => $entite]);
        }
    }
}
