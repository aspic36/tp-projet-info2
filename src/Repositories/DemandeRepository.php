<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DemandeRepository
{
    private const SELECT_BASE = "
        SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id, d.encadreur_propose_id,
               d.statut, d.motif_refus, d.commentaire_decision, d.date_soumission, d.date_decision,
               d.sujet, d.missions, d.date_debut, d.date_fin, d.tuteur_entreprise, d.tuteur_email,
               d.date_proposition, d.rapport_valide, d.rapport_valide_le, d.rapport_valide_par,
               d.note, d.mention, d.appreciation,
               e.matricule, e.filiere, e.promotion,
               u.id AS etudiant_utilisateur_id,
               u.prenom AS etudiant_prenom, u.nom AS etudiant_nom, u.email AS etudiant_email,
               en.raison_sociale AS entreprise, en.ville AS entreprise_ville,
               en.statut AS entreprise_statut, en.email_contact AS entreprise_email,
               ue.id AS encadreur_utilisateur_id,
               ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
               up.id AS propose_utilisateur_id,
               up.prenom AS propose_prenom, up.nom AS propose_nom,
               (SELECT COUNT(*) FROM piece_jointe p WHERE p.demande_id = d.id) AS nb_pieces,
               (SELECT COUNT(*) FROM commentaire_demande c WHERE c.demande_id = d.id) AS nb_commentaires
        FROM demande_stage d
        JOIN etudiant e      ON e.id = d.etudiant_id
        JOIN utilisateur u   ON u.id = e.utilisateur_id
        JOIN entreprise en   ON en.id = d.entreprise_id
        LEFT JOIN encadreur enc ON enc.id = d.encadreur_id
        LEFT JOIN utilisateur ue ON ue.id = enc.utilisateur_id
        LEFT JOIN encadreur encp ON encp.id = d.encadreur_propose_id
        LEFT JOIN utilisateur up ON up.id = encp.utilisateur_id
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

    /** @return array<string,mixed>|null */
    public function findByIdForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE d.id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    /** @return array<int,array<string,mixed>> */
    public function findByStudent(int $etudiantId): array
    {
        return $this->search(['etudiant_id' => $etudiantId]);
    }

    /**
     * @param array{etudiant_id?:int,encadreur_id?:int} $scope
     * @return array<int,array<string,mixed>>
     */
    public function search(
        array $scope,
        ?string $statut = null,
        ?string $q = null,
        ?int $entrepriseId = null,
        ?string $filiere = null,
        ?int $annee = null
    ): array {
        $resultat = $this->searchPage($scope, $statut, $q, $entrepriseId, $filiere, $annee, 1, 10000);
        return $resultat['data'];
    }

    /**
     * @param array{etudiant_id?:int,encadreur_id?:int} $scope
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,par_page:int}
     */
    public function searchPage(
        array $scope,
        ?string $statut,
        ?string $q,
        ?int $entrepriseId,
        ?string $filiere,
        ?int $annee,
        int $page,
        int $parPage
    ): array {
        [$where, $params] = $this->filtres($scope, $statut, $q, $entrepriseId, $filiere, $annee);
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM demande_stage d' . $this->jointuresCount() . $clause);
        foreach ($params as $key => $value) {
            $count->bindValue($key, $value);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();

        $sql = self::SELECT_BASE . $clause
            . ' ORDER BY COALESCE(d.date_soumission, d.date_decision, d.id) DESC, d.id DESC'
            . ' LIMIT :limite OFFSET :decalage';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $parPage, PDO::PARAM_INT);
        $stmt->bindValue(':decalage', ($page - 1) * $parPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'     => array_map([$this, 'normaliser'], $stmt->fetchAll()),
            'total'    => $total,
            'page'     => $page,
            'par_page' => $parPage,
        ];
    }

    private function jointuresCount(): string
    {
        return ' JOIN etudiant e ON e.id = d.etudiant_id
                 JOIN utilisateur u ON u.id = e.utilisateur_id
                 JOIN entreprise en ON en.id = d.entreprise_id';
    }

    /**
     * @param array{etudiant_id?:int,encadreur_id?:int} $scope
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private function filtres(
        array $scope,
        ?string $statut,
        ?string $q,
        ?int $entrepriseId,
        ?string $filiere,
        ?int $annee
    ): array {
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
        if ($entrepriseId !== null && $entrepriseId > 0) {
            $where[] = 'd.entreprise_id = :entreprise_id';
            $params[':entreprise_id'] = $entrepriseId;
        }
        if ($filiere !== null && $filiere !== '') {
            $where[] = 'e.filiere = :filiere';
            $params[':filiere'] = $filiere;
        }
        if ($annee !== null && $annee > 0) {
            $where[] = 'YEAR(COALESCE(d.date_soumission, d.date_decision)) = :annee';
            $params[':annee'] = $annee;
        }
        if ($q !== null && $q !== '') {
            $where[] = '(u.nom LIKE :q_nom OR u.prenom LIKE :q_prenom OR e.matricule LIKE :q_matricule
                         OR en.raison_sociale LIKE :q_entreprise OR d.sujet LIKE :q_sujet)';
            $motif = '%' . $q . '%';
            $params[':q_nom'] = $motif;
            $params[':q_prenom'] = $motif;
            $params[':q_matricule'] = $motif;
            $params[':q_entreprise'] = $motif;
            $params[':q_sujet'] = $motif;
        }

        return [$where, $params];
    }

    /** @param array<string,mixed> $data */
    public function create(int $etudiantId, int $entrepriseId, string $statut, ?string $dateSoumission, array $data = []): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO demande_stage
                (etudiant_id, entreprise_id, statut, date_soumission, sujet, missions,
                 date_debut, date_fin, tuteur_entreprise, tuteur_email)
             VALUES
                (:etudiant_id, :entreprise_id, :statut, :date_soumission, :sujet, :missions,
                 :date_debut, :date_fin, :tuteur_entreprise, :tuteur_email)'
        );
        $stmt->execute([
            ':etudiant_id'       => $etudiantId,
            ':entreprise_id'     => $entrepriseId,
            ':statut'            => $statut,
            ':date_soumission'   => $dateSoumission,
            ':sujet'             => $data['sujet'] ?? null,
            ':missions'          => $data['missions'] ?? null,
            ':date_debut'        => $data['dateDebut'] ?? null,
            ':date_fin'          => $data['dateFin'] ?? null,
            ':tuteur_entreprise' => $data['tuteurEntreprise'] ?? null,
            ':tuteur_email'      => $data['tuteurEmail'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function updateDetails(int $id, int $entrepriseId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage SET
                entreprise_id = :entreprise_id, sujet = :sujet, missions = :missions,
                date_debut = :date_debut, date_fin = :date_fin,
                tuteur_entreprise = :tuteur_entreprise, tuteur_email = :tuteur_email
             WHERE id = :id'
        );
        $stmt->execute([
            ':entreprise_id'     => $entrepriseId,
            ':sujet'             => $data['sujet'] ?? null,
            ':missions'          => $data['missions'] ?? null,
            ':date_debut'        => $data['dateDebut'] ?? null,
            ':date_fin'          => $data['dateFin'] ?? null,
            ':tuteur_entreprise' => $data['tuteurEntreprise'] ?? null,
            ':tuteur_email'      => $data['tuteurEmail'] ?? null,
            ':id'                => $id,
        ]);
    }

    public function updateStatus(
        int $id,
        string $statut,
        ?string $motif,
        ?string $dateSoumission,
        ?string $dateDecision,
        ?string $commentaire = null
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage
             SET statut = :statut, motif_refus = :motif, commentaire_decision = :commentaire,
                 date_soumission = :date_soumission, date_decision = :date_decision
             WHERE id = :id'
        );
        $stmt->execute([
            ':statut'          => $statut,
            ':motif'           => $motif,
            ':commentaire'     => $commentaire,
            ':date_soumission' => $dateSoumission,
            ':date_decision'   => $dateDecision,
            ':id'              => $id,
        ]);
    }

    public function affecterEncadreur(int $id, int $encadreurId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage
             SET encadreur_id = :encadreur_id, encadreur_propose_id = NULL, date_proposition = NULL
             WHERE id = :id'
        );
        $stmt->execute([':encadreur_id' => $encadreurId, ':id' => $id]);
    }

    public function proposerEncadreur(int $id, int $encadreurId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage
             SET encadreur_propose_id = :encadreur_id, date_proposition = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':encadreur_id' => $encadreurId, ':id' => $id]);
    }

    public function confirmerEncadreur(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage
             SET encadreur_id = encadreur_propose_id, encadreur_propose_id = NULL, date_proposition = NULL
             WHERE id = :id AND encadreur_propose_id IS NOT NULL'
        );
        $stmt->execute([':id' => $id]);
    }

    public function updateRapport(int $id, bool $valide, int $parId, ?string $commentaire): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage
             SET rapport_valide = :valide,
                 rapport_valide_le = CASE WHEN :valide2 = 1 THEN NOW() ELSE NULL END,
                 rapport_valide_par = :par_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':valide'  => $valide ? 1 : 0,
            ':valide2' => $valide ? 1 : 0,
            ':par_id'  => $parId,
            ':id'      => $id,
        ]);
    }

    public function updateNote(int $id, float $note, ?string $mention, ?string $appreciation): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demande_stage SET note = :note, mention = :mention, appreciation = :appreciation WHERE id = :id'
        );
        $stmt->execute([
            ':note'         => $note,
            ':mention'      => $mention,
            ':appreciation' => $appreciation,
            ':id'           => $id,
        ]);
    }

    public function findActiveDuplicate(int $etudiantId, int $entrepriseId, ?int $saufId = null): ?int
    {
        $sql = "SELECT id FROM demande_stage
                WHERE etudiant_id = :etudiant_id AND entreprise_id = :entreprise_id
                  AND statut IN ('BROUILLON','SOUMISE','VALIDEE')";
        $params = [':etudiant_id' => $etudiantId, ':entreprise_id' => $entrepriseId];
        if ($saufId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $saufId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return array<string,int> */
    public function compterParStatut(): array
    {
        $rows = $this->pdo->query(
            'SELECT statut, COUNT(*) AS total FROM demande_stage GROUP BY statut'
        )->fetchAll();
        $resultat = [
            'BROUILLON' => 0,
            'SOUMISE'   => 0,
            'VALIDEE'   => 0,
            'REFUSEE'   => 0,
            'ANNULEE'   => 0,
            'TOTAL'     => 0,
        ];
        foreach ($rows as $row) {
            $resultat[(string) $row['statut']] = (int) $row['total'];
            $resultat['TOTAL'] += (int) $row['total'];
        }
        return $resultat;
    }

    /** @return array<int,array<string,mixed>> */
    public function findAllForExport(): array
    {
        $sql = self::SELECT_BASE . ' ORDER BY d.id DESC';
        return array_map([$this, 'normaliser'], $this->pdo->query($sql)->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $marques = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(self::SELECT_BASE . " WHERE d.id IN ($marques)");
        $stmt->execute(array_values($ids));
        return array_map([$this, 'normaliser'], $stmt->fetchAll());
    }

    /** @param array<string,mixed> $row */
    private function normaliser(array $row): array
    {
        foreach ([
            'id', 'etudiant_id', 'entreprise_id', 'encadreur_id', 'encadreur_propose_id',
            'etudiant_utilisateur_id', 'encadreur_utilisateur_id', 'propose_utilisateur_id',
            'rapport_valide_par', 'nb_pieces', 'nb_commentaires',
        ] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        if (array_key_exists('rapport_valide', $row)) {
            $row['rapport_valide'] = (int) $row['rapport_valide'] === 1;
        }
        if (array_key_exists('note', $row) && $row['note'] !== null) {
            $row['note'] = (float) $row['note'];
        }
        return $row;
    }
}
