<?php

declare(strict_types=1);

use App\Controllers\AuditController;
use App\Controllers\DashboardController;
use App\Controllers\DemandeController;
use App\Controllers\DocumentController;
use App\Controllers\EntrepriseController;
use App\Controllers\ExportController;
use App\Controllers\NotificationController;
use App\Controllers\PieceController;
use App\Controllers\ProfileController;
use App\Controllers\ReferentielController;
use App\Controllers\SalleController;
use App\Controllers\SanteController;
use App\Controllers\SessionController;
use App\Controllers\SoutenanceController;
use App\Controllers\UserController;
use App\Http\Router;
use App\Kernel;

/**
 * Table de routage : méthode, chemin, contrôleur, rôles autorisés.
 * null = public ; [] = tout compte connecté.
 */
$router = new Router(static fn () => Kernel::auth());

// --- Santé et session (public) -------------------------------------------
$router->add('GET', '/api/sante', static fn ($r) => (new SanteController())->index($r), null, false);
$router->add('GET', '/api/session', static fn ($r) => (new SessionController())->show($r), null, false);
$router->add('POST', '/api/session', static fn ($r) => (new SessionController())->login($r), null, true);
$router->add('DELETE', '/api/session', static fn ($r, $u) => (new SessionController())->logout($r), []);
$router->add('POST', '/api/mot-de-passe-oublie', static fn ($r) => (new ProfileController())->motDePasseOublie($r), null, true);
$router->add('POST', '/api/mot-de-passe-reset', static fn ($r) => (new ProfileController())->reinitialiser($r), null, true);

// --- Profil, notifications et tableau de bord ----------------------------
$router->add('GET', '/api/profil', static fn ($r, $u) => (new ProfileController())->show($r, $u), []);
$router->add('PATCH', '/api/profil', static fn ($r, $u) => (new ProfileController())->update($r, $u), []);
$router->add('PATCH', '/api/profil/mot-de-passe', static fn ($r, $u) => (new ProfileController())->motDePasse($r, $u), []);
$router->add('GET', '/api/notifications', static fn ($r, $u) => (new NotificationController())->index($r, $u), []);
$router->add('PATCH', '/api/notifications/{id}', static fn ($r, $u) => (new NotificationController())->lire($r, $u), []);
$router->add('PATCH', '/api/notifications-tout-lire', static fn ($r, $u) => (new NotificationController())->lireTout($r, $u), []);
$router->add('GET', '/api/tableau-de-bord', static fn ($r, $u) => (new DashboardController())->index($r, $u), []);

// --- Données de référence -------------------------------------------------
$router->add('GET', '/api/entreprises', static fn ($r, $u) => (new EntrepriseController())->index($r, $u), []);
$router->add('POST', '/api/entreprises', static fn ($r, $u) => (new EntrepriseController())->store($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);
$router->add('POST', '/api/entreprises/proposer', static fn ($r, $u) => (new EntrepriseController())->proposer($r, $u), ['ETUDIANT']);
$router->add('PATCH', '/api/entreprises/{id}/statut', static fn ($r, $u) => (new EntrepriseController())->decider($r, $u), ['RESPONSABLE', 'ADMIN']);
$router->add('GET', '/api/encadreurs', static fn ($r, $u) => (new ReferentielController())->encadreurs($r, $u), ['SECRETARIAT', 'RESPONSABLE', 'ADMIN']);
$router->add('GET', '/api/comptes', static fn ($r, $u) => (new ReferentielController())->comptes($r, $u), ['SECRETARIAT', 'RESPONSABLE', 'ADMIN']);
$router->add('GET', '/api/salles', static fn ($r, $u) => (new SalleController())->index($r, $u), []);
$router->add('POST', '/api/salles', static fn ($r, $u) => (new SalleController())->store($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('PATCH', '/api/salles/{id}', static fn ($r, $u) => (new SalleController())->update($r, $u), ['SECRETARIAT', 'ADMIN']);

// --- Comptes et suivi -----------------------------------------------------
$router->add('GET', '/api/utilisateurs', static fn ($r, $u) => (new UserController())->index($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('POST', '/api/utilisateurs', static fn ($r, $u) => (new UserController())->store($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('POST', '/api/utilisateurs/import', static fn ($r, $u) => (new UserController())->importer($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('PATCH', '/api/utilisateurs/{id}/actif', static fn ($r, $u) => (new UserController())->actif($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/relances', static fn ($r, $u) => (new UserController())->relances($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/audit', static fn ($r, $u) => (new AuditController())->index($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);

// --- Demandes de stage ----------------------------------------------------
$router->add('GET', '/api/demandes', static fn ($r, $u) => (new DemandeController())->index($r, $u), []);
$router->add('POST', '/api/demandes', static fn ($r, $u) => (new DemandeController())->store($r, $u), ['ETUDIANT']);
$router->add('POST', '/api/demandes/lot', static fn ($r, $u) => (new DemandeController())->traiterLot($r, $u), ['RESPONSABLE']);
$router->add('GET', '/api/demandes/{id}', static fn ($r, $u) => (new DemandeController())->show($r, $u), []);
$router->add('PATCH', '/api/demandes/{id}', static fn ($r, $u) => (new DemandeController())->update($r, $u), ['ETUDIANT']);
$router->add('PATCH', '/api/demandes/{id}/annuler', static fn ($r, $u) => (new DemandeController())->annuler($r, $u), ['ETUDIANT']);
$router->add('PATCH', '/api/demandes/{id}/statut', static fn ($r, $u) => (new DemandeController())->changerStatut($r, $u), []);
$router->add('PATCH', '/api/demandes/{id}/encadreur', static fn ($r, $u) => (new DemandeController())->affecterEncadreur($r, $u), ['SECRETARIAT', 'RESPONSABLE', 'ADMIN']);
$router->add('PATCH', '/api/demandes/{id}/encadreur/confirmer', static fn ($r, $u) => (new DemandeController())->confirmerEncadreur($r, $u), ['RESPONSABLE', 'ADMIN']);
$router->add('PATCH', '/api/demandes/{id}/rapport', static fn ($r, $u) => (new DemandeController())->rapport($r, $u), ['ENCADREUR', 'RESPONSABLE', 'ADMIN']);
$router->add('PATCH', '/api/demandes/{id}/note', static fn ($r, $u) => (new DemandeController())->noter($r, $u), ['ENCADREUR', 'RESPONSABLE', 'ADMIN']);
$router->add('POST', '/api/demandes/{id}/commentaires', static fn ($r, $u) => (new DemandeController())->commenter($r, $u), []);
$router->add('GET', '/api/demandes/{id}/historique', static fn ($r, $u) => (new DemandeController())->historique($r, $u), []);

// --- Pièces jointes -------------------------------------------------------
$router->add('GET', '/api/demandes/{id}/pieces', static fn ($r, $u) => (new PieceController())->index($r, $u), []);
$router->add('POST', '/api/demandes/{id}/pieces', static fn ($r, $u) => (new PieceController())->upload($r, $u), []);
$router->add('GET', '/api/pieces/{id}/telechargement', static fn ($r, $u) => (new PieceController())->telecharger($r, $u), []);
$router->add('PATCH', '/api/pieces/{id}/statut', static fn ($r, $u) => (new PieceController())->statut($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);
$router->add('DELETE', '/api/pieces/{id}', static fn ($r, $u) => (new PieceController())->supprimer($r, $u), []);

// --- Soutenances ----------------------------------------------------------
$router->add('GET', '/api/soutenances', static fn ($r, $u) => (new SoutenanceController())->index($r, $u), []);
$router->add('GET', '/api/soutenances/planning', static fn ($r, $u) => (new SoutenanceController())->planning($r, $u), []);
$router->add('POST', '/api/soutenances', static fn ($r, $u) => (new SoutenanceController())->store($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/soutenances/{id}', static fn ($r, $u) => (new SoutenanceController())->show($r, $u), []);
$router->add('PATCH', '/api/soutenances/{id}', static fn ($r, $u) => (new SoutenanceController())->update($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('PATCH', '/api/soutenances/{id}/convoquer', static fn ($r, $u) => (new SoutenanceController())->convoquer($r, $u), ['SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/soutenances/{id}/convocation', static fn ($r, $u) => (new SoutenanceController())->convocation($r, $u), []);

// --- Documents et exports -------------------------------------------------
$router->add('GET', '/api/documents/{id}', static fn ($r, $u) => (new DocumentController())->generer($r, $u), []);
$router->add('GET', '/api/exports/demandes.csv', static fn ($r, $u) => (new ExportController())->demandesCsv($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/exports/statistiques.csv', static fn ($r, $u) => (new ExportController())->statistiquesCsv($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/exports/demandes.pdf', static fn ($r, $u) => (new ExportController())->demandesPdf($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);
$router->add('GET', '/api/exports/global.zip', static fn ($r, $u) => (new ExportController())->globalZip($r, $u), ['RESPONSABLE', 'SECRETARIAT', 'ADMIN']);

return $router;
