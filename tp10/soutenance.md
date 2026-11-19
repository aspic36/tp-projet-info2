# TP10 — Soutenance : réponses individuelles et rétrospective

Le contrôle oral est individuel. Quatre fiches sont préparées ; elles seront
attribuées aux membres réels du groupe. Chacune contient une règle métier, une
requête, un test et un commit. Les références pointent vers le code livré, pas
vers une intention.

## Fiche 1 — socle et modèle de données

**Règle — peut-on refuser une demande déjà validée ?**

Non. La table des transitions est explicite et `VALIDEE` est terminale, comme
`ANNULEE` (`src/Services/DemandeService.php:21-29`). La tentative renvoie 409,
vérifiée par `T-09` et `C-03`.

**Requête — comment deux responsables ne peuvent-ils pas écraser leur décision ?**

La ligne est relue dans la transaction avec un verrou :

```sql
SELECT ... FROM demande_stage d WHERE d.id = :id FOR UPDATE
```

C’est `findByIdForUpdate()` dans `src/Repositories/DemandeRepository.php:52-58`.
Le second responsable attend la fin de la transaction, relit le nouveau statut
et reçoit 409 au lieu d’écrire.

**Test — où prouver que la base refuse un statut inconnu ?**

`tp3/preuves/contraintes.txt` contient cinq violations provoquées, toutes
rejetées. Le test `S-16` vérifie aussi qu’un statut inconnu renvoie 400 côté API.

**Commit — `a6e8e7d` puis `aedf8dd`.**

`a6e8e7d` pose le schéma initial, le jeu d’essai et les preuves de contraintes.
`aedf8dd` ajoute la migration `005_extensions.sql`, qui porte le modèle à 14
tables et ajoute le statut `ANNULEE`.

## Fiche 2 — API et règles métier

**Règle — peut-on refuser sans motif ?**

Non. Le motif est obligatoire et borné à 255 caractères
(`src/Services/DemandeService.php:226-233`). Sans motif, la réponse est 400 avec
le champ `motif` dans `details`.

**Requête — comment la liste est-elle filtrée par utilisateur ?**

Le périmètre vient de la session, jamais du client :

```php
'ETUDIANT'  => ['etudiant_id' => ...],
'ENCADREUR' => ['encadreur_id' => ...],
default     => [],
```

C’est `scope()` dans `src/Services/DemandeService.php:646-653`. La clause
`d.etudiant_id = :etudiant_id` est ensuite ajoutée dans
`src/Repositories/DemandeRepository.php:147-154`. Il n’existe aucun paramètre
client pour demander les dossiers d’un autre étudiant.

**Test — `T-07`, `T-08`, `T-09` dans `tests/api.php`.**

Ils vérifient dans l’ordre : refus sans motif → 400, validation → 200, puis
nouvelle décision sur une demande validée → 409. Sortie dans
`tp10/preuves/repetition.txt`.

**Commit — `b7d1526` « tp6: API des demandes et transitions de statut ».**

Il introduit les dépôts, les services, les contrôleurs, la table de routes et
`tests/api.php`. Le contrat `docs/contrat-api.md` avait été écrit avant le code.

## Fiche 3 — interface, sécurité et tests

**Règle — un bouton masqué suffit-il à interdire une action ?**

Non. L’interface masque ce que le rôle ne peut pas faire, mais le service
revérifie le rôle, la propriété du dossier et la transition. Un appel direct
avec un mauvais rôle renvoie 403 (`src/Services/DemandeService.php:655-667`).

**Requête — pourquoi une injection SQL ne fonctionne pas dans la recherche ?**

Toutes les valeurs passent par des marqueurs préparés. La recherche construit
une clause avec `:q_nom`, `:q_prenom`, `:q_matricule`, `:q_entreprise` et
`:q_sujet` (`src/Repositories/DemandeRepository.php:171-179`). La chaîne
`' OR 1=1 --` devient un simple motif `LIKE` et ne retourne aucune ligne.

**Test — `S-02`, `S-03`, `S-06`, `S-08` dans `tests/securite.php`.**

Ils couvrent le dossier d’autrui, l’absence de fuite dans la réponse 403,
l’identifiant injecté et la recherche injectée. `tp7/preuves/xss.txt` complète
avec le rendu `textContent` de `public/app.js:151-153`.

**Commit — `9315514` puis `8855463`.**

`9315514` livre l’interface ; `8855463` livre l’audit de sécurité et la revue
de code. Le défaut `SQLSTATE[HY093]` y est corrigé en donnant un marqueur
distinct à chaque champ de recherche.

## Fiche 4 — exploitation et sauvegarde

**Règle — pourquoi le second poste ne peut-il pas modifier la structure ?**

Le compte applicatif `stages_tp9_app` reçoit seulement `SELECT`, `INSERT`,
`UPDATE`, `DELETE` sur sa base. Une tentative de `DROP TABLE` renvoie
`ERROR 1142`, conservée dans `tp9/preuves/installation.txt`.

**Requête — comment prouver qu’une restauration est conforme ?**

`tp9/scripts/comptage.sh` exécute un `SELECT COUNT(*)` par table et isole les
écarts. Après la perte simulée de `soutenance_jury`, il affiche `-1` sur cette
table seulement. Après restauration, il affiche `Verdict : IDENTIQUES` sur 14
tables et 37 lignes.

**Test — `tp9/preuves/restauration.txt` et `tp9/preuves/incident.txt`.**

Le premier prouve la restauration ; le second documente l’incident
`INC-2026-11-19-01`, la table supprimée, le code `1146` et l’écart isolé.

**Commit — `5779d0d` « Feat TP9 : ajouter déploiement, sauvegarde et preuves ».**

Il contient les scripts `.sh`, les six fichiers de preuves, le ticket
d’incident et la procédure de déploiement. Les scripts utilisent des variables
d’environnement, pas des mots de passe codés en dur.

## Justification des choix

| Choix | Argument vérifiable |
| --- | --- |
| PHP + PDO | Aucune dépendance Composer ; installation en quelques commandes. Les requêtes sont préparées et `tests/connexion.php` prouve l’accès à la base. |
| Transaction de validation | Statut, historique et audit doivent bouger ensemble ; la notification part après `commit`. Le `beginTransaction()` / `commit()` de `changerStatut()` évite tout état partiel. |
| Contrôle côté serveur | Le périmètre est déduit du rôle en session. Les cas `S-02` à `S-05` montrent des appels directs refusés. |
| Monolithe modulaire | Couches séparées dans un seul déploiement : correction locale, pas d’orchestrateur à installer. `docs/ADR-001.md` recense les alternatives écartées. |

## Rétrospective

**Point positif.** Le découpage en couches a tenu malgré les ajouts. Quand les
espaces, les pièces jointes et les exports sont arrivés, les changements se sont
répartis entre quelques services et contrôleurs sans réécrire l’API existante.
Les preuves rangées par TP ont aussi permis d’assembler ce dossier sans recherche.

**Difficulté.** Le modèle a bougé en cours de route : 7 tables au départ, 14
après la migration `005`, 4 puis 5 statuts, 16 puis 17 cas dans `tests/api.php`.
Les rapports TP4 à TP8 ont dû être alignés (`c8910d4`, `c68144d`) pour que les
chiffres du dossier collent au code réel.

**Changement pour la version 2.** Isoler complètement les tests sur une base
jetable, brancher un SMTP réel, ajouter un antivirus sur les pièces jointes et
limiter les tentatives de connexion. Le frontend sera découpé en modules pour
éviter un fichier `app.js` trop gros.

## Questions probables du jury

| Question | Réponse courte |
| --- | --- |
| Pourquoi pas de framework ? | Le cours impose une installation courte sans Composer ; l’ADR-001 documente le choix. |
| Où sont les migrations ? | `001` à `005` dans `tp3/`, à appliquer dans l’ordre. |
| Que se passe-t-il si la base tombe ? | `/api/sante` répond 503 et l’interface affiche un message générique ; le détail reste dans les logs. |
| Comment sait-on qu’un mot de passe n’est pas stocké en clair ? | `S-10` et `S-11` vérifient le hachage bcrypt et l’absence de clair. |
| Une pièce jointe peut-elle être téléchargée par n’importe qui ? | Non : `PieceService` vérifie le périmètre avant le téléchargement. |
| Pourquoi planning demande-t-il une année et un mois ? | L’écran est mensuel ; sans `annee=2027&mois=1`, la soutenance du 15 janvier n’apparaît pas. |
| Combien d’exigences sont couvertes ? | 8 exigences fonctionnelles et 3 exigences non fonctionnelles, avec matrice dans `tp2/exigences.html`. |
| Quelle preuve montre le déploiement ? | `tp9/preuves/installation.txt` : base neuve, compte limité, DDL refusé, santé 200. |
