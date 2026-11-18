# TP7 — Scénario de bout en bout

**Date** : 18 novembre 2026 · **Environnement** : PHP 8.3.33, MySQL 8.4.3,
<http://127.0.0.1:8000> · **Données** : jeu d'essai fictif `tp3/002_seed.sql`.

## Parcours des deux rôles

### Étudiant (4 écrans)

1. **Connexion** — e-mail + mot de passe, erreurs sous chaque champ.
2. **Mes demandes** — tableau filtré par le serveur sur `etudiant_id` ;
   boutons *Soumettre* (brouillon), *Corriger* (refusée), *Historique*.
3. **Dépôt** — choix de l'entreprise, case « Déposer immédiatement ».
4. **Historique** — fenêtre modale listant chaque transition, son auteur et son motif.

### Responsable / secrétariat (3 écrans)

1. **Connexion**.
2. **Demandes à traiter** — recherche par nom, matricule ou entreprise ; filtre
   par statut.
3. **Décision ou planification** — validation directe, refus avec motif
   obligatoire, affectation d'un encadreur, programmation de la soutenance.

## Scénario joué

| # | Action | Résultat observé | Preuve |
| --- | --- | --- | --- |
| 1 | Ouvrir `/` sans session | Écran de connexion, aucun bandeau d'erreur | `preuves/01-connexion.jpeg` |
| 2 | Connexion `rachid.kpanou@campus.test` | 2 demandes (1 soumise, 1 refusée) ; dépôt visible | `preuves/02-etudiant-liste.jpeg` |
| 3 | Choisir « Delta Logistique » | Formulaire rempli, entreprise sélectionnée | `preuves/03-etudiant-formulaire.jpeg` |
| 4 | Créer la demande (dépôt immédiat) | Bandeau « Demande déposée. », 3 demandes, nouvelle ligne **Soumise** | `preuves/04-etudiant-depot.jpeg` |
| 5 | Recharger la page (F5) | 3 demandes, statuts identiques : persistance en base | `preuves/04-etudiant-depot.jpeg` + requête SQL |
| 6 | Connexion responsable | 5 demandes visibles, boutons *Valider* / *Refuser* sur les soumises | `preuves/05-responsable-liste.jpeg` |
| 7 | Refuser la nouvelle demande | Motif saisi, dialogue accessible | `preuves/06-refus-dialogue.jpeg` |
| 8 | Confirmer le refus | Statut **Refusée**, motif affiché sous le badge | `preuves/07-refus-confirme.jpeg` |
| 9 | Valider la demande #1 | Statut **Validée**, bandeau de confirmation | `preuves/08-validation.jpeg` |
| 10 | Connexion secrétariat, affecter A. DJEGBA | Encadreur affiché dans la ligne | `preuves/09-affectation.jpeg` |
| 11 | Programmer la soutenance (11/12/2026 10:00, salle C3, 40 min) | Bandeau « Soutenance programmée. », ligne ajoutée au planning | `preuves/planning.txt` |
| 12 | Recharger après programmation | Planning et statuts conservés | `preuves/planning.txt` |

Contrôle de persistance exécuté après l'étape 5 :

```sql
SELECT id, statut, date_soumission FROM demande_stage ORDER BY id;
```

La demande créée figure en base avec `statut = 'SOUMISE'` et une
`date_soumission` renseignée : le rafraîchissement ne fait que relire la base.

## Gestion des erreurs

| Réponse | Comportement de l'interface |
| --- | --- |
| `400` | message renvoyé par l'API placé sous le champ concerné (`details`) |
| `401` | retour à l'écran de connexion, jeton CSRF rafraîchi |
| `403` | bandeau rouge « Accès interdit à ce dossier. » |
| `409` | bandeau explicite (transition interdite, créneau occupé) |
| `500` | bandeau neutre « Erreur interne du serveur. », détail dans `logs/app.log` |

## Rendu sécurisé

Voir `preuves/xss.txt` : la chaîne
`<b>Delta</b> Logistique <script>alert(1)</script>` est affichée littéralement,
aucune balise n'est créée et aucun script ne s'exécute. Le code utilise
`textContent` et `document.createElement`, jamais `innerHTML` avec une donnée
saisie.
