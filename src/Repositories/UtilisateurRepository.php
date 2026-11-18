<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Accès aux comptes et aux fiches rattachées (étudiant, encadreur).
 */
final class UtilisateurRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, mot_de_passe, role, prenom, nom, actif
             FROM utilisateur WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, role, prenom, nom, actif
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
            'SELECT id, utilisateur_id, grade, departement
             FROM encadreur WHERE utilisateur_id = :uid'
        );
        $stmt->execute([':uid' => $utilisateurId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listerEncadreurs(): array
    {
        $sql = 'SELECT enc.id, enc.grade, enc.departement, u.prenom, u.nom, u.email
                FROM encadreur enc
                JOIN utilisateur u ON u.id = enc.utilisateur_id
                WHERE u.actif = 1
                ORDER BY u.nom, u.prenom';
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findEncadreurById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT enc.id, enc.grade, enc.departement, u.prenom, u.nom, u.email, u.actif
             FROM encadreur enc
             JOIN utilisateur u ON u.id = enc.utilisateur_id
             WHERE enc.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function listerComptes(): array
    {
        $sql = 'SELECT id, email, role, prenom, nom, actif
                FROM utilisateur ORDER BY role, nom, prenom';
        return $this->pdo->query($sql)->fetchAll();
    }
}
