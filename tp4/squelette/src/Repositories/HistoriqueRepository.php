<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Journal des transitions de statut : preuve de qui a décidé quoi.
 */
final class HistoriqueRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(
        int $demandeId,
        ?string $statutAvant,
        string $statutApres,
        int $auteurId,
        ?string $motif = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO historique (demande_id, statut_avant, statut_apres, auteur_id, motif)
             VALUES (:demande_id, :statut_avant, :statut_apres, :auteur_id, :motif)'
        );
        $stmt->execute([
            ':demande_id'   => $demandeId,
            ':statut_avant' => $statutAvant,
            ':statut_apres' => $statutApres,
            ':auteur_id'    => $auteurId,
            ':motif'        => $motif,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function findByDemande(int $demandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.id, h.statut_avant, h.statut_apres, h.motif, h.horodatage,
                    u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.role AS auteur_role
             FROM historique h
             JOIN utilisateur u ON u.id = h.auteur_id
             WHERE h.demande_id = :demande_id
             ORDER BY h.horodatage, h.id'
        );
        $stmt->execute([':demande_id' => $demandeId]);
        return $stmt->fetchAll();
    }
}
