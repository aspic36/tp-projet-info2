# Ticket d'incident — INC-2026-11-19-01

| Champ | Valeur |
| --- | --- |
| **Identifiant** | INC-2026-11-19-01 |
| **Date / heure** | 19/11/2026, 22:25 (GMT+1) |
| **Type** | Simulation de perte de données — TP9 |
| **Environnement** | Base `stages_tp9_restore`, MySQL 8.0.46 |
| **Sévérité** | Majeure sur la base de test, aucune donnée de production touchée |
| **Statut** | Clos — restauration vérifiée |

## Symptôme

Une table a disparu de la base de test après une commande `DROP TABLE` exécutée
par erreur avec le compte administrateur :

```sql
DROP TABLE stages_tp9_restore.soutenance_jury;
```

Le nombre de tables est passé de 14 à 13. Toute lecture de la table renvoyait :

```
ERROR 1146 (42S02) at line 1: Table 'stages_tp9_restore.soutenance_jury' doesn't exist
```

Le compte applicatif `stages_tp9_app` n'a pas pu provoquer cet incident : il ne
dispose que de `SELECT, INSERT, UPDATE, DELETE` et reçoit `ERROR 1142` pour un
`DROP`. La preuve figure dans `preuves/installation.txt`.

## Impact

- 1 table manquante sur 14.
- 1 ligne de jury de soutenance perdue dans la base de test.
- Aucun impact sur `stages_tp9_poste`, qui sert de source de sauvegarde.
- Aucun impact sur la base principale `stages`.

## Diagnostic

La cause est une action humaine avec un compte disposant de droits DDL. Le
script `comptage.sh` exécuté avant restauration a isolé l'écart :

```
soutenance_jury   1  -1  <-- ECART
Totaux : stages_tp9_poste = 37 | stages_tp9_restore = 35
Verdict : DIFFERENTES
```

Les autres tables restent à zéro écart. Le détail est dans `preuves/incident.txt`.

## Remédiation

Restauration de la base cible depuis le dump de 22:22 :

```sh
export DB_HOST=127.0.0.1 DB_PORT=3308 DB_USER=root DB_PASS=mot-de-passe-admin
bash tp9/scripts/restauration.sh \
  sauvegardes/stages_tp9_poste-20261119-222227.sql \
  stages_tp9_restore
bash tp9/scripts/comptage.sh stages_tp9_poste stages_tp9_restore
```

Résultat :

```
Totaux : stages_tp9_poste = 37 | stages_tp9_restore = 37
Verdict : IDENTIQUES
```

La table `soutenance_jury` est revenue avec sa ligne. Sortie complète :
`preuves/restauration.txt`.

## Prévention

1. Réserver les droits DDL au compte administrateur ; le compte applicatif reste
   limité à `SELECT, INSERT, UPDATE, DELETE`.
2. Ne jamais exécuter de commande destructive sur la base principale : passer par
   `stages_test` puis comparer avec `comptage.sh`.
3. conserver un dump vérifié par restauration avant toute manipulation de schéma.
4. Copier le dump hors du poste après chaque sauvegarde quotidienne.

## Chronologie

| Heure | Événement |
| --- | --- |
| 22:22 | Sauvegarde de `stages_tp9_poste`, SHA-256 `9756afe5…d54939` |
| 22:23 | Restauration de contrôle dans `stages_tp9_restore` : `IDENTIQUES` |
| 22:25 | `DROP TABLE soutenance_jury` sur la base de test |
| 22:25 | Comptage : 13 tables, `DIFFERENTES` |
| 22:25 | Restauration depuis le dump, nouveau comptage : `IDENTIQUES` |
