# TP9 — Installation, exploitation et sauvegarde

Procédure vérifiée le 18/11/2026 sur Windows 11, PHP 8.3.33, MySQL 8.4.3.
Toutes les commandes partent de la racine du dépôt cloné.

## 1. Prérequis

| Outil | Version testée | Vérification |
| --- | --- | --- |
| PHP CLI | 8.3.33 | `php -v` |
| Extensions PHP | `pdo_mysql`, `mbstring`, `json`, `session` | `php -m` |
| MySQL Server | 8.4.3 | `mysql --version` |
| Client `mysqldump` | 8.0.46 | `mysqldump --version` |
| Git | 2.55 | `git --version` |

Aucune dépendance Composer : le projet n'en a pas.

## 2. Récupérer le code

```powershell
git clone https://github.com/aspic36/tp-projet-info2.git
cd tp-projet-info2
```

Sur un dépôt déjà cloné : `git pull --ff-only`.

## 3. Créer la base (une seule fois)

Les scripts de création sont exécutés par un compte administrateur MySQL,
jamais par le compte applicatif : `stages_app` ne possède aucun droit DDL.

```powershell
$bin = "C:\Mysql\mysql-8.0.46-winx64\bin"   # adapter au poste

& "$bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS stages CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
cmd /c "`"$bin\mysql.exe`" -u root --default-character-set=utf8mb4 stages < tp3/001_schema.sql"
cmd /c "`"$bin\mysql.exe`" -u root --default-character-set=utf8mb4 stages < tp3/002_seed.sql"
```

Contrôle : `tp3/003_verification.sql` doit annoncer 7 tables et les comptages
attendus (8 utilisateurs, 4 demandes, 5 événements d'historique, 1 soutenance).

## 4. Créer le compte applicatif

```sql
CREATE USER 'stages_app'@'localhost' IDENTIFIED BY 'StagesApp!2026';
GRANT SELECT, INSERT, UPDATE, DELETE ON stages.*      TO 'stages_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON stages_test.* TO 'stages_app'@'localhost';
```

Pas de `GRANT ALL`, pas de `DROP`, pas d'`ALTER` : une injection SQL réussie
ne peut ni détruire ni modifier le schéma.

## 5. Configurer l'application

```powershell
Copy-Item .env.example .env
notepad .env     # renseigner DB_PASS
```

`.env` n'est jamais versionné (`.gitignore` ligne 2) ; `.env.example` ne contient
que des valeurs factices.

## 6. Démarrer et vérifier

```powershell
php -S 127.0.0.1:8000 -t public public/index.php
```

| Contrôle | Commande | Attendu |
| --- | --- | --- |
| Connexion PDO | `php tests/connexion.php` | 7 tables, 4 demandes |
| Sonde de santé | `curl http://127.0.0.1:8000/api/sante` | `200` + `"database":"ok"` |
| Tests API | `php tests/api.php` | 0 échec sur 16 cas |
| Audit sécurité | `php tests/securite.php` | 0 échec sur 17 contrôles |

Comptes de démonstration (mot de passe commun `Stage2026!`) :
`administrateur@campus.test`, `accueil@campus.test`,
`responsable.stages@campus.test`, `rachid.kpanou@campus.test`,
`clarisse.sodjinou@campus.test`, `samuel.vodounou@campus.test`.

## 7. Sauvegarde

```powershell
powershell -ExecutionPolicy Bypass -File tp9/scripts/sauvegarde.ps1
```

Le script écrit `sauvegardes/stages-AAAAMMJJ-HHMMSS.sql`, affiche sa taille, son
nombre de lignes et son empreinte SHA-256.

Options retenues pour `mysqldump` :

| Option | Raison |
| --- | --- |
| `--single-transaction` | instantané cohérent sans verrouiller les tables (InnoDB) |
| `--routines --triggers` | sauvegarder aussi la logique serveur, pas seulement les données |
| `--default-character-set=utf8mb4` | éviter toute conversion d'encodage |
| pas de `--databases` | le dump se recharge dans n'importe quelle base cible |

Le dossier `sauvegardes/` est ignoré par Git : un dump n'a pas sa place dans un
dépôt (taille, données, faux sentiment de sécurité). Ce qu'on versionne, c'est la
procédure et les preuves.

## 8. Restauration

```powershell
powershell -ExecutionPolicy Bypass -File tp9/scripts/restauration.ps1 `
    -Fichier sauvegardes/stages-20261118-154238.sql -Cible stages_test
powershell -ExecutionPolicy Bypass -File tp9/scripts/comptage.ps1 -Bases "stages,stages_test"
```

Le script recrée la base cible vide avant de rejouer le dump, puis le comptage
compare table par table. Verdict attendu : `IDENTIQUES`.

## 9. Rythme d'exploitation proposé

| Fréquence | Action | Conservation |
| --- | --- | --- |
| Quotidienne | `sauvegarde.ps1` + copie hors du poste | 7 jours |
| Hebdomadaire | restauration de contrôle dans `stages_test` + `comptage.ps1` | 4 semaines |
| Mensuelle | copie archive chiffrée | 12 mois |

Une sauvegarde jamais restaurée n'est pas une sauvegarde : le contrôle
hebdomadaire est la seule preuve qu'elle est utilisable.

## 10. Limites connues

- `secure` du cookie de session vaut `false` : à passer à `true` derrière HTTPS.
- Pas de chiffrement du dump (à faire avec 7-Zip ou `gpg` avant copie hors site).
- Pas de supervision : la sonde `/api/sante` existe mais n'est interrogée par aucun
  outil externe.
