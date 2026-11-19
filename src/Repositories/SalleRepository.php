<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SalleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function findAll(bool $seulementActives = false): array
    {
        $sql = 'SELECT id, nom, batiment, capacite, active FROM salle';
        if ($seulementActives) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY nom';
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['capacite'] = (int) $row['capacite'];
            $row['active'] = (int) $row['active'] === 1;
            return $row;
        }, $this->pdo->query($sql)->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, batiment, capacite, active FROM salle WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['capacite'] = (int) $row['capacite'];
        $row['active'] = (int) $row['active'] === 1;
        return $row;
    }

    public function create(string $nom, ?string $batiment, int $capacite, bool $active = true): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO salle (nom, batiment, capacite, active)
             VALUES (:nom, :batiment, :capacite, :active)'
        );
        $stmt->execute([
            ':nom'      => $nom,
            ':batiment' => $batiment,
            ':capacite' => $capacite,
            ':active'   => $active ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $nom, ?string $batiment, int $capacite, bool $active): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE salle SET nom = :nom, batiment = :batiment, capacite = :capacite, active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            ':nom'      => $nom,
            ':batiment' => $batiment,
            ':capacite' => $capacite,
            ':active'   => $active ? 1 : 0,
            ':id'       => $id,
        ]);
    }
}
