<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use App\Repositories\SoutenanceRepository;
use PDOException;

/**
 * Programmation des soutenances : uniquement pour une demande validée,
 * avec contrôle de créneau assuré par la base.
 */
final class SoutenanceService
{
    public function __construct(
        private readonly DemandeRepository $demandes,
        private readonly SoutenanceRepository $soutenances
    ) {
    }

    /**
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public function lister(array $user): array
    {
        $scope = match ($user['role']) {
            'ETUDIANT'  => ['etudiant_id' => (int) ($user['etudiant_id'] ?? 0)],
            'ENCADREUR' => ['encadreur_id' => (int) ($user['encadreur_id'] ?? 0)],
            default     => [],
        };
        return $this->soutenances->search($scope);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function programmer(
        array $user,
        int $demandeId,
        string $dateHeure,
        string $salle,
        int $dureeMin
    ): array {
        if (!in_array($user['role'], ['SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Seul le secrétariat programme les soutenances.');
        }

        $demande = $this->demandes->findById($demandeId);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if ($demande['statut'] !== 'VALIDEE') {
            throw new ApiException(409, 'Seule une demande validée peut être programmée.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateHeure);
        if ($date === false || $date <= new \DateTimeImmutable()) {
            throw new ApiException(400, 'Données invalides.', ['dateHeure' => 'La date de soutenance doit être future.']);
        }
        if (preg_match('/^[A-Za-z0-9À-ÿ \-]{1,40}$/', $salle) !== 1) {
            throw new ApiException(400, 'Données invalides.', ['salle' => 'Nom de salle invalide.']);
        }
        if ($dureeMin < 20 || $dureeMin > 90) {
            throw new ApiException(400, 'Données invalides.', ['dureeMin' => 'La durée doit être comprise entre 20 et 90 minutes.']);
        }

        if ($this->soutenances->findByDemande($demandeId) !== null) {
            throw new ApiException(409, 'Cette demande a déjà une soutenance programmée.');
        }

        try {
            $id = $this->soutenances->create($demandeId, $dateHeure, $salle, $dureeMin);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ApiException(409, 'Créneau déjà occupé dans cette salle.');
            }
            throw $e;
        }

        return [
            'id'         => $id,
            'demande_id' => $demandeId,
            'date_heure' => $dateHeure,
            'salle'      => $salle,
            'duree_min'  => $dureeMin,
        ];
    }
}
