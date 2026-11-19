# Projet informatique 2 — Gestion des stages (L3 SIL)

Application web de gestion des demandes de stage et des soutenances universitaires.
Données **fictives** uniquement. Dépôt partagé du groupe :
<https://github.com/aspic36/tp-projet-info2>.

## Sommaire

- [Périmètre](#périmètre)
- [Prérequis](#prérequis)
- [Installation pas à pas](#installation-pas-à-pas)
- [Comptes de démonstration](#comptes-de-démonstration)
- [Lancer l'application](#lancer-lapplication)
- [Routes de l'API](#routes-de-lapi)
- [Tests](#tests)
- [Structure du projet](#structure-du-projet)
- [Sécurité](#sécurité)
- [Travaux pratiques livrés](#travaux-pratiques-livrés)
- [Dette technique connue](#dette-technique-connue)

## Périmètre

Un étudiant dépose une demande de stage ; le responsable pédagogique la valide ou la
refuse avec un motif ; le secrétariat affecte un encadreur puis programme la
soutenance. Chaque décision est horodatée et conservée dans un historique.

Cycle de vie d'une demande :

```
BROUILLON ──► SOUMISE ──► VALIDEE
                 │
                 └─────► REFUSEE ──► BROUILLON
```

Toute autre transition est refusée avec le code **409**.

## Prérequis

| Outil | Version testée | Remarque |
| --- | --- | --- |
| PHP | 8.3.33 | extensions `pdo_mysql`, `mbstring`, `json`, `session` |
| MySQL | 8.4.3 | compte applicatif dédié, jamais `root` |
| Git | 2.55 | |
| Navigateur | Chrome / Edge / Firefox récent | |

Aucun framework, aucun gestionnaire de dépendances : l'autoload PSR-4 est écrit à la main.

## Installation pas à pas

Ces étapes ont été rejouées sur un second poste (voir `tp9/`).

1. **Cloner le dépôt**

   ```sh
   git clone https://github.com/aspic36/tp-projet-info2.git projet-stages
   cd projet-stages
   ```

2. **Créer la base et le compte SQL dédié**

   ```sh
   mysql -u root -p -e "CREATE DATABASE stages CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
   mysql -u root -p -e "CREATE USER 'stages_app'@'localhost' IDENTIFIED BY 'mot-de-passe-local'"
   mysql -u root -p -e "GRANT SELECT, INSERT, UPDATE, DELETE ON stages.* TO 'stages_app'@'localhost'"
   ```

   Le compte applicatif ne reçoit **aucun** droit `DROP`, `ALTER` ou `GRANT`.

3. **Importer le schéma et le jeu d'essai** (compte administrateur, car ces
   scripts créent les tables)

   ```sh
   mysql -u root -p stages < tp3/001_schema.sql
   mysql -u root -p stages < tp3/002_seed.sql
   mysql -u root -p stages < tp3/003_verification.sql
   ```

4. **Configurer l'environnement local**

   ```sh
   copy .env.example .env      # Windows
   cp .env.example .env        # Linux / macOS
   ```

   Renseigner `DB_USER`, `DB_PASS` et `APP_URL`. Le fichier `.env` est ignoré par Git.

5. **Vérifier la connexion à la base**

   ```sh
   php tests/connexion.php
   ```

   Sortie attendue : `Connexion PDO : OK`, la version du serveur et le nombre de tables.

6. **Démarrer le serveur**

   ```sh
   php -S 127.0.0.1:8000 -t public public/index.php
   ```

7. **Contrôler la santé de l'application**

   ```sh
   curl http://127.0.0.1:8000/api/sante
   ```

   `200` et `{"status":"ok","database":"ok",…}` si MySQL répond ; `503` sinon.

8. **Ouvrir l'interface** : <http://127.0.0.1:8000/>.

## Comptes de démonstration

Mot de passe commun : `Stage2026!` (haché en bcrypt dans `tp3/002_seed.sql`).

| Rôle | Adresse |
| --- | --- |
| Étudiant | `rachid.kpanou@campus.test` |
| Étudiante | `clarisse.sodjinou@campus.test` |
| Responsable pédagogique | `responsable.stages@campus.test` |
| Secrétariat | `accueil@campus.test` |
| Encadreur | `arsene.djegba@campus.test` |
| Administrateur | `administrateur@campus.test` |

## Lancer l'application

- Interface : `GET /` (fichiers statiques `public/index.html`, `public/app.js`, `public/style.css`).
- API : préfixe `/api`, réponses JSON, cookie de session `STAGESESSID`.
- Les écritures (`POST`, `PATCH`, `DELETE`) exigent l'en-tête `X-CSRF-Token`, fourni par
  `GET /api/session`.

## Routes de l'API

Contrat détaillé : [`docs/contrat-api.md`](docs/contrat-api.md).

| Méthode | Chemin | Rôles | Succès |
| --- | --- | --- | --- |
| `GET` | `/api/sante` | public | 200 / 503 |
| `GET` | `/api/session` | public | 200 |
| `POST` | `/api/session` | public | 200 |
| `DELETE` | `/api/session` | connecté | 204 |
| `GET` | `/api/demandes` | connecté (périmètre selon rôle) | 200 |
| `POST` | `/api/demandes` | étudiant | 201 |
| `GET` | `/api/demandes/{id}` | propriétaire, encadreur affecté, responsable, secrétariat, admin | 200 |
| `PATCH` | `/api/demandes/{id}/statut` | étudiant propriétaire (`SOUMISE`, `BROUILLON`) ou responsable (`VALIDEE`, `REFUSEE`) | 200 |
| `PATCH` | `/api/demandes/{id}/encadreur` | secrétariat, admin | 200 |
| `GET` | `/api/demandes/{id}/historique` | mêmes droits que la lecture | 200 |
| `GET` | `/api/soutenances` | connecté (périmètre selon rôle) | 200 |
| `POST` | `/api/soutenances` | secrétariat, admin | 201 |
| `GET` | `/api/entreprises` | connecté | 200 |
| `GET` | `/api/encadreurs` | secrétariat, responsable, admin | 200 |
| `GET` | `/api/comptes` | admin | 200 |

Codes d'erreur : `400` données invalides, `401` non authentifié, `403` accès interdit,
`404` ressource absente, `409` conflit d'état, `500` erreur serveur.

## Tests

```sh
php tests/connexion.php      # TP4 : PDO et base joignable
php tests/api.php            # TP6/TP8 : scénario HTTP complet (serveur démarré)
php tests/securite.php       # TP8 : autorisations, injection, hachage, CSRF
```

Les sorties sont archivées dans `tp6/preuves/`, `tp7/preuves/` et `tp8/preuves/`.

## Structure du projet

```
public/                 point d'entrée unique et fichiers statiques
  index.php             front controller
  index.html            interface
  app.js                appels fetch, rendu, gestion des erreurs
  style.css             feuille de style responsive
src/
  bootstrap.php         autoload, configuration, erreurs, session
  Kernel.php            fabrique des services
  Controllers/          couche HTTP (aucun SQL)
  Services/             règles métier (transitions, autorisations)
  Repositories/         SQL en requêtes préparées
  Http/                 Request, Response, Router, Validator, ApiException
  Database/             connexion PDO
  Support/              Env, Config, Csrf, Log
config/
  config.php            configuration sans secret
  routes.php            table de routage
tests/                  scripts de test en ligne de commande
docs/                   contrat d'API, ADR, Kanban, dossier technique
tp1/ … tp10/            livrables du cours et preuves
```

## Sécurité

- Mots de passe hachés avec `password_hash()` (bcrypt, coût 12) et vérifiés par
  `password_verify()`.
- `session_regenerate_id(true)` après connexion ; cookie `HttpOnly`, `SameSite=Lax`.
- Jeton CSRF aléatoire de 32 octets (256 bits), comparé avec `hash_equals()` sur toute
  écriture, y compris les `DELETE`.
- Requêtes préparées PDO : aucune concaténation de valeur dans le SQL.
- Autorisations vérifiées côté serveur à chaque lecture et écriture, jamais seulement
  dans l'interface.
- Rendu avec `textContent` dans le navigateur : aucune donnée saisie n'est interprétée
  comme du HTML.
- Erreurs techniques journalisées dans `logs/app.log`, jamais renvoyées au client.

## Travaux pratiques livrés

| TP | Sujet | Livrable |
| --- | --- | --- |
| TP1 | Fiche de cadrage | `tp1/fiche-cadrage.pdf` |
| TP2 | Exigences et traçabilité | `tp2/exigences.pdf` |
| TP3 | UML et modèle de données | `tp3/modele_donnees.pdf`, scripts SQL, diagrammes |
| TP4 | Squelette et contrat d'API | ce README, `docs/contrat-api.md`, `docs/ADR-001.md` |
| TP5 | Git et organisation d'équipe | `tp5/` (Kanban, historique, conflit résolu) |
| TP6 | API des demandes | `src/`, `tp6/` (six cas testés) |
| TP7 | Interface et intégration | `public/`, `tp7/` (captures, test XSS, scénario complet) |
| TP8 | Tests et sécurité | `tp8/` (matrice, audit, revue de code) |
| TP9 | Déploiement et sauvegarde | `tp9/` (installation, sauvegarde/restauration) |
| TP10 | Dossier et soutenance | `tp10/` (dossier, scénario, rétrospective) |

## Dette technique connue

- Pièces jointes (convention PDF) reportées à la version 2 : le dépôt de fichiers
  demande un stockage et une analyse antivirus.
- Notifications par e-mail et portail entreprise hors périmètre.
- Le frontend est un fichier JavaScript unique ; un découpage en modules sera fait
  si l'interface grossit.
- Pas d'envoi de mot de passe de réinitialisation : les comptes sont créés par
  l'administrateur.
