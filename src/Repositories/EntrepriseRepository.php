<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EntrepriseRepository
{
    private const CHAMPS = 'id, raison_sociale, ville, email_contact, secteur, contact_nom,
        telephone, adresse, statut, motif_statut, cree_par, date_creation';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function findAll(?string $statut = null): array
    {
        $sql = 'SELECT ' . self::CHAMPS . ' FROM entreprise';
        $params = [];
        if ($statut !== null && $statut !== '') {
            $sql .= ' WHERE statut = :statut';
            $params[':statut'] = $statut;
        }
        $sql .= ' ORDER BY raison_sociale, ville';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normaliser'], $stmt->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function findPartenaires(): array
    {
        return $this->findAll('PARTENAIRE');
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::CHAMPS . ' FROM entreprise WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    public function existe(string $raisonSociale, string $ville, ?int $saufId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM entreprise WHERE raison_sociale = :raison AND ville = :ville';
        $params = [':raison' => $raisonSociale, ':ville' => $ville];
        if ($saufId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $saufId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, ?int $creePar, string $statut): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO entreprise
                (raison_sociale, ville, email_contact, secteur, contact_nom, telephone, adresse, statut, cree_par)
             VALUES
                (:raison_sociale, :ville, :email_contact, :secteur, :contact_nom, :telephone, :adresse, :statut, :cree_par)'
        );
        $stmt->execute([
            ':raison_sociale' => $data['raisonSociale'],
            ':ville'          => $data['ville'],
            ':email_contact'  => $data['emailContact'] ?? null,
            ':secteur'        => $data['secteur'] ?? null,
            ':contact_nom'    => $data['contactNom'] ?? null,
            ':telephone'      => $data['telephone'] ?? null,
            ':adresse'        => $data['adresse'] ?? null,
            ':statut'         => $statut,
            ':cree_par'       => $creePar,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, ?string $motif): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE entreprise SET statut = :statut, motif_statut = :motif WHERE id = :id'
        );
        $stmt->execute([':statut' => $statut, ':motif' => $motif, ':id' => $id]);
    }

    /** @param array<string,mixed> $row */
    private function normaliser(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['cree_par'] = $row['cree_par'] === null ? null : (int) $row['cree_par'];
        return $row;
    }
}
