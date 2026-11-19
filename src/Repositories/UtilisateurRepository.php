<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UtilisateurRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, mot_de_passe, role, prenom, nom, telephone, actif
             FROM utilisateur WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, role, prenom, nom, telephone, actif, date_creation
             FROM utilisateur WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findEtudiant(int $utilisateurId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, utilisateur_id, matricule, filiere, promotion
             FROM etudiant WHERE utilisateur_id = :uid'
        );
        $stmt->execute([':uid' => $utilisateurId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findEncadreur(int $utilisateurId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, utilisateur_id, grade, departement, charge_max
             FROM encadreur WHERE utilisateur_id = :uid'
        );
        $stmt->execute([':uid' => $utilisateurId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listerEncadreurs(): array
    {
        $sql = 'SELECT enc.id, enc.grade, enc.departement, enc.charge_max,
                       u.prenom, u.nom, u.email,
                       (SELECT COUNT(*) FROM demande_stage d
                        WHERE d.encadreur_id = enc.id AND d.statut = \'VALIDEE\') AS charge
                FROM encadreur enc
                JOIN utilisateur u ON u.id = enc.utilisateur_id
                WHERE u.actif = 1
                ORDER BY u.nom, u.prenom';
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['charge_max'] = (int) $row['charge_max'];
            $row['charge'] = (int) $row['charge'];
            $row['disponible'] = $row['charge'] < $row['charge_max'];
            return $row;
        }, $rows);
    }

    /** @return array<string,mixed>|null */
    public function findEncadreurById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT enc.id, enc.utilisateur_id, enc.grade, enc.departement, enc.charge_max,
                    u.prenom, u.nom, u.email, u.actif,
                    (SELECT COUNT(*) FROM demande_stage d
                     WHERE d.encadreur_id = enc.id AND d.statut = \'VALIDEE\') AS charge
             FROM encadreur enc
             JOIN utilisateur u ON u.id = enc.utilisateur_id
             WHERE enc.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['utilisateur_id'] = (int) $row['utilisateur_id'];
        $row['charge_max'] = (int) $row['charge_max'];
        $row['charge'] = (int) $row['charge'];
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function listerParRole(string $role): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, prenom, nom, role FROM utilisateur WHERE role = :role AND actif = 1'
        );
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listerComptes(): array
    {
        $sql = 'SELECT id, email, role, prenom, nom, telephone, actif, date_creation
                FROM utilisateur ORDER BY role, nom, prenom';
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listerEtudiants(): array
    {
        $sql = 'SELECT e.id, e.utilisateur_id, e.matricule, e.filiere, e.promotion,
                       u.prenom, u.nom, u.email, u.telephone, u.actif
                FROM etudiant e JOIN utilisateur u ON u.id = e.utilisateur_id
                ORDER BY u.nom, u.prenom';
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listerEtudiantsSansDemande(): array
    {
        $sql = 'SELECT e.id, e.matricule, e.filiere, e.promotion,
                       u.prenom, u.nom, u.email
                FROM etudiant e
                JOIN utilisateur u ON u.id = e.utilisateur_id
                WHERE u.actif = 1
                  AND NOT EXISTS (
                    SELECT 1 FROM demande_stage d
                    WHERE d.etudiant_id = e.id
                      AND d.date_soumission IS NOT NULL
                      AND d.statut <> \'ANNULEE\'
                  )
                ORDER BY u.nom, u.prenom';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function emailExiste(string $email, ?int $saufId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM utilisateur WHERE email = :email';
        $params = [':email' => $email];
        if ($saufId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $saufId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function matriculeExiste(string $matricule): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM etudiant WHERE matricule = :matricule');
        $stmt->execute([':matricule' => $matricule]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function creerCompte(
        string $email,
        string $hash,
        string $role,
        string $prenom,
        string $nom,
        ?string $telephone,
        bool $actif = true
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO utilisateur (email, mot_de_passe, role, prenom, nom, telephone, actif)
             VALUES (:email, :hash, :role, :prenom, :nom, :telephone, :actif)'
        );
        $stmt->execute([
            ':email'     => $email,
            ':hash'      => $hash,
            ':role'      => $role,
            ':prenom'    => $prenom,
            ':nom'       => $nom,
            ':telephone' => $telephone,
            ':actif'     => $actif ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function creerEtudiant(int $utilisateurId, string $matricule, string $filiere, string $promotion): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO etudiant (utilisateur_id, matricule, filiere, promotion)
             VALUES (:utilisateur_id, :matricule, :filiere, :promotion)'
        );
        $stmt->execute([
            ':utilisateur_id' => $utilisateurId,
            ':matricule'      => $matricule,
            ':filiere'        => $filiere,
            ':promotion'      => $promotion,
        ]);
    }

    public function creerEncadreur(int $utilisateurId, string $grade, string $departement, int $chargeMax): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO encadreur (utilisateur_id, grade, departement, charge_max)
             VALUES (:utilisateur_id, :grade, :departement, :charge_max)'
        );
        $stmt->execute([
            ':utilisateur_id' => $utilisateurId,
            ':grade'          => $grade,
            ':departement'    => $departement,
            ':charge_max'     => $chargeMax,
        ]);
    }

    public function mettreAJourProfil(int $id, string $prenom, string $nom, string $email, ?string $telephone): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE utilisateur SET prenom = :prenom, nom = :nom, email = :email, telephone = :telephone
             WHERE id = :id'
        );
        $stmt->execute([
            ':prenom'    => $prenom,
            ':nom'       => $nom,
            ':email'     => $email,
            ':telephone' => $telephone,
            ':id'        => $id,
        ]);
    }

    public function mettreAJourMotDePasse(int $id, string $hash): void
    {
        $stmt = $this->pdo->prepare('UPDATE utilisateur SET mot_de_passe = :hash WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $id]);
    }

    public function changerActif(int $id, bool $actif): void
    {
        $stmt = $this->pdo->prepare('UPDATE utilisateur SET actif = :actif WHERE id = :id');
        $stmt->execute([':actif' => $actif ? 1 : 0, ':id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findCompteAvecProfil(int $id): ?array
    {
        $compte = $this->findById($id);
        if ($compte === null) {
            return null;
        }
        $compte['etudiant'] = $compte['role'] === 'ETUDIANT' ? $this->findEtudiant($id) : null;
        $compte['encadreur'] = $compte['role'] === 'ENCADREUR' ? $this->findEncadreur($id) : null;
        return $compte;
    }
}
