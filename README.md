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
- [Déploiement et sauvegarde](#déploiement-et-sauvegarde)
- [Soutenance et bilan](#soutenance-et-bilan)
- [Structure du projet](#structure-du-projet)
- [Sécurité](#sécurité)
- [Travaux pratiques livrés](#travaux-pratiques-livrés)
- [Dette technique connue](#dette-technique-connue)

## Périmètre

Un étudiant dépose une demande de stage, propose une entreprise et suit ses
notifications ; le responsable pédagogique valide, refuse ou traite les demandes en
lot ; le secrétariat gère les comptes, affecte un encadreur, programme la soutenance
et envoie les convocations ; l'encadreur rend un rapport et attribue une note.
Chaque décision est horodatée, journalisée dans l'audit et conservée dans un
historique. Tableaux de bord, exports et documents sont disponibles selon le rôle.

Cycle de vie d'une demande :

```
BROUILLON → SOUMISE → VALIDEE
BROUILLON → ANNULEE
SOUMISE   → ANNULEE
SOUMISE   → REFUSEE → BROUILLON
```

Une seule demande active (`BROUILLON`, `SOUMISE`, `VALIDEE`) est autorisée par
étudiant et par entreprise. Toute autre transition est refusée avec le code **409**.

## Prérequis

| Outil | Version testée | Remarque |
| --- | --- | --- |
| PHP | 8.3.33 | extensions `pdo_mysql`, `mbstring`, `json`, `session` |
| MySQL | 8.4.3 | compte applicatif dédié, jamais `root` |
| Git | 2.55 | |
| Navigateur | Chrome / Edge / Firefox récent | |

Aucun framework, aucun gestionnaire de dépendances : l'autoload PSR-4 est écrit à la main.

## Installation pas à pas

Ces étapes sont la procédure d'installation retenue pour le projet.

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
   mysql -u root -p stages < tp3/005_extensions.sql
   mysql -u root -p stages < tp3/003_verification.sql   # contrôle facultatif
   ```

   L'ordre `001` → `002` → `005` est obligatoire : la migration `005` ajoute les
   tables et colonnes utilisées par les espaces Étudiant, Encadreur, Secrétariat et
   Responsable.

4. **Configurer l'environnement local**

   ```sh
   copy .env.example .env      # Windows
   cp .env.example .env        # Linux / macOS
   ```

   Renseigner `DB_USER`, `DB_PASS`, `DB_PORT` et `APP_URL`. `MAIL_MODE=log` écrit
   les messages dans `storage/mails/` ; `UPLOAD_MAX_MB` borne les pièces jointes.
   Le fichier `.env` est ignoré par Git.

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
- Les pièces jointes sont stockées hors racine web dans `storage/pieces/`, les courriels
  de test dans `storage/mails/` et les exports dans `storage/exports/`. Ces dossiers
  sont créés automatiquement et ignorés par Git.
- La navigation s'adapte au rôle : Étudiant (demandes, entreprises, soutenances),
  Encadreur (dossiers affectés, rapport, note), Secrétariat (comptes, salles, relances,
  convocations), Responsable (validation en lot, audit, exports), Administrateur
  (tous les espaces).

## Routes de l'API

Contrat détaillé : [`docs/contrat-api.md`](docs/contrat-api.md).

| Méthode | Chemin | Rôles | Succès |
| --- | --- | --- | --- |
| `GET` | `/api/sante` | public | 200 / 503 |
| `GET`, `POST` | `/api/session` | public | 200 |
| `DELETE` | `/api/session` | connecté | 204 |
| `POST` | `/api/mot-de-passe-oublie`, `/api/mot-de-passe-reset` | public | 200 |
| `GET`, `PATCH` | `/api/profil` | connecté | 200 |
| `PATCH` | `/api/profil/mot-de-passe` | connecté | 200 |
| `GET` | `/api/notifications` | connecté | 200 |
| `PATCH` | `/api/notifications/{id}`, `/api/notifications-tout-lire` | connecté | 200 |
| `GET` | `/api/tableau-de-bord` | connecté | 200 |
| `GET` | `/api/entreprises` | connecté | 200 |
| `POST` | `/api/entreprises/proposer` | étudiant | 201 |
| `POST` | `/api/entreprises` | responsable, secrétariat, admin | 201 |
| `PATCH` | `/api/entreprises/{id}/statut` | responsable, admin | 200 |
| `GET` | `/api/encadreurs`, `/api/comptes`, `/api/salles` | selon rôle | 200 |
| `GET`, `POST` | `/api/utilisateurs`, `/api/salles` | secrétariat, admin | 200 / 201 |
| `POST` | `/api/utilisateurs/import` | secrétariat, admin | 200 |
| `PATCH` | `/api/utilisateurs/{id}/actif`, `/api/salles/{id}` | secrétariat, admin | 200 |
| `GET` | `/api/relances`, `/api/audit` | selon rôle | 200 |
| `GET`, `POST` | `/api/demandes` | connecté / étudiant | 200 / 201 |
| `GET`, `PATCH` | `/api/demandes/{id}` | selon périmètre | 200 |
| `POST` | `/api/demandes/lot` | responsable | 200 |
| `PATCH` | `/api/demandes/{id}/statut` | étudiant, responsable | 200 |
| `PATCH` | `/api/demandes/{id}/encadreur`, `/encadreur/confirmer` | secrétariat, responsable, admin | 200 |
| `PATCH` | `/api/demandes/{id}/rapport`, `/note` | encadreur, responsable, admin | 200 |
| `POST`, `GET` | `/api/demandes/{id}/commentaires`, `/historique` | selon périmètre | 200 / 201 |
| `GET`, `POST` | `/api/demandes/{id}/pieces` | selon périmètre | 200 / 201 |
| `GET`, `PATCH`, `DELETE` | `/api/pieces/{id}…` | selon périmètre | 200 / 204 |
| `GET`, `POST` | `/api/soutenances` | connecté / secrétariat, admin | 200 / 201 |
| `GET` | `/api/soutenances/planning` | connecté | 200 |
| `PATCH` | `/api/soutenances/{id}`, `/convoquer` | secrétariat, admin | 200 |
| `GET` | `/api/soutenances/{id}/convocation` | connecté | 200 |
| `GET` | `/api/documents/{id}?type=…&format=html/pdf` | selon périmètre | 200 |
| `GET` | `/api/exports/demandes.csv`, `statistiques.csv`, `demandes.pdf`, `global.zip` | responsable, secrétariat, admin | 200 |

Codes d'erreur : `400` données invalides, `401` non authentifié, `403` accès interdit,
`404` ressource absente, `409` conflit d'état, `500` erreur serveur.

## Tests

```sh
php tests/connexion.php      # TP4 : PDO et base joignable
php tests/api-six-cas.php    # TP6 : six cas réglementaires (serveur démarré)
php tests/api.php            # TP7 : scénario complet, 17 cas (serveur démarré)
php tests/cas-limites.php    # TP8 : quatre cas limites (serveur démarré)
php tests/securite.php       # TP8 : autorisations, injection, hachage, CSRF
```

Les sorties sont archivées dans `tp6/preuves/`, `tp7/preuves/` et `tp8/preuves/`.

## Déploiement et sauvegarde

La procédure complète est dans [`tp9/installation.md`](tp9/installation.md). Les
scripts associés évitent tout root dans le `.env` et stockent les dumps hors Git.

```sh
export DB_HOST=127.0.0.1 DB_PORT=3308 DB_USER=root DB_PASS=mot-de-passe-admin
bash tp9/scripts/sauvegarde.sh stages sauvegardes
bash tp9/scripts/restauration.sh sauvegardes/stages-AAAAMMJJ-HHMMSS.sql stages_test
bash tp9/scripts/comptage.sh stages stages_test
```

Le contrôle final doit afficher `Verdict : IDENTIQUES`. La perte simulée d'une
table et sa restauration sont documentées dans [`tp9/incident.md`](tp9/incident.md),
avec les preuves dans `tp9/preuves/`.

## Soutenance et bilan

Le dossier de livraison est contrôlé en huit points dans
[`tp10/dossier.md`](tp10/dossier.md). Le déroulé de huit minutes, le script oral
et le plan de repli sont dans [`tp10/demonstration.md`](tp10/demonstration.md) ;
les réponses individuelles et la rétrospective sont dans
[`tp10/soutenance.md`](tp10/soutenance.md).

```sh
bash tp10/scripts/repetition.sh
```

Le relevé [`tp10/preuves/repetition.txt`](tp10/preuves/repetition.txt) montre le
scénario technique (5,92 s) et la campagne de tests (45 contrôles, 0 échec).
Les captures et sorties à ouvrir en secours sont inventoriées dans
[`tp10/preuves/repli.txt`](tp10/preuves/repli.txt).

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
storage/                données générées, ignorées par Git
  pieces/               pièces jointes PDF
  mails/                courriels journalisés
  exports/              archives CSV, PDF et ZIP
tests/                  scripts de test en ligne de commande
docs/                   contrat d'API, ADR, Kanban, dossier technique
tp1/ … tp10/            livrables du cours et preuves
tp9/
  installation.md       procédure de déploiement et sauvegarde
  incident.md           ticket de perte de table simulée
  scripts/              sauvegarde, restauration, comptage
  preuves/              sorties réelles des contrôles
tp10/
  dossier.md            liste de contrôle en huit points
  demonstration.md      scénario de huit minutes et repli
  soutenance.md         réponses individuelles et rétrospective
  scripts/              répétition reproductible
  preuves/              chrono, campagne de tests, inventaire de repli
```

## Sécurité

- Mots de passe hachés avec `password_hash()` (bcrypt, coût 12) et vérifiés par
  `password_verify()`.
- `session_regenerate_id(true)` après connexion ; cookie `HttpOnly`, `SameSite=Lax`.
- Jeton CSRF aléatoire de 32 octets (64 caractères hexadécimaux), comparé avec
  `hash_equals()` sur toute écriture, y compris les `DELETE`.
- Requêtes préparées PDO : aucune concaténation de valeur dans le SQL.
- Autorisations vérifiées côté serveur à chaque lecture et écriture, jamais seulement
  dans l'interface.
- Rendu avec `textContent` dans le navigateur : aucune donnée saisie n'est interprétée
  comme du HTML.
- Erreurs techniques journalisées dans `logs/app.log`, jamais renvoyées au client.
- Pièces jointes limitées au PDF, à `UPLOAD_MAX_MB`, stockées hors racine web avec nom
  de fichier régénéré ; le nom d'origine n'est révélé qu'au téléchargement autorisé.

## Travaux pratiques livrés

| TP | Sujet | Livrable |
| --- | --- | --- |
| TP1 | Fiche de cadrage | `tp1/fiche-cadrage.html/.pdf/.docx` |
| TP2 | Exigences et traçabilité | `tp2/exigences.html/.pdf/.docx` |
| TP3 | UML et modèle de données | `tp3/modele_donnees.html/.pdf/.docx`, scripts SQL, diagrammes |
| TP4 | Squelette et contrat d'API | `tp4/squelette/` : `config/`, `docs/`, `public/`, `src/`, `tests/`, `.env.example`, `.gitignore`, `README.md` et `squelette_contrat_adr.html/.pdf/.docx` |
| TP5 | Git et organisation d'équipe | `tp5/` (Kanban, historique, conflit résolu) |
| TP6 | API des demandes | `src/`, `tp6/` (six cas réglementaires testés) |
| TP7 | Interface et intégration | `public/`, `tp7/` (captures, test XSS, scénario complet) |
| TP8 | Tests et sécurité | `tp8/` (matrice 12 contrôles, 4 cas limites, audit, revue classée) |
| TP9 | Déploiement et sauvegarde | `tp9/` (procédure, scripts, preuves de restauration, ticket d'incident) |
| TP10 | Dossier de livraison et soutenance | `tp10/` (checklist, scénario 8 min, répétition, réponses, rétrospective) |

Les rapports TP1 à TP8 sont livrés en trois formats : source HTML, PDF A4 et
document Word `.docx`, afin de pouvoir être relus et corrigés dans l'outil du cours.

## Dette technique connue

- Aucune analyse antivirus des pièces jointes : seule la validation MIME/taille est
  effectuée.
- Les courriels sont journalisés dans `storage/mails/` avec `MAIL_MODE=log` ; brancher
  un SMTP réel reste à faire en production.
- Le frontend est un fichier JavaScript unique ; un découpage en modules sera fait
  si l'interface grossit.
- La réinitialisation de mot de passe génère un lien journalisé, sans transport mail
  réel par défaut.
- Pas de limitation des tentatives de connexion : compteur par IP et par compte
  reporté à la version 2.
