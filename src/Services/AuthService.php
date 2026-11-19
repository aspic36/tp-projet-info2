<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\UtilisateurRepository;
use App\Support\Audit;

/**
 * Authentification par session : mot de passe vérifié, identifiant de session
 * régénéré, rôle porté par le compte.
 */
final class AuthService
{
    public function __construct(
        private readonly UtilisateurRepository $users,
        private readonly ?Audit $audit = null
    ) {
    }

    /** @return array<string,mixed>|null */
    public function current(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = $this->users->findById((int) $id);
        if ($user === null || (int) $user['actif'] !== 1) {
            $this->logout();
            return null;
        }

        $user['id'] = (int) $user['id'];
        $user['etudiant_id'] = null;
        $user['encadreur_id'] = null;

        if ($user['role'] === 'ETUDIANT') {
            $etudiant = $this->users->findEtudiant($user['id']);
            $user['etudiant_id'] = $etudiant['id'] ?? null;
            $user['matricule'] = $etudiant['matricule'] ?? null;
            $user['filiere'] = $etudiant['filiere'] ?? null;
            $user['promotion'] = $etudiant['promotion'] ?? null;
        }
        if ($user['role'] === 'ENCADREUR') {
            $encadreur = $this->users->findEncadreur($user['id']);
            $user['encadreur_id'] = $encadreur['id'] ?? null;
            $user['grade'] = $encadreur['grade'] ?? null;
            $user['departement'] = $encadreur['departement'] ?? null;
            $user['charge_max'] = $encadreur['charge_max'] ?? null;
        }

        return $user;
    }

    /** @return array<string,mixed> */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $row = $this->users->findByEmail($email);

        // Message identique pour un compte inconnu et un mot de passe faux.
        if ($row === null || (int) $row['actif'] !== 1) {
            $this->audit?->log(null, 'CONNEXION_ECHEC', 'session', null, ['email' => $email]);
            throw new ApiException(401, 'Identifiants incorrects.');
        }
        if (!password_verify($password, (string) $row['mot_de_passe'])) {
            $this->audit?->log((int) $row['id'], 'CONNEXION_ECHEC', 'session', null, ['email' => $email]);
            throw new ApiException(401, 'Identifiants incorrects.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];

        $user = $this->current();
        if ($user === null) {
            throw new ApiException(500, 'Session impossible à établir.');
        }
        $this->audit?->log((int) $row['id'], 'CONNEXION', 'session');
        return $user;
    }

    public function logout(): void
    {
        $id = $_SESSION['user_id'] ?? null;
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $this->audit?->log((int) $id, 'DECONNEXION', 'session');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    /**
     * @param array<int,string> $roles
     * @return array<string,mixed>
     */
    public function requireRole(array $roles): array
    {
        $user = $this->current();
        if ($user === null) {
            throw new ApiException(401, 'Authentification requise.');
        }
        if ($roles !== [] && !in_array($user['role'], $roles, true)) {
            throw new ApiException(403, 'Rôle insuffisant pour cette action.');
        }
        return $user;
    }
}
