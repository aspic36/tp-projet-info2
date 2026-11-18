<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EntrepriseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function findAll(): array
    {
        return $this->pdo
            ->query('SELECT id, raison_sociale, ville, email_contact FROM entreprise ORDER BY raison_sociale')
            ->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, raison_sociale, ville, email_contact FROM entreprise WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
