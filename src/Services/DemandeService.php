<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use App\Repositories\EntrepriseRepository;
use App\Repositories\HistoriqueRepository;
use App\Repositories\UtilisateurRepository;
use PDO;

/**
 * Règles métier des demandes : périmètre de lecture par rôle, cycle de vie,
 * affectation d'un encadreur, journal des transitions.
 */
final class DemandeService
{
    /** Transitions autorisées du cycle de vie (chapitre 6). */
    public const TRANSITIONS = [
        'BROUILLON' => ['SOUMISE'],
        'SOUMISE'   => ['VALIDEE', 'REFUSEE'],
        'REFUSEE'   => ['BROUILLON'],
        'VALIDEE'   => [],
    ];

    public const STATUTS = ['BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly DemandeRepository $demandes,
        private readonly HistoriqueRepository $historique,
        private readonly EntrepriseRepository $entreprises,
        private readonly UtilisateurRepository $utilisateurs
    ) {
    }

    public static function peutPasser(string $de, string $vers): bool
    {
        return in_array($vers, self::TRANSITIONS[$de] ?? [], true);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public function lister(array $user, ?string $statut = null, ?string $q = null): array
    {
        if ($statut !== null && $statut !== '' && !in_array($statut, self::STATUTS, true)) {
            throw new ApiException(400, 'Statut inconnu.', ['statut' => 'Valeurs possibles : ' . implode(', ', self::STATUTS)]);
        }

        return $this->demandes->search($this->scope($user), $statut, $q);
    }

    /** @param array<string,mixed> $user */
    public function detail(array $user, int $id): array
    {
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $this->autoriserLecture($user, $demande);
        return $demande;
    }

    /**
     * Crée une demande pour l'étudiant connecté.
     * $soumettre = false crée un brouillon ; true dépose immédiatement.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function creer(array $user, int $entrepriseId, bool $soumettre): array
    {
        $this->exigerEtudiant($user);

        if ($this->entreprises->findById($entrepriseId) === null) {
            throw new ApiException(400, 'Données invalides.', ['entrepriseId' => 'Entreprise inconnue.']);
        }

        $statut = $soumettre ? 'SOUMISE' : 'BROUILLON';
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $id = $this->demandes->create(
                (int) $user['etudiant_id'],
                $entrepriseId,
                $statut,
                $soumettre ? $now : null
            );
            $this->historique->add($id, null, $statut, (int) $user['id']);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->demandes->findById($id) ?? throw new ApiException(500, 'Demande non relue.');
    }

    /**
     * Valide ou refuse une demande, ou la (re)soumet : la cible dépend du rôle.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function changerStatut(array $user, int $id, string $cible, ?string $motif): array
    {
        if (!in_array($cible, self::STATUTS, true)) {
            throw new ApiException(400, 'Statut inconnu.', ['statut' => 'Valeurs possibles : ' . implode(', ', self::STATUTS)]);
        }

        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }

        $this->autoriserTransition($user, $demande, $cible);

        if ($cible === 'REFUSEE') {
            $motif = trim((string) $motif);
            if ($motif === '') {
                throw new ApiException(400, 'Données invalides.', ['motif' => 'Le motif de refus est obligatoire.']);
            }
            if (mb_strlen($motif) > 255) {
                throw new ApiException(400, 'Données invalides.', ['motif' => 'Le motif est trop long (max 255).']);
            }
        } else {
            $motif = null;
        }

        $this->pdo->beginTransaction();
        try {
            // Verrou pessimiste : deux responsables ne peuvent pas décider en même temps.
            $courante = $this->demandes->findByIdForUpdate($id);
            if ($courante === null) {
                throw new ApiException(404, 'Demande introuvable.');
            }
            if (!self::peutPasser((string) $courante['statut'], $cible)) {
                throw new ApiException(
                    409,
                    'Transition interdite : ' . $courante['statut'] . ' vers ' . $cible . '.'
                );
            }

            $now = date('Y-m-d H:i:s');
            $dateSoumission = $courante['date_soumission'];
            $dateDecision = $courante['date_decision'];

            if ($cible === 'SOUMISE') {
                $dateSoumission = $now;
                $dateDecision = null;
            } elseif ($cible === 'BROUILLON') {
                $dateSoumission = null;
                $dateDecision = null;
            } else {
                $dateDecision = $now;
            }

            $this->demandes->updateStatus($id, $cible, $motif, $dateSoumission, $dateDecision);
            $this->historique->add($id, (string) $courante['statut'], $cible, (int) $user['id'], $motif);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->demandes->findById($id) ?? throw new ApiException(500, 'Demande non relue.');
    }

    /**
     * Affecte un encadreur à une demande validée (secrétariat).
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function affecterEncadreur(array $user, int $id, int $encadreurId): array
    {
        if (!in_array($user['role'], ['SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Seul le secrétariat affecte un encadreur.');
        }

        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if ($demande['statut'] !== 'VALIDEE') {
            throw new ApiException(409, 'Seule une demande validée peut recevoir un encadreur.');
        }
        if ($this->utilisateurs->findEncadreurById($encadreurId) === null) {
            throw new ApiException(400, 'Données invalides.', ['encadreurId' => 'Encadreur inconnu.']);
        }

        $this->demandes->affecterEncadreur($id, $encadreurId);
        return $this->demandes->findById($id) ?? throw new ApiException(500, 'Demande non relue.');
    }

    /**
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public function historiqueDe(array $user, int $id): array
    {
        $this->detail($user, $id);
        return $this->historique->findByDemande($id);
    }

    /**
     * Périmètre de lecture : un étudiant ne voit que ses demandes, un
     * encadreur seulement celles qui lui sont affectées.
     *
     * @param array<string,mixed> $user
     * @return array{etudiant_id?:int,encadreur_id?:int}
     */
    private function scope(array $user): array
    {
        return match ($user['role']) {
            'ETUDIANT'  => ['etudiant_id' => (int) $this->exigerEtudiant($user)['etudiant_id']],
            'ENCADREUR' => ['encadreur_id' => (int) ($user['encadreur_id'] ?? 0)],
            default     => [],
        };
    }

    /** @param array<string,mixed> $user */
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
        throw new ApiException(403, 'Accès interdit à ce dossier.');
    }

    /** @param array<string,mixed> $user */
    private function autoriserTransition(array $user, array $demande, string $cible): void
    {
        $proprietaire = $user['role'] === 'ETUDIANT'
            && (int) $demande['etudiant_id'] === (int) ($user['etudiant_id'] ?? 0);

        if (in_array($cible, ['SOUMISE', 'BROUILLON'], true)) {
            if (!$proprietaire) {
                throw new ApiException(403, 'Seul l’étudiant propriétaire peut soumettre ce dossier.');
            }
            return;
        }

        if ($user['role'] !== 'RESPONSABLE') {
            throw new ApiException(403, 'Seul le responsable pédagogique peut valider ou refuser.');
        }
    }

    /** @param array<string,mixed> $user */
    private function exigerEtudiant(array $user): array
    {
        if ($user['role'] !== 'ETUDIANT' || empty($user['etudiant_id'])) {
            throw new ApiException(403, 'Action réservée à un compte étudiant.');
        }
        return $user;
    }
}
