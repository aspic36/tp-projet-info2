<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use App\Repositories\SalleRepository;
use App\Repositories\SoutenanceRepository;
use App\Repositories\UtilisateurRepository;
use App\Support\Audit;
use PDO;
use PDOException;

final class SoutenanceService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly DemandeRepository $demandes,
        private readonly SoutenanceRepository $soutenances,
        private readonly SalleRepository $salles,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly NotificationService $notifications,
        private readonly Audit $audit
    ) {
    }

    /** @param array<string,mixed> $user */
    public function lister(array $user, ?int $annee = null, ?int $mois = null): array
    {
        return $this->soutenances->search($this->scope($user), $annee, $mois);
    }

    /** @param array<string,mixed> $user */
    public function detail(array $user, int $id): array
    {
        $soutenance = $this->soutenances->findById($id);
        if ($soutenance === null) {
            throw new ApiException(404, 'Soutenance introuvable.');
        }
        $demande = $this->demandes->findById((int) $soutenance['demande_id']);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $this->autoriserLecture($user, $demande);
        $soutenance['jury'] = $this->soutenances->jury($id);
        return $soutenance;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<int,array{utilisateurId:int,role:string}> $jury
     * @return array<string,mixed>
     */
    public function programmer(
        array $user,
        int $demandeId,
        string $dateHeure,
        ?int $salleId,
        string $salle,
        int $dureeMin,
        array $jury = []
    ): array {
        $this->exigerSecretariat($user);

        $demande = $this->demandes->findById($demandeId);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if ($demande['statut'] !== 'VALIDEE') {
            throw new ApiException(409, 'Seule une demande validée peut être programmée.');
        }
        if ($demande['encadreur_id'] === null) {
            throw new ApiException(409, 'La demande doit avoir un encadreur avant la programmation.');
        }
        if ($this->soutenances->findByDemande($demandeId) !== null) {
            throw new ApiException(409, 'Cette demande a déjà une soutenance programmée.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateHeure);
        if ($date === false || $date <= new \DateTimeImmutable()) {
            throw new ApiException(400, 'Données invalides.', ['dateHeure' => 'La date de soutenance doit être future.']);
        }
        if ($dureeMin < 20 || $dureeMin > 90) {
            throw new ApiException(400, 'Données invalides.', ['dureeMin' => 'La durée doit être comprise entre 20 et 90 minutes.']);
        }

        $salleRow = null;
        if ($salleId !== null) {
            $salleRow = $this->salles->findById($salleId);
            if ($salleRow === null || !$salleRow['active']) {
                throw new ApiException(400, 'Données invalides.', ['salleId' => 'Salle inconnue ou inactive.']);
            }
            $salle = (string) $salleRow['nom'];
        }
        if (preg_match('/^[A-Za-z0-9À-ÿ \-]{1,40}$/', $salle) !== 1) {
            throw new ApiException(400, 'Données invalides.', ['salle' => 'Nom de salle invalide.']);
        }

        $jury = $this->normaliserJury($jury, (int) ($demande['encadreur_utilisateur_id'] ?? 0));
        $conflits = $this->soutenances->conflits(
            $salleRow['id'] ?? null,
            $salle,
            $dateHeure,
            $dureeMin,
            (int) $demande['encadreur_id'],
            array_map(static fn (array $m): int => $m['utilisateurId'], $jury)
        );
        if ($conflits !== []) {
            throw new ApiException(409, $conflits[0]['message'], ['conflits' => $conflits]);
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->soutenances->create($demandeId, $dateHeure, $salle, $salleRow['id'] ?? null, $dureeMin);
            $this->soutenances->remplacerJury($id, $jury);
            $this->audit->log((int) $user['id'], 'SOUTENANCE_PROGRAMMEE', 'soutenance', $id, [
                'demande_id' => $demandeId,
                'date_heure' => $dateHeure,
                'salle'      => $salle,
            ]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() === '23000') {
                throw new ApiException(409, 'Créneau déjà occupé.');
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifierParties($demande, $dateHeure, $salle, $dureeMin, 'SOUTENANCE_PROGRAMMEE', 'Soutenance programmée');
        return $this->detail($user, $id);
    }

    /**
     * @param array<string,mixed> $user
     * @param array<int,array{utilisateurId:int,role:string}> $jury
     * @return array<string,mixed>
     */
    public function modifier(
        array $user,
        int $id,
        string $dateHeure,
        ?int $salleId,
        string $salle,
        int $dureeMin,
        array $jury = []
    ): array {
        $this->exigerSecretariat($user);
        $soutenance = $this->soutenances->findById($id);
        if ($soutenance === null) {
            throw new ApiException(404, 'Soutenance introuvable.');
        }
        $demande = $this->demandes->findById((int) $soutenance['demande_id']);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateHeure);
        if ($date === false || $date <= new \DateTimeImmutable()) {
            throw new ApiException(400, 'Données invalides.', ['dateHeure' => 'La date de soutenance doit être future.']);
        }

        $salleRow = null;
        if ($salleId !== null) {
            $salleRow = $this->salles->findById($salleId);
            if ($salleRow === null || !$salleRow['active']) {
                throw new ApiException(400, 'Données invalides.', ['salleId' => 'Salle inconnue ou inactive.']);
            }
            $salle = (string) $salleRow['nom'];
        }

        $jury = $this->normaliserJury($jury, (int) ($demande['encadreur_utilisateur_id'] ?? 0));
        $conflits = $this->soutenances->conflits(
            $salleRow['id'] ?? null,
            $salle,
            $dateHeure,
            $dureeMin,
            (int) $demande['encadreur_id'],
            array_map(static fn (array $m): int => $m['utilisateurId'], $jury),
            $id
        );
        if ($conflits !== []) {
            throw new ApiException(409, $conflits[0]['message'], ['conflits' => $conflits]);
        }

        $this->pdo->beginTransaction();
        try {
            $this->soutenances->update($id, $dateHeure, $salle, $salleRow['id'] ?? null, $dureeMin);
            $this->soutenances->remplacerJury($id, $jury);
            $this->audit->log((int) $user['id'], 'SOUTENANCE_MODIFIEE', 'soutenance', $id, [
                'date_heure' => $dateHeure,
                'salle'      => $salle,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifierParties($demande, $dateHeure, $salle, $dureeMin, 'SOUTENANCE_MODIFIEE', 'Soutenance modifiée');
        return $this->detail($user, $id);
    }

    /** @param array<string,mixed> $user */
    public function convoquer(array $user, int $id): array
    {
        $this->exigerSecretariat($user);
        $soutenance = $this->soutenances->findById($id);
        if ($soutenance === null) {
            throw new ApiException(404, 'Soutenance introuvable.');
        }
        $demande = $this->demandes->findById((int) $soutenance['demande_id']);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }

        $destinataires = $this->destinataires($demande, $id);
        foreach ($destinataires as $destinataire) {
            $this->notifications->envoyer(
                $destinataire['utilisateur_id'],
                'CONVOCATION',
                'Convocation à la soutenance',
                sprintf(
                    'Soutenance de %s %s le %s en %s (%d min).',
                    $demande['etudiant_prenom'],
                    $demande['etudiant_nom'],
                    $soutenance['date_heure'],
                    $soutenance['salle'],
                    $soutenance['duree_min']
                ),
                (int) $demande['id'],
                $destinataire['email']
            );
        }
        $this->soutenances->marquerConvocation($id);
        $this->audit->log((int) $user['id'], 'CONVOCATION_ENVOYEE', 'soutenance', $id, [
            'destinataires' => count($destinataires),
        ]);
        return $this->detail($user, $id);
    }

    /** @param array<string,mixed> $user */
    public function planningMensuel(array $user, int $annee, int $mois): array
    {
        $lignes = $this->lister($user, $annee, $mois);
        $parJour = [];
        foreach ($lignes as $ligne) {
            $jour = substr((string) $ligne['date_heure'], 0, 10);
            $parJour[$jour][] = $ligne;
        }
        ksort($parJour);
        return $parJour;
    }

    /** @param array<string,mixed> $user */
    public function convocationTexte(array $user, int $id): array
    {
        $soutenance = $this->detail($user, $id);
        $demande = $this->demandes->findById((int) $soutenance['demande_id']);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $jury = [];
        foreach ($soutenance['jury'] as $membre) {
            $jury[] = $membre['role_jury'] . ' : ' . $membre['prenom'] . ' ' . $membre['nom'];
        }
        $texte = "CONVOCATION DE SOUTENANCE\n\n"
            . "Étudiant : {$demande['etudiant_prenom']} {$demande['etudiant_nom']} ({$demande['matricule']})\n"
            . "Entreprise : {$demande['entreprise']}\n"
            . "Date : {$soutenance['date_heure']}\n"
            . "Salle : {$soutenance['salle']}\n"
            . "Durée : {$soutenance['duree_min']} minutes\n"
            . ($jury === [] ? '' : "Jury :\n- " . implode("\n- ", $jury) . "\n");
        return ['soutenance' => $soutenance, 'demande' => $demande, 'texte' => $texte];
    }

    private function normaliserJury(array $jury, int $encadreurUtilisateurId): array
    {
        $resultat = [];
        $vus = [];
        foreach ($jury as $membre) {
            $utilisateurId = (int) $membre['utilisateurId'];
            $role = strtoupper((string) $membre['role']);
            if (!in_array($role, ['PRESIDENT', 'RAPPORTEUR', 'MEMBRE', 'ENCADREUR'], true)) {
                throw new ApiException(400, 'Rôle de jury invalide.', ['jury' => 'Rôle inconnu.']);
            }
            $utilisateur = $this->utilisateurs->findById($utilisateurId);
            if ($utilisateur === null || (int) $utilisateur['actif'] !== 1) {
                throw new ApiException(400, 'Membre du jury inconnu.', ['jury' => 'Compte inactif ou inexistant.']);
            }
            if (!isset($vus[$utilisateurId])) {
                $vus[$utilisateurId] = true;
                $resultat[] = ['utilisateurId' => $utilisateurId, 'role' => $role];
            }
        }
        if ($encadreurUtilisateurId > 0 && !isset($vus[$encadreurUtilisateurId])) {
            $resultat[] = ['utilisateurId' => $encadreurUtilisateurId, 'role' => 'ENCADREUR'];
        }
        return $resultat;
    }

    private function notifierParties(array $demande, string $dateHeure, string $salle, int $dureeMin, string $type, string $titre): void
    {
        $message = "La soutenance de {$demande['etudiant_prenom']} {$demande['etudiant_nom']} est prévue le $dateHeure en $salle ($dureeMin min).";
        $this->notifications->envoyer((int) $demande['etudiant_utilisateur_id'], $type, $titre, $message, (int) $demande['id'], (string) $demande['etudiant_email']);
        if (!empty($demande['encadreur_utilisateur_id'])) {
            $this->notifications->envoyer((int) $demande['encadreur_utilisateur_id'], $type, $titre, $message, (int) $demande['id']);
        }
    }

    private function destinataires(array $demande, int $soutenanceId): array
    {
        $destinataires = [[
            'utilisateur_id' => (int) $demande['etudiant_utilisateur_id'],
            'email'          => (string) $demande['etudiant_email'],
        ]];
        if (!empty($demande['encadreur_utilisateur_id'])) {
            $destinataires[] = [
                'utilisateur_id' => (int) $demande['encadreur_utilisateur_id'],
                'email'          => null,
            ];
        }
        foreach ($this->soutenances->jury($soutenanceId) as $membre) {
            $destinataires[(int) $membre['utilisateur_id']] = [
                'utilisateur_id' => (int) $membre['utilisateur_id'],
                'email'          => (string) $membre['email'],
            ];
        }
        return array_values($destinataires);
    }

    private function scope(array $user): array
    {
        return match ($user['role']) {
            'ETUDIANT'  => ['etudiant_id' => (int) ($user['etudiant_id'] ?? 0)],
            'ENCADREUR' => ['encadreur_id' => (int) ($user['encadreur_id'] ?? 0)],
            default     => [],
        };
    }

    private function autoriserLecture(array $user, array $demande): void
    {
        if (in_array($user['role'], ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            return;
        }
        if ($user['role'] === 'ETUDIANT' && (int) $demande['etudiant_id'] === (int) ($user['etudiant_id'] ?? 0)) {
            return;
        }
        if ($user['role'] === 'ENCADREUR' && (int) $demande['encadreur_id'] === (int) ($user['encadreur_id'] ?? 0)) {
            return;
        }
        throw new ApiException(403, 'Accès interdit à cette soutenance.');
    }

    private function exigerSecretariat(array $user): void
    {
        if (!in_array($user['role'], ['SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Seul le secrétariat programme les soutenances.');
        }
    }
}
