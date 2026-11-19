<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Seule couche autorisée à écrire du SQL sur les demandes de stage.
 * Toutes les valeurs passent par des requêtes préparées.
 */
final class DemandeRepository
{
    private const SELECT_BASE = "
        SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id,
               d.statut, d.motif_refus, d.date_soumission, d.date_decision,
               e.matricule, e.filiere, e.promotion,
               u.prenom AS etudiant_prenom, u.nom AS etudiant_nom, u.email AS etudiant_email,
               en.raison_sociale AS entreprise, en.ville AS entreprise_ville,
               ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom
        FROM demande_stage d
        JOIN etudiant e      ON e.id = d.etudiant_id
        JOIN utilisateur u   ON u.id = e.utilisateur_id
        JOIN entreprise en   ON en.id = d.entreprise_id
        LEFT JOIN encadreur enc ON enc.id = d.encadreur_id
        LEFT JOIN utilisateur ue ON ue.id = enc.utilisateur_id
    ";

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE d.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    /** @return array<string,mixed>|null Verrouille la ligne jusqu'à la fin de la transaction. */
    public function findByIdForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE d.id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    /**
     * @param array{etudiant_id?:int,encadreur_id?:int} $scope
     * @return array<int,array<string,mixed>>
     */
    public function findByStudent(int $etudiantId): array
    {
        return $this->search(['etudiant_id' => $etudiantId]);
    }

    public function search(array $scope, ?string $statut = null, ?string $q = null): array
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
        if ($statut !== null && $statut !== '') {
            $where[] = 'd.statut = :statut';
            $params[':statut'] = $statut;
        }
        if ($q !== null && $q !== '') {
            $where[] = '(u.nom LIKE :q_nom OR u.prenom LIKE :q_prenom OR e.matricule LIKE :q_matricule OR en.raison_sociale LIKE :q_entreprise)';
            $motif = '%' . $q . '%';
            $params[':q_nom'] = $motif;
            $params[':q_prenom'] = $motif;
            $params[':q_matricule'] = $motif;
            $params[':q_entreprise'] = $motif;
        }

        $sql = self::SELECT_BASE;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(d.date_soumission, d.date_decision) DESC, d.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normaliser'], $stmt->fetchAll());
    }

    public function create(
        int $etudiantId,
        int $entrepriseId,
        string $statut,
        ?string $dateSoumission
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO demande_stage (etudiant_id, entreprise_id, statut, date_soumission)
             VALUES (:etudiant_id, :entreprise_id, :statut, :date_soumission)'
        );
        $stmt->execute([
            ':etudiant_id'     => $etudiantId,
            ':entreprise_id'   => $entrepriseId,
            ':statut'          => $statut,
            ':date_soumission' => $dateSoumission,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(
        int $id,
        string $statut,
        ?string $motif,
        ?string $dateSoumission,
        ?string $dateDecision
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage
             SET statut = :statut, motif_refus = :motif,
                 date_soumission = :date_soumission, date_decision = :date_decision
             WHERE id = :id'
        );
        $stmt->execute([
            ':statut'          => $statut,
            ':motif'           => $motif,
            ':date_soumission' => $dateSoumission,
            ':date_decision'   => $dateDecision,
            ':id'              => $id,
        ]);
    }

    public function affecterEncadreur(int $id, int $encadreurId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage SET encadreur_id = :encadreur_id WHERE id = :id'
        );
        $stmt->execute([':encadreur_id' => $encadreurId, ':id' => $id]);
    }

    /** @return array<string,mixed>|null */
    private function normaliser(array $row): array
    {
        foreach (['id', 'etudiant_id', 'entreprise_id', 'encadreur_id'] as $key) {
            if ($row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        return $row;
    }
}
