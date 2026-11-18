<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

/**
 * Soutenances : une par demande validée, pas deux au même créneau.
 */
final class SoutenanceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByDemande(int $demandeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, demande_id, date_heure, salle, duree_min
             FROM soutenance WHERE demande_id = :demande_id'
        );
        $stmt->execute([':demande_id' => $demandeId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['demande_id'] = (int) $row['demande_id'];
        $row['duree_min'] = (int) $row['duree_min'];
        return $row;
    }

    /**
     * @param array{etudiant_id?:int,encadreur_id?:int} $scope
     * @return array<int,array<string,mixed>>
     */
    public function search(array $scope): array
    {
        $where = [];
        $params = [];
        if (isset($scope['etudiant_id'])) {
            $where[] = 'd.etudiant_id = :etudiant_id';
            $params[':etudiant_id'] = $scope['etudiant_id'];
        }
        if (isset($scope['encadreur_id'])) {
            $where[] = 'd.encadreur_id = :encadreur_id';
            $params[':encadreur_id'] = $scope['encadreur_id'];
        }

        $sql = 'SELECT s.id, s.demande_id, s.date_heure, s.salle, s.duree_min,
                       en.raison_sociale AS entreprise,
                       u.prenom AS etudiant_prenom, u.nom AS etudiant_nom
                FROM soutenance s
                JOIN demande_stage d ON d.id = s.demande_id
                JOIN etudiant e      ON e.id = d.etudiant_id
                JOIN utilisateur u   ON u.id = e.utilisateur_id
                JOIN entreprise en   ON en.id = d.entreprise_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.date_heure';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @throws PDOException 1062 : doublon de demande ou de créneau.
     */
    public function create(int $demandeId, string $dateHeure, string $salle, int $dureeMin): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO soutenance (demande_id, date_heure, salle, duree_min)
             VALUES (:demande_id, :date_heure, :salle, :duree_min)'
        );
        $stmt->execute([
            ':demande_id' => $demandeId,
            ':date_heure' => $dateHeure,
            ':salle'      => $salle,
            ':duree_min'  => $dureeMin,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
