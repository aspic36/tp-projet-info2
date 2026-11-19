<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\EntrepriseRepository;
use App\Repositories\UtilisateurRepository;
use App\Support\Audit;

final class EntrepriseService
{
    public function __construct(
        private readonly EntrepriseRepository $entreprises,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly NotificationService $notifications,
        private readonly Audit $audit
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function lister(array $user, ?string $statut = null): array
    {
        if ($user['role'] === 'ETUDIANT') {
            return $this->entreprises->findPartenaires();
        }
        return $this->entreprises->findAll($statut);
    }

    /** @return array<string,mixed> */
    public function proposer(array $user, array $data): array
    {
        if ($this->entreprises->existe($data['raisonSociale'], $data['ville'])) {
            throw new ApiException(409, 'Cette entreprise existe déjà dans le référentiel.');
        }
        $id = $this->entreprises->create($data, (int) $user['id'], 'A_VALIDER');
        $this->audit->log((int) $user['id'], 'ENTREPRISE_PROPOSEE', 'entreprise', $id, [
            'raison_sociale' => $data['raisonSociale'],
            'ville'          => $data['ville'],
        ]);

        foreach ($this->utilisateurs->listerParRole('RESPONSABLE') as $responsable) {
            $this->notifications->envoyer(
                (int) $responsable['id'],
                'ENTREPRISE_A_VALIDER',
                'Nouvelle entreprise à valider',
                $data['raisonSociale'] . ' (' . $data['ville'] . ') proposée par ' . $user['prenom'] . ' ' . $user['nom'] . '.',
                null,
                (string) $responsable['email']
            );
        }

        return $this->entreprises->findById($id) ?? throw new ApiException(500, 'Entreprise non relue.');
    }

    /** @return array<string,mixed> */
    public function decider(array $user, int $id, string $statut, ?string $motif): array
    {
        if (!in_array($user['role'], ['RESPONSABLE', 'ADMIN'], true)) {
            throw new ApiException(403, 'Seul le responsable peut gérer la liste des entreprises.');
        }
        if (!in_array($statut, ['PARTENAIRE', 'INTERDITE'], true)) {
            throw new ApiException(400, 'Statut d’entreprise invalide.');
        }
        $entreprise = $this->entreprises->findById($id);
        if ($entreprise === null) {
            throw new ApiException(404, 'Entreprise introuvable.');
        }
        if ($statut === 'INTERDITE' && ($motif === null || trim($motif) === '')) {
            throw new ApiException(400, 'Données invalides.', ['motif' => 'Le motif de mise en liste noire est obligatoire.']);
        }

        $this->entreprises->updateStatut($id, $statut, $motif);
        $this->audit->log((int) $user['id'], 'ENTREPRISE_STATUT', 'entreprise', $id, [
            'statut' => $statut,
            'motif'  => $motif,
        ]);

        if ($entreprise['cree_par'] !== null) {
            $this->notifications->envoyer(
                (int) $entreprise['cree_par'],
                'ENTREPRISE_DECISION',
                'Décision sur votre proposition d’entreprise',
                $entreprise['raison_sociale'] . ' est maintenant ' . ($statut === 'PARTENAIRE' ? 'partenaire.' : 'interdite.'),
                null
            );
        }

        return $this->entreprises->findById($id) ?? throw new ApiException(500, 'Entreprise non relue.');
    }

    /** @return array<string,mixed> */
    public function creer(array $user, array $data, string $statut): array
    {
        if (!in_array($user['role'], ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Action réservée au personnel.');
        }
        if (!in_array($statut, ['PARTENAIRE', 'A_VALIDER', 'INTERDITE'], true)) {
            throw new ApiException(400, 'Statut d’entreprise invalide.');
        }
        if ($this->entreprises->existe($data['raisonSociale'], $data['ville'])) {
            throw new ApiException(409, 'Cette entreprise existe déjà dans le référentiel.');
        }
        $id = $this->entreprises->create($data, (int) $user['id'], $statut);
        $this->audit->log((int) $user['id'], 'ENTREPRISE_CREEE', 'entreprise', $id, [
            'statut' => $statut,
        ]);
        return $this->entreprises->findById($id) ?? throw new ApiException(500, 'Entreprise non relue.');
    }
}
