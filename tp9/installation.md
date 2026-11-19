# TP9 — Déploiement et sauvegarde

Procédure vérifiée le 19/11/2026 sur PHP 8.4.16 et MySQL 8.0.46. Le « second poste »
a été simulé par un clone Git distinct et une base séparée sur le même serveur MySQL :
`stages_tp9_poste`. Toutes les commandes partent de la racine du dépôt cloné.

## 1. Prérequis

| Outil | Version vérifiée | Contrôle |
| --- | --- | --- |
| PHP CLI | 8.4.16 | `php -v` |
| Extensions PHP | `pdo_mysql`, `mbstring`, `json`, `session` | `php -m` |
| MySQL | 8.0.46 | `mysql --version` |
| Client `mysqldump` | 8.0.46 | `mysqldump --version` |
| Git | 2.55 | `git --version` |

Aucun gestionnaire de dépendances : l'autoload PSR-4 est écrit à la main.

## 2. Récupérer le code

```sh
git clone https://github.com/aspic36/tp-projet-info2.git
cd tp-projet-info2
```

Sur un dépôt déjà présent : `git pull --ff-only`.

## 3. Créer la base et le compte applicatif

Un compte administrateur crée la base et importe les scripts. Le compte applicatif
n'a aucun droit DDL.

```sh
mysql -u root -p -e "CREATE DATABASE stages CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql -u root -p stages < tp3/001_schema.sql
mysql -u root -p stages < tp3/002_seed.sql
mysql -u root -p stages < tp3/005_extensions.sql
```

L'ordre `001` → `002` → `005` est obligatoire. Le script `003_verification.sql`
reste disponible pour un contrôle rapide.

```sql
CREATE USER 'stages_app'@'localhost' IDENTIFIED BY 'mot-de-passe-local';
GRANT SELECT, INSERT, UPDATE, DELETE ON stages.* TO 'stages_app'@'localhost';
```

Aucun `GRANT ALL`, aucun `DROP`, aucun `ALTER` : une injection SQL réussie ne peut
pas détruire le schéma.

## 4. Configurer l'environnement

```sh
cp .env.example .env
```

Renseigner `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` et `DB_PASS`. Le fichier `.env`
est ignoré par Git (`.gitignore` ligne 2) ; `.env.example` ne contient que des
valeurs factices.

## 5. Démarrer et vérifier

```sh
php -S 127.0.0.1:8000 -t public public/index.php
```

| Contrôle | Commande | Attendu |
| --- | --- | --- |
| Connexion PDO | `php tests/connexion.php` | 14 tables, 4 demandes |
| Sonde de santé | `curl http://127.0.0.1:8000/api/sante` | `200` et `"database":"ok"` |
| Scénario API | `php tests/api.php` | 0 échec sur 17 cas |
| Audit sécurité | `php tests/securite.php` | 0 échec |

Le compte applicatif doit refuser un `DROP TABLE` avec `ERROR 1142`. La preuve
complète est dans `preuves/installation.txt`.

## 6. Sauvegarde

Les scripts utilisent les variables d'environnement `DB_HOST`, `DB_PORT`, `DB_USER`
et `DB_PASS`. Ici, `DB_USER` désigne un compte autorisé à lancer `mysqldump`.

```sh
export DB_HOST=127.0.0.1 DB_PORT=3308 DB_USER=root DB_PASS=mot-de-passe-admin
bash tp9/scripts/sauvegarde.sh stages sauvegardes
```

Le script écrit `sauvegardes/stages-AAAAMMJJ-HHMMSS.sql`, puis affiche la taille,
le nombre de lignes et l'empreinte SHA-256. Options retenues :

| Option | Raison |
| --- | --- |
| `--single-transaction` | instantané cohérent sans verrouiller les tables InnoDB |
| `--routines --triggers` | sauvegarde aussi la logique serveur |
| `--default-character-set=utf8mb4` | évite toute conversion d'encodage |
| pas de `--databases` | le dump se recharge dans n'importe quelle base cible |

Le dossier `sauvegardes/` est ignoré par Git. La procédure et les preuves sont
versionnées, pas les dumps.

## 7. Restauration

```sh
bash tp9/scripts/restauration.sh sauvegardes/stages-AAAAMMJJ-HHMMSS.sql stages_test
bash tp9/scripts/comptage.sh stages stages_test
```

Le script recrée la base cible vide avant de rejouer le dump. `comptage.sh` compare
les 14 tables une par une et termine par `Verdict : IDENTIQUES` ou `DIFFERENTES`.
La restauration de contrôle est détaillée dans `preuves/restauration.txt`.

## 8. Rythme d'exploitation proposé

| Fréquence | Action | Conservation |
| --- | --- | --- |
| Quotidienne | `sauvegarde.sh` + copie hors du poste | 7 jours |
| Hebdomadaire | restauration dans `stages_test` + `comptage.sh` | 4 semaines |
| Mensuelle | archive chiffrée | 12 mois |

Une sauvegarde jamais restaurée n'apporte aucune preuve : le contrôle hebdomadaire
dans `stages_test` est celui qui compte.

## 9. Limites connues

- Le dump n'est pas chiffré : le chiffrer avant toute copie hors du poste.
- Aucune supervision n'interroge automatiquement `/api/sante`.
- La simulation du second poste utilise le même serveur MySQL ; la procédure de
  création du compte et du `.env` reste identique sur une machine distante.
