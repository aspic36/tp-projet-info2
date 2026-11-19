<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use App\Repositories\PieceRepository;
use App\Support\Audit;
use App\Support\Config;

final class PieceService
{
    private const TYPES = [
        'CONVENTION',
        'LETTRE_ACCEPTATION',
        'CV',
        'RAPPORT',
        'DOCUMENT_SOUTENANCE',
        'AUTRE',
    ];

    public function __construct(
        private readonly DemandeRepository $demandes,
        private readonly PieceRepository $pieces,
        private readonly Audit $audit
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function lister(array $user, int $demandeId): array
    {
        $this->demandeAutorisee($user, $demandeId);
        return $this->pieces->findByDemande($demandeId);
    }

    /** @return array<string,mixed> */
    public function televerser(array $user, int $demandeId, string $type, array $file): array
    {
        $demande = $this->demandeModifiable($user, $demandeId);
        if (!in_array($type, self::TYPES, true)) {
            throw new ApiException(400, 'Type de pièce invalide.', ['type' => 'Type inconnu.']);
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ApiException(400, 'Téléversement impossible.', ['fichier' => 'Aucun fichier valide reçu.']);
        }
        $taille = (int) ($file['size'] ?? 0);
        $max = (int) Config::get('upload.max_bytes', 5 * 1024 * 1024);
        if ($taille < 1 || $taille > $max) {
            throw new ApiException(400, 'Fichier trop volumineux.', ['fichier' => 'Taille maximale : ' . round($max / 1048576, 1) . ' Mo.']);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new ApiException(400, 'Téléversement invalide.', ['fichier' => 'Fichier temporaire absent.']);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            throw new ApiException(400, 'Format non accepté.', ['fichier' => 'Seuls les fichiers PDF sont acceptés.']);
        }

        $nomOriginal = basename((string) ($file['name'] ?? 'document.pdf'));
        $nomOriginal = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nomOriginal) ?: 'document.pdf';
        $dossier = rtrim((string) Config::get('storage.pieces'), '/') . '/' . $demandeId;
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new ApiException(500, 'Stockage indisponible.');
        }
        $nomFichier = bin2hex(random_bytes(16)) . '.pdf';
        $cheminAbsolu = $dossier . '/' . $nomFichier;
        if (!move_uploaded_file($tmp, $cheminAbsolu)) {
            throw new ApiException(500, 'Copie du fichier impossible.');
        }
        $cheminRelatif = 'storage/pieces/' . $demandeId . '/' . $nomFichier;
        $hash = hash_file('sha256', $cheminAbsolu) ?: '';

        try {
            $id = $this->pieces->create(
                $demandeId,
                $type,
                $nomOriginal,
                $cheminRelatif,
                $mime,
                $taille,
                $hash,
                (int) $user['id']
            );
        } catch (\Throwable $e) {
            @unlink($cheminAbsolu);
            throw $e;
        }

        $this->audit->log((int) $user['id'], 'PIECE_AJOUTEE', 'piece', $id, [
            'demande_id' => $demandeId,
            'type'       => $type,
            'nom'        => $nomOriginal,
        ]);

        return $this->pieces->findById($id) ?? throw new ApiException(500, 'Pièce non relue.');
    }

    /** @return array<string,mixed> */
    public function telecharger(array $user, int $pieceId): array
    {
        $piece = $this->pieces->findById($pieceId);
        if ($piece === null) {
            throw new ApiException(404, 'Pièce jointe introuvable.');
        }
        $this->demandeAutorisee($user, (int) $piece['demande_id']);
        $chemin = dirname(__DIR__, 2) . '/' . $piece['chemin'];
        if (!is_file($chemin)) {
            throw new ApiException(404, 'Fichier introuvable.');
        }
        $piece['chemin_absolu'] = $chemin;
        return $piece;
    }

    /** @return array<string,mixed> */
    public function changerStatut(array $user, int $pieceId, string $statut, ?string $commentaire): array
    {
        if (!in_array($user['role'], ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Seul le personnel peut valider une pièce jointe.');
        }
        if (!in_array($statut, ['EN_ATTENTE', 'VALIDE', 'REJETE'], true)) {
            throw new ApiException(400, 'Statut de pièce invalide.');
        }
        $piece = $this->pieces->findById($pieceId);
        if ($piece === null) {
            throw new ApiException(404, 'Pièce jointe introuvable.');
        }
        $this->demandeAutorisee($user, (int) $piece['demande_id']);
        $this->pieces->changerStatut($pieceId, $statut, $commentaire);
        $this->audit->log((int) $user['id'], 'PIECE_STATUT', 'piece', $pieceId, [
            'statut' => $statut,
            'commentaire' => $commentaire,
        ]);
        return $this->pieces->findById($pieceId) ?? throw new ApiException(500, 'Pièce non relue.');
    }

    public function supprimer(array $user, int $pieceId): void
    {
        $piece = $this->pieces->findById($pieceId);
        if ($piece === null) {
            throw new ApiException(404, 'Pièce jointe introuvable.');
        }
        $demande = $this->demandeModifiable($user, (int) $piece['demande_id']);
        if ((int) $piece['depose_par'] !== (int) $user['id'] && !in_array($user['role'], ['ADMIN', 'SECRETARIAT'], true)) {
            throw new ApiException(403, 'Vous ne pouvez pas supprimer cette pièce.');
        }
        if (in_array($demande['statut'], ['VALIDEE', 'REFUSEE', 'ANNULEE'], true) && $user['role'] === 'ETUDIANT') {
            throw new ApiException(409, 'La demande n’est plus modifiable.');
        }
        $chemin = dirname(__DIR__, 2) . '/' . $piece['chemin'];
        $this->pieces->supprimer($pieceId);
        if (is_file($chemin)) {
            @unlink($chemin);
        }
        $this->audit->log((int) $user['id'], 'PIECE_SUPPRIMEE', 'piece', $pieceId, [
            'demande_id' => $piece['demande_id'],
        ]);
    }

    /** @return array<string,mixed> */
    private function demandeAutorisee(array $user, int $demandeId): array
    {
        $demande = $this->demandes->findById($demandeId);
        if ($demande === null) {
            throw new ApiException(404, 'Demande introuvable.');
        }
        if (in_array($user['role'], ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            return $demande;
        }
        if ($user['role'] === 'ETUDIANT' && (int) $demande['etudiant_id'] === (int) ($user['etudiant_id'] ?? 0)) {
            return $demande;
        }
        if ($user['role'] === 'ENCADREUR' && (int) $demande['encadreur_id'] === (int) ($user['encadreur_id'] ?? 0)) {
            return $demande;
        }
        throw new ApiException(403, 'Accès interdit à cette demande.');
    }

    /** @return array<string,mixed> */
    private function demandeModifiable(array $user, int $demandeId): array
    {
        $demande = $this->demandeAutorisee($user, $demandeId);
        if ($user['role'] !== 'ETUDIANT') {
            return $demande;
        }
        if ((int) $demande['etudiant_id'] !== (int) ($user['etudiant_id'] ?? 0)) {
            throw new ApiException(403, 'Accès interdit à cette demande.');
        }
        if (!in_array($demande['statut'], ['BROUILLON', 'SOUMISE', 'VALIDEE'], true)) {
            throw new ApiException(409, 'La demande n’est plus modifiable.');
        }
        return $demande;
    }
}
