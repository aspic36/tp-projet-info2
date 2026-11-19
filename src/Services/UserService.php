<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\UtilisateurRepository;
use App\Support\Audit;
use App\Support\Config;
use PDO;

final class UserService
{
    private const ROLES = ['ETUDIANT', 'SECRETARIAT', 'RESPONSABLE', 'ENCADREUR', 'ADMIN'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly NotificationService $notifications,
        private readonly MailService $mail,
        private readonly Audit $audit
    ) {
    }

    /** @return array<string,mixed> */
    public function profil(array $user): array
    {
        return $this->utilisateurs->findCompteAvecProfil((int) $user['id'])
            ?? throw new ApiException(404, 'Compte introuvable.');
    }

    /** @return array<string,mixed> */
    public function mettreAJourProfil(array $user, string $prenom, string $nom, string $email, ?string $telephone): array
    {
        if ($this->utilisateurs->emailExiste($email, (int) $user['id'])) {
            throw new ApiException(409, 'Cette adresse e-mail est déjà utilisée.');
        }
        $this->utilisateurs->mettreAJourProfil((int) $user['id'], $prenom, $nom, $email, $telephone);
        $this->audit->log((int) $user['id'], 'PROFIL_MODIFIE', 'utilisateur', (int) $user['id']);
        return $this->profil($user);
    }

    public function changerMotDePasse(array $user, string $actuel, string $nouveau): void
    {
        if (mb_strlen($nouveau) < 10) {
            throw new ApiException(400, 'Données invalides.', ['nouveauMotDePasse' => 'Le nouveau mot de passe doit contenir au moins 10 caractères.']);
        }
        if (!preg_match('/[A-Z]/', $nouveau) || !preg_match('/[a-z]/', $nouveau) || !preg_match('/\d/', $nouveau)) {
            throw new ApiException(400, 'Données invalides.', ['nouveauMotDePasse' => 'Ajoutez au moins une majuscule, une minuscule et un chiffre.']);
        }
        $compte = $this->utilisateurs->findByEmail((string) $user['email']);
        if ($compte === null || !password_verify($actuel, (string) $compte['mot_de_passe'])) {
            throw new ApiException(400, 'Données invalides.', ['motDePasseActuel' => 'Le mot de passe actuel est incorrect.']);
        }
        $this->utilisateurs->mettreAJourMotDePasse((int) $user['id'], password_hash($nouveau, PASSWORD_DEFAULT));
        $this->audit->log((int) $user['id'], 'MOT_DE_PASSE_MODIFIE', 'utilisateur', (int) $user['id']);
    }

    public function demanderReinitialisation(string $email): void
    {
        $compte = $this->utilisateurs->findByEmail($email);
        if ($compte === null || (int) $compte['actif'] !== 1) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expire = (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO reinitialisation_mot_de_passe (utilisateur_id, token_hash, expire_le)
             VALUES (:utilisateur_id, :token_hash, :expire_le)'
        );
        $stmt->execute([
            ':utilisateur_id' => (int) $compte['id'],
            ':token_hash'     => $hash,
            ':expire_le'      => $expire,
        ]);
        $lien = rtrim((string) Config::get('app.url'), '/') . '/?reset=' . $token;
        $this->mail->envoyer(
            $email,
            'Réinitialisation de votre mot de passe',
            "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien dans l’heure :\n$lien\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message."
        );
        $this->audit->log((int) $compte['id'], 'REINITIALISATION_DEMANDEE', 'utilisateur', (int) $compte['id']);
    }

    public function reinitialiser(string $token, string $nouveau): void
    {
        if (mb_strlen($nouveau) < 10 || !preg_match('/[A-Z]/', $nouveau) || !preg_match('/[a-z]/', $nouveau) || !preg_match('/\d/', $nouveau)) {
            throw new ApiException(400, 'Données invalides.', ['motDePasse' => 'Le mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule et un chiffre.']);
        }
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT id, utilisateur_id FROM reinitialisation_mot_de_passe
             WHERE token_hash = :hash AND utilise_le IS NULL AND expire_le > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $ligne = $stmt->fetch();
        if ($ligne === false) {
            throw new ApiException(400, 'Lien de réinitialisation invalide ou expiré.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->utilisateurs->mettreAJourMotDePasse((int) $ligne['utilisateur_id'], password_hash($nouveau, PASSWORD_DEFAULT));
            $maj = $this->pdo->prepare(
                'UPDATE reinitialisation_mot_de_passe SET utilise_le = NOW() WHERE utilisateur_id = :uid AND utilise_le IS NULL'
            );
            $maj->execute([':uid' => $ligne['utilisateur_id']]);
            $this->audit->log((int) $ligne['utilisateur_id'], 'MOT_DE_PASSE_REINITIALISE', 'utilisateur', (int) $ligne['utilisateur_id']);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listerComptes(array $user): array
    {
        $this->exigerGestion($user);
        return $this->utilisateurs->listerComptes();
    }

    /** @return array<int,array<string,mixed>> */
    public function relances(array $user): array
    {
        $this->exigerGestion($user);
        return $this->utilisateurs->listerEtudiantsSansDemande();
    }

    /** @return array<string,mixed> */
    public function creerCompte(array $user, array $data): array
    {
        $this->exigerGestion($user);
        $role = (string) ($data['role'] ?? 'ETUDIANT');
        if (!in_array($role, self::ROLES, true)) {
            throw new ApiException(400, 'Rôle invalide.');
        }
        if ($role === 'ADMIN' && $user['role'] !== 'ADMIN') {
            throw new ApiException(403, 'Seul un administrateur crée un administrateur.');
        }
        $email = (string) $data['email'];
        if ($this->utilisateurs->emailExiste($email)) {
            throw new ApiException(409, 'Cette adresse e-mail existe déjà.');
        }
        $motDePasse = (string) ($data['motDePasse'] ?? 'Stage2026!');
        if (mb_strlen($motDePasse) < 8) {
            throw new ApiException(400, 'Données invalides.', ['motDePasse' => 'Mot de passe trop court.']);
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->utilisateurs->creerCompte(
                $email,
                password_hash($motDePasse, PASSWORD_DEFAULT),
                $role,
                (string) $data['prenom'],
                (string) $data['nom'],
                $data['telephone'] ?? null
            );
            if ($role === 'ETUDIANT') {
                $this->utilisateurs->creerEtudiant(
                    $id,
                    (string) $data['matricule'],
                    (string) $data['filiere'],
                    (string) $data['promotion']
                );
            }
            if ($role === 'ENCADREUR') {
                $this->utilisateurs->creerEncadreur(
                    $id,
                    (string) $data['grade'],
                    (string) $data['departement'],
                    (int) ($data['chargeMax'] ?? 5)
                );
            }
            $this->audit->log((int) $user['id'], 'UTILISATEUR_CREE', 'utilisateur', $id, ['role' => $role]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifications->envoyer(
            $id,
            'BIENVENUE',
            'Votre compte est créé',
            'Votre compte de gestion des stages est actif. Changez votre mot de passe après la première connexion.',
            null,
            $email
        );

        return $this->utilisateurs->findCompteAvecProfil($id)
            ?? throw new ApiException(500, 'Compte non relu.');
    }

    public function changerActif(array $user, int $id, bool $actif): void
    {
        $this->exigerGestion($user);
        if ($id === (int) $user['id'] && !$actif) {
            throw new ApiException(409, 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($this->utilisateurs->findById($id) === null) {
            throw new ApiException(404, 'Compte introuvable.');
        }
        $this->utilisateurs->changerActif($id, $actif);
        $this->audit->log((int) $user['id'], $actif ? 'UTILISATEUR_ACTIVE' : 'UTILISATEUR_DESACTIVE', 'utilisateur', $id);
    }

    /** @return array{crees:int,ignores:int,erreurs:array<int,string>} */
    public function importerEtudiants(array $user, array $file): array
    {
        $this->exigerGestion($user);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ApiException(400, 'Fichier CSV invalide.');
        }
        $handle = fopen((string) $file['tmp_name'], 'rb');
        if ($handle === false) {
            throw new ApiException(400, 'Fichier CSV illisible.');
        }
        $entete = fgetcsv($handle);
        if ($entete === false) {
            fclose($handle);
            throw new ApiException(400, 'Fichier CSV vide.');
        }
        $entete = array_map(static fn ($v): string => strtolower(trim((string) $v)), $entete);
        $requis = ['email', 'prenom', 'nom', 'matricule', 'filiere', 'promotion'];
        foreach ($requis as $colonne) {
            if (!in_array($colonne, $entete, true)) {
                fclose($handle);
                throw new ApiException(400, 'Colonne manquante dans le CSV : ' . $colonne . '.');
            }
        }

        $crees = 0;
        $ignores = 0;
        $erreurs = [];
        $ligneNum = 1;
        while (($ligne = fgetcsv($handle)) !== false) {
            $ligneNum++;
            if ($ligne === [null] || count(array_filter($ligne, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($entete as $index => $colonne) {
                $row[$colonne] = trim((string) ($ligne[$index] ?? ''));
            }
            try {
                if ($this->utilisateurs->emailExiste($row['email']) || $this->utilisateurs->matriculeExiste($row['matricule'])) {
                    $ignores++;
                    continue;
                }
                $this->creerCompte($user, [
                    'role'       => 'ETUDIANT',
                    'email'      => $row['email'],
                    'prenom'     => $row['prenom'],
                    'nom'        => $row['nom'],
                    'telephone'  => $row['telephone'] ?? null,
                    'matricule'  => $row['matricule'],
                    'filiere'    => $row['filiere'],
                    'promotion'  => $row['promotion'],
                    'motDePasse' => $row['mot_de_passe'] ?? 'Stage2026!',
                ]);
                $crees++;
            } catch (\Throwable $e) {
                $erreurs[] = 'Ligne ' . $ligneNum . ' : ' . $e->getMessage();
            }
        }
        fclose($handle);
        return ['crees' => $crees, 'ignores' => $ignores, 'erreurs' => $erreurs];
    }

    private function exigerGestion(array $user): void
    {
        if (!in_array($user['role'], ['SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Action réservée au secrétariat ou à l’administration.');
        }
    }
}
