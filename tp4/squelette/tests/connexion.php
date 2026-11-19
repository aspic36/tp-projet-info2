<?php

declare(strict_types=1);

/**
 * TP4 — Test de connexion PDO.
 * Usage : php tests/connexion.php   (code retour 0 si la base répond)
 */

require __DIR__ . '/bootstrap.php';

use App\Database\Database;

try {
    $pdo = Database::pdo();
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $base = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
    )->fetchColumn();
    $demandes = (int) $pdo->query('SELECT COUNT(*) FROM demande_stage')->fetchColumn();

    printf("Connexion PDO : OK\n");
    printf("Serveur MySQL : %s\n", $version);
    printf("Base courante : %s\n", $base);
    printf("Tables         : %d\n", $tables);
    printf("Demandes       : %d\n", $demandes);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Connexion PDO : ECHEC\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
