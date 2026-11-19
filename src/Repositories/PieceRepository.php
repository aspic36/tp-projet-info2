<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PieceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function findByDemande(int $demandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.demande_id, p.type, p.nom_original, p.mime, p.taille,
                    p.statut, p.commentaire, p.depose_le,
                    u.prenom AS depose_prenom, u.nom AS depose_nom
             FROM piece_jointe p
             JOIN utilisateur u ON u.id = p.depose_par
             WHERE p.demande_id = :demande_id
             ORDER BY p.depose_le DESC, p.id DESC'
        );
        $stmt->execute([':demande_id' => $demandeId]);
        return array_map([$this, 'normaliser'], $stmt->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, demande_id, type, nom_original, chemin, mime, taille, sha256,
                    statut, commentaire, depose_par, depose_le
             FROM piece_jointe WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    public function create(
        int $demandeId,
        string $type,
        string $nomOriginal,
        string $chemin,
        string $mime,
        int $taille,
        string $sha256,
        int $deposePar
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO piece_jointe
                (demande_id, type, nom_original, chemin, mime, taille, sha256, depose_par)
             VALUES
                (:demande_id, :type, :nom_original, :chemin, :mime, :taille, :sha256, :depose_par)'
        );
        $stmt->execute([
            ':demande_id'   => $demandeId,
            ':type'         => $type,
            ':nom_original' => $nomOriginal,
            ':chemin'       => $chemin,
            ':mime'         => $mime,
            ':taille'       => $taille,
            ':sha256'       => $sha256,
            ':depose_par'   => $deposePar,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function supprimer(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM piece_jointe WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function changerStatut(int $id, string $statut, ?string $commentaire): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE piece_jointe SET statut = :statut, commentaire = :commentaire WHERE id = :id'
        );
        $stmt->execute([':statut' => $statut, ':commentaire' => $commentaire, ':id' => $id]);
    }

    /** @param array<string,mixed> $row */
    private function normaliser(array $row): array
    {
        foreach (['id', 'demande_id', 'taille', 'depose_par'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        return $row;
    }
}
