<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CommentaireRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $demandeId, int $auteurId, string $contenu, string $type = 'COMMENTAIRE'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO commentaire_demande (demande_id, auteur_id, type, contenu)
             VALUES (:demande_id, :auteur_id, :type, :contenu)'
        );
        $stmt->execute([
            ':demande_id' => $demandeId,
            ':auteur_id'  => $auteurId,
            ':type'       => $type,
            ':contenu'    => $contenu,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function findByDemande(int $demandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.demande_id, c.type, c.contenu, c.cree_le,
                    u.id AS auteur_id, u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.role AS auteur_role
             FROM commentaire_demande c
             JOIN utilisateur u ON u.id = c.auteur_id
             WHERE c.demande_id = :demande_id
             ORDER BY c.cree_le, c.id'
        );
        $stmt->execute([':demande_id' => $demandeId]);
        return $stmt->fetchAll();
    }
}
