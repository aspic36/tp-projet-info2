<?php

declare(strict_types=1);

namespace App;

use App\Database\Database;
use App\Repositories\DemandeRepository;
use App\Repositories\EntrepriseRepository;
use App\Repositories\HistoriqueRepository;
use App\Repositories\SoutenanceRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\AuthService;
use App\Services\DemandeService;
use App\Services\SoutenanceService;
use PDO;

/**
 * Fabrique paresseuse : la base n'est ouverte que si la route en a besoin.
 * La route /api/sante peut donc répondre 503 quand MySQL est arrêté.
 */
final class Kernel
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        return self::$pdo ??= Database::pdo();
    }

    public static function auth(): AuthService
    {
        return new AuthService(new UtilisateurRepository(self::pdo()));
    }

    public static function demandes(): DemandeService
    {
        return new DemandeService(
            self::pdo(),
            new DemandeRepository(self::pdo()),
            new HistoriqueRepository(self::pdo()),
            new EntrepriseRepository(self::pdo()),
            new UtilisateurRepository(self::pdo())
        );
    }

    public static function soutenances(): SoutenanceService
    {
        return new SoutenanceService(
            new DemandeRepository(self::pdo()),
            new SoutenanceRepository(self::pdo())
        );
    }

    public static function referentiel(): array
    {
        return [
            'entreprises' => new EntrepriseRepository(self::pdo()),
            'utilisateurs' => new UtilisateurRepository(self::pdo()),
        ];
    }

    public static function reset(): void
    {
        self::$pdo = null;
        Database::reset();
    }
}
