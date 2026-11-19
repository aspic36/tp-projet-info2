'use strict';

/* ------------------------------------------------------------------ *
 * État de l'interface
 * ------------------------------------------------------------------ */
const etat = {
  user: null,
  csrf: null,
  entreprises: [],
  encadreurs: [],
  demandes: [],
  soutenances: [],
  filtres: { q: '', statut: '' },
  demandeCourante: null,
};

const LIBELLES_ROLE = {
  ETUDIANT: 'Étudiant',
  SECRETARIAT: 'Secrétariat',
  RESPONSABLE: 'Responsable pédagogique',
  ENCADREUR: 'Encadreur',
  ADMIN: 'Administrateur',
};

class ErreurApi extends Error {
  constructor(message, status, details = {}) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

const $ = (id) => document.getElementById(id);

/* ------------------------------------------------------------------ *
 * Client HTTP : JSON, cookie de session, jeton CSRF
 * ------------------------------------------------------------------ */
async function api(methode, chemin, corps) {
  const entetes = { Accept: 'application/json' };
  if (corps !== undefined) entetes['Content-Type'] = 'application/json';
  if (etat.csrf) entetes['X-CSRF-Token'] = etat.csrf;

  let reponse;
  try {
    reponse = await fetch(chemin, {
      method: methode,
      headers: entetes,
      credentials: 'same-origin',
      body: corps === undefined ? undefined : JSON.stringify(corps),
    });
  } catch {
    throw new ErreurApi('Serveur injoignable. Vérifiez que le serveur PHP est démarré.', 0);
  }

  let donnees = null;
  if (reponse.status !== 204) {
    try {
      donnees = await reponse.json();
    } catch {
      donnees = null;
    }
  }

  if (!reponse.ok) {
    if (reponse.status === 401 && chemin !== '/api/session') {
      afficherConnexion();
    }
    throw new ErreurApi(
      (donnees && donnees.message) || 'Erreur inattendue.',
      reponse.status,
      (donnees && donnees.details) || {}
    );
  }

  return donnees;
}

/* ------------------------------------------------------------------ *
 * Utilitaires d'affichage
 * ------------------------------------------------------------------ */
function creer(balise, { classe, texte } = {}) {
  const noeud = document.createElement(balise);
  if (classe) noeud.className = classe;
  if (texte !== undefined) noeud.textContent = texte;
  return noeud;
}

function formaterDate(valeur) {
  if (!valeur) return '—';
  const date = new Date(String(valeur).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(valeur);
  return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

function libelleStatut(statut) {
  return {
    BROUILLON: 'Brouillon',
    SOUMISE: 'Soumise',
    VALIDEE: 'Validée',
    REFUSEE: 'Refusée',
  }[statut] || statut;
}

function badgeStatut(statut) {
  return creer('span', { classe: `badge statut-${String(statut).toLowerCase()}`, texte: libelleStatut(statut) });
}

function afficherBandeau(message, type = 'info') {
  const bandeau = $('bandeau');
  bandeau.textContent = message;
  bandeau.className = 'bandeau' + (type === 'erreur' ? ' erreur' : type === 'succes' ? ' succes' : '');
  bandeau.hidden = false;
  if (type === 'succes') {
    window.clearTimeout(afficherBandeau.minuteur);
    afficherBandeau.minuteur = window.setTimeout(() => { bandeau.hidden = true; }, 6000);
  }
}

function masquerBandeau() {
  $('bandeau').hidden = true;
}

function nettoyerErreurs(formulaire) {
  formulaire.querySelectorAll('.erreur').forEach((p) => { p.textContent = ''; });
  formulaire.querySelectorAll('[aria-invalid]').forEach((champ) => champ.removeAttribute('aria-invalid'));
}

function afficherErreurChamp(idErreur, message, champ) {
  const zone = $(idErreur);
  if (zone) zone.textContent = message;
  if (champ) {
    champ.setAttribute('aria-invalid', 'true');
    champ.focus();
  }
}

/* ------------------------------------------------------------------ *
 * Connexion
 * ------------------------------------------------------------------ */
/**
 * La déconnexion détruit la session côté serveur : le jeton CSRF en mémoire
 * devient donc invalide. On en récupère un neuf avant tout nouvel envoi.
 */
async function rafraichirJeton() {
  try {
    const reponse = await fetch('/api/session', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const session = await reponse.json();
    etat.csrf = session.csrfToken;
    if (session.authenticated && !etat.user) {
      etat.user = session.user;
    }
  } catch {
    /* réseau indisponible : le message d'erreur viendra de la tentative */
  }
}

function afficherConnexion() {
  etat.user = null;
  etat.demandes = [];
  etat.soutenances = [];
  $('vue-application').hidden = true;
  $('zone-utilisateur').hidden = true;
  $('vue-connexion').hidden = false;
  $('form-connexion').reset();
  nettoyerErreurs($('form-connexion'));
  $('email').focus();
  rafraichirJeton();
}

async function connexion(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-connexion');
  nettoyerErreurs(formulaire);
  masquerBandeau();

  const email = $('email').value.trim();
  const motDePasse = $('mot-de-passe').value;

  if (!email) return afficherErreurChamp('erreur-email', 'L’adresse e-mail est obligatoire.', $('email'));
  if (!motDePasse) return afficherErreurChamp('erreur-mot-de-passe', 'Le mot de passe est obligatoire.', $('mot-de-passe'));

  const bouton = formulaire.querySelector('button[type="submit"]');
  bouton.disabled = true;
  try {
    const reponse = await api('POST', '/api/session', { email, motDePasse });
    etat.csrf = reponse.csrfToken;
    etat.user = reponse.user;
    await demarrerApplication();
  } catch (erreur) {
    if (erreur.status === 401) {
      afficherErreurChamp('erreur-mot-de-passe', 'Identifiants incorrects.', $('mot-de-passe'));
    } else {
      afficherBandeau(erreur.message, 'erreur');
    }
  } finally {
    bouton.disabled = false;
  }
}

async function deconnexion() {
  try {
    await api('DELETE', '/api/session');
  } catch {
    /* la session est de toute façon fermée côté navigateur */
  }
  etat.csrf = null;
  afficherConnexion();
  await rafraichirJeton();
}

/* ------------------------------------------------------------------ *
 * Démarrage de l'application selon le rôle
 * ------------------------------------------------------------------ */
async function demarrerApplication() {
  $('vue-connexion').hidden = true;
  $('vue-application').hidden = false;
  $('zone-utilisateur').hidden = false;

  $('nom-utilisateur').textContent = `${etat.user.prenom} ${etat.user.nom}`;
  $('role-utilisateur').textContent = LIBELLES_ROLE[etat.user.role] || etat.user.role;

  const estEtudiant = etat.user.role === 'ETUDIANT';
  $('bloc-etudiant').hidden = !estEtudiant;
  $('filtres').hidden = !['RESPONSABLE', 'SECRETARIAT', 'ADMIN'].includes(etat.user.role);

  await chargerReferentiels();
  await Promise.all([chargerDemandes(), chargerSoutenances()]);
}

async function chargerReferentiels() {
  if (etat.user.role === 'ETUDIANT') {
    const reponse = await api('GET', '/api/entreprises');
    etat.entreprises = reponse.data;
    const select = $('entreprise');
    select.replaceChildren(creer('option', { texte: 'Choisir une entreprise…' }));
    select.firstChild.value = '';
    for (const entreprise of etat.entreprises) {
      const option = creer('option', { texte: `${entreprise.raison_sociale} — ${entreprise.ville}` });
      option.value = String(entreprise.id);
      select.append(option);
    }
  }

  if (['SECRETARIAT', 'RESPONSABLE', 'ADMIN'].includes(etat.user.role)) {
    const reponse = await api('GET', '/api/encadreurs');
    etat.encadreurs = reponse.data;
  }
}

async function chargerDemandes() {
  const parametres = new URLSearchParams();
  if (etat.filtres.q) parametres.set('q', etat.filtres.q);
  if (etat.filtres.statut) parametres.set('statut', etat.filtres.statut);
  const suffixe = parametres.toString() ? `?${parametres}` : '';
  const reponse = await api('GET', `/api/demandes${suffixe}`);
  etat.demandes = reponse.data;
  rendreDemandes();
}

async function chargerSoutenances() {
  const reponse = await api('GET', '/api/soutenances');
  etat.soutenances = reponse.data;
  rendrePlanning();
}

/* ------------------------------------------------------------------ *
 * Tableau des demandes
 * ------------------------------------------------------------------ */
function rendreDemandes() {
  const corps = $('corps-demandes');
  corps.replaceChildren();

  $('resume-demandes').textContent = etat.demandes.length === 0
    ? 'Aucune demande dans votre périmètre.'
    : `${etat.demandes.length} demande${etat.demandes.length > 1 ? 's' : ''} dans votre périmètre.`;

  $('etat-vide').hidden = etat.demandes.length > 0;

  for (const demande of etat.demandes) {
    const ligne = creer('tr');

    const demandeur = creer('td');
    demandeur.append(creer('strong', { texte: `${demande.etudiant_prenom} ${demande.etudiant_nom}` }));
    demandeur.append(creer('div', { classe: 'col-secondaire', texte: `${demande.matricule} · ${demande.filiere}` }));
    demandeur.dataset.label = 'Demandeur';

    const entreprise = creer('td');
    entreprise.append(creer('strong', { texte: demande.entreprise }));
    entreprise.append(creer('div', { classe: 'col-secondaire', texte: demande.entreprise_ville }));
    entreprise.dataset.label = 'Entreprise';

    const statut = creer('td');
    statut.append(badgeStatut(demande.statut));
    if (demande.statut === 'REFUSEE' && demande.motif_refus) {
      statut.append(creer('div', { classe: 'col-secondaire', texte: demande.motif_refus }));
    }
    statut.dataset.label = 'Statut';

    const encadreur = creer('td');
    encadreur.textContent = demande.encadreur_nom
      ? `${demande.encadreur_prenom} ${demande.encadreur_nom}`
      : '—';
    encadreur.dataset.label = 'Encadreur';

    const date = creer('td', { texte: formaterDate(demande.date_soumission || demande.date_decision) });
    date.dataset.label = 'Déposée le';

    const actions = creer('td');
    actions.dataset.label = 'Actions';
    const groupe = creer('div', { classe: 'actions-cellule' });
    construireActions(groupe, demande);
    actions.append(groupe);

    ligne.append(demandeur, entreprise, statut, encadreur, date, actions);
    corps.append(ligne);
  }
}

function boutonAction(libelle, action, demandeId, classe = 'btn btn-petit') {
  const bouton = creer('button', { classe, texte: libelle });
  bouton.type = 'button';
  bouton.dataset.action = action;
  bouton.dataset.id = String(demandeId);
  return bouton;
}

function construireActions(conteneur, demande) {
  const role = etat.user.role;

  if (role === 'ETUDIANT') {
    if (demande.statut === 'BROUILLON') {
      conteneur.append(boutonAction('Soumettre', 'soumettre', demande.id, 'btn btn-petit btn-principal'));
    }
    if (demande.statut === 'REFUSEE') {
      conteneur.append(boutonAction('Corriger', 'brouillon', demande.id));
    }
  }

  if (role === 'RESPONSABLE' && demande.statut === 'SOUMISE') {
    conteneur.append(boutonAction('Valider', 'valider', demande.id, 'btn btn-petit btn-principal'));
    conteneur.append(boutonAction('Refuser', 'refuser', demande.id, 'btn btn-petit btn-danger'));
  }

  if (['SECRETARIAT', 'ADMIN'].includes(role) && demande.statut === 'VALIDEE') {
    const select = creer('select', { classe: 'btn btn-petit' });
    select.setAttribute('aria-label', `Encadreur de la demande ${demande.id}`);
    select.append(creer('option', { texte: 'Encadreur…' }));
    select.firstChild.value = '';
    for (const encadreur of etat.encadreurs) {
      const option = creer('option', { texte: `${encadreur.prenom} ${encadreur.nom} (${encadreur.grade})` });
      option.value = String(encadreur.id);
      if (demande.encadreur_id === encadreur.id) option.selected = true;
      select.append(option);
    }
    select.dataset.selectEncadreur = String(demande.id);
    conteneur.append(select);
    conteneur.append(boutonAction('Affecter', 'affecter', demande.id));

    const dejaProgrammee = etat.soutenances.some((s) => s.demande_id === demande.id);
    if (!dejaProgrammee) {
      conteneur.append(boutonAction('Programmer', 'programmer', demande.id, 'btn btn-petit btn-principal'));
    }
  }

  conteneur.append(boutonAction('Détail', 'detail', demande.id, 'btn btn-petit btn-discret'));
  conteneur.append(boutonAction('Historique', 'historique', demande.id, 'btn btn-petit btn-discret'));
}

/* ------------------------------------------------------------------ *
 * Planning des soutenances
 * ------------------------------------------------------------------ */
function rendrePlanning() {
  const bloc = $('bloc-planning');
  bloc.hidden = !['SECRETARIAT', 'RESPONSABLE', 'ADMIN', 'ETUDIANT', 'ENCADREUR'].includes(etat.user.role);

  const corps = $('corps-planning');
  corps.replaceChildren();
  $('planning-vide').hidden = etat.soutenances.length > 0;

  for (const soutenance of etat.soutenances) {
    const ligne = creer('tr');
    const cellules = [
      ['Date', formaterDate(soutenance.date_heure)],
      ['Salle', soutenance.salle],
      ['Étudiant', `${soutenance.etudiant_prenom} ${soutenance.etudiant_nom}`],
      ['Entreprise', soutenance.entreprise],
      ['Durée', `${soutenance.duree_min} min`],
    ];
    for (const [libelle, valeur] of cellules) {
      const cellule = creer('td', { texte: valeur });
      cellule.dataset.label = libelle;
      ligne.append(cellule);
    }
    corps.append(ligne);
  }
}

/* ------------------------------------------------------------------ *
 * Actions sur une demande
 * ------------------------------------------------------------------ */
async function agir(action, demandeId) {
  const demande = etat.demandes.find((d) => d.id === demandeId);
  if (!demande) return;

  try {
    masquerBandeau();

    if (action === 'soumettre') {
      await api('PATCH', `/api/demandes/${demandeId}/statut`, { statut: 'SOUMISE' });
      afficherBandeau('Demande soumise au responsable.', 'succes');
      await chargerDemandes();
    }

    if (action === 'brouillon') {
      await api('PATCH', `/api/demandes/${demandeId}/statut`, { statut: 'BROUILLON' });
      afficherBandeau('La demande repasse en brouillon : corrigez puis soumettez à nouveau.', 'succes');
      await chargerDemandes();
    }

    if (action === 'valider') {
      await api('PATCH', `/api/demandes/${demandeId}/statut`, { statut: 'VALIDEE' });
      afficherBandeau('Demande validée.', 'succes');
      await chargerDemandes();
    }

    if (action === 'refuser') {
      ouvrirRefus(demandeId);
    }

    if (action === 'affecter') {
      const select = document.querySelector(`select[data-select-encadreur="${demandeId}"]`);
      const encadreurId = select ? Number(select.value) : 0;
      if (!encadreurId) {
        afficherBandeau('Choisissez un encadreur avant d’affecter.', 'erreur');
        return;
      }
      await api('PATCH', `/api/demandes/${demandeId}/encadreur`, { encadreurId });
      afficherBandeau('Encadreur affecté.', 'succes');
      await chargerDemandes();
    }

    if (action === 'programmer') {
      ouvrirSoutenance(demandeId);
    }

    if (action === 'detail') {
      await ouvrirDetail(demandeId);
    }

    if (action === 'historique') {
      await ouvrirHistorique(demandeId);
    }
  } catch (erreur) {
    afficherBandeau(erreur.message, 'erreur');
  }
}

/* ------------------------------------------------------------------ *
 * Dialogue de refus
 * ------------------------------------------------------------------ */
function ouvrirRefus(demandeId) {
  etat.demandeCourante = demandeId;
  const dialogue = $('dialogue-refus');
  $('motif-refus').value = '';
  nettoyerErreurs($('form-refus'));
  dialogue.showModal();
  $('motif-refus').focus();
}

async function confirmerRefus(evenement) {
  evenement.preventDefault();
  const motif = $('motif-refus').value.trim();
  nettoyerErreurs($('form-refus'));

  if (!motif) {
    afficherErreurChamp('erreur-motif', 'Le motif de refus est obligatoire.', $('motif-refus'));
    return;
  }

  try {
    await api('PATCH', `/api/demandes/${etat.demandeCourante}/statut`, { statut: 'REFUSEE', motif });
    $('dialogue-refus').close();
    afficherBandeau('Demande refusée avec motif.', 'succes');
    await chargerDemandes();
  } catch (erreur) {
    afficherErreurChamp('erreur-motif', erreur.details.motif || erreur.message, $('motif-refus'));
  }
}

/* ------------------------------------------------------------------ *
 * Dialogue de soutenance
 * ------------------------------------------------------------------ */
function ouvrirSoutenance(demandeId) {
  etat.demandeCourante = demandeId;
  const demain = new Date();
  demain.setDate(demain.getDate() + 1);
  demain.setHours(9, 0, 0, 0);
  const valeur = demain.toISOString().slice(0, 16);
  $('date-soutenance').value = valeur;
  $('salle-soutenance').value = '';
  $('duree-soutenance').value = '30';
  nettoyerErreurs($('form-soutenance'));
  $('dialogue-soutenance').showModal();
  $('date-soutenance').focus();
}

async function confirmerSoutenance(evenement) {
  evenement.preventDefault();
  nettoyerErreurs($('form-soutenance'));

  const dateHeure = $('date-soutenance').value;
  const salle = $('salle-soutenance').value.trim();
  const dureeMin = Number($('duree-soutenance').value);

  if (!dateHeure) {
    afficherErreurChamp('erreur-date', 'La date est obligatoire.', $('date-soutenance'));
    return;
  }
  if (!salle) {
    afficherErreurChamp('erreur-salle', 'La salle est obligatoire.', $('salle-soutenance'));
    return;
  }

  try {
    await api('POST', '/api/soutenances', {
      demandeId: etat.demandeCourante,
      dateHeure: dateHeure.replace('T', ' '),
      salle,
      dureeMin,
    });
    $('dialogue-soutenance').close();
    afficherBandeau('Soutenance programmée.', 'succes');
    await Promise.all([chargerDemandes(), chargerSoutenances()]);
  } catch (erreur) {
    const details = erreur.details || {};
    if (details.dateHeure) afficherErreurChamp('erreur-date', details.dateHeure, $('date-soutenance'));
    else if (details.salle) afficherErreurChamp('erreur-salle', details.salle, $('salle-soutenance'));
    else if (details.dureeMin) afficherErreurChamp('erreur-duree', details.dureeMin, $('duree-soutenance'));
    else afficherBandeau(erreur.message, 'erreur');
  }
}

/* ------------------------------------------------------------------ *
 * Dialogue de détail
 * ------------------------------------------------------------------ */
async function ouvrirDetail(demandeId) {
  const reponse = await api('GET', `/api/demandes/${demandeId}`);
  const demande = reponse.data;
  const liste = $('detail-demande');
  liste.replaceChildren();
  const champs = [
    ['Statut', libelleStatut(demande.statut)],
    ['Étudiant', `${demande.etudiant_prenom} ${demande.etudiant_nom}`],
    ['Entreprise', demande.entreprise],
    ['Matricule', demande.matricule || '—'],
    ['Soumission', formaterDate(demande.date_soumission)],
    ['Décision', formaterDate(demande.date_decision)],
    ['Motif de refus', demande.motif_refus || '—'],
    ['Encadreur', demande.encadreur_id ? `${demande.encadreur_prenom} ${demande.encadreur_nom}` : '—'],
  ];
  for (const [libelle, valeur] of champs) {
    liste.append(creer('dt', { texte: libelle }));
    liste.append(creer('dd', { texte: String(valeur ?? '—') }));
  }
  $('dialogue-detail').showModal();
}

/* ------------------------------------------------------------------ *
 * Dialogue d'historique
 * ------------------------------------------------------------------ */
async function ouvrirHistorique(demandeId) {
  const reponse = await api('GET', `/api/demandes/${demandeId}/historique`);
  const liste = $('liste-historique');
  liste.replaceChildren();

  if (reponse.data.length === 0) {
    liste.append(creer('li', { texte: 'Aucun événement enregistré.' }));
  }

  for (const evenement of reponse.data) {
    const item = creer('li');
    const parcours = evenement.statut_avant
      ? `${evenement.statut_avant} vers ${evenement.statut_apres}`
      : `Création (${evenement.statut_apres})`;
    item.append(creer('strong', { texte: parcours }));
    const meta = creer('div', {
      classe: 'meta',
      texte: `${formaterDate(evenement.horodatage)} · ${evenement.auteur_prenom} ${evenement.auteur_nom} (${LIBELLES_ROLE[evenement.auteur_role] || evenement.auteur_role})`,
    });
    item.append(meta);
    if (evenement.motif) {
      item.append(creer('div', { texte: `Motif : ${evenement.motif}` }));
    }
    liste.append(item);
  }

  $('dialogue-historique').showModal();
}

/* ------------------------------------------------------------------ *
 * Création d'une demande (étudiant)
 * ------------------------------------------------------------------ */
async function creerDemande(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-demande');
  nettoyerErreurs(formulaire);
  masquerBandeau();

  const entrepriseId = Number($('entreprise').value);
  const soumettre = $('soumettre').checked;

  if (!entrepriseId) {
    afficherErreurChamp('erreur-entreprise', 'Choisissez une entreprise.', $('entreprise'));
    return;
  }

  try {
    await api('POST', '/api/demandes', { entrepriseId, soumettre });
    formulaire.reset();
    afficherBandeau(soumettre ? 'Demande déposée.' : 'Brouillon enregistré.', 'succes');
    await chargerDemandes();
  } catch (erreur) {
    if (erreur.details && erreur.details.entrepriseId) {
      afficherErreurChamp('erreur-entreprise', erreur.details.entrepriseId, $('entreprise'));
    } else {
      afficherBandeau(erreur.message, 'erreur');
    }
  }
}

/* ------------------------------------------------------------------ *
 * Filtres et événements
 * ------------------------------------------------------------------ */
function appliquerFiltres() {
  etat.filtres.q = $('filtre-q').value.trim();
  etat.filtres.statut = $('filtre-statut').value;
  chargerDemandes().catch((erreur) => afficherBandeau(erreur.message, 'erreur'));
}

function lierEvenements() {
  $('form-connexion').addEventListener('submit', connexion);
  $('btn-deconnexion').addEventListener('click', deconnexion);
  $('form-demande').addEventListener('submit', creerDemande);
  $('form-refus').addEventListener('submit', confirmerRefus);
  $('form-soutenance').addEventListener('submit', confirmerSoutenance);

  $('corps-demandes').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (!bouton) return;
    agir(bouton.dataset.action, Number(bouton.dataset.id));
  });

  document.querySelectorAll('[data-fermer]').forEach((bouton) => {
    bouton.addEventListener('click', () => $(bouton.dataset.fermer).close());
  });

  let minuteurFiltre = null;
  $('filtre-q').addEventListener('input', () => {
    window.clearTimeout(minuteurFiltre);
    minuteurFiltre = window.setTimeout(appliquerFiltres, 300);
  });
  $('filtre-statut').addEventListener('change', appliquerFiltres);
}

/* ------------------------------------------------------------------ *
 * Amorçage
 * ------------------------------------------------------------------ */
async function initialiser() {
  lierEvenements();
  try {
    const reponse = await fetch('/api/session', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const session = await reponse.json();
    etat.csrf = session.csrfToken;
    if (session.authenticated) {
      etat.user = session.user;
      await demarrerApplication();
    } else {
      afficherConnexion();
    }
  } catch {
    afficherConnexion();
    afficherBandeau('Serveur injoignable. Démarrez le serveur PHP puis rechargez la page.', 'erreur');
  }
}

document.addEventListener('DOMContentLoaded', initialiser);
