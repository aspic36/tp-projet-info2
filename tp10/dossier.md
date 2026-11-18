# TP10 — Dossier de livraison

Liste de contrôle en huit éléments. Chaque ligne est cochée uniquement si la pièce
existe **et** si sa vérification a été exécutée.

| # | Élément | Pièce fournie | Vérification | État |
| --- | --- | --- | --- | --- |
| 1 | Cadrage et besoins | `tp1/`, `tp2/exigences.html`, matrice de traçabilité EF-01 → EF-08 | Chaque `Must` a un test associé | Fait |
| 2 | Modélisation UML et base | `tp3/diagrammes/`, `tp3/001_schema.sql`, `002_seed.sql`, `003_verification.sql`, `004_contraintes.sql` | `COUNT(*)` du jeu d'essai : 3 étudiants, 2 entreprises, 4 demandes, 1 soutenance | Fait |
| 3 | Architecture et contrat d'API | `tp4/`, `docs/contrat-api.md`, `docs/ADR-001.md` | `GET /api/sante` répond 200 | Fait |
| 4 | Organisation Git | Historique sur `main`, `docs/kanban.md` (8 cartes), `tp5/` | `git log --oneline --graph` | Fait |
| 5 | Application (backend + interface) | `src/`, `config/`, `public/`, `tp6/`, `tp7/` | Scénario de bout en bout (12 étapes) | Fait |
| 6 | Tests et sécurité | `tests/`, `tp8/rapport.html` | 34 vérifications, 0 échec ; refus 403 prouvé | Fait |
| 7 | Installation et exploitation | `README.md`, `tp9/installation.md`, `tp9/scripts/`, `tp9/preuves/` | Installation sur base neuve + restauration `COUNT(*)` identiques | Fait |
| 8 | Démonstration et rétrospective | `tp10/demonstration.md`, `tp10/soutenance.md` | Répétition chronométrée sous 8 min | Fait |

## Détail des pièces

### 1. Cadrage et besoins

- Fiche de cadrage de deux pages : problème, acteurs, périmètre, indicateurs.
- Huit exigences fonctionnelles `EF-01` à `EF-08` et trois exigences non
  fonctionnelles `ENF-01` à `ENF-03`.
- Matrice de traçabilité : chaque exigence pointe vers le composant et le test.

### 2. Modélisation UML et base de données

- Diagramme de cas d'utilisation et diagramme de classes.
- `001_schema.sql` : 7 tables, clés étrangères, contraintes `CHECK` sur les
  statuts et l'unicité de la soutenance par demande.
- `002_seed.sql` : jeu d'essai fictif, une demande par statut.
- `003_verification.sql` et `004_contraintes.sql` : comptages et violations
  provoquées volontairement pour prouver que les contraintes tiennent.

### 3. Architecture et contrat d'API

- Arborescence en couches `Controllers → Services → Repositories`.
- Contrat des trois routes imposées : `GET /api/demandes`,
  `POST /api/demandes`, `PATCH /api/demandes/{id}/statut`, plus `GET /api/sante`.
- ADR-001 : monolithe modulaire PHP sans framework, avec les alternatives
  écartées et leurs raisons.

### 4. Organisation Git

- Branches courtes `feature/<carte>`, revue du diff, fusion `--no-ff`.
- Kanban de huit cartes avec définition de « fini » et commit associé.
- Conflit provoqué sur une ligne du `README.md`, résolu et retesté.

### 5. Application

- Backend : dépôts en requêtes préparées, services porteurs des règles,
  contrôleurs qui traduisent HTTP.
- Interface : page unique, deux parcours (étudiant, responsable/secrétariat),
  rendu par `textContent`.

### 6. Tests et sécurité

- `tests/connexion.php` (1 contrôle), `tests/api.php` (16 cas),
  `tests/securite.php` (17 contrôles).
- Revue de code : quatre remarques classées, dont un défaut critique réel
  (`SQLSTATE[HY093]`) corrigé et documenté.
- Contrôle d'accès prouvé : un étudiant qui lit le dossier d'un autre reçoit 403.

### 7. Installation et exploitation

- `README.md` : huit étapes d'installation, comptes de démonstration, routes,
  structure, sécurité, dette technique.
- `tp9/installation.md` : procédure complète avec compte applicatif à droits
  réduits.
- Sauvegarde `mysqldump` horodatée + empreinte SHA-256, restauration chronométrée
  et comparaison `COUNT(*)` table par table.

### 8. Démonstration et rétrospective

- Scénario de huit minutes découpé par minute, avec la preuve montrée à chaque
  étape.
- Solution de repli : captures et sorties de tests archivées.
- Douze réponses individuelles préparées (règle métier, requête, test, commit)
  et rétrospective en trois points.

## Ce qui n'est pas livré (et pourquoi)

| Élément | Raison | Repris en |
| --- | --- | --- |
| Pièces jointes (convention PDF) | Hors périmètre du MVP ; stockage de fichiers non spécifié | v2 |
| Notifications par e-mail | Nécessite un serveur SMTP et un ordonnanceur | v2 |
| Portail entreprise | Troisième acteur, non demandé par les exigences `Must` | v2 |
| Limitation des tentatives de connexion | Nécessite un stockage partagé des compteurs | v2 |
| Tests de charge | Poste unique, pas d'outil de charge installé | v2 |
