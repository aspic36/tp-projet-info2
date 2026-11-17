# Projet informatique 2 — L3 SIL

Travaux pratiques du cours « Projet informatique 2 » (L3 Systèmes d'information et logiciels).

Fil rouge : application web de gestion des stages et des soutenances universitaires.
Données fictives uniquement.

## TP

| TP | Sujet | Livrables |
| --- | --- | --- |
| TP1 | Fiche de cadrage | `tp1/fiche-cadrage.pdf` (2 pages), source `tp1/fiche-cadrage.html` |
| TP2 | Exigences et matrice de traçabilité | `tp2/exigences.pdf` (3 pages), source `tp2/exigences.html` |

## Régénérer les PDF

```sh
cd tp1 && libreoffice --headless --convert-to pdf fiche-cadrage.html --outdir .
cd ../tp2 && libreoffice --headless --convert-to pdf exigences.html --outdir .
```

## Auteur

aspickiller
