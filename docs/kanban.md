# Kanban — TP5

Tableau de huit cartes, chacune tenant dans moins d'une séance (1 h à 2 h).
Une carte n'est terminée que si sa définition de « fini » est remplie et si un
commit signé la référence.

## Colonnes

| À faire | En cours | En revue | Terminé |
| --- | --- | --- | --- |
| — | — | — | K-01 à K-08 |

## Cartes

| ID | Carte | Responsable | Estimation | Définition de « fini » | Commit |
| --- | --- | --- | --- | --- | --- |
| K-01 | Modèle relationnel et jeu d'essai | M1 | 1 h 30 | `001_schema.sql` + `002_seed.sql` rejouables ; `COUNT(*)` conformes ; contraintes testées | `tp3: modèle, jeu d'essai et preuves` |
| K-02 | Squelette, configuration et connexion PDO | M1 | 1 h | `.env` ignoré ; `tests/connexion.php` OK ; `/api/sante` 200 et 503 | `tp4: socle technique et contrat d'API` |
| K-03 | Contrat d'API et ADR-001 | M2 | 45 min | Trois routes décrites (corps, codes) ; ADR avec alternatives écartées | idem K-02 |
| K-04 | API des demandes et transitions | M2 | 2 h | 16 cas `tests/api.php` sans échec ; 400/401/403/404/409 couverts | `tp6: API des demandes` |
| K-05 | Interface web des deux rôles | M3 | 2 h | Parcours de bout en bout ; rendu par `textContent` ; dialogues accessibles | `tp7: interface web` |
| K-06 | Tests de sécurité et revue de code | M3 | 1 h 30 | 17 contrôles sans échec ; bug HY093 corrigé et documenté | `tp8: tests et sécurité` |
| K-07 | Installation, sauvegarde et restauration | M1 | 1 h 30 | Installation sur base neuve ; dump + restauration + `COUNT(*)` identiques | `tp9: installation et exploitation` |
| K-08 | Dossier de livraison et soutenance | M2 + M3 | 1 h 30 | Liste de contrôle des 8 éléments ; démonstration chronométrée ; rétrospective | `tp10: dossier et soutenance` |

## Règles de l'équipe

1. **Une carte = une branche courte** nommée `feature/<carte>`.
2. **Deux commits maximum par branche** : un pour le code, un pour les preuves.
3. **Revue obligatoire** : le diff est relu par un autre membre avant fusion
   (`git diff main...feature/xxx`), puis fusion `--no-ff` pour garder la trace.
4. **Tests verts sur `main` après chaque fusion** : `tests/connexion.php`,
   `tests/api.php`, `tests/securite.php`. Une carte qui casse une carte déjà
   fusionnée repasse en revue avant la fusion suivante.
5. **Une carte ne repasse pas en « À faire »** : si elle bloque, elle est réduite
   ou découpée, jamais laissée en cours plus d'une séance.

## Suivi des séances

| Séance | Cartes entamées | Cartes terminées | Blocage |
| --- | --- | --- | --- |
| S1 | K-01 | K-01 | Aucun |
| S2 | K-02, K-03 | K-02, K-03 | Compilation du contrat d'API |
| S3 | K-04 | K-04 | Paramètre `:q` répété (voir INC-2026-11-18-01) |
| S4 | K-05 | K-05 | Rendu des écrans masqués (`[hidden]`) |
| S5 | K-06 | K-06 | Aucun |
| S6 | K-07 | K-07 | Restauration à rejouer deux fois |
| S7 | K-08 | K-08 | Répétition à 9 min, ramenée à 8 |

## Contribution visible

| Membre | Branches | Commits |
| --- | --- | --- |
| M1 | `feature/tp4-socle`, `feature/tp9-exploitation` | 2 |
| M2 | `feature/tp6-api`, `feature/tp10-livraison` | 2 |
| M3 | `feature/tp7-interface`, `feature/tp8-tests` | 2 |

Les libellés `M1` à `M3` sont remplacés par les prénoms de l'équipe dans la
version remise ; l'historique Git fait foi pour l'attribution réelle.
