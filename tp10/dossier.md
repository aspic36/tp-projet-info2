# TP10 — Dossier de livraison

État vérifié le 19/11/2026 sur `main`, commit de référence `5779d0d`.
Chaque ligne est cochée seulement si la pièce existe et si sa vérification a
été exécutée.

| # | Élément | Pièce fournie | Vérification | État |
| --- | --- | --- | --- | --- |
| 1 | Cadrage et besoins | `tp1/fiche-cadrage.*`, `tp2/exigences.*` | 8 exigences `EF-01…EF-08`, 3 exigences `ENF-01…ENF-03`, 2 histoires | Fait |
| 2 | Modélisation et base | `tp3/cas_usage.*`, `tp3/classes.*`, `tp3/modele_donnees.*`, `tp3/001`→`005_*.sql` | Import sur base neuve : 14 tables, 2 vues, 37 lignes ; contraintes refusées dans `tp3/preuves/contraintes.txt` | Fait |
| 3 | Architecture et contrat d’API | `docs/contrat-api.md`, `docs/ADR-001.md`, `config/routes.php`, `tp4/squelette/` | 58 routes ; `GET /api/sante` → 200 dans `tp10/preuves/repetition.txt` | Fait |
| 4 | Organisation Git | `docs/kanban.md`, `tp5/preuves/historique.txt`, `conflit.txt` | 8 cartes `K-01…K-08`, conflit `README.md` résolu, `git log --oneline --graph` | Fait |
| 5 | Application | `src/`, `config/`, `public/`, `tp6/`, `tp7/` | Parcours complet : dépôt, décision, encadrement, soutenance, pièces, exports | Fait |
| 6 | Tests et sécurité | `tests/*.php`, `tp8/rapport.*`, `tp8/preuves/*.txt` | 45 contrôles exécutés le 19/11 : 1 + 6 + 17 + 4 + 17, 0 échec | Fait |
| 7 | Installation et exploitation | `README.md`, `tp9/installation.md`, `tp9/incident.md`, `tp9/scripts/`, `tp9/preuves/` | Second poste simulé, compte à droits réduits, restauration `Verdict : IDENTIQUES` | Fait |
| 8 | Démonstration et rétrospective | `tp10/demonstration.md`, `tp10/soutenance.md`, `tp10/preuves/repetition.txt`, `tp10/preuves/repli.txt` | Scénario cadré à 8 min ; répétition technique reproductible ; repli inventorié | Fait |

## Détail des pièces

### 1. Cadrage et besoins

Le projet part d’un constat simple : les demandes de stage circulent par e-mail
et par papier, sans suivi ni traçabilité. La fiche de cadrage fixe le problème,
les acteurs, le périmètre et les indicateurs. Le document d’exigences liste les
huit besoins fonctionnels, les trois contraintes non fonctionnelles et deux
histoires utilisateur.

### 2. Modélisation et base

Les diagrammes de cas d’utilisation et de classes sont fournis en PNG et en
source PlantUML. `001_schema.sql` pose le socle, `005_extensions.sql` ajoute les
espaces, les notifications, les pièces et les exports. Les cinq insertions
volontairement invalides de `004_contraintes.sql` sont toutes refusées.

### 3. Architecture et contrat d’API

Le code suit `Controllers → Services → Repositories`. Les contrôleurs ne
contiennent ni SQL ni règle métier. Le contrat d’API fixe le format JSON, la
session `STAGESESSID` et l’en-tête `X-CSRF-Token`. L’ADR-001 justifie le
monolithe PHP sans framework.

### 4. Organisation Git

Le Kanban contient huit cartes avec une définition de « fini ». La preuve de
conflit montre une résolution sur `README.md`, puis un nouveau passage des
tests. L’historique garde les étapes dans l’ordre : socle, API, interface,
tests, déploiement.

### 5. Application

L’interface est une page unique qui adapte la navigation au rôle connecté. Les
parcours étudiant, secrétariat, responsable, encadreur et administration sont
opérationnels. La programmation d’une soutenance exige une demande validée, un
encadreur confirmé, une date future, une salle libre et un jury sans conflit.

### 6. Tests et sécurité

La campagne du 19/11 couvre la connexion PDO, les six cas historiques, les 17 cas
de l’API, les quatre cas limites et les 17 contrôles de sécurité. Tous passent.
Un test prouve qu’un étudiant ne peut pas lire le dossier d’un autre ; un autre
vérifie qu’une réponse 403 ne contient aucune donnée privée.

### 7. Installation et exploitation

La procédure TP9 installe le projet sur un second poste avec un compte MySQL
sans droits de structure. Une tentative de `DROP TABLE` est refusée. La
sauvegarde horodatée est restaurée sur une base séparée, puis les comptages
table par table donnent `Verdict : IDENTIQUES` sur 14 tables et 37 lignes.

### 8. Démonstration et rétrospective

Le scénario de huit minutes est découpé minute par minute avec la preuve
associée. `tp10/scripts/repetition.sh` a joué le scénario technique le 19/11 sur
la base `stages_tp10_demo` : 5,92 s pour les appels, 14,83 s pour la campagne de
tests, zéro échec. Le plan de repli est inventorié et haché dans
`tp10/preuves/repli.txt`.

## Vérification à rejouer

```sh
git status --short
bash -n tp9/scripts/*.sh tp10/scripts/repetition.sh
bash tp10/scripts/repetition.sh
git diff --check
```

Le fichier `.env` reste local et ignoré par Git. Aucun mot de passe réel ni jeton
d’accès n’est présent dans les livrables.

## Dette technique assumée

| Élément | Raison | Repris en |
| --- | --- | --- |
| Antivirus sur les pièces jointes | Stockage local contrôlé par type et taille | v2 |
| SMTP réel | `MAIL_MODE=log` suffit pour la démonstration | v2 |
| Limitation des tentatives de connexion | Nécessite un compteur partagé et verrouillé | v2 |
| Frontend en un seul fichier | Correctif rapide, mais difficile à maintenir | v2 |
| Tests sur la base cible | Isolation possible via `ENV_FILE`, pas encore automatisée | v2 |
