# TP10 — Soutenance : réponses individuelles et rétrospective

Le contrôle oral est individuel : chacun doit pouvoir expliquer une **règle
métier**, une **requête**, un **test** et un **commit** de sa propre
contribution. Les réponses ci-dessous sont rédigées à partir du code réellement
écrit, pas de l'intention.

## M1 — modélisation, socle, exploitation

**Règle métier — « que se passe-t-il si on revalide une demande déjà validée ? »**

Rien : c'est refusé. Le cycle de vie est une table explicite et `VALIDEE` n'a
aucune transition sortante, donc la tentative renvoie `409`.

```php
public const TRANSITIONS = [
    'BROUILLON' => ['SOUMISE'],
    'SOUMISE'   => ['VALIDEE', 'REFUSEE'],
    'REFUSEE'   => ['BROUILLON'],
    'VALIDEE'   => [],
];
```

La valeur `VALIDEE` sert aussi de garde pour l'affectation d'un encadreur :
`409 Seule une demande validée peut recevoir un encadreur.`

**Requête — « comment empêchez-vous deux responsables de décider en même temps ? »**

Le statut est relu **dans la transaction** avec un verrou de ligne :

```sql
SELECT d.id, d.statut, …
FROM demande_stage d
JOIN etudiant e ON e.id = d.etudiant_id
…
WHERE d.id = :id
FOR UPDATE
```

Le `FOR UPDATE` bloque la ligne jusqu'au `commit()` : le second responsable voit
le statut déjà mis à jour et reçoit `409` au lieu d'écraser la décision.

**Test — « lequel prouve que la contrainte de statut tient en base ? »**

`tp3/004_contraintes.sql` : une insertion avec `statut = 'EN_ATTENTE'` doit être
rejetée par la contrainte `CHECK`. Sortie conservée dans
`tp3/preuves/contraintes.txt` : la base refuse la valeur, l'application ne peut
donc pas la produire même en cas de bug du service.

**Commit — « montrez le commit de la modélisation. »**

`tp3: modèle, jeu d'essai et preuves` — il contient `001_schema.sql`
(7 tables, clés étrangères, `CHECK`, `UNIQUE` sur `soutenance.demande_id`),
`002_seed.sql` (une demande par statut), `003_verification.sql` (comptages) et
`004_contraintes.sql` (violations provoquées). Ce commit a été refait parce que
la première version du diagramme et du jeu d'essai ne correspondait pas au
modèle attendu.

## M2 — contrat d'API, backend, dossier

**Règle métier — « peut-on refuser une demande sans expliquer pourquoi ? »**

Non : `400` avec le champ fautif. Le motif est obligatoire **et** borné à
255 caractères, contrôlé côté serveur, pas seulement dans le formulaire.

```php
if ($cible === 'REFUSEE') {
    $motif = trim((string) $motif);
    if ($motif === '') {
        throw new ApiException(400, 'Données invalides.',
            ['motif' => 'Le motif de refus est obligatoire.']);
    }
    if (mb_strlen($motif) > 255) {
        throw new ApiException(400, 'Données invalides.',
            ['motif' => 'Le motif est trop long (max 255).']);
    }
}
```

**Requête — « comment la liste est-elle filtrée sans que l'étudiant voie les autres ? »**

Le périmètre est calculé à partir du rôle, jamais envoyé par le client :

```php
private function scope(array $user): array
{
    return match ($user['role']) {
        'ETUDIANT'  => ['etudiant_id' => (int) $this->exigerEtudiant($user)['etudiant_id']],
        'ENCADREUR' => ['encadreur_id' => (int) ($user['encadreur_id'] ?? 0)],
        default     => [],
    };
}
```

`search()` ajoute alors `AND d.etudiant_id = :etudiant_id` (ou
`d.encadreur_id = :encadreur_id`) dans la clause `WHERE`. Un étudiant ne peut pas
demander « les demandes de quelqu'un d'autre » : il n'y a pas de paramètre pour
cela.

**Test — « quelle preuve avez-vous que le motif est vraiment exigé ? »**

`T-07 REFUSEE sans motif, attendu 400 | obtenu 400` dans
`tp6/preuves/api.txt`. Le même script vérifie que la transition interdite
`VALIDEE → REFUSEE` renvoie `409` (cas T-09).

**Commit — « montrez le commit de l'API. »**

`tp6: API des demandes` — dépôts, services, contrôleurs, table de routes et
`tests/api.php` (16 cas). C'est aussi dans ce lot que le contrat
`docs/contrat-api.md` a été écrit, avant le code, pour figer les codes HTTP.

## M3 — interface, tests de sécurité

**Règle métier — « un bouton masqué suffit-il à interdire une action ? »**

Non. L'interface masque ce que le rôle ne peut pas faire, mais chaque appel est
revalidé côté serveur : rôle, propriété du dossier, transition. Un `PATCH` forgé
avec un jeton CSRF valide mais un mauvais rôle reçoit `403` — c'est le cas S-04.
Le masquage est un confort, la sécurité est dans le service.

**Requête — « où se joue la protection contre l'injection ? »**

Partout, parce qu'il n'existe aucun SQL concaténé : 100 % des requêtes sont
préparées et `PDO::ATTR_EMULATE_PREPARES` vaut `false`. La recherche construit
une clause `WHERE` dynamique mais uniquement avec des marqueurs :

```php
$where[] = '(u.nom LIKE :q_nom OR u.prenom LIKE :q_prenom
  OR e.matricule LIKE :q_matricule OR en.raison_sociale LIKE :q_entreprise)';
```

C'est exactement la correction de l'incident `INC-2026-11-18-01` : le même
marqueur réutilisé quatre fois provoquait `SQLSTATE[HY093]`.

**Test — « montrez un 403. »**

S-02 : l'étudiant B demande le dossier de l'étudiant A →
`403 Accès interdit à ce dossier.` S-03 vérifie en plus que la réponse ne
contient aucune donnée du dossier : un refus ne doit pas fuiter d'information.
Sortie : `tp8/preuves/securite.txt`.

**Commit — « montrez le commit de l'interface. »**

`tp7: interface web` — `index.html`, `style.css`, `app.js`. Le rendu utilise
`textContent` et `createElement`, jamais `innerHTML` avec une donnée : c'est ce
qui rend la capture `preuves/xss.txt` concluante (zéro balise créée).

## Justification des choix (exercice 3 du cours, deux lignes chacun)

| Choix | Justification vérifiable |
| --- | --- |
| PHP + PDO | Installation limitée à PHP et MySQL, sans Composer ; PDO fournit les requêtes préparées et les exceptions. Preuve : `tests/connexion.php` et 0 dépendance dans le dépôt. |
| Transaction de validation | Le statut et l'historique doivent bouger ensemble ; le `rollBack()` garantit qu'aucun état partiel ne subsiste. Preuve : code de `changerStatut()` + cas T-09. |
| Contrôle d'accès côté serveur | Le périmètre est déduit du rôle en session, jamais d'un paramètre client ; un appel direct renvoie 403. Preuve : cas S-02 à S-05. |

## Rétrospective

**Point positif.** Le découpage `Controllers → Services → Repositories` a tenu
jusqu'au bout : quand l'incident `HY093` est apparu, la correction a tenu dans une
seule méthode d'un seul fichier, sans toucher au reste. Les preuves étaient déjà
rangées par TP, donc le dossier s'est assemblé sans fouille.

**Difficulté.** Le temps. Le TP3 a dû être refait parce que le diagramme et le jeu
d'essai ne correspondaient pas au modèle attendu, et cette reprise a consommé une
séance entière. La seconde difficulté a été l'encodage : des scripts SQL en UTF-8
rejoués par un client en ANSI ont produit des `Ars??ne`, corrigé en imposant
`--default-character-set=utf8mb4` partout.

**Changement pour la version 2.** Sortir les tests de la base de développement :
`tests/api.php` et `tests/securite.php` écrivent aujourd'hui dans `stages`, ce qui
oblige à rejouer le jeu d'essai après chaque campagne. La v2 les fera tourner sur
`stages_test` avec une remise à zéro automatique, et ajoutera la limitation des
tentatives de connexion ainsi que les pièces jointes.

## Questions probables du jury

| Question | Réponse courte |
| --- | --- |
| Pourquoi pas de framework ? | Le cours interdit d'en dépendre pour l'évaluation ; le temps d'installation doit rester sous 10 minutes sur un poste sans Composer. |
| Où sont les migrations ? | `tp3/001_schema.sql` fait office de migration initiale ; la v2 introduira un dossier `migrations/` numéroté. |
| Que se passe-t-il si la base tombe ? | `/api/sante` répond 503, l'interface affiche « Erreur interne du serveur », le détail va dans `logs/app.log`. |
| Comment savez-vous que le rendu est sûr ? | Test XSS stocké : balise injectée en base, zéro élément créé, texte affiché littéralement. |
| Combien de commits, combien d'auteurs ? | Voir `git log --oneline --graph` : branches courtes fusionnées en `--no-ff`, un commit par carte du Kanban. |
