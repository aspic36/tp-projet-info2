<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SoutenanceRepository
{
    private const SELECT_BASE = '
        SELECT s.id, s.demande_id, s.date_heure, s.salle, s.salle_id, s.duree_min,
               s.convoquee_le, s.convocation_envoyee,
               en.raison_sociale AS entreprise,
               u.prenom AS etudiant_prenom, u.nom AS etudiant_nom,
               d.encadreur_id, d.note, d.mention, d.statut AS demande_statut,
               ue.prenom AS encadreur_prenom, ue.nom AS encadreur_nom,
               sa.nom AS salle_nom, sa.batiment AS salle_batiment
        FROM soutenance s
        JOIN demande_stage d ON d.id = s.demande_id
        JOIN etudiant e      ON e.id = d.etudiant_id
        JOIN utilisateur u   ON u.id = e.utilisateur_id
        JOIN entreprise en   ON en.id = d.entreprise_id
        LEFT JOIN encadreur enc ON enc.id = d.encadreur_id
        LEFT JOIN utilisateur ue ON ue.id = enc.utilisateur_id
        LEFT JOIN salle sa ON sa.id = s.salle_id
    ';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByDemande(int $demandeId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE s.demande_id = :demande_id');
        $stmt->execute([':demande_id' => $demandeId]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE s.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->normaliser($row);
    }

    /**
     * @param array{etudiant_id?:int,encadreur_id?:int} $scope
     * @return array<int,array<string,mixed>>
     */
    public function search(array $scope, ?int $annee = null, ?int $mois = null): array
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
        if ($annee !== null && $annee > 0) {
            $where[] = 'YEAR(s.date_heure) = :annee';
            $params[':annee'] = $annee;
        }
        if ($mois !== null && $mois > 0) {
            $where[] = 'MONTH(s.date_heure) = :mois';
            $params[':mois'] = $mois;
        }

        $sql = self::SELECT_BASE;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.date_heure, s.id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'normaliser'], $stmt->fetchAll());
    }

    public function create(
        int $demandeId,
        string $dateHeure,
        string $salle,
        ?int $salleId,
        int $dureeMin
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO soutenance (demande_id, date_heure, salle, salle_id, duree_min)
             VALUES (:demande_id, :date_heure, :salle, :salle_id, :duree_min)'
        );
        $stmt->execute([
            ':demande_id' => $demandeId,
            ':date_heure' => $dateHeure,
            ':salle'      => $salle,
            ':salle_id'   => $salleId,
            ':duree_min'  => $dureeMin,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $dateHeure, string $salle, ?int $salleId, int $dureeMin): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE soutenance SET date_heure = :date_heure, salle = :salle,
                salle_id = :salle_id, duree_min = :duree_min,
                convocation_envoyee = 0, convoquee_le = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            ':date_heure' => $dateHeure,
            ':salle'      => $salle,
            ':salle_id'   => $salleId,
            ':duree_min'  => $dureeMin,
            ':id'         => $id,
        ]);
    }

    /**
     * @return array<int,array{type:string,message:string,soutenance_id:int}>
     */
    public function conflits(
        ?int $salleId,
        string $salleNom,
        string $dateHeure,
        int $dureeMin,
        ?int $encadreurId,
        array $juryIds,
        ?int $saufSoutenanceId = null
    ): array {
        $debut = new \DateTimeImmutable($dateHeure);
        $fin = $debut->modify('+' . $dureeMin . ' minutes');
        $params = [
            ':debut' => $debut->format('Y-m-d H:i:s'),
            ':fin'   => $fin->format('Y-m-d H:i:s'),
        ];
        $exclusion = '';
        if ($saufSoutenanceId !== null) {
            $exclusion = ' AND s.id <> :sauf_id';
            $params[':sauf_id'] = $saufSoutenanceId;
        }
        $chevauchement = 's.date_heure < :fin AND DATE_ADD(s.date_heure, INTERVAL s.duree_min MINUTE) > :debut';

        $conflits = [];

        $paramsSalle = $params;
        $paramsSalle[':salle_nom'] = $salleNom;
        if ($salleId === null) {
            $salleSql = 'SELECT s.id, s.salle FROM soutenance s
                         WHERE s.salle = :salle_nom AND ' . $chevauchement . $exclusion;
        } else {
            $salleSql = 'SELECT s.id, s.salle FROM soutenance s
                         WHERE (s.salle_id = :salle_id OR (s.salle_id IS NULL AND s.salle = :salle_nom))
                           AND ' . $chevauchement . $exclusion;
            $paramsSalle[':salle_id'] = $salleId;
        }
        $stmt = $this->pdo->prepare($salleSql);
        $stmt->execute($paramsSalle);
        foreach ($stmt->fetchAll() as $row) {
            $conflits[] = [
                'type'          => 'SALLE',
                'message'       => 'La salle est déjà occupée à ce créneau.',
                'soutenance_id' => (int) $row['id'],
            ];
        }

        if ($encadreurId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT s.id FROM soutenance s
                 JOIN demande_stage d ON d.id = s.demande_id
                 WHERE d.encadreur_id = :encadreur_id AND ' . $chevauchement . $exclusion
            );
            $paramsEnc = $params;
            $paramsEnc[':encadreur_id'] = $encadreurId;
            $stmt->execute($paramsEnc);
            foreach ($stmt->fetchAll() as $row) {
                $conflits[] = [
                    'type'          => 'ENCADREUR',
                    'message'       => 'L’encadreur est déjà mobilisé sur ce créneau.',
                    'soutenance_id' => (int) $row['id'],
                ];
            }
        }

        if ($juryIds !== []) {
            $marques = [];
            $paramsJury = $params;
            foreach (array_values($juryIds) as $index => $juryId) {
                $cle = ':jury_' . $index;
                $marques[] = $cle;
                $paramsJury[$cle] = $juryId;
            }
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT s.id FROM soutenance s
                 JOIN soutenance_jury j ON j.soutenance_id = s.id
                 WHERE j.utilisateur_id IN (' . implode(',', $marques) . ') AND ' . $chevauchement . $exclusion
            );
            $stmt->execute($paramsJury);
            foreach ($stmt->fetchAll() as $row) {
                $conflits[] = [
                    'type'          => 'JURY',
                    'message'       => 'Un membre du jury est déjà mobilisé sur ce créneau.',
                    'soutenance_id' => (int) $row['id'],
                ];
            }
        }

        return $conflits;
    }

    public function ajouterJury(int $soutenanceId, int $utilisateurId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO soutenance_jury (soutenance_id, utilisateur_id, role_jury)
             VALUES (:soutenance_id, :utilisateur_id, :role)
             ON DUPLICATE KEY UPDATE role_jury = VALUES(role_jury)'
        );
        $stmt->execute([
            ':soutenance_id' => $soutenanceId,
            ':utilisateur_id' => $utilisateurId,
            ':role' => $role,
        ]);
    }

    public function remplacerJury(int $soutenanceId, array $membres): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM soutenance_jury WHERE soutenance_id = :id');
        $stmt->execute([':id' => $soutenanceId]);
        foreach ($membres as $membre) {
            $this->ajouterJury($soutenanceId, (int) $membre['utilisateurId'], (string) $membre['role']);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function jury(int $soutenanceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT j.utilisateur_id, j.role_jury, j.present,
                    u.prenom, u.nom, u.email
             FROM soutenance_jury j
             JOIN utilisateur u ON u.id = j.utilisateur_id
             WHERE j.soutenance_id = :id
             ORDER BY FIELD(j.role_jury, \'PRESIDENT\', \'RAPPORTEUR\', \'ENCADREUR\', \'MEMBRE\'), u.nom'
        );
        $stmt->execute([':id' => $soutenanceId]);
        return $stmt->fetchAll();
    }

    public function marquerConvocation(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE soutenance SET convoquee_le = NOW(), convocation_envoyee = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /** @param array<string,mixed> $row */
    private function normaliser(array $row): array
    {
        foreach (['id', 'demande_id', 'salle_id', 'duree_min', 'encadreur_id'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        if (array_key_exists('convocation_envoyee', $row)) {
            $row['convocation_envoyee'] = (int) $row['convocation_envoyee'] === 1;
        }
        if (array_key_exists('note', $row) && $row['note'] !== null) {
            $row['note'] = (float) $row['note'];
        }
        return $row;
    }
}
