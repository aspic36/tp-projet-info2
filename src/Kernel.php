<?php

declare(strict_types=1);

namespace App;

use App\Database\Database;
use App\Repositories\AuditRepository;
use App\Repositories\CommentaireRepository;
use App\Repositories\DemandeRepository;
use App\Repositories\EntrepriseRepository;
use App\Repositories\HistoriqueRepository;
use App\Repositories\PieceRepository;
use App\Repositories\SalleRepository;
use App\Repositories\SoutenanceRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\DemandeService;
use App\Services\DocumentService;
use App\Services\EntrepriseService;
use App\Services\ExportService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\PieceService;
use App\Services\SoutenanceService;
use App\Services\UserService;
use App\Support\Audit;
use App\Support\Pdf;
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

    private static function audit(): Audit
    {
        return new Audit(self::pdo());
    }

    private static function mail(): MailService
    {
        return new MailService();
    }

    public static function notifications(): NotificationService
    {
        return new NotificationService(self::pdo(), self::mail());
    }

    public static function auth(): AuthService
    {
        return new AuthService(new UtilisateurRepository(self::pdo()), self::audit());
    }

    public static function demandes(): DemandeService
    {
        $pdo = self::pdo();
        return new DemandeService(
            $pdo,
            new DemandeRepository($pdo),
            new HistoriqueRepository($pdo),
            new EntrepriseRepository($pdo),
            new UtilisateurRepository($pdo),
            new CommentaireRepository($pdo),
            new PieceRepository($pdo),
            new SoutenanceRepository($pdo),
            self::notifications(),
            self::audit()
        );
    }

    public static function soutenances(): SoutenanceService
    {
        $pdo = self::pdo();
        return new SoutenanceService(
            $pdo,
            new DemandeRepository($pdo),
            new SoutenanceRepository($pdo),
            new SalleRepository($pdo),
            new UtilisateurRepository($pdo),
            self::notifications(),
            self::audit()
        );
    }

    public static function entreprises(): EntrepriseService
    {
        $pdo = self::pdo();
        return new EntrepriseService(
            new EntrepriseRepository($pdo),
            new UtilisateurRepository($pdo),
            self::notifications(),
            self::audit()
        );
    }

    public static function pieces(): PieceService
    {
        $pdo = self::pdo();
        return new PieceService(
            new DemandeRepository($pdo),
            new PieceRepository($pdo),
            self::audit()
        );
    }

    public static function utilisateurs(): UserService
    {
        $pdo = self::pdo();
        return new UserService(
            $pdo,
            new UtilisateurRepository($pdo),
            self::notifications(),
            self::mail(),
            self::audit()
        );
    }

    public static function tableauBord(): DashboardService
    {
        return new DashboardService(self::pdo(), self::notifications());
    }

    public static function exports(): ExportService
    {
        $pdo = self::pdo();
        return new ExportService($pdo, new DemandeRepository($pdo), new Pdf());
    }

    public static function documents(): DocumentService
    {
        $pdo = self::pdo();
        return new DocumentService(self::demandes(), new SoutenanceRepository($pdo), new Pdf());
    }

    public static function auditRepository(): AuditRepository
    {
        return new AuditRepository(self::pdo());
    }

    public static function salles(): SalleRepository
    {
        return new SalleRepository(self::pdo());
    }

    public static function referentiel(): array
    {
        $pdo = self::pdo();
        return [
            'entreprises' => new EntrepriseRepository($pdo),
            'utilisateurs' => new UtilisateurRepository($pdo),
            'salles' => new SalleRepository($pdo),
        ];
    }

    public static function reset(): void
    {
        self::$pdo = null;
        Database::reset();
    }
}
