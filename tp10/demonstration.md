# TP10 — Scénario de démonstration (8 minutes)

Découpage imposé par le cours : contexte, dépôt, validation et programmation,
contrôle d'accès, tests et bilan. Une seule machine, un seul navigateur, deux
fenêtres de terminal préparées à l'avance.

## Préparation (avant que le jury entre)

```powershell
# Terminal 1 — serveur
cd C:\Users\Back Office Permis\Documents\projet-stages
php -S 127.0.0.1:8000 -t public public/index.php

# Terminal 2 — base remise dans son état canonique
cmd /c "C:\Mysql\...\mysql.exe -u root --default-character-set=utf8mb4 stages < tp3/001_schema.sql"
cmd /c "C:\Mysql\...\mysql.exe -u root --default-character-set=utf8mb4 stages < tp3/002_seed.sql"
```

Checklist de démarrage (30 s, hors chronomètre) :

- [ ] `curl http://127.0.0.1:8000/api/sante` renvoie `200`.
- [ ] Aucune session ouverte dans le navigateur (`/` affiche l'écran de connexion).
- [ ] Deux onglets : `http://127.0.0.1:8000` et le dépôt GitHub.
- [ ] Terminal 2 positionné sur la requête `SELECT id, statut FROM demande_stage;`.
- [ ] Captures du dossier `tp7/preuves/` ouvertes dans un troisième onglet (repli).

## Déroulé chronométré

| Minutes | Séquence | Ce qui est montré | Preuve |
| --- | --- | --- | --- |
| 0:00 – 1:00 | **Contexte et problème** | 1 phrase de cadrage, les 4 statuts, les 6 rôles. Aucune démonstration technique. | `tp1/` : indicateurs du cadrage |
| 1:00 – 3:00 | **Dépôt d'une demande** | Connexion `rachid.kpanou@campus.test`, choix de l'entreprise, dépôt immédiat. Bandeau « Demande déposée. », nouvelle ligne **Soumise**. | Ligne créée en base : `SELECT` dans le terminal 2 |
| 3:00 – 5:00 | **Validation et programmation** | Connexion `responsable.stages@campus.test`, refus motivé d'une demande puis validation d'une autre. Connexion `accueil@campus.test`, affectation d'un encadreur, programmation de la soutenance. | Statut **Validée**, encadreur affiché, ligne au planning |
| 5:00 – 6:00 | **Contrôle d'accès** | Retour sur le compte étudiant, appel direct `GET /api/demandes/2` (dossier d'un autre étudiant) → **403**. Puis `PATCH` sans jeton CSRF → **403**. | Réponse JSON 403 affichée dans le navigateur |
| 6:00 – 8:00 | **Tests et bilan** | `php tests/api.php` puis `php tests/securite.php` : 0 échec. Une phrase sur la dette technique, une sur la v2. | Matrice de tests `tp8/rapport.html` |

## Script oral (aide-mémoire, 15 lignes)

> « Le problème : les demandes de stage circulent par e-mail et papier, sans
> traçabilité. Nous livrons une application web qui porte le cycle de vie complet,
> du brouillon à la soutenance.
> Trois routes d'API, quatre statuts, six rôles, un journal de chaque transition.
> Regardez : je dépose une demande… elle arrive en base à l'instant, statut
> *Soumise*.
> Le responsable la voit dans son périmètre, il peut la refuser — mais alors le
> motif est obligatoire — ou la valider. Le secrétariat affecte un encadreur et
> programme la soutenance.
> Question de sécurité : est-ce qu'un étudiant peut lire le dossier d'un autre ?
> Non — 403, et c'est le serveur qui décide, pas le bouton masqué.
> Les tests : 34 vérifications, zéro échec, y compris l'injection SQL et
> l'absence de jeton CSRF.
> Ce qui manque : les pièces jointes et la limitation des tentatives de connexion.
> C'est notre version 2. »

## Solution de repli

| Panne | Repli | Temps de bascule |
| --- | --- | --- |
| Serveur PHP arrêté | Relancer la commande du terminal 1 (elle est en historique) | 10 s |
| MySQL arrêté | `net start MySQL` puis relancer `/api/sante` | 30 s |
| Navigateur ou réseau | Ouvrir `tp7/preuves/` : les 9 captures couvrent tout le parcours | 5 s |
| Base polluée par les essais | Rejouer `001_schema.sql` + `002_seed.sql` | 20 s |
| Projection impossible | Le rapport PDF `tp7/rapport.pdf` contient les captures | 5 s |

La répétition a été faite deux fois au chronomètre : 9 min 10 s au premier essai,
ramenée à 7 min 40 s en supprimant la création du deuxième compte et en
préparant les onglets à l'avance.

## Points de vigilance

1. Ne jamais saisir de données réelles : le jeu d'essai est entièrement fictif.
2. Ne pas montrer le mot de passe à l'écran pendant la saisie (le champ est masqué).
3. Si une action échoue, l'annoncer et enchaîner : le jury évalue la maîtrise, pas
   l'absence de panne.
4. Garder le terminal lisible : agrandir la police avant de commencer.
