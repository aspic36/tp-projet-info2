# Projet informatique 2 — L3 SIL

Travaux pratiques du cours « Projet informatique 2 » (L3 Systèmes d'information et logiciels).

Fil rouge : application web de gestion des stages et des soutenances universitaires.
Données fictives uniquement.

## TP

| TP | Sujet | Livrables |
| --- | --- | --- |
| TP1 | Fiche de cadrage | `tp1/fiche-cadrage.pdf` (2 pages), source `tp1/fiche-cadrage.html` |
| TP2 | Exigences et matrice de traçabilité | `tp2/exigences.pdf` (3 pages), source `tp2/exigences.html` |
| TP3 | UML et modélisation des données | `tp3/modele_donnees.pdf` (4 pages), source `tp3/modele_donnees.html` ; scripts SQL, diagrammes PlantUML et PNG versions |

## Régénérer les PDF

```sh
cd tp1 && libreoffice --headless --convert-to pdf fiche-cadrage.html --outdir .
cd ../tp2 && libreoffice --headless --convert-to pdf exigences.html --outdir .
cd ../tp3 && libreoffice --headless --convert-to pdf modele_donnees.html --outdir .
```

## Reconstruire la base du TP3

```sh
mysql -u root -p -e "CREATE DATABASE stages CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p stages < tp3/001_schema.sql
mysql -u root -p stages < tp3/002_seed.sql
mysql -u root -p stages < tp3/003_verification.sql
```

## Auteur

aspickiller
