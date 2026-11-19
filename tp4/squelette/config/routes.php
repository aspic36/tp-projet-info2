<?php

declare(strict_types=1);

use App\Controllers\DemandeController;
use App\Controllers\ReferentielController;
use App\Controllers\SanteController;
use App\Controllers\SessionController;
use App\Controllers\SoutenanceController;
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

// --- Données de référence -------------------------------------------------
$router->add('GET', '/api/entreprises', static fn ($r, $u) => (new ReferentielController())->entreprises($r, $u), []);
$router->add('GET', '/api/encadreurs', static fn ($r, $u) => (new ReferentielController())->encadreurs($r, $u), ['SECRETARIAT', 'RESPONSABLE', 'ADMIN']);
$router->add('GET', '/api/comptes', static fn ($r, $u) => (new ReferentielController())->comptes($r, $u), ['ADMIN']);

// --- Demandes de stage ----------------------------------------------------
$router->add('GET', '/api/demandes', static fn ($r, $u) => (new DemandeController())->index($r, $u), []);
$router->add('POST', '/api/demandes', static fn ($r, $u) => (new DemandeController())->store($r, $u), ['ETUDIANT']);
$router->add('GET', '/api/demandes/{id}', static fn ($r, $u) => (new DemandeController())->show($r, $u), []);
$router->add('PATCH', '/api/demandes/{id}/statut', static fn ($r, $u) => (new DemandeController())->changerStatut($r, $u), []);
$router->add('PATCH', '/api/demandes/{id}/encadreur', static fn ($r, $u) => (new DemandeController())->affecterEncadreur($r, $u), []);
$router->add('GET', '/api/demandes/{id}/historique', static fn ($r, $u) => (new DemandeController())->historique($r, $u), []);

// --- Soutenances ----------------------------------------------------------
$router->add('GET', '/api/soutenances', static fn ($r, $u) => (new SoutenanceController())->index($r, $u), []);
$router->add('POST', '/api/soutenances', static fn ($r, $u) => (new SoutenanceController())->store($r, $u), ['SECRETARIAT', 'ADMIN']);

return $router;
