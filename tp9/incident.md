# Ticket d'incident — INC-2026-11-18-01

| Champ | Valeur |
| --- | --- |
| **Identifiant** | INC-2026-11-18-01 |
| **Date / heure** | 18/11/2026, 15:42 (GMT+1) |
| **Détecté par** | Audit automatisé `tests/securite.php`, contrôles S-07 et S-08 |
| **Sévérité** | Majeure (fonction de recherche HS, aucune perte de données) |
| **Environnement** | Base `stages`, PHP 8.3.33, MySQL 8.4.3 |
| **Statut** | Clos — corrigé et vérifié |

## Symptôme

La recherche multicritère des demandes renvoyait une erreur **500** dès qu'une
valeur était saisie. Sans valeur, la liste s'affichait normalement.

```
S-07  recherche « ' OR 1=1 -- »   attendu 200 | obtenu 500 | ECHEC
S-08  aucune ligne retournée      ECHEC
```

## Diagnostic

Journal applicatif (`logs/app.log`) :

```
[2026-11-18 15:18:04] Erreur SQLSTATE[HY093]: Invalid parameter number
```

Dans `DemandeRepository::search()`, la clause de recherche réutilisait quatre fois
le même marqueur nommé `:q`, alors que `PDO::ATTR_EMULATE_PREPARES` vaut `false`.
Avec les requêtes réellement préparées, MySQL refuse qu'un marqueur soit lié
plusieurs fois : l'émulation côté PDO masquait ce comportement en développement,
la désactiver l'a rendu visible.

La chaîne saisie n'était jamais concaténée au SQL — il **n'y a donc pas eu de
faille d'injection**, seulement une erreur de liaison de paramètres. C'est le test
qui a révélé le défaut, pas l'inverse.

## Action corrective

Un marqueur par occurrence (`:q_nom`, `:q_prenom`, `:q_matricule`,
`:q_entreprise`), tous liés à la même valeur `%valeur%` :

```php
$where[] = '(u.nom LIKE :q_nom OR u.prenom LIKE :q_prenom
  OR e.matricule LIKE :q_matricule OR en.raison_sociale LIKE :q_entreprise)';
$motif = '%' . $q . '%';
$params[':q_nom'] = $motif;
$params[':q_prenom'] = $motif;
$params[':q_matricule'] = $motif;
$params[':q_entreprise'] = $motif;
```

## Vérification

| Contrôle | Avant | Après |
| --- | --- | --- |
| S-07 recherche `' OR 1=1 --` | 500 | 200 |
| S-08 lignes retournées par l'injection | échec | 0 ligne |
| Jeu de tests complet | 2 échecs | 0 échec sur 17 |

## Prévention

1. Le jeu de tests comporte désormais un cas par clause `WHERE` construite
   dynamiquement.
2. La règle « un marqueur = une liaison » est notée dans `docs/ADR-001.md`
   (section conventions SQL).
3. Exécuter `tests/securite.php` avant chaque `git push` sur `main`.

## Chronologie

| Heure | Événement |
| --- | --- |
| 15:18 | Premier échec S-07 détecté, sortie conservée dans `preuves/securite-avant-correctif.txt` |
| 15:26 | Lecture de `logs/app.log`, identification de `SQLSTATE[HY093]` |
| 15:31 | Correction du dépôt, nouvelle exécution : 0 échec (`preuves/securite.txt`) |
| 15:42 | Sauvegarde de contrôle et restauration validées (TP9) |

## Incident d'exploitation simulé (différent)

Le TP9 demande aussi de simuler une perte de données :

| Champ | Valeur |
| --- | --- |
| **Identifiant** | INC-2026-11-18-02 (simulation) |
| **Symptôme** | `ERROR 1146 (42S02): Table 'stages_test.historique' doesn't exist` |
| **Cause** | `DROP TABLE historique` exécuté par erreur sur `stages_test` |
| **Détection** | Nombre de tables passé de 7 à 6 ; la lecture de l'historique échoue |
| **Remédiation** | `restauration.ps1 -Cible stages_test` depuis le dump de 15:42 (2,51 s) |
| **Vérification** | 7 tables, 5 lignes d'historique, comptage identique à `stages` |
| **Prévention** | Droits DDL réservés au compte administrateur ; `stages_app` ne peut pas faire de `DROP` |

Preuves : `tp9/preuves/incident.txt`.
