# TP10 — Scénario de démonstration (8 minutes)

Durée cible : huit minutes. Une machine, un navigateur, deux onglets et un
terminal. Le scénario technique a été rejoué sur `stages_tp10_demo` le 19/11 à
22:58 ; la sortie est dans `tp10/preuves/repetition.txt`.

## Préparation avant le jury

```sh
cp .env.example .env
docker exec tp6_check mysql -uroot -proot -e \
  "DROP DATABASE IF EXISTS stages; CREATE DATABASE stages CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for sql in tp3/001_schema.sql tp3/002_seed.sql tp3/005_extensions.sql; do
  docker exec -i tp6_check mysql -uroot -proot stages < "$sql"
done
php -S 127.0.0.1:8000 -t public public/index.php
curl http://127.0.0.1:8000/api/sante
```

Checklist de démarrage, hors chronomètre :

- `/api/sante` répond `{"status":"ok","database":"ok", ...}`.
- Aucune session ouverte ; `/` affiche l’écran de connexion.
- Onglet 1 : application ; onglet 2 : dépôt GitHub ; terminal prêt pour `SELECT`.
- `tp10/preuves/repli.txt` ouvert dans un troisième onglet.

## Déroulé

| Minutes | Séquence | Action | Preuve montrée |
| --- | --- | --- | --- |
| 0–1 | Contexte | Une phrase : demandes papier, cinq statuts, cinq rôles, journal de chaque transition | Indicateurs de `tp1/fiche-cadrage.html` |
| 1–3 | Dépôt | Connexion `rachid.kpanou@campus.test`, `POST /api/demandes` vers l’entreprise 4 | Ligne `SOUMISE` en base, `GET /api/demandes/{id}/historique` |
| 3–5 | Décision et soutenance | Responsable : refus sans motif → 400, puis validation → 200, nouvelle décision → 409. Secrétariat propose l’encadreur, responsable confirme, secrétariat programme le 2027-01-15 | Statut `VALIDEE`, encadreur confirmé, créneau dans `/api/soutenances/planning?annee=2027&mois=1` |
| 5–6 | Contrôle d’accès | Avec le compte étudiant, `GET /api/demandes/2` → 403 ; `POST /api/demandes` sans `X-CSRF-Token` → 403 | Réponses JSON 403 affichées |
| 6–8 | Tests et bilan | `php tests/api.php` et `php tests/securite.php` | `0 échec(s)`, puis une phrase sur la dette et la v2 |

Mot de passe de démonstration : `Stage2026!`. Ne pas l’afficher à l’écran
pendant la saisie.

## Script oral

> « Le problème : une demande de stage passe d’un bureau à l’autre sans état
> fiable. Nous livrons une application qui porte tout le cycle, du brouillon à
> la soutenance.
> Cinq statuts, cinq rôles, une transition journalisée à chaque changement.
> Je dépose une demande : la ligne apparaît immédiatement en base, statut
> *Soumise*.
> Le responsable ne peut pas refuser sans motif. Il valide ; une seconde
> décision est alors refusée avec un 409.
> Le secrétariat propose un encadreur, le responsable confirme, puis la
> soutenance est programmée avec contrôle de salle et de jury.
> Un étudiant qui ouvre le dossier d’un autre reçoit 403. Ce n’est pas un bouton
> caché : le serveur vérifie à chaque requête.
> Les tests API et sécurité passent sans échec. Restent l’antivirus, le SMTP
> réel et la limitation des tentatives : c’est la version 2. »

## Répétition

`bash tp10/scripts/repetition.sh` remet la base à zéro, lance le serveur sur le
port 8002, joue les appels du scénario, puis exécute les cinq scripts de test.
Résultat enregistré le 19/11 :

- scénario technique : **5,92 s**, 21 appels, codes attendus obtenus ;
- campagne : **14,83 s**, 45 contrôles, **0 échec** ;
- planning : la soutenance du 2027-01-15 apparaît bien quand on demande
  `annee=2027&mois=1`.

Ce chrono couvre la partie technique. Le discours se cale sur le tableau des
huit minutes ; il ne doit pas être lu.

## Solution de repli

| Panne | Repli | Temps de bascule |
| --- | --- | --- |
| Serveur arrêté | Relancer la commande `php -S` préparée | 10 s |
| MySQL arrêté | `systemctl start docker` puis `docker start tp6_check` | 30 s |
| Base polluée | Rejouer `001` + `002` + `005` | 20 s |
| Réseau ou projection HS | Ouvrir `tp10/preuves/repli.txt` puis les captures `tp7/preuves/*.jpeg` | 5 s |
| Jury pressé | Montrer seulement `tp10/preuves/repetition.txt` et `tp8/preuves/securite.txt` | 5 s |

La liste des fichiers de repli, avec tailles et empreintes SHA-256, est dans
`tp10/preuves/repli.txt`.

## Points de vigilance

1. Données fictives uniquement ; le jeu de démonstration est celui du `002_seed.sql`.
2. Ne jamais montrer le mot de passe saisi.
3. Si une action échoue, l’annoncer et enchaîner : la maîtrise compte plus que
   l’absence de panne.
4. Garder le terminal lisible et agrandir la police avant de commencer.
