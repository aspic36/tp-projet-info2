# Contrat de l'API — version 1

Toutes les réponses sont en JSON UTF-8. Les erreurs suivent la forme
`{"message": "...", "details": {"champ": "..."}}`. Le cookie de session
`STAGESESSID` porte l'authentification ; toute écriture exige en plus
l'en-tête `X-CSRF-Token` obtenu via `GET /api/session`.

## 1. `GET /api/demandes`

Liste les demandes visibles par le compte connecté.

- **Authentification** : requise.
- **Périmètre** : étudiant → ses demandes ; encadreur → celles qui lui sont
  affectées ; responsable, secrétariat, administrateur → toutes.
- **Paramètres de requête** (facultatifs) : `statut` (`BROUILLON`, `SOUMISE`,
  `VALIDEE`, `REFUSEE`), `q` (nom, prénom, matricule ou entreprise).

**Succès — 200**

```json
{
  "data": [
    {
      "id": 1,
      "etudiant_id": 1,
      "entreprise_id": 1,
      "encadreur_id": null,
      "statut": "SOUMISE",
      "motif_refus": null,
      "date_soumission": "2026-09-14 08:45:00",
      "date_decision": null,
      "matricule": "SIL24-001",
      "filiere": "SIL",
      "promotion": "2026",
      "etudiant_prenom": "Rachid",
      "etudiant_nom": "KPANOU",
      "etudiant_email": "rachid.kpanou@campus.test",
      "entreprise": "Sahara Tech",
      "entreprise_ville": "Cotonou",
      "encadreur_prenom": null,
      "encadreur_nom": null
    }
  ]
}
```

**Erreurs**

| Code | Cas |
| --- | --- |
| 400 | `statut` hors de la liste autorisée |
| 401 | session absente ou expirée |

## 2. `POST /api/demandes`

Crée une demande pour l'étudiant connecté.

- **Authentification** : requise, rôle `ETUDIANT`.
- **Corps**

```json
{ "entrepriseId": 2, "soumettre": true }
```

| Champ | Type | Obligatoire | Règle |
| --- | --- | --- | --- |
| `entrepriseId` | entier > 0 | oui | doit exister dans `entreprise` |
| `soumettre` | booléen | non (défaut `true`) | `true` → `SOUMISE`, `false` → `BROUILLON` |

**Succès — 201** : la demande créée, même forme que dans la liste, avec
`date_soumission` renseignée si `soumettre` vaut `true`. Un événement est
ajouté à l'historique dans la même transaction.

**Erreurs**

| Code | Cas |
| --- | --- |
| 400 | corps JSON invalide, `entrepriseId` absent ou inconnu |
| 401 | non authentifié |
| 403 | compte non étudiant |

## 3. `PATCH /api/demandes/{id}/statut`

Fait évoluer le statut d'une demande.

- **Authentification** : requise.
- **Corps**

```json
{ "statut": "REFUSEE", "motif": "Convention non signée" }
```

| Champ | Type | Règle |
| --- | --- | --- |
| `statut` | chaîne | `BROUILLON`, `SOUMISE`, `VALIDEE` ou `REFUSEE` |
| `motif` | chaîne ≤ 255 | obligatoire si `statut = REFUSEE`, ignoré sinon |

**Transitions autorisées**

| De | Vers | Qui |
| --- | --- | --- |
| `BROUILLON` | `SOUMISE` | étudiant propriétaire |
| `SOUMISE` | `VALIDEE` | responsable pédagogique |
| `SOUMISE` | `REFUSEE` | responsable pédagogique, motif obligatoire |
| `REFUSEE` | `BROUILLON` | étudiant propriétaire |
| `VALIDEE` | — | aucune transition |

**Succès — 200** : demande relue après mise à jour. Le statut et l'événement
d'historique sont écrits dans une seule transaction, sous verrou
`SELECT … FOR UPDATE`.

**Erreurs**

| Code | Cas |
| --- | --- |
| 400 | statut inconnu, motif absent ou trop long |
| 401 | non authentifié |
| 403 | rôle sans droit sur cette transition, ou dossier d'un autre étudiant |
| 404 | demande inexistante |
| 409 | transition interdite (ex. `VALIDEE → REFUSEE`) |

## 4. Routes complémentaires

| Méthode | Chemin | Corps | Succès | Erreurs principales |
| --- | --- | --- | --- | --- |
| `GET` | `/api/sante` | — | 200 `{"status":"ok","database":"ok"}` | 503 base injoignable |
| `GET` | `/api/session` | — | 200 `{authenticated, user, csrfToken}` | — |
| `POST` | `/api/session` | `{email, motDePasse}` | 200 `{user, csrfToken}` | 400, 401, 403 CSRF |
| `DELETE` | `/api/session` | — | 204 | 401, 403 CSRF |
| `GET` | `/api/demandes/{id}` | — | 200 `{data}` | 401, 403, 404 |
| `PATCH` | `/api/demandes/{id}/encadreur` | `{encadreurId}` | 200 `{data}` | 400, 403, 404, 409 |
| `GET` | `/api/demandes/{id}/historique` | — | 200 `{data:[…]}` | 401, 403, 404 |
| `GET` | `/api/soutenances` | — | 200 `{data:[…]}` | 401 |
| `POST` | `/api/soutenances` | `{demandeId, dateHeure, salle, dureeMin}` | 201 `{data}` | 400, 403, 404, 409 |
| `GET` | `/api/entreprises` | — | 200 `{data:[…]}` | 401 |
| `GET` | `/api/encadreurs` | — | 200 `{data:[…]}` | 401, 403 |
| `GET` | `/api/comptes` | — | 200 `{data:[…]}` | 401, 403 |

### Règles de `POST /api/soutenances`

- Demande existante et **validée** (sinon 409).
- Date future au format `AAAA-MM-JJ HH:MM` (sinon 400).
- Salle de 1 à 40 caractères (sinon 400).
- Durée entre 20 et 90 minutes (défaut 30).
- Une seule soutenance par demande (409) et un seul dossier par créneau
  salle/heure (contrainte d'unicité, 409).
