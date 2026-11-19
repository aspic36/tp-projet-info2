<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Http\ApiException;
use App\Repositories\CommentaireRepository;
use App\Repositories\DemandeRepository;
use App\Repositories\EntrepriseRepository;
use App\Repositories\HistoriqueRepository;
use App\Repositories\PieceRepository;
use App\Repositories\SoutenanceRepository;
use App\Repositories\UtilisateurRepository;
use App\Support\Audit;
use PDO;

final class DemandeService
{
    public const TRANSITIONS = [
        'BROUILLON' => ['SOUMISE', 'ANNULEE'],
        'SOUMISE'   => ['VALIDEE', 'REFUSEE', 'ANNULEE'],
        'REFUSEE'   => ['BROUILLON', 'ANNULEE'],
        'VALIDEE'   => [],
        'ANNULEE'   => [],
    ];

    public const STATUTS = ['BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE', 'ANNULEE'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly DemandeRepository $demandes,
        private readonly HistoriqueRepository $historique,
        private readonly EntrepriseRepository $entreprises,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly CommentaireRepository $commentaires,
        private readonly PieceRepository $pieces,
        private readonly SoutenanceRepository $soutenances,
        private readonly NotificationService $notifications,
        private readonly Audit $audit
    ) {
    }

    public static function peutPasser(string $de, string $vers): bool
    {
        return in_array($vers, self::TRANSITIONS[$de] ?? [], true);
    }

    /** @return array{data:array<int,array<string,mixed>>,meta:array<string,int>} */
    public function lister(
        array $user,
        ?string $statut = null,
        ?string $q = null,
        ?int $entrepriseId = null,
        ?string $filiere = null,
        ?int $annee = null,
        int $page = 1,
        int $parPage = 25
    ): array {
        if ($statut !== null && $statut !== '' && !in_array($statut, self::STATUTS, true)) {
            throw new ApiException(400, 'Statut inconnu.', ['statut' => 'Valeurs possibles : ' . implode(', ', self::STATUTS)]);
        }
        $resultat = $this->demandes->searchPage(
            $this->scope($user),
            $statut,
            $q,
            $entrepriseId,
            $filiere,
            $annee,
            max(1, $page),
            min(100, max(1, $parPage))
        );
        $resultat['meta'] = [
            'total'        => $resultat['total'],
            'page'         => $resultat['page'],
            'par_page'     => $resultat['par_page'],
            'derniere_page' => (int) ceil($resultat['total'] / max(1, $resultat['par_page'])),
        ];
        return $resultat;
    }

    /** @return array<string,mixed> */
    public function detail(array $user, int $id): array
    {
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $this->autoriserLecture($user, $demande);
        $demande['pieces'] = array_map(static function (array $piece): array {
            unset($piece['chemin']);
            return $piece;
        }, $this->pieces->findByDemande($id));
        $demande['commentaires'] = $this->commentaires->findByDemande($id);
        $demande['soutenance'] = $this->soutenances->findByDemande($id);
        $demande['etapes'] = $this->etapes($demande);
        if ($demande['encadreur_id'] !== null) {
            $demande['encadreur_charge'] = $this->utilisateurs->findEncadreurById((int) $demande['encadreur_id']);
        }
        return $demande;
    }

    /** @return array<string,mixed> */
    public function creer(array $user, int $entrepriseId, bool $soumettre, array $data = []): array
    {
        $this->exigerEtudiant($user);
        $entreprise = $this->entrepriseChoisissable($entrepriseId);
        $this->verifierFiche($data);
        $this->verifierDoublon((int) $user['etudiant_id'], $entrepriseId);

        $statut = $soumettre ? 'SOUMISE' : 'BROUILLON';
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $id = $this->demandes->create(
                (int) $user['etudiant_id'],
                $entrepriseId,
                $statut,
                $soumettre ? $now : null,
                $data
            );
            $this->historique->add($id, null, $statut, (int) $user['id']);
            $this->audit->log((int) $user['id'], 'DEMANDE_CREEE', 'demande', $id, [
                'statut'     => $statut,
                'entreprise' => $entreprise['raison_sociale'],
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if ($soumettre) {
            $this->notifierResponsables($id, 'Nouvelle demande à traiter', $this->messageDemande($user, $entreprise['raison_sociale'], 'soumise'));
        }
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function modifier(array $user, int $id, int $entrepriseId, array $data): array
    {
        $this->exigerEtudiant($user);
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if ((int) $demande['etudiant_id'] !== (int) ($user['etudiant_id'] ?? 0)) {
            throw new ApiException(403, 'Accès interdit à cette demande.');
        }
        if (!in_array($demande['statut'], ['BROUILLON', 'SOUMISE'], true)) {
            throw new ApiException(409, 'Seule une demande en brouillon ou soumise peut être modifiée.');
        }
        $entreprise = $this->entrepriseChoisissable($entrepriseId);
        $this->verifierFiche($data);
        $this->verifierDoublon((int) $user['etudiant_id'], $entrepriseId, $id);

        $this->pdo->beginTransaction();
        try {
            $this->demandes->updateDetails($id, $entrepriseId, $data);
            $this->audit->log((int) $user['id'], 'DEMANDE_MODIFIEE', 'demande', $id, [
                'entreprise' => $entreprise['raison_sociale'],
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if ($demande['statut'] === 'SOUMISE') {
            $this->notifierResponsables($id, 'Demande modifiée', $this->messageDemande($user, $entreprise['raison_sociale'], 'modifiée'));
        }
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function annuler(array $user, int $id, ?string $raison): array
    {
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $proprietaire = $user['role'] === 'ETUDIANT' && (int) $demande['etudiant_id'] === (int) ($user['etudiant_id'] ?? 0);
        if (!$proprietaire) {
            throw new ApiException(403, 'Seul l’étudiant propriétaire peut annuler cette demande.');
        }
        if (!in_array($demande['statut'], ['BROUILLON', 'SOUMISE'], true)) {
            throw new ApiException(409, 'Seule une demande en brouillon ou soumise peut être annulée.');
        }

        $this->pdo->beginTransaction();
        try {
            $courante = $this->demandes->findByIdForUpdate($id);
            if ($courante === null) {
                throw new ApiException(404, 'Demande introuvable.');
            }
            $this->demandes->updateStatus($id, 'ANNULEE', null, $courante['date_soumission'], date('Y-m-d H:i:s'), $raison);
            $this->historique->add($id, (string) $courante['statut'], 'ANNULEE', (int) $user['id'], $raison);
            $this->audit->log((int) $user['id'], 'DEMANDE_ANNULEE', 'demande', $id, ['raison' => $raison]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if ($demande['statut'] === 'SOUMISE') {
            $this->notifierResponsables($id, 'Demande annulée', 'Une demande soumise a été annulée par son étudiant.');
        }
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function changerStatut(array $user, int $id, string $cible, ?string $motif, ?string $commentaire = null): array
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
        $commentaire = $commentaire === null ? null : trim($commentaire);
        if ($commentaire !== null && mb_strlen($commentaire) > 500) {
            throw new ApiException(400, 'Données invalides.', ['commentaire' => 'Le commentaire est trop long (max 500).']);
        }

        $this->pdo->beginTransaction();
        try {
            $courante = $this->demandes->findByIdForUpdate($id);
            if ($courante === null) {
                throw new ApiException(404, 'Demande introuvable.');
            }
            if (!self::peutPasser((string) $courante['statut'], $cible)) {
                throw new ApiException(409, 'Transition interdite : ' . $courante['statut'] . ' vers ' . $cible . '.');
            }
            if ($cible === 'SOUMISE') {
                $this->verifierDoublon((int) $courante['etudiant_id'], (int) $courante['entreprise_id'], $id);
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

            $this->demandes->updateStatus($id, $cible, $motif, $dateSoumission, $dateDecision, $commentaire);
            $this->historique->add(
                $id,
                (string) $courante['statut'],
                $cible,
                (int) $user['id'],
                $motif ?? $commentaire
            );
            $this->audit->log((int) $user['id'], 'DEMANDE_STATUT', 'demande', $id, [
                'avant'       => $courante['statut'],
                'apres'       => $cible,
                'motif'       => $motif,
                'commentaire' => $commentaire,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifierChangementStatut($demande, $cible, $motif, $commentaire);
        return $this->detail($user, $id);
    }

    /** @return array{traites:int,echecs:array<int,string>} */
    public function changerStatutLot(array $user, array $ids, string $cible, ?string $motif, ?string $commentaire): array
    {
        if ($user['role'] !== 'RESPONSABLE') {
            throw new ApiException(403, 'Seul le responsable peut traiter un lot de demandes.');
        }
        if (!in_array($cible, ['VALIDEE', 'REFUSEE'], true)) {
            throw new ApiException(400, 'Action de lot invalide.');
        }
        $traites = 0;
        $echecs = [];
        foreach ($ids as $id) {
            try {
                $this->changerStatut($user, (int) $id, $cible, $motif, $commentaire);
                $traites++;
            } catch (ApiException $e) {
                $echecs[] = '#' . $id . ' : ' . $e->getMessage();
            }
        }
        return ['traites' => $traites, 'echecs' => $echecs];
    }

    /** @return array<string,mixed> */
    public function affecterEncadreur(array $user, int $id, int $encadreurId): array
    {
        if (!in_array($user['role'], ['SECRETARIAT', 'RESPONSABLE', 'ADMIN'], true)) {
            throw new ApiException(403, 'Action réservée au personnel pédagogique.');
        }
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if ($demande['statut'] !== 'VALIDEE') {
            throw new ApiException(409, 'Seule une demande validée peut recevoir un encadreur.');
        }
        $encadreur = $this->encadreurDisponible($encadreurId);

        if ($user['role'] === 'SECRETARIAT') {
            $this->demandes->proposerEncadreur($id, $encadreurId);
            $this->audit->log((int) $user['id'], 'ENCADREUR_PROPOSE', 'demande', $id, ['encadreur_id' => $encadreurId]);
            $this->notifierResponsables($id, 'Encadreur proposé', 'Le secrétariat propose ' . $encadreur['prenom'] . ' ' . $encadreur['nom'] . ' pour cette demande.');
            return $this->detail($user, $id);
        }

        $this->pdo->beginTransaction();
        try {
            $this->demandes->affecterEncadreur($id, $encadreurId);
            $this->audit->log((int) $user['id'], 'ENCADREUR_AFFECTE', 'demande', $id, ['encadreur_id' => $encadreurId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->notifierEncadreur($demande, $encadreurId, 'Nouvel étudiant encadré');
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function confirmerEncadreur(array $user, int $id): array
    {
        if (!in_array($user['role'], ['RESPONSABLE', 'ADMIN'], true)) {
            throw new ApiException(403, 'Seul le responsable valide le choix de l’encadreur.');
        }
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if ($demande['encadreur_propose_id'] === null) {
            throw new ApiException(409, 'Aucun encadreur n’est proposé.');
        }
        $encadreur = $this->encadreurDisponible((int) $demande['encadreur_propose_id']);
        $this->pdo->beginTransaction();
        try {
            $this->demandes->confirmerEncadreur($id);
            $this->audit->log((int) $user['id'], 'ENCADREUR_CONFIRME', 'demande', $id, ['encadreur_id' => $demande['encadreur_propose_id']]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->notifierEncadreur($demande, (int) $demande['encadreur_propose_id'], 'Encadrement confirmé');
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function rapport(array $user, int $id, bool $valide, ?string $commentaire): array
    {
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $autorise = in_array($user['role'], ['RESPONSABLE', 'ADMIN'], true)
            || ($user['role'] === 'ENCADREUR' && (int) $demande['encadreur_id'] === (int) ($user['encadreur_id'] ?? 0));
        if (!$autorise) {
            throw new ApiException(403, 'Seul l’encadreur ou le responsable valide le rapport.');
        }
        if ($demande['statut'] !== 'VALIDEE') {
            throw new ApiException(409, 'La demande doit être validée.');
        }
        if ($valide) {
            $pieces = $this->pieces->findByDemande($id);
            $aRapport = false;
            foreach ($pieces as $piece) {
                if ($piece['type'] === 'RAPPORT') {
                    $aRapport = true;
                    break;
                }
            }
            if (!$aRapport) {
                throw new ApiException(409, 'Aucun rapport n’a encore été déposé.');
            }
        }
        $this->demandes->updateRapport($id, $valide, (int) $user['id'], $commentaire);
        if ($commentaire !== null && trim($commentaire) !== '') {
            $this->commentaires->create($id, (int) $user['id'], trim($commentaire), 'RAPPORT');
        }
        $this->audit->log((int) $user['id'], $valide ? 'RAPPORT_VALIDE' : 'RAPPORT_REJETE', 'demande', $id, ['commentaire' => $commentaire]);
        $this->notifications->envoyer(
            (int) $demande['etudiant_utilisateur_id'],
            'RAPPORT',
            $valide ? 'Rapport de stage validé' : 'Rapport de stage à corriger',
            $commentaire ?: ($valide ? 'Votre rapport a été validé.' : 'Votre rapport doit être corrigé.'),
            $id,
            (string) $demande['etudiant_email']
        );
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function noter(array $user, int $id, float $note, ?string $mention, ?string $appreciation): array
    {
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $autorise = in_array($user['role'], ['RESPONSABLE', 'ADMIN'], true)
            || ($user['role'] === 'ENCADREUR' && (int) $demande['encadreur_id'] === (int) ($user['encadreur_id'] ?? 0));
        if (!$autorise) {
            throw new ApiException(403, 'Seul l’encadreur ou le responsable saisit la note.');
        }
        $soutenance = $this->soutenances->findByDemande($id);
        if ($soutenance === null) {
            throw new ApiException(409, 'La soutenance doit être programmée avant la saisie de la note.');
        }
        $mention = $mention ?: $this->mentionAutomatique($note);
        $this->demandes->updateNote($id, $note, $mention, $appreciation);
        $this->audit->log((int) $user['id'], 'NOTE_SAISIE', 'demande', $id, ['note' => $note, 'mention' => $mention]);
        $this->notifications->envoyer(
            (int) $demande['etudiant_utilisateur_id'],
            'NOTE',
            'Note de stage disponible',
            'Votre note de stage est disponible : ' . number_format($note, 2, ',', ' ') . '/20 (' . $mention . ').',
            $id,
            (string) $demande['etudiant_email']
        );
        return $this->detail($user, $id);
    }

    /** @return array<string,mixed> */
    public function commenter(array $user, int $id, string $contenu): array
    {
        $demande = $this->demandes->findById($id);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        $this->autoriserLecture($user, $demande);
        $contenu = trim($contenu);
        if ($contenu === '' || mb_strlen($contenu) > 2000) {
            throw new ApiException(400, 'Commentaire invalide.', ['contenu' => 'Le commentaire doit contenir entre 1 et 2000 caractères.']);
        }
        $this->commentaires->create($id, (int) $user['id'], $contenu);
        $this->audit->log((int) $user['id'], 'COMMENTAIRE_AJOUTE', 'demande', $id);
        return $this->detail($user, $id);
    }

    /** @return array<int,array<string,mixed>> */
    public function historiqueDe(array $user, int $id): array
    {
        $this->detail($user, $id);
        return $this->historique->findByDemande($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function etapes(array $demande): array
    {
        $soutenance = $this->soutenances->findByDemande((int) $demande['id']);
        $statut = (string) $demande['statut'];
        $etapes = [
            ['cle' => 'BROUILLON', 'libelle' => 'Brouillon', 'fait' => true, 'date' => null],
            ['cle' => 'SOUMISE', 'libelle' => 'Soumise', 'fait' => $demande['date_soumission'] !== null, 'date' => $demande['date_soumission']],
            ['cle' => 'VALIDEE', 'libelle' => 'Validée', 'fait' => $statut === 'VALIDEE', 'date' => $demande['date_decision']],
            ['cle' => 'ENCADREUR', 'libelle' => 'Encadreur affecté', 'fait' => $demande['encadreur_id'] !== null, 'date' => null],
            ['cle' => 'SOUTENANCE', 'libelle' => 'Soutenance programmée', 'fait' => $soutenance !== null, 'date' => $soutenance['date_heure'] ?? null],
        ];
        if ($statut === 'REFUSEE') {
            $etapes[2]['libelle'] = 'Refusée';
            $etapes[2]['fait'] = true;
            $etapes[2]['refus'] = true;
            $etapes[3]['fait'] = false;
            $etapes[4]['fait'] = false;
        }
        if ($statut === 'ANNULEE') {
            $etapes[1]['libelle'] = 'Annulée';
            $etapes[1]['fait'] = true;
            $etapes[1]['refus'] = true;
            $etapes[2]['fait'] = false;
            $etapes[3]['fait'] = false;
            $etapes[4]['fait'] = false;
        }
        return $etapes;
    }

    /** @return array<string,mixed> */
    private function entrepriseChoisissable(int $entrepriseId): array
    {
        $entreprise = $this->entreprises->findById($entrepriseId);
        if ($entreprise === null) {
            throw new ApiException(400, 'Données invalides.', ['entrepriseId' => 'Entreprise inconnue.']);
        }
        if ($entreprise['statut'] === 'INTERDITE') {
            throw new ApiException(409, 'Cette entreprise figure sur la liste noire.', ['entrepriseId' => 'Choisissez une entreprise partenaire.']);
        }
        if ($entreprise['statut'] !== 'PARTENAIRE') {
            throw new ApiException(409, 'Cette entreprise n’est pas encore validée.', ['entrepriseId' => 'Attendez la validation du responsable.']);
        }
        return $entreprise;
    }

    private function verifierDoublon(int $etudiantId, int $entrepriseId, ?int $saufId = null): void
    {
        $existante = $this->demandes->findActiveDuplicate($etudiantId, $entrepriseId, $saufId);
        if ($existante !== null) {
            throw new ApiException(409, 'Une demande active existe déjà pour cette entreprise.', [
                'entrepriseId' => 'Demande #' . $existante . ' déjà active.',
            ]);
        }
    }

    /** @param array<string,mixed> $data */
    private function verifierFiche(array $data): void
    {
        if (isset($data['dateDebut'], $data['dateFin']) && $data['dateDebut'] !== null && $data['dateFin'] !== null && $data['dateFin'] < $data['dateDebut']) {
            throw new ApiException(400, 'Données invalides.', ['dateFin' => 'La date de fin doit suivre la date de début.']);
        }
        if (isset($data['tuteurEmail']) && $data['tuteurEmail'] !== null && !filter_var($data['tuteurEmail'], FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(400, 'Données invalides.', ['tuteurEmail' => 'Adresse du tuteur invalide.']);
        }
    }

    private function encadreurDisponible(int $encadreurId): array
    {
        $encadreur = $this->utilisateurs->findEncadreurById($encadreurId);
        if ($encadreur === null || (int) $encadreur['actif'] !== 1) {
            throw new ApiException(400, 'Données invalides.', ['encadreurId' => 'Encadreur inconnu ou inactif.']);
        }
        if ((int) $encadreur['charge'] >= (int) $encadreur['charge_max']) {
            throw new ApiException(409, 'Cet encadreur a atteint sa charge maximale.', ['encadreurId' => 'Choisissez un autre encadreur.']);
        }
        return $encadreur;
    }

    private function notifierChangementStatut(array $demande, string $cible, ?string $motif, ?string $commentaire): void
    {
        $titre = match ($cible) {
            'SOUMISE'  => 'Demande soumise',
            'VALIDEE'  => 'Demande validée',
            'REFUSEE'  => 'Demande refusée',
            'BROUILLON'=> 'Demande à corriger',
            'ANNULEE'  => 'Demande annulée',
            default    => 'Demande mise à jour',
        };
        $message = match ($cible) {
            'SOUMISE'  => 'Votre demande a été soumise au responsable pédagogique.',
            'VALIDEE'  => 'Votre demande a été validée.' . ($commentaire ? ' ' . $commentaire : ''),
            'REFUSEE'  => 'Votre demande a été refusée. Motif : ' . $motif,
            'BROUILLON'=> 'Votre demande repasse en brouillon : corrigez-la puis soumettez-la à nouveau.',
            'ANNULEE'  => 'Votre demande a été annulée.',
            default    => 'Le statut de votre demande a changé.',
        };
        $this->notifications->envoyer(
            (int) $demande['etudiant_utilisateur_id'],
            'DEMANDE',
            $titre,
            $message,
            (int) $demande['id'],
            (string) $demande['etudiant_email']
        );

        if ($cible === 'SOUMISE') {
            $this->notifierResponsables((int) $demande['id'], 'Demande soumise', $demande['etudiant_prenom'] . ' ' . $demande['etudiant_nom'] . ' a soumis une demande.');
        }
        if ($cible === 'VALIDEE' || $cible === 'REFUSEE') {
            $this->notifierRole('SECRETARIAT', (int) $demande['id'], 'Décision de stage', $titre . ' pour ' . $demande['etudiant_prenom'] . ' ' . $demande['etudiant_nom'] . '.');
        }
    }

    private function notifierResponsables(int $demandeId, string $titre, string $message): void
    {
        $this->notifierRole('RESPONSABLE', $demandeId, $titre, $message);
    }

    private function notifierRole(string $role, int $demandeId, string $titre, string $message): void
    {
        foreach ($this->utilisateurs->listerParRole($role) as $compte) {
            $this->notifications->envoyer(
                (int) $compte['id'],
                'DEMANDE',
                $titre,
                $message,
                $demandeId,
                (string) $compte['email']
            );
        }
    }

    private function notifierEncadreur(array $demande, int $encadreurId, string $titre): void
    {
        $encadreur = $this->utilisateurs->findEncadreurById($encadreurId);
        if ($encadreur === null) {
            return;
        }
        $this->notifications->envoyer(
            (int) $encadreur['utilisateur_id'],
            'ENCADREMENT',
            $titre,
            'Vous encadrez ' . $demande['etudiant_prenom'] . ' ' . $demande['etudiant_nom'] . ' (' . $demande['entreprise'] . ').',
            (int) $demande['id'],
            (string) $encadreur['email']
        );
    }

    private function messageDemande(array $user, string $entreprise, string $action): string
    {
        return $user['prenom'] . ' ' . $user['nom'] . ' a ' . $action . ' une demande pour ' . $entreprise . '.';
    }

    private function mentionAutomatique(float $note): string
    {
        if ($note >= 16) {
            return 'Très bien';
        }
        if ($note >= 14) {
            return 'Bien';
        }
        if ($note >= 12) {
            return 'Assez bien';
        }
        if ($note >= 10) {
            return 'Passable';
        }
        return 'Insuffisant';
    }

    private function scope(array $user): array
    {
        return match ($user['role']) {
            'ETUDIANT'  => ['etudiant_id' => (int) $this->exigerEtudiant($user)['etudiant_id']],
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
        throw new ApiException(403, 'Accès interdit à ce dossier.');
    }

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
        if ($cible === 'ANNULEE') {
            if (!$proprietaire) {
                throw new ApiException(403, 'Seul l’étudiant propriétaire peut annuler ce dossier.');
            }
            return;
        }
        if ($user['role'] !== 'RESPONSABLE') {
            throw new ApiException(403, 'Seul le responsable pédagogique peut valider ou refuser.');
        }
    }

    private function exigerEtudiant(array $user): array
    {
        if ($user['role'] !== 'ETUDIANT' || empty($user['etudiant_id'])) {
            throw new ApiException(403, 'Action réservée à un compte étudiant.');
        }
        return $user;
    }
}
