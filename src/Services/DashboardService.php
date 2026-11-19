<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DashboardService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly NotificationService $notifications
    ) {
    }

    /** @return array<string,mixed> */
    public function pour(array $user): array
    {
        $role = (string) $user['role'];
        $scope = '';
        $params = [];
        if ($role === 'ETUDIANT') {
            $scope = ' WHERE d.etudiant_id = :scope_id';
            $params[':scope_id'] = (int) ($user['etudiant_id'] ?? 0);
        } elseif ($role === 'ENCADREUR') {
            $scope = ' WHERE d.encadreur_id = :scope_id';
            $params[':scope_id'] = (int) ($user['encadreur_id'] ?? 0);
        }

        $stmt = $this->pdo->prepare(
            "SELECT d.statut, COUNT(*) AS total FROM demande_stage d$scope GROUP BY d.statut"
        );
        $stmt->execute($params);
        $parStatut = ['BROUILLON' => 0, 'SOUMISE' => 0, 'VALIDEE' => 0, 'REFUSEE' => 0, 'ANNULEE' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $parStatut[(string) $row['statut']] = (int) $row['total'];
        }

        $resultat = [
            'role'              => $role,
            'demandes'          => $parStatut,
            'total_demandes'    => array_sum($parStatut),
            'notifications'     => $this->notifications->compterNonLues((int) $user['id']),
            'soutenances_a_venir' => $this->compterSoutenancesAVenir($user),
        ];

        if (in_array($role, ['RESPONSABLE', 'ADMIN'], true)) {
            $resultat['entreprises_a_valider'] = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM entreprise WHERE statut = 'A_VALIDER'"
            )->fetchColumn();
            $resultat['entreprises_partenaires'] = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM entreprise WHERE statut = 'PARTENAIRE'"
            )->fetchColumn();
            $resultat['entreprises_interdites'] = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM entreprise WHERE statut = 'INTERDITE'"
            )->fetchColumn();
            $resultat['encadreurs'] = $this->chargesEncadreurs();
            $resultat['soutenances_non_convoquees'] = (int) $this->pdo->query(
                'SELECT COUNT(*) FROM soutenance WHERE convocation_envoyee = 0'
            )->fetchColumn();
        }

        if (in_array($role, ['SECRETARIAT', 'ADMIN'], true)) {
            $resultat['validees_sans_encadreur'] = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM demande_stage WHERE statut = 'VALIDEE' AND encadreur_id IS NULL"
            )->fetchColumn();
            $resultat['etudiants_sans_demande'] = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM etudiant e JOIN utilisateur u ON u.id = e.utilisateur_id
                 WHERE u.actif = 1 AND NOT EXISTS (
                   SELECT 1 FROM demande_stage d
                   WHERE d.etudiant_id = e.id AND d.date_soumission IS NOT NULL AND d.statut <> 'ANNULEE'
                 )"
            )->fetchColumn();
            $resultat['salles_actives'] = (int) $this->pdo->query(
                'SELECT COUNT(*) FROM salle WHERE active = 1'
            )->fetchColumn();
            $resultat['propositions_encadreur'] = (int) $this->pdo->query(
                'SELECT COUNT(*) FROM demande_stage WHERE encadreur_propose_id IS NOT NULL AND encadreur_id IS NULL'
            )->fetchColumn();
        }

        if ($role === 'ENCADREUR') {
            $resultat['etudiants'] = $parStatut['VALIDEE'];
            $resultat['rapports_a_valider'] = $this->compterRapportsAValider((int) ($user['encadreur_id'] ?? 0));
            $resultat['notes_saisies'] = $this->compterNotes((int) ($user['encadreur_id'] ?? 0));
        }

        if ($role === 'ETUDIANT') {
            $resultat['prochaine_soutenance'] = $this->prochaineSoutenance((int) ($user['etudiant_id'] ?? 0));
        }

        return $resultat;
    }

    private function compterSoutenancesAVenir(array $user): int
    {
        $sql = 'SELECT COUNT(*) FROM soutenance s JOIN demande_stage d ON d.id = s.demande_id WHERE s.date_heure >= NOW()';
        $params = [];
        if ($user['role'] === 'ETUDIANT') {
            $sql .= ' AND d.etudiant_id = :id';
            $params[':id'] = (int) ($user['etudiant_id'] ?? 0);
        } elseif ($user['role'] === 'ENCADREUR') {
            $sql .= ' AND d.encadreur_id = :id';
            $params[':id'] = (int) ($user['encadreur_id'] ?? 0);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    private function chargesEncadreurs(): array
    {
        $sql = "SELECT enc.id, enc.charge_max,
                       u.prenom, u.nom,
                       (SELECT COUNT(*) FROM demande_stage d
                        WHERE d.encadreur_id = enc.id AND d.statut = 'VALIDEE') AS charge
                FROM encadreur enc JOIN utilisateur u ON u.id = enc.utilisateur_id
                WHERE u.actif = 1 ORDER BY charge DESC, u.nom";
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['charge'] = (int) $row['charge'];
            $row['charge_max'] = (int) $row['charge_max'];
            return $row;
        }, $this->pdo->query($sql)->fetchAll());
    }

    private function compterRapportsAValider(int $encadreurId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT d.id) FROM demande_stage d
             JOIN piece_jointe p ON p.demande_id = d.id AND p.type = 'RAPPORT'
             WHERE d.encadreur_id = :id AND d.rapport_valide = 0"
        );
        $stmt->execute([':id' => $encadreurId]);
        return (int) $stmt->fetchColumn();
    }

    private function compterNotes(int $encadreurId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM demande_stage WHERE encadreur_id = :id AND note IS NOT NULL'
        );
        $stmt->execute([':id' => $encadreurId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    private function prochaineSoutenance(int $etudiantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.date_heure, s.salle, s.duree_min
             FROM soutenance s JOIN demande_stage d ON d.id = s.demande_id
             WHERE d.etudiant_id = :id AND s.date_heure >= NOW()
             ORDER BY s.date_heure LIMIT 1'
        );
        $stmt->execute([':id' => $etudiantId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
