'use strict';

const etat = {
  user: null,
  csrf: null,
  vue: 'tableau',
  entreprises: [],
  encadreurs: [],
  comptes: [],
  salles: [],
  demandes: [],
  metaDemandes: { total: 0, page: 1, par_page: 10, derniere_page: 1 },
  filtres: { q: '', statut: '', entrepriseId: '', filiere: '', annee: '' },
  selection: new Set(),
  soutenances: [],
  planning: {},
  periode: { annee: new Date().getFullYear(), mois: new Date().getMonth() + 1 },
  notifications: [],
  utilisateurs: [],
  relances: [],
  audit: { data: [], total: 0, page: 1, par_page: 25 },
  demandeCourante: null,
  demandeDetail: null,
  pieceDemandeId: null,
  soutenanceCourante: null,
  modeRefus: 'refuser',
  filiereOptions: [],
};

const LIBELLES_ROLE = {
  ETUDIANT: 'Étudiant',
  SECRETARIAT: 'Secrétariat',
  RESPONSABLE: 'Responsable pédagogique',
  ENCADREUR: 'Encadreur',
  ADMIN: 'Administrateur',
};

const LIBELLES_STATUT = {
  BROUILLON: 'Brouillon',
  SOUMISE: 'Soumise',
  VALIDEE: 'Validée',
  REFUSEE: 'Refusée',
  ANNULEE: 'Annulée',
};

const LIBELLES_STATUT_ENTREPRISE = {
  PARTENAIRE: 'Partenaire',
  A_VALIDER: 'À valider',
  INTERDITE: 'Interdite',
};

const LIBELLES_TYPE_PIECE = {
  CV: 'CV',
  LETTRE_MOTIVATION: 'Lettre de motivation',
  CONVENTION: 'Convention',
  RAPPORT: 'Rapport',
  AUTRE: 'Autre',
};

const VUES_PAR_ROLE = {
  ETUDIANT: ['tableau', 'demandes', 'entreprises', 'soutenances', 'notifications'],
  ENCADREUR: ['tableau', 'demandes', 'soutenances', 'notifications'],
  SECRETARIAT: ['tableau', 'demandes', 'entreprises', 'soutenances', 'utilisateurs', 'notifications', 'audit', 'exports'],
  RESPONSABLE: ['tableau', 'demandes', 'entreprises', 'soutenances', 'notifications', 'audit', 'exports'],
  ADMIN: ['tableau', 'demandes', 'entreprises', 'soutenances', 'utilisateurs', 'notifications', 'audit', 'exports'],
};

class ErreurApi extends Error {
  constructor(message, status, details = {}) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

const $ = (id) => document.getElementById(id);

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

async function apiFormulaire(chemin, corps) {
  const entetes = { Accept: 'application/json' };
  if (etat.csrf) entetes['X-CSRF-Token'] = etat.csrf;
  let reponse;
  try {
    reponse = await fetch(chemin, {
      method: 'POST',
      headers: entetes,
      credentials: 'same-origin',
      body: corps,
    });
  } catch {
    throw new ErreurApi('Serveur injoignable.', 0);
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
    throw new ErreurApi(
      (donnees && donnees.message) || 'Erreur inattendue.',
      reponse.status,
      (donnees && donnees.details) || {}
    );
  }
  return donnees;
}

function creer(balise, { classe, texte, type } = {}) {
  const noeud = document.createElement(balise);
  if (classe) noeud.className = classe;
  if (texte !== undefined) noeud.textContent = texte;
  if (type) noeud.type = type;
  return noeud;
}

function creerOption(valeur, libelle) {
  const option = creer('option', { texte: libelle });
  option.value = String(valeur ?? '');
  return option;
}

function formaterDate(valeur) {
  if (!valeur) return '—';
  const date = new Date(String(valeur).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(valeur);
  return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

function estVrai(valeur) {
  return valeur === true || valeur === 1 || valeur === '1';
}

function formaterTaille(valeur) {
  const octets = Number(valeur || 0);
  if (octets < 1024) return `${octets} o`;
  if (octets < 1048576) return `${(octets / 1024).toFixed(1)} Ko`;
  return `${(octets / 1048576).toFixed(1)} Mo`;
}

function libelleStatut(statut) {
  return LIBELLES_STATUT[statut] || statut || '—';
}

function badgeStatut(statut) {
  return creer('span', { classe: `badge statut-${String(statut).toLowerCase()}`, texte: libelleStatut(statut) });
}

function badgeEntreprise(statut) {
  const classes = { PARTENAIRE: 'statut-validee', A_VALIDER: 'statut-soumise', INTERDITE: 'statut-refusee' };
  return creer('span', {
    classe: `badge ${classes[statut] || ''}`,
    texte: LIBELLES_STATUT_ENTREPRISE[statut] || statut || '—',
  });
}

function afficherBandeau(message, type = 'info') {
  const bandeau = $('bandeau');
  bandeau.textContent = message;
  bandeau.className = 'bandeau' + (type === 'erreur' ? ' erreur' : type === 'succes' ? ' succes' : '');
  bandeau.hidden = false;
  window.clearTimeout(afficherBandeau.minuteur);
  if (type === 'succes') {
    afficherBandeau.minuteur = window.setTimeout(() => { bandeau.hidden = true; }, 6000);
  }
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

function appliquerDetails(details, formulaire) {
  let premiere = null;
  for (const [cle, message] of Object.entries(details || {})) {
    const champ = formulaire.querySelector(`[name="${cle}"], #${cle}`);
    const erreur = formulaire.querySelector(`#erreur-${cle}`);
    if (erreur) erreur.textContent = message;
    if (champ) {
      champ.setAttribute('aria-invalid', 'true');
      premiere = premiere || champ;
    }
  }
  if (premiere) premiere.focus();
}

async function avecBouton(bouton, callback) {
  bouton.disabled = true;
  try {
    return await callback();
  } finally {
    bouton.disabled = false;
  }
}

async function tenter(callback) {
  try {
    await callback();
  } catch (erreur) {
    afficherBandeau(erreur.message, 'erreur');
  }
}

function estGestionnaire() {
  return ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'].includes(etat.user?.role);
}

function peutValider() {
  return ['RESPONSABLE', 'ADMIN'].includes(etat.user?.role);
}

function peutProgrammer() {
  return ['SECRETARIAT', 'ADMIN'].includes(etat.user?.role);
}

function boutonAction(libelle, action, id, classe = 'btn btn-petit', attributs = {}) {
  const bouton = creer('button', { classe, texte: libelle, type: 'button' });
  bouton.dataset.action = action;
  bouton.dataset.id = String(id);
  for (const [cle, valeur] of Object.entries(attributs)) {
    bouton.dataset[cle] = String(valeur);
  }
  return bouton;
}

async function rafraichirJeton() {
  try {
    const reponse = await fetch('/api/session', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const session = await reponse.json();
    etat.csrf = session.csrfToken;
    if (session.authenticated && !etat.user) etat.user = session.user;
  } catch {
    afficherBandeau('Serveur injoignable. Démarrez le serveur PHP puis rechargez la page.', 'erreur');
  }
}

function afficherConnexion() {
  etat.user = null;
  etat.vue = 'tableau';
  etat.demandes = [];
  etat.soutenances = [];
  etat.selection.clear();
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
  const email = $('email').value.trim();
  const motDePasse = $('mot-de-passe').value;

  if (!email) return afficherErreurChamp('erreur-email', 'L’adresse e-mail est obligatoire.', $('email'));
  if (!motDePasse) return afficherErreurChamp('erreur-mot-de-passe', 'Le mot de passe est obligatoire.', $('mot-de-passe'));

  await avecBouton(formulaire.querySelector('button[type="submit"]'), async () => {
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
    }
  });
}

async function deconnexion() {
  try {
    await api('DELETE', '/api/session');
  } catch {
    afficherBandeau('La session a été fermée localement.', 'info');
  }
  etat.csrf = null;
  afficherConnexion();
  await rafraichirJeton();
}

function configurerInterface() {
  $('vue-connexion').hidden = true;
  $('vue-application').hidden = false;
  $('zone-utilisateur').hidden = false;
  $('nom-utilisateur').textContent = `${etat.user.prenom} ${etat.user.nom}`;
  $('role-utilisateur').textContent = LIBELLES_ROLE[etat.user.role] || etat.user.role;

  const vuesAutorisees = VUES_PAR_ROLE[etat.user.role] || ['tableau'];
  document.querySelectorAll('#navigation button[data-vue]').forEach((bouton) => {
    bouton.hidden = !vuesAutorisees.includes(bouton.dataset.vue);
  });
  $('bloc-etudiant').hidden = etat.user.role !== 'ETUDIANT';
  $('bloc-proposition-entreprise').hidden = etat.user.role !== 'ETUDIANT';
  $('bloc-creation-entreprise').hidden = !estGestionnaire();
  $('filtres').hidden = etat.user.role === 'ETUDIANT' || etat.user.role === 'ENCADREUR';
  $('col-selection').hidden = etat.user.role !== 'RESPONSABLE';
  $('controle-lot').hidden = etat.user.role !== 'RESPONSABLE';
  $('btn-convoquer').hidden = !peutProgrammer();
}

async function chargerReferentiels() {
  const reponse = await api('GET', '/api/entreprises');
  etat.entreprises = reponse.data || [];
  const entreprises = $('entreprise');
  entreprises.replaceChildren(creerOption('', 'Choisir une entreprise…'));
  for (const entreprise of etat.entreprises) {
    entreprises.append(creerOption(entreprise.id, `${entreprise.raison_sociale} — ${entreprise.ville}`));
  }
  const filtreEntreprises = $('filtre-entreprise');
  filtreEntreprises.replaceChildren(creerOption('', 'Toutes'));
  for (const entreprise of etat.entreprises) {
    filtreEntreprises.append(creerOption(entreprise.id, entreprise.raison_sociale));
  }

  if (estGestionnaire()) {
    const encadreurs = await api('GET', '/api/encadreurs');
    etat.encadreurs = encadreurs.data || [];
    const comptes = await api('GET', '/api/comptes');
    etat.comptes = comptes.data || [];
  }

  const salles = await api('GET', '/api/salles');
  etat.salles = salles.data || [];
  const selectSalle = $('salle-soutenance');
  selectSalle.replaceChildren(creerOption('', 'Salle libre saisie ci-dessous'));
  for (const salle of etat.salles) {
    if (estVrai(salle.active)) selectSalle.append(creerOption(salle.id, `${salle.nom}${salle.batiment ? ' — ' + salle.batiment : ''}`));
  }

  const jury = $('jury-soutenance');
  jury.replaceChildren();
  for (const compte of etat.comptes.filter((c) => ['ENCADREUR', 'RESPONSABLE', 'ADMIN'].includes(c.role))) {
    const option = creerOption(compte.id, `${compte.prenom} ${compte.nom} (${LIBELLES_ROLE[compte.role]})`);
    jury.append(option);
  }

  const docDemande = $('doc-demande');
  docDemande.replaceChildren(creerOption('', 'Choisir une demande…'));
  for (const demande of etat.demandes) {
    docDemande.append(creerOption(demande.id, `${demande.etudiant_prenom} ${demande.etudiant_nom} — ${demande.entreprise}`));
  }
}

async function demarrerApplication() {
  configurerInterface();
  try {
    await chargerReferentiels();
  } catch (erreur) {
    afficherBandeau(`Référentiels partiels : ${erreur.message}`, 'erreur');
  }
  await chargerNotifications();
  await changerVue('tableau');
}

const CHARGEURS = {
  tableau: chargerTableauBord,
  demandes: chargerDemandes,
  entreprises: chargerEntreprises,
  soutenances: chargerSoutenances,
  utilisateurs: chargerUtilisateurs,
  notifications: chargerNotifications,
  audit: chargerAudit,
  exports: chargerExports,
};

async function changerVue(vue) {
  const vuesAutorisees = VUES_PAR_ROLE[etat.user?.role] || ['tableau'];
  if (!vuesAutorisees.includes(vue)) vue = 'tableau';
  etat.vue = vue;
  document.querySelectorAll('section[data-section-vue]').forEach((section) => {
    section.hidden = section.dataset.sectionVue !== vue;
  });
  document.querySelectorAll('#navigation button[data-vue]').forEach((bouton) => {
    if (bouton.dataset.vue === vue) bouton.setAttribute('aria-current', 'page');
    else bouton.removeAttribute('aria-current');
  });
  await tenter(CHARGEURS[vue]);
}

async function chargerTableauBord() {
  const reponse = await api('GET', '/api/tableau-de-bord');
  const donnees = reponse.data;
  const libelles = {
    total_demandes: 'Demandes',
    notifications: 'Notifications',
    soutenances_a_venir: 'Soutenances à venir',
    entreprises_a_valider: 'Entreprises à valider',
    entreprises_partenaires: 'Partenaires',
    entreprises_interdites: 'Entreprises interdites',
    soutenances_non_convoquees: 'Convocations à envoyer',
    validees_sans_encadreur: 'Sans encadreur',
    etudiants_sans_demande: 'Étudiants à relancer',
    salles_actives: 'Salles actives',
    propositions_encadreur: 'Propositions à confirmer',
    rapports_a_valider: 'Rapports à valider',
    notes_saisies: 'Notes saisies',
    etudiants: 'Étudiants encadrés',
  };
  $('resume-tableau').textContent = `Vue ${LIBELLES_ROLE[donnees.role] || donnees.role} : ${donnees.total_demandes} demande(s) dans votre périmètre.`;
  const stats = $('statistiques');
  stats.replaceChildren();

  const paires = [
    ['total_demandes', donnees.total_demandes],
    ['demandes.SOUMISE', donnees.demandes?.SOUMISE],
    ['demandes.VALIDEE', donnees.demandes?.VALIDEE],
    ['notifications', donnees.notifications],
    ['soutenances_a_venir', donnees.soutenances_a_venir],
  ];
  for (const [cle, valeur] of Object.entries(donnees)) {
    if (cle === 'demandes') {
      continue;
    }
    if (typeof valeur === 'number' && !paires.some(([p]) => p === cle)) {
      paires.push([cle, valeur]);
    }
  }

  for (const [cle, valeur] of paires.slice(0, 6)) {
    const nom = cle.startsWith('demandes.') ? `${libelleStatut(cle.split('.')[1])}s` : libelles[cle] || cle;
    const carte = creer('div', { classe: 'statistique' });
    carte.append(creer('span', { classe: 'statistique-valeur', texte: String(valeur ?? 0) }));
    carte.append(creer('span', { classe: 'statistique-libelle', texte: nom }));
    stats.append(carte);
  }

  const alertes = $('alertes-tableau');
  alertes.replaceChildren();
  const liens = [
    ['notifications', 'Consulter les notifications', 'notifications'],
    ['validees_sans_encadreur', 'Affecter un encadreur', 'demandes'],
    ['entreprises_a_valider', 'Valider les entreprises proposées', 'entreprises'],
    ['propositions_encadreur', 'Confirmer les encadreurs proposés', 'demandes'],
    ['rapports_a_valider', 'Voir les rapports à valider', 'demandes'],
    ['soutenances_non_convoquees', 'Envoyer les convocations', 'soutenances'],
  ];
  for (const [cle, libelle, vue] of liens) {
    if (!donnees[cle]) continue;
    const alerte = creer('div', { classe: 'alerte' });
    alerte.append(creer('span', { texte: `${libelle} : ${donnees[cle]}` }));
    const bouton = creer('button', { classe: 'btn btn-petit', texte: 'Ouvrir', type: 'button' });
    bouton.dataset.aller = vue;
    alerte.append(bouton);
    alertes.append(alerte);
  }
  if (alertes.childElementCount === 0) {
    alertes.append(creer('p', { classe: 'aide', texte: 'Aucune action urgente.' }));
  }
}

function queryDemandes() {
  const parametres = new URLSearchParams();
  for (const [cle, valeur] of Object.entries(etat.filtres)) {
    if (valeur !== '') parametres.set(cle, String(valeur));
  }
  parametres.set('page', String(etat.metaDemandes.page));
  parametres.set('parPage', '10');
  return parametres.toString();
}

async function chargerDemandes() {
  const reponse = await api('GET', `/api/demandes?${queryDemandes()}`);
  etat.demandes = reponse.data || [];
  etat.metaDemandes = reponse.meta || etat.metaDemandes;
  const filieres = new Set(etat.filiereOptions);
  for (const demande of etat.demandes) {
    if (demande.filiere) filieres.add(demande.filiere);
  }
  etat.filiereOptions = [...filieres].sort();
  const selectFiliere = $('filtre-filiere');
  const valeurFiliere = selectFiliere.value;
  selectFiliere.replaceChildren(creerOption('', 'Toutes'));
  for (const filiere of etat.filiereOptions) selectFiliere.append(creerOption(filiere, filiere));
  selectFiliere.value = valeurFiliere;
  rendreDemandes();
  rendrePagination('demandes');
}

function rendreDemandes() {
  const corps = $('corps-demandes');
  corps.replaceChildren();
  const meta = etat.metaDemandes;
  $('resume-demandes').textContent = `${meta.total} demande(s) — page ${meta.page}/${Math.max(1, meta.derniere_page)}.`;
  $('etat-vide').hidden = etat.demandes.length > 0;

  for (const demande of etat.demandes) {
    const ligne = creer('tr');

    if (etat.user.role === 'RESPONSABLE') {
      const cellule = creer('td');
      const caseA = creer('input', { type: 'checkbox' });
      caseA.checked = etat.selection.has(demande.id);
      caseA.dataset.selection = String(demande.id);
      caseA.setAttribute('aria-label', `Sélectionner la demande ${demande.id}`);
      cellule.append(caseA);
      cellule.dataset.label = 'Sélection';
      ligne.append(cellule);
    }

    const demandeur = creer('td');
    demandeur.append(creer('strong', { texte: `${demande.etudiant_prenom} ${demande.etudiant_nom}` }));
    demandeur.append(creer('div', { classe: 'col-secondaire', texte: `${demande.matricule || '—'} · ${demande.filiere || '—'}` }));
    demandeur.dataset.label = 'Demandeur';

    const entreprise = creer('td');
    entreprise.append(creer('strong', { texte: demande.entreprise }));
    entreprise.append(creer('div', { classe: 'col-secondaire', texte: demande.entreprise_ville || '' }));
    entreprise.dataset.label = 'Entreprise';

    const statut = creer('td');
    statut.append(badgeStatut(demande.statut));
    if (demande.rapport_valide) statut.append(creer('div', { classe: 'col-secondaire', texte: 'Rapport validé' }));
    if (demande.note !== null && demande.note !== undefined) {
      statut.append(creer('div', { classe: 'col-secondaire', texte: `Note : ${String(demande.note).replace('.', ',')}/20` }));
    }
    statut.dataset.label = 'Statut';

    const encadreur = creer('td');
    if (demande.encadreur_id) {
      encadreur.textContent = `${demande.encadreur_prenom || ''} ${demande.encadreur_nom || ''}`.trim();
    } else if (demande.encadreur_propose_id) {
      encadreur.textContent = `Proposition : ${demande.propose_prenom || ''} ${demande.propose_nom || ''}`.trim();
    } else {
      encadreur.textContent = '—';
    }
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

  $('resume-lot').textContent = etat.selection.size
    ? `${etat.selection.size} demande(s) sélectionnée(s).`
    : 'Cochez des demandes pour appliquer une action.';
}

function construireActions(conteneur, demande) {
  const role = etat.user.role;
  const aSoutenance = etat.soutenances.some((s) => s.demande_id === demande.id);

  if (role === 'ETUDIANT') {
    if (['BROUILLON', 'SOUMISE'].includes(demande.statut)) {
      conteneur.append(boutonAction('Modifier', 'modifier', demande.id));
    }
    if (demande.statut === 'BROUILLON') {
      conteneur.append(boutonAction('Soumettre', 'soumettre', demande.id, 'btn btn-petit btn-principal'));
    }
    if (demande.statut === 'REFUSEE') {
      conteneur.append(boutonAction('Corriger', 'corriger', demande.id, 'btn btn-petit btn-principal'));
    }
    if (['BROUILLON', 'SOUMISE'].includes(demande.statut)) {
      conteneur.append(boutonAction('Annuler', 'annuler', demande.id, 'btn btn-petit btn-danger'));
    }
  }

  if (peutValider() && demande.statut === 'SOUMISE') {
    conteneur.append(boutonAction('Valider', 'valider', demande.id, 'btn btn-petit btn-principal'));
    conteneur.append(boutonAction('Refuser', 'refuser', demande.id, 'btn btn-petit btn-danger'));
  }

  if (estGestionnaire() && demande.statut === 'VALIDEE' && !demande.encadreur_id) {
    const select = creer('select', { classe: 'select-petit' });
    select.setAttribute('aria-label', `Encadreur de la demande ${demande.id}`);
    select.append(creerOption('', 'Encadreur…'));
    for (const encadreur of etat.encadreurs) {
      const option = creerOption(encadreur.id, `${encadreur.prenom} ${encadreur.nom} (${encadreur.charge}/${encadreur.charge_max})`);
      select.append(option);
    }
    select.dataset.selectEncadreur = String(demande.id);
    conteneur.append(select);
    const libelle = role === 'SECRETARIAT' ? 'Proposer' : 'Affecter';
    const action = role === 'SECRETARIAT' ? 'proposer' : 'affecter';
    conteneur.append(boutonAction(libelle, action, demande.id));
  }

  if (peutValider() && demande.encadreur_propose_id && !demande.encadreur_id) {
    conteneur.append(boutonAction('Confirmer l’encadreur', 'confirmer-encadreur', demande.id, 'btn btn-petit btn-principal'));
  }

  if (peutProgrammer() && demande.statut === 'VALIDEE' && demande.encadreur_id && !aSoutenance) {
    conteneur.append(boutonAction('Programmer', 'programmer', demande.id, 'btn btn-petit btn-principal'));
  }

  if (['ENCADREUR', 'RESPONSABLE', 'ADMIN'].includes(role) && demande.statut === 'VALIDEE') {
    if (!demande.rapport_valide) {
      conteneur.append(boutonAction('Valider le rapport', 'rapport-oui', demande.id));
      conteneur.append(boutonAction('Rapport à corriger', 'rapport-non', demande.id, 'btn btn-petit btn-danger'));
    }
    if (aSoutenance) conteneur.append(boutonAction('Noter', 'noter', demande.id));
  }

  conteneur.append(boutonAction('Détail', 'detail', demande.id, 'btn btn-petit btn-discret'));
  conteneur.append(boutonAction('Pièces', 'pieces', demande.id, 'btn btn-petit btn-discret'));
  conteneur.append(boutonAction('Historique', 'historique', demande.id, 'btn btn-petit btn-discret'));
}

function rendrePagination(prefixe) {
  const meta = etat.metaDemandes;
  const conteneur = $('pagination-demandes');
  conteneur.replaceChildren();
  if (meta.derniere_page <= 1) return;

  const precedent = creer('button', { classe: 'btn btn-petit', texte: 'Précédent', type: 'button' });
  precedent.disabled = meta.page <= 1;
  precedent.dataset.page = String(meta.page - 1);
  const suivant = creer('button', { classe: 'btn btn-petit', texte: 'Suivant', type: 'button' });
  suivant.disabled = meta.page >= meta.derniere_page;
  suivant.dataset.page = String(meta.page + 1);
  conteneur.append(precedent, creer('span', { classe: 'aide', texte: `Page ${meta.page} sur ${meta.derniere_page}` }), suivant);
}

async function chargerEntreprises() {
  const statut = $('filtre-statut-entreprise').value;
  const reponse = await api('GET', `/api/entreprises${statut ? `?statut=${encodeURIComponent(statut)}` : ''}`);
  etat.entreprises = reponse.data || [];
  const corps = $('corps-entreprises');
  corps.replaceChildren();
  $('entreprises-vide').hidden = etat.entreprises.length > 0;

  for (const entreprise of etat.entreprises) {
    const ligne = creer('tr');
    const nom = creer('td');
    nom.append(creer('strong', { texte: entreprise.raison_sociale }));
    nom.append(creer('div', { classe: 'col-secondaire', texte: entreprise.secteur || '' }));
    nom.dataset.label = 'Entreprise';
    const ville = creer('td', { texte: entreprise.ville });
    ville.dataset.label = 'Ville';
    const contact = creer('td');
    contact.append(creer('div', { texte: entreprise.contact_nom || entreprise.email_contact || '—' }));
    contact.append(creer('div', { classe: 'col-secondaire', texte: entreprise.telephone || '' }));
    contact.dataset.label = 'Contact';
    const statutCellule = creer('td');
    statutCellule.append(badgeEntreprise(entreprise.statut));
    if (entreprise.motif_statut) statutCellule.append(creer('div', { classe: 'col-secondaire', texte: entreprise.motif_statut }));
    statutCellule.dataset.label = 'Statut';
    const actions = creer('td');
    actions.dataset.label = 'Actions';
    if (peutValider() && entreprise.statut !== 'PARTENAIRE') {
      actions.append(boutonAction('Marquer partenaire', 'entreprise-partenaires', entreprise.id, 'btn btn-petit btn-principal', { action2: 'PARTENAIRE' }));
    }
    if (peutValider() && entreprise.statut !== 'INTERDITE') {
      actions.append(boutonAction('Interdire', 'entreprise-interdire', entreprise.id, 'btn btn-petit btn-danger', { action2: 'INTERDITE' }));
    }
    ligne.append(nom, ville, contact, statutCellule, actions);
    corps.append(ligne);
  }
}

async function chargerSoutenances() {
  const parametres = new URLSearchParams({ annee: String(etat.periode.annee), mois: String(etat.periode.mois) });
  const [liste, planning] = await Promise.all([
    api('GET', `/api/soutenances?${parametres}`),
    api('GET', `/api/soutenances/planning?${parametres}`),
  ]);
  etat.soutenances = liste.data || [];
  etat.planning = planning.data || {};
  const corps = $('corps-planning');
  corps.replaceChildren();
  $('planning-vide').hidden = etat.soutenances.length > 0;

  for (const soutenance of etat.soutenances) {
    const ligne = creer('tr');
    const cellules = [
      ['Date', formaterDate(soutenance.date_heure)],
      ['Salle', soutenance.salle_nom || soutenance.salle],
      ['Étudiant', `${soutenance.etudiant_prenom} ${soutenance.etudiant_nom}`],
      ['Entreprise', soutenance.entreprise],
      ['Encadreur', soutenance.encadreur_nom ? `${soutenance.encadreur_prenom} ${soutenance.encadreur_nom}` : '—'],
      ['Durée', `${soutenance.duree_min} min`],
    ];
    for (const [libelle, valeur] of cellules) {
      const cellule = creer('td', { texte: String(valeur) });
      cellule.dataset.label = libelle;
      ligne.append(cellule);
    }
    const actions = creer('td');
    actions.dataset.label = 'Actions';
    actions.append(boutonAction('Détail', 'soutenance-detail', soutenance.id, 'btn btn-petit btn-discret'));
    ligne.append(actions);
    corps.append(ligne);
  }
  await chargerDemandes();
}

async function chargerNotifications() {
  const reponse = await api('GET', '/api/notifications');
  etat.notifications = reponse.data || [];
  const nonLues = etat.notifications.filter((n) => !n.lue).length;
  const compteur = $('compteur-notifications');
  compteur.textContent = String(nonLues);
  compteur.hidden = nonLues === 0;
  const liste = $('liste-notifications');
  liste.replaceChildren();
  $('notifications-vide').hidden = etat.notifications.length > 0;

  for (const notification of etat.notifications) {
    const item = creer('li', { classe: notification.lue ? 'notification' : 'notification notification-non-lue' });
    const titre = creer('strong', { texte: notification.titre });
    item.append(titre);
    item.append(creer('div', { texte: notification.message }));
    item.append(creer('div', { classe: 'meta', texte: formaterDate(notification.cree_le) }));
    if (!notification.lue) {
      item.append(boutonAction('Marquer comme lu', 'notification-lire', notification.id, 'btn btn-petit'));
    }
    liste.append(item);
  }
}

async function chargerUtilisateurs() {
  const [comptes, relances] = await Promise.all([
    api('GET', '/api/utilisateurs'),
    api('GET', '/api/relances'),
  ]);
  etat.utilisateurs = comptes.data || [];
  etat.relances = relances.data || [];
  const corps = $('corps-utilisateurs');
  corps.replaceChildren();
  $('utilisateurs-vide').hidden = etat.utilisateurs.length > 0;

  for (const compte of etat.utilisateurs) {
    const ligne = creer('tr');
    const nom = creer('td');
    nom.append(creer('strong', { texte: `${compte.prenom} ${compte.nom}` }));
    nom.dataset.label = 'Nom';
    const email = creer('td', { texte: compte.email });
    email.dataset.label = 'E-mail';
    const role = creer('td', { texte: LIBELLES_ROLE[compte.role] || compte.role });
    role.dataset.label = 'Rôle';
    const actif = creer('td');
    const actifCompte = estVrai(compte.actif);
    actif.append(creer('span', { classe: `badge ${actifCompte ? 'statut-validee' : 'statut-refusee'}`, texte: actifCompte ? 'Actif' : 'Inactif' }));
    actif.dataset.label = 'État';
    const action = creer('td');
    action.append(boutonAction(
      actifCompte ? 'Désactiver' : 'Activer',
      'compte-actif',
      compte.id,
      'btn btn-petit',
      { etat: actifCompte ? '0' : '1' }
    ));
    action.dataset.label = 'Action';
    ligne.append(nom, email, role, actif, action);
    corps.append(ligne);
  }

  const corpsRelances = $('corps-relances');
  corpsRelances.replaceChildren();
  $('relances-vide').hidden = etat.relances.length > 0;
  for (const etudiant of etat.relances) {
    const ligne = creer('tr');
    const nom = creer('td', { texte: `${etudiant.prenom} ${etudiant.nom} (${etudiant.matricule})` });
    nom.dataset.label = 'Étudiant';
    const email = creer('td', { texte: etudiant.email });
    email.dataset.label = 'E-mail';
    const promotion = creer('td', { texte: `${etudiant.filiere || '—'} ${etudiant.promotion || ''}`.trim() });
    promotion.dataset.label = 'Promotion';
    ligne.append(nom, email, promotion);
    corpsRelances.append(ligne);
  }
}

async function chargerAudit() {
  const parametres = new URLSearchParams();
  const action = $('audit-action').value.trim();
  const debut = $('audit-debut').value;
  const fin = $('audit-fin').value;
  if (action) parametres.set('action', action);
  if (debut) parametres.set('dateDebut', debut);
  if (fin) parametres.set('dateFin', fin);
  const reponse = await api('GET', `/api/audit?${parametres}`);
  etat.audit = reponse;
  const corps = $('corps-audit');
  corps.replaceChildren();
  $('audit-vide').hidden = etat.audit.data.length > 0;

  for (const entree of etat.audit.data) {
    const ligne = creer('tr');
    const cellules = [
      ['Date', formaterDate(entree.cree_le)],
      ['Action', entree.action],
      ['Auteur', `${entree.auteur_prenom || ''} ${entree.auteur_nom || ''}`.trim() || 'Système'],
      ['Entité', `${entree.entite || ''}${entree.entite_id ? ' #' + entree.entite_id : ''}`],
      ['Détails', entree.details || '—'],
    ];
    for (const [libelle, valeur] of cellules) {
      const cellule = creer('td', { texte: String(valeur || '—') });
      cellule.dataset.label = libelle;
      ligne.append(cellule);
    }
    corps.append(ligne);
  }
}

async function chargerExports() {
  const reponse = await api('GET', '/api/demandes?parPage=100');
  const select = $('doc-demande');
  const valeur = select.value;
  select.replaceChildren(creerOption('', 'Choisir une demande…'));
  for (const demande of reponse.data || []) {
    select.append(creerOption(demande.id, `${demande.etudiant_prenom} ${demande.etudiant_nom} — ${demande.entreprise}`));
  }
  select.value = valeur;
}

async function agirDemande(action, id, element) {
  const demande = etat.demandes.find((d) => d.id === id);
  await tenter(async () => {
    if (action === 'soumettre') {
      await api('PATCH', `/api/demandes/${id}/statut`, { statut: 'SOUMISE' });
      afficherBandeau('Demande soumise au responsable.', 'succes');
    }
    if (action === 'corriger') {
      await api('PATCH', `/api/demandes/${id}/statut`, { statut: 'BROUILLON' });
      await chargerDemandes();
      ouvrirModification(id);
      return;
    }
    if (action === 'valider') {
      await api('PATCH', `/api/demandes/${id}/statut`, { statut: 'VALIDEE' });
      afficherBandeau('Demande validée.', 'succes');
    }
    if (action === 'refuser') {
      ouvrirRefus(id, 'refuser');
      return;
    }
    if (action === 'annuler') {
      ouvrirRefus(id, 'annuler');
      return;
    }
    if (action === 'modifier') {
      ouvrirModification(id);
      return;
    }
    if (['affecter', 'proposer'].includes(action)) {
      const select = document.querySelector(`select[data-select-encadreur="${id}"]`);
      const encadreurId = Number(select?.value || 0);
      if (!encadreurId) {
        afficherBandeau('Choisissez un encadreur avant de continuer.', 'erreur');
        return;
      }
      await api('PATCH', `/api/demandes/${id}/encadreur`, { encadreurId });
      afficherBandeau(action === 'proposer' ? 'Encadreur proposé au responsable.' : 'Encadreur affecté.', 'succes');
    }
    if (action === 'confirmer-encadreur') {
      await api('PATCH', `/api/demandes/${id}/encadreur/confirmer`, {});
      afficherBandeau('Encadrement confirmé.', 'succes');
    }
    if (action === 'programmer') {
      ouvrirSoutenance(id);
      return;
    }
    if (['rapport-oui', 'rapport-non'].includes(action)) {
      const valide = action === 'rapport-oui';
      await api('PATCH', `/api/demandes/${id}/rapport`, { valide, commentaire: valide ? 'Rapport validé.' : 'Rapport à corriger.' });
      afficherBandeau(valide ? 'Rapport validé.' : 'Demande de correction envoyée.', 'succes');
    }
    if (action === 'noter') {
      ouvrirNote(id);
      return;
    }
    if (action === 'detail') {
      await ouvrirDetail(id);
      return;
    }
    if (action === 'pieces') {
      await ouvrirPieces(id);
      return;
    }
    if (action === 'historique') {
      await ouvrirHistorique(id);
      return;
    }
    if (action === 'entreprise-partenaires' || action === 'entreprise-interdire') {
      await api('PATCH', `/api/entreprises/${id}/statut`, { statut: element.dataset.action2 });
      afficherBandeau('Statut de l’entreprise mis à jour.', 'succes');
      await chargerEntreprises();
      return;
    }
    if (action === 'soutenance-detail') {
      await ouvrirDetailSoutenance(id);
      return;
    }
    if (action === 'notification-lire') {
      await api('PATCH', `/api/notifications/${id}`, {});
      afficherBandeau('Notification marquée comme lue.', 'succes');
    }
    if (action === 'compte-actif') {
      await api('PATCH', `/api/utilisateurs/${id}/actif`, { actif: element.dataset.etat === '1' });
      afficherBandeau('État du compte mis à jour.', 'succes');
    }
    if (action === 'piece-valider' || action === 'piece-rejeter') {
      await api('PATCH', `/api/pieces/${id}/statut`, {
        statut: action === 'piece-valider' ? 'VALIDE' : 'REJETE',
        commentaire: '',
      });
      afficherBandeau('Statut de la pièce mis à jour.', 'succes');
      await ouvrirPieces(etat.pieceDemandeId);
      return;
    }
    if (action === 'piece-supprimer') {
      await api('DELETE', `/api/pieces/${id}`);
      afficherBandeau('Pièce supprimée.', 'succes');
      await ouvrirPieces(etat.pieceDemandeId);
      return;
    }

    await Promise.all([chargerDemandes(), chargerSoutenances(), chargerNotifications()]);
  });
}

function remplirFormulaireDemande(demande) {
  $('demande-id').value = String(demande.id);
  $('entreprise').value = String(demande.entreprise_id || '');
  $('sujet').value = demande.sujet || '';
  $('missions').value = demande.missions || '';
  $('date-debut').value = demande.date_debut || '';
  $('date-fin').value = demande.date_fin || '';
  $('tuteur-entreprise').value = demande.tuteur_entreprise || '';
  $('tuteur-email').value = demande.tuteur_email || '';
  $('soumettre').checked = demande.statut === 'BROUILLON';
  $('btn-enregistrer-demande').textContent = 'Enregistrer les modifications';
  $('btn-annuler-edit-demande').hidden = false;
  $('titre-depot').textContent = 'Modifier la demande';
  $('bloc-etudiant').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function reinitialiserFormulaireDemande() {
  $('form-demande').reset();
  $('demande-id').value = '';
  $('btn-enregistrer-demande').textContent = 'Envoyer la demande';
  $('btn-annuler-edit-demande').hidden = true;
  $('titre-depot').textContent = 'Déposer une demande';
  nettoyerErreurs($('form-demande'));
}

function ouvrirModification(id) {
  const demande = etat.demandes.find((d) => d.id === id);
  if (demande) remplirFormulaireDemande(demande);
}

async function creerOuModifierDemande(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-demande');
  nettoyerErreurs(formulaire);
  const id = Number($('demande-id').value || 0);
  const payload = {
    entrepriseId: Number($('entreprise').value),
    sujet: $('sujet').value.trim(),
    missions: $('missions').value.trim(),
    dateDebut: $('date-debut').value,
    dateFin: $('date-fin').value,
    tuteurEntreprise: $('tuteur-entreprise').value.trim(),
    tuteurEmail: $('tuteur-email').value.trim(),
    soumettre: $('soumettre').checked,
  };

  if (!payload.entrepriseId) {
    afficherErreurChamp('erreur-entreprise', 'Choisissez une entreprise.', $('entreprise'));
    return;
  }

  await avecBouton($('btn-enregistrer-demande'), async () => {
    try {
      if (id) await api('PATCH', `/api/demandes/${id}`, payload);
      else await api('POST', '/api/demandes', payload);
      reinitialiserFormulaireDemande();
      afficherBandeau(id ? 'Demande mise à jour.' : payload.soumettre ? 'Demande déposée.' : 'Brouillon enregistré.', 'succes');
      await Promise.all([chargerDemandes(), chargerSoutenances()]);
    } catch (erreur) {
      appliquerDetails(erreur.details, formulaire);
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

function ouvrirRefus(id, mode) {
  etat.demandeCourante = id;
  etat.modeRefus = mode;
  const formulaire = $('form-refus');
  nettoyerErreurs(formulaire);
  $('motif-refus').value = '';
  $('titre-refus').textContent = mode === 'annuler' ? 'Annuler la demande' : 'Refuser la demande';
  formulaire.querySelector('button[type="submit"]').textContent = mode === 'annuler' ? 'Confirmer l’annulation' : 'Confirmer le refus';
  $('dialogue-refus').showModal();
  $('motif-refus').focus();
}

async function confirmerRefus(evenement) {
  evenement.preventDefault();
  const motif = $('motif-refus').value.trim();
  nettoyerErreurs($('form-refus'));
  if (!motif) {
    afficherErreurChamp('erreur-motif', 'Le motif est obligatoire.', $('motif-refus'));
    return;
  }
  await avecBouton($('form-refus').querySelector('button[type="submit"]'), async () => {
    try {
      const id = etat.demandeCourante;
      if (etat.modeRefus === 'annuler') {
        await api('PATCH', `/api/demandes/${id}/annuler`, { raison: motif });
      } else {
        await api('PATCH', `/api/demandes/${id}/statut`, { statut: 'REFUSEE', motif });
      }
      $('dialogue-refus').close();
      afficherBandeau(etat.modeRefus === 'annuler' ? 'Demande annulée.' : 'Demande refusée avec motif.', 'succes');
      await chargerDemandes();
    } catch (erreur) {
      afficherErreurChamp('erreur-motif', erreur.details.motif || erreur.message, $('motif-refus'));
    }
  });
}

function ouvrirSoutenance(id) {
  etat.demandeCourante = id;
  const demain = new Date();
  demain.setDate(demain.getDate() + 1);
  demain.setHours(9, 0, 0, 0);
  const decalage = demain.getTimezoneOffset() * 60000;
  $('date-soutenance').value = new Date(demain.getTime() - decalage).toISOString().slice(0, 16);
  $('salle-soutenance').value = '';
  $('salle-libre').value = '';
  $('duree-soutenance').value = '30';
  nettoyerErreurs($('form-soutenance'));
  $('dialogue-soutenance').showModal();
  $('date-soutenance').focus();
}

async function confirmerSoutenance(evenement) {
  evenement.preventDefault();
  nettoyerErreurs($('form-soutenance'));
  const dateHeure = $('date-soutenance').value;
  const salleId = Number($('salle-soutenance').value || 0);
  const salle = $('salle-libre').value.trim();
  const dureeMin = Number($('duree-soutenance').value);
  const jury = [...$('jury-soutenance').selectedOptions].map((option) => ({
    utilisateurId: Number(option.value),
    role: 'MEMBRE',
  }));

  if (!dateHeure) return afficherErreurChamp('erreur-date', 'La date est obligatoire.', $('date-soutenance'));
  if (!salleId && !salle) return afficherErreurChamp('erreur-salle', 'Choisissez ou saisissez une salle.', $('salle-libre'));

  await avecBouton($('form-soutenance').querySelector('button[type="submit"]'), async () => {
    try {
      await api('POST', '/api/soutenances', {
        demandeId: etat.demandeCourante,
        dateHeure: dateHeure.replace('T', ' '),
        salleId: salleId || null,
        salle,
        dureeMin,
        jury,
      });
      $('dialogue-soutenance').close();
      afficherBandeau('Soutenance programmée.', 'succes');
      await Promise.all([chargerSoutenances(), chargerDemandes()]);
    } catch (erreur) {
      const details = erreur.details || {};
      if (details.dateHeure) afficherErreurChamp('erreur-date', details.dateHeure, $('date-soutenance'));
      else if (details.salle || details.salleId) afficherErreurChamp('erreur-salle', (details.salle || details.salleId).join?.() || details.salle || details.salleId, $('salle-libre'));
      else afficherBandeau(erreur.message, 'erreur');
    }
  });
}

function ajouterDefinition(liste, libelle, valeur) {
  liste.append(creer('dt', { texte: libelle }));
  liste.append(creer('dd', { texte: String(valeur ?? '—') }));
}

async function ouvrirDetail(id) {
  const reponse = await api('GET', `/api/demandes/${id}`);
  const demande = reponse.data;
  etat.demandeDetail = demande;
  const liste = $('detail-demande');
  liste.replaceChildren();
  ajouterDefinition(liste, 'Statut', libelleStatut(demande.statut));
  ajouterDefinition(liste, 'Étudiant', `${demande.etudiant_prenom} ${demande.etudiant_nom} (${demande.matricule || '—'})`);
  ajouterDefinition(liste, 'Entreprise', demande.entreprise);
  ajouterDefinition(liste, 'Sujet', demande.sujet || '—');
  ajouterDefinition(liste, 'Missions', demande.missions || '—');
  ajouterDefinition(liste, 'Dates', `${demande.date_debut || '—'} au ${demande.date_fin || '—'}`);
  ajouterDefinition(liste, 'Tuteur', `${demande.tuteur_entreprise || '—'} ${demande.tuteur_email ? '(' + demande.tuteur_email + ')' : ''}`);
  ajouterDefinition(liste, 'Encadreur', demande.encadreur_id ? `${demande.encadreur_prenom} ${demande.encadreur_nom}` : '—');
  ajouterDefinition(liste, 'Rapport', demande.rapport_valide ? 'Validé' : 'En attente');
  ajouterDefinition(liste, 'Note', demande.note !== null ? `${String(demande.note).replace('.', ',')}/20 ${demande.mention || ''}` : '—');

  const pieces = $('detail-pieces');
  pieces.replaceChildren();
  const listePieces = demande.pieces || [];
  if (listePieces.length === 0) pieces.append(creer('li', { texte: 'Aucune pièce jointe.' }));
  for (const piece of listePieces) {
    pieces.append(creer('li', { texte: `${LIBELLES_TYPE_PIECE[piece.type] || piece.type} — ${piece.nom_original} (${formaterTaille(piece.taille)})` }));
  }

  const commentaires = $('detail-commentaires');
  commentaires.replaceChildren();
  const listeCommentaires = demande.commentaires || [];
  if (listeCommentaires.length === 0) commentaires.append(creer('li', { texte: 'Aucun commentaire.' }));
  for (const commentaire of listeCommentaires) {
    commentaires.append(creer('li', { texte: `${commentaire.auteur_prenom || ''} ${commentaire.auteur_nom || ''} : ${commentaire.contenu}` }));
  }

  $('btn-detail-pieces').dataset.id = String(id);
  $('btn-detail-commenter').dataset.id = String(id);
  $('btn-detail-historique').dataset.id = String(id);
  $('dialogue-detail').showModal();
}

async function ouvrirHistorique(id) {
  const reponse = await api('GET', `/api/demandes/${id}/historique`);
  const liste = $('liste-historique');
  liste.replaceChildren();
  if (reponse.data.length === 0) liste.append(creer('li', { texte: 'Aucun événement enregistré.' }));
  for (const evenement of reponse.data) {
    const item = creer('li');
    const parcours = evenement.statut_avant
      ? `${libelleStatut(evenement.statut_avant)} vers ${libelleStatut(evenement.statut_apres)}`
      : `Création (${libelleStatut(evenement.statut_apres)})`;
    item.append(creer('strong', { texte: parcours }));
    item.append(creer('div', {
      classe: 'meta',
      texte: `${formaterDate(evenement.horodatage)} · ${evenement.auteur_prenom} ${evenement.auteur_nom} (${LIBELLES_ROLE[evenement.auteur_role] || evenement.auteur_role})`,
    }));
    if (evenement.motif) item.append(creer('div', { texte: `Motif : ${evenement.motif}` }));
    liste.append(item);
  }
  $('dialogue-historique').showModal();
}

async function ouvrirPieces(id) {
  etat.pieceDemandeId = id;
  const reponse = await api('GET', `/api/demandes/${id}/pieces`);
  const liste = $('liste-pieces');
  liste.replaceChildren();
  if (reponse.data.length === 0) liste.append(creer('li', { texte: 'Aucune pièce déposée.' }));
  for (const piece of reponse.data) {
    const item = creer('li', { classe: 'piece-ligne' });
    const titre = creer('div');
    titre.append(creer('strong', { texte: LIBELLES_TYPE_PIECE[piece.type] || piece.type }));
    titre.append(creer('span', { texte: ` — ${piece.nom_original} (${formaterTaille(piece.taille)})` }));
    item.append(titre);
    item.append(creer('div', { classe: 'meta', texte: `${piece.depose_prenom} ${piece.depose_nom} · ${formaterDate(piece.depose_le)} · ${piece.statut}` }));
    const actions = creer('div', { classe: 'actions-cellule' });
    const lien = creer('a', { classe: 'btn btn-petit', texte: 'Télécharger' });
    lien.href = `/api/pieces/${piece.id}/telechargement`;
    actions.append(lien);
    if (estGestionnaire()) {
      actions.append(boutonAction('Valider', 'piece-valider', piece.id, 'btn btn-petit btn-principal'));
      actions.append(boutonAction('Rejeter', 'piece-rejeter', piece.id, 'btn btn-petit btn-danger'));
    }
    actions.append(boutonAction('Supprimer', 'piece-supprimer', piece.id, 'btn btn-petit btn-discret'));
    item.append(actions);
    liste.append(item);
  }
  nettoyerErreurs($('form-piece'));
  $('dialogue-pieces').showModal();
}

async function televerserPiece(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-piece');
  nettoyerErreurs(formulaire);
  const fichier = $('piece-fichier').files[0];
  if (!fichier) return afficherErreurChamp('erreur-piece', 'Choisissez un fichier PDF.', $('piece-fichier'));
  const corps = new FormData();
  corps.append('type', $('piece-type').value);
  corps.append('fichier', fichier);
  await avecBouton(formulaire.querySelector('button[type="submit"]'), async () => {
    try {
      await apiFormulaire(`/api/demandes/${etat.pieceDemandeId}/pieces`, corps);
      formulaire.reset();
      afficherBandeau('Pièce ajoutée.', 'succes');
      await ouvrirPieces(etat.pieceDemandeId);
      await chargerDemandes();
    } catch (erreur) {
      appliquerDetails(erreur.details, formulaire);
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

function ouvrirCommentaire(id) {
  etat.demandeCourante = id;
  $('commentaire-contenu').value = '';
  nettoyerErreurs($('form-commentaire'));
  $('dialogue-commentaire').showModal();
  $('commentaire-contenu').focus();
}

async function ajouterCommentaire(evenement) {
  evenement.preventDefault();
  const contenu = $('commentaire-contenu').value.trim();
  nettoyerErreurs($('form-commentaire'));
  if (!contenu) return afficherErreurChamp('erreur-commentaire', 'Le commentaire est obligatoire.', $('commentaire-contenu'));
  try {
    await api('POST', `/api/demandes/${etat.demandeCourante}/commentaires`, { contenu });
    $('dialogue-commentaire').close();
    afficherBandeau('Commentaire ajouté.', 'succes');
    await ouvrirDetail(etat.demandeCourante);
  } catch (erreur) {
    afficherErreurChamp('erreur-commentaire', erreur.message, $('commentaire-contenu'));
  }
}

function ouvrirNote(id) {
  etat.demandeCourante = id;
  $('note-valeur').value = '';
  $('note-mention').value = '';
  $('note-appreciation').value = '';
  nettoyerErreurs($('form-note'));
  $('dialogue-note').showModal();
  $('note-valeur').focus();
}

async function enregistrerNote(evenement) {
  evenement.preventDefault();
  nettoyerErreurs($('form-note'));
  const note = Number($('note-valeur').value);
  if (!Number.isFinite(note) || note < 0 || note > 20) {
    return afficherErreurChamp('erreur-note', 'La note doit être comprise entre 0 et 20.', $('note-valeur'));
  }
  try {
    await api('PATCH', `/api/demandes/${etat.demandeCourante}/note`, {
      note,
      mention: $('note-mention').value.trim(),
      appreciation: $('note-appreciation').value.trim(),
    });
    $('dialogue-note').close();
    afficherBandeau('Note enregistrée.', 'succes');
    await chargerDemandes();
  } catch (erreur) {
    appliquerDetails(erreur.details, $('form-note'));
    afficherBandeau(erreur.message, 'erreur');
  }
}

async function ouvrirDetailSoutenance(id) {
  const reponse = await api('GET', `/api/soutenances/${id}`);
  const soutenance = reponse.data;
  etat.soutenanceCourante = id;
  const texte = [
    `Date : ${formaterDate(soutenance.date_heure)}`,
    `Salle : ${soutenance.salle_nom || soutenance.salle}`,
    `Durée : ${soutenance.duree_min} min`,
    `Étudiant : ${soutenance.etudiant_prenom} ${soutenance.etudiant_nom}`,
    `Entreprise : ${soutenance.entreprise}`,
    `Convocation : ${soutenance.convocation_envoyee ? 'envoyée' : 'non envoyée'}`,
  ];
  for (const membre of soutenance.jury || []) {
    texte.push(`Jury : ${membre.role_jury} — ${membre.prenom} ${membre.nom}`);
  }
  $('detail-soutenance').textContent = texte.join('\n');
  $('dialogue-soutenance-detail').showModal();
}

async function afficherConvocation() {
  await tenter(async () => {
    const reponse = await api('GET', `/api/soutenances/${etat.soutenanceCourante}/convocation`);
    $('detail-soutenance').textContent = reponse.data.texte;
  });
}

async function envoyerConvocations() {
  await avecBouton($('btn-convoquer'), async () => {
    try {
      await api('PATCH', `/api/soutenances/${etat.soutenanceCourante}/convoquer`, {});
      afficherBandeau('Convocations envoyées.', 'succes');
      await ouvrirDetailSoutenance(etat.soutenanceCourante);
      await chargerSoutenances();
    } catch (erreur) {
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

async function appliquerFiltres() {
  etat.filtres.q = $('filtre-q').value.trim();
  etat.filtres.statut = $('filtre-statut').value;
  etat.filtres.entrepriseId = $('filtre-entreprise').value;
  etat.filtres.filiere = $('filtre-filiere').value;
  etat.filtres.annee = $('filtre-annee').value.trim();
  etat.metaDemandes.page = 1;
  etat.selection.clear();
  await tenter(chargerDemandes);
}

function reinitialiserFiltres() {
  $('filtre-q').value = '';
  $('filtre-statut').value = '';
  $('filtre-entreprise').value = '';
  $('filtre-filiere').value = '';
  $('filtre-annee').value = '';
  appliquerFiltres();
}

async function traiterLot() {
  const ids = [...etat.selection];
  const statut = $('action-lot').value;
  if (ids.length === 0) return afficherBandeau('Sélectionnez au moins une demande.', 'erreur');
  if (!statut) return afficherBandeau('Choisissez une action groupée.', 'erreur');
  await avecBouton($('btn-appliquer-lot'), async () => {
    try {
      const reponse = await api('POST', '/api/demandes/lot', { ids, statut });
      etat.selection.clear();
      afficherBandeau(`${reponse.data.traites} demande(s) traitée(s).`, 'succes');
      await chargerDemandes();
    } catch (erreur) {
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

async function proposerEntreprise(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-proposition-entreprise');
  nettoyerErreurs(formulaire);
  const payload = {
    raisonSociale: $('prop-raison').value.trim(),
    ville: $('prop-ville').value.trim(),
    secteur: $('prop-secteur').value.trim(),
    emailContact: $('prop-email').value.trim(),
  };
  if (!payload.raisonSociale) return afficherErreurChamp('erreur-prop-raison', 'La raison sociale est obligatoire.', $('prop-raison'));
  if (!payload.ville) return afficherErreurChamp('erreur-prop-ville', 'La ville est obligatoire.', $('prop-ville'));
  await avecBouton(formulaire.querySelector('button[type="submit"]'), async () => {
    try {
      await api('POST', '/api/entreprises/proposer', payload);
      formulaire.reset();
      afficherBandeau('Entreprise proposée au responsable.', 'succes');
      await chargerEntreprises();
    } catch (erreur) {
      appliquerDetails(erreur.details, formulaire);
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

async function creerEntreprise(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-creation-entreprise');
  nettoyerErreurs(formulaire);
  const payload = {
    raisonSociale: $('cre-raison').value.trim(),
    ville: $('cre-ville').value.trim(),
    statut: $('cre-statut').value,
    secteur: $('cre-secteur').value.trim(),
    contactNom: $('cre-contact').value.trim(),
    telephone: $('cre-telephone').value.trim(),
  };
  if (!payload.raisonSociale) return afficherErreurChamp('erreur-cre-raison', 'La raison sociale est obligatoire.', $('cre-raison'));
  if (!payload.ville) return afficherErreurChamp('erreur-cre-ville', 'La ville est obligatoire.', $('cre-ville'));
  await avecBouton(formulaire.querySelector('button[type="submit"]'), async () => {
    try {
      await api('POST', '/api/entreprises', payload);
      formulaire.reset();
      afficherBandeau('Entreprise ajoutée.', 'succes');
      await chargerEntreprises();
      await chargerReferentiels();
    } catch (erreur) {
      appliquerDetails(erreur.details, formulaire);
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

function ajusterChampsCompte() {
  const role = $('cmp-role').value;
  document.querySelectorAll('.champs-etudiant').forEach((noeud) => { noeud.hidden = role !== 'ETUDIANT'; });
  document.querySelectorAll('.champs-encadreur').forEach((noeud) => { noeud.hidden = role !== 'ENCADREUR'; });
}

async function creerCompte(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-compte');
  nettoyerErreurs(formulaire);
  const role = $('cmp-role').value;
  const payload = {
    role,
    email: $('cmp-email').value.trim(),
    prenom: $('cmp-prenom').value.trim(),
    nom: $('cmp-nom').value.trim(),
    telephone: $('cmp-telephone').value.trim(),
    motDePasse: $('cmp-mot-de-passe').value,
  };
  if (role === 'ETUDIANT') {
    payload.matricule = $('cmp-matricule').value.trim();
    payload.filiere = $('cmp-filiere').value.trim();
    payload.promotion = $('cmp-promotion').value.trim();
    for (const cle of ['matricule', 'filiere', 'promotion']) {
      if (!payload[cle]) return afficherErreurChamp('erreur-cmp-email', `Le champ ${cle} est obligatoire pour un étudiant.`, $('cmp-email'));
    }
  }
  if (role === 'ENCADREUR') {
    payload.grade = $('cmp-grade').value.trim();
    payload.departement = $('cmp-departement').value.trim();
    payload.chargeMax = Number($('cmp-charge').value);
    if (!payload.grade || !payload.departement) {
      return afficherErreurChamp('erreur-cmp-email', 'Le grade et le département sont obligatoires pour un encadreur.', $('cmp-email'));
    }
  }
  await avecBouton(formulaire.querySelector('button[type="submit"]'), async () => {
    try {
      await api('POST', '/api/utilisateurs', payload);
      formulaire.reset();
      $('cmp-mot-de-passe').value = 'Stage2026!';
      $('cmp-charge').value = '5';
      ajusterChampsCompte();
      afficherBandeau('Compte créé.', 'succes');
      await chargerUtilisateurs();
    } catch (erreur) {
      appliquerDetails(erreur.details, formulaire);
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

async function importerEtudiants(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-import');
  nettoyerErreurs(formulaire);
  const fichier = $('import-fichier').files[0];
  if (!fichier) return afficherErreurChamp('erreur-import', 'Choisissez un fichier CSV.', $('import-fichier'));
  const corps = new FormData();
  corps.append('fichier', fichier);
  await avecBouton(formulaire.querySelector('button[type="submit"]'), async () => {
    try {
      const reponse = await apiFormulaire('/api/utilisateurs/import', corps);
      formulaire.reset();
      afficherBandeau(`${reponse.data.crees || 0} compte(s) créé(s).`, 'succes');
      await chargerUtilisateurs();
    } catch (erreur) {
      appliquerDetails(erreur.details, formulaire);
      afficherBandeau(erreur.message, 'erreur');
    }
  });
}

async function genererDocument(evenement) {
  evenement.preventDefault();
  const id = $('doc-demande').value;
  if (!id) return afficherErreurChamp('erreur-doc-demande', 'Choisissez une demande.', $('doc-demande'));
  const type = $('doc-type').value;
  const format = $('doc-format').value;
  window.location.assign(`/api/documents/${id}?type=${encodeURIComponent(type)}&format=${encodeURIComponent(format)}`);
}

async function ouvrirProfil() {
  const reponse = await api('GET', '/api/profil');
  const profil = reponse.data;
  $('profil-prenom').value = profil.prenom || '';
  $('profil-nom').value = profil.nom || '';
  $('profil-email').value = profil.email || '';
  $('profil-telephone').value = profil.telephone || '';
  const complement = [];
  if (profil.etudiant) complement.push(`${profil.etudiant.matricule} · ${profil.etudiant.filiere} · ${profil.etudiant.promotion}`);
  if (profil.encadreur) complement.push(`${profil.encadreur.grade} · ${profil.encadreur.departement}`);
  $('profil-complement').textContent = complement.join(' · ');
  nettoyerErreurs($('form-profil'));
  nettoyerErreurs($('form-mot-de-passe'));
  $('dialogue-profil').showModal();
}

async function modifierProfil(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-profil');
  nettoyerErreurs(formulaire);
  try {
    const reponse = await api('PATCH', '/api/profil', {
      prenom: $('profil-prenom').value.trim(),
      nom: $('profil-nom').value.trim(),
      email: $('profil-email').value.trim(),
      telephone: $('profil-telephone').value.trim(),
    });
    etat.user.prenom = reponse.data.prenom;
    etat.user.nom = reponse.data.nom;
    etat.user.email = reponse.data.email;
    $('nom-utilisateur').textContent = `${etat.user.prenom} ${etat.user.nom}`;
    afficherBandeau('Profil mis à jour.', 'succes');
  } catch (erreur) {
    appliquerDetails(erreur.details, formulaire);
    afficherBandeau(erreur.message, 'erreur');
  }
}

async function modifierMotDePasse(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-mot-de-passe');
  nettoyerErreurs(formulaire);
  try {
    await api('PATCH', '/api/profil/mot-de-passe', {
      motDePasseActuel: $('mdp-actuel').value,
      nouveauMotDePasse: $('mdp-nouveau').value,
    });
    $('mdp-actuel').value = '';
    $('mdp-nouveau').value = '';
    afficherBandeau('Mot de passe modifié.', 'succes');
  } catch (erreur) {
    appliquerDetails(erreur.details, formulaire);
    afficherBandeau(erreur.message, 'erreur');
  }
}

async function demanderReinitialisation(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-mot-de-passe-oublie');
  nettoyerErreurs(formulaire);
  try {
    const reponse = await api('POST', '/api/mot-de-passe-oublie', { email: $('oublie-email').value.trim() });
    afficherBandeau(reponse.message, 'succes');
  } catch (erreur) {
    afficherErreurChamp('erreur-oublie', erreur.message, $('oublie-email'));
  }
}

async function reinitialiserMotDePasse(evenement) {
  evenement.preventDefault();
  const formulaire = $('form-reset-mot-de-passe');
  nettoyerErreurs(formulaire);
  try {
    const reponse = await api('POST', '/api/mot-de-passe-reset', {
      token: $('reset-token').value.trim(),
      motDePasse: $('reset-mot-de-passe').value,
    });
    $('reset-token').value = '';
    $('reset-mot-de-passe').value = '';
    afficherBandeau(reponse.message, 'succes');
  } catch (erreur) {
    appliquerDetails(erreur.details, formulaire);
    afficherBandeau(erreur.message, 'erreur');
  }
}

function lierEvenements() {
  $('form-connexion').addEventListener('submit', connexion);
  $('btn-deconnexion').addEventListener('click', deconnexion);
  $('btn-profil').addEventListener('click', () => tenter(ouvrirProfil));
  $('btn-notifications').addEventListener('click', () => changerVue('notifications'));
  $('btn-mot-de-passe-oublie').addEventListener('click', () => $('dialogue-mot-de-passe-oublie').showModal());
  $('form-mot-de-passe-oublie').addEventListener('submit', demanderReinitialisation);
  $('form-reset-mot-de-passe').addEventListener('submit', reinitialiserMotDePasse);
  $('form-profil').addEventListener('submit', modifierProfil);
  $('form-mot-de-passe').addEventListener('submit', modifierMotDePasse);

  document.querySelectorAll('#navigation button[data-vue]').forEach((bouton) => {
    bouton.addEventListener('click', () => changerVue(bouton.dataset.vue));
  });
  document.querySelectorAll('[data-aller]').forEach((bouton) => {
    bouton.addEventListener('click', () => changerVue(bouton.dataset.aller));
  });

  $('form-demande').addEventListener('submit', creerOuModifierDemande);
  $('btn-annuler-edit-demande').addEventListener('click', reinitialiserFormulaireDemande);
  $('form-refus').addEventListener('submit', confirmerRefus);
  $('form-soutenance').addEventListener('submit', confirmerSoutenance);
  $('form-piece').addEventListener('submit', televerserPiece);
  $('form-commentaire').addEventListener('submit', ajouterCommentaire);
  $('form-note').addEventListener('submit', enregistrerNote);
  $('form-proposition-entreprise').addEventListener('submit', proposerEntreprise);
  $('form-creation-entreprise').addEventListener('submit', creerEntreprise);
  $('form-compte').addEventListener('submit', creerCompte);
  $('form-import').addEventListener('submit', importerEtudiants);
  $('form-document').addEventListener('submit', genererDocument);
  $('cmp-role').addEventListener('change', ajusterChampsCompte);

  $('filtre-q').addEventListener('input', () => {
    window.clearTimeout(lierEvenements.minuteurFiltre);
    lierEvenements.minuteurFiltre = window.setTimeout(appliquerFiltres, 300);
  });
  for (const id of ['filtre-statut', 'filtre-entreprise', 'filtre-filiere', 'filtre-annee']) {
    $(id).addEventListener('change', appliquerFiltres);
  }
  $('btn-reset-filtres').addEventListener('click', reinitialiserFiltres);
  $('pagination-demandes').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-page]');
    if (!bouton || bouton.disabled) return;
    etat.metaDemandes.page = Number(bouton.dataset.page);
    tenter(chargerDemandes);
  });
  $('btn-appliquer-lot').addEventListener('click', traiterLot);
  $('filtre-statut-entreprise').addEventListener('change', () => tenter(chargerEntreprises));
  $('btn-tout-lire').addEventListener('click', () => tenter(async () => {
    await api('PATCH', '/api/notifications-tout-lire', {});
    afficherBandeau('Notifications marquées comme lues.', 'succes');
    await chargerNotifications();
  }));
  for (const id of ['audit-action', 'audit-debut', 'audit-fin']) {
    $(id).addEventListener('change', () => tenter(chargerAudit));
  }
  $('planning-annee').addEventListener('change', () => {
    etat.periode.annee = Number($('planning-annee').value) || new Date().getFullYear();
    tenter(chargerSoutenances);
  });
  $('planning-mois').addEventListener('change', () => {
    etat.periode.mois = Number($('planning-mois').value);
    tenter(chargerSoutenances);
  });

  $('corps-demandes').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (!bouton) return;
    agirDemande(bouton.dataset.action, Number(bouton.dataset.id), bouton);
  });
  $('corps-demandes').addEventListener('change', (evenement) => {
    const caseA = evenement.target.closest('input[data-selection]');
    if (!caseA) return;
    const id = Number(caseA.dataset.selection);
    if (caseA.checked) etat.selection.add(id);
    else etat.selection.delete(id);
    $('resume-lot').textContent = etat.selection.size
      ? `${etat.selection.size} demande(s) sélectionnée(s).`
      : 'Cochez des demandes pour appliquer une action.';
  });
  $('corps-entreprises').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (bouton) agirDemande(bouton.dataset.action, Number(bouton.dataset.id), bouton);
  });
  $('corps-planning').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (bouton) agirDemande(bouton.dataset.action, Number(bouton.dataset.id), bouton);
  });
  $('liste-notifications').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (bouton) agirDemande(bouton.dataset.action, Number(bouton.dataset.id), bouton);
  });
  $('corps-utilisateurs').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (bouton) agirDemande(bouton.dataset.action, Number(bouton.dataset.id), bouton);
  });
  $('liste-pieces').addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('button[data-action]');
    if (bouton) agirDemande(bouton.dataset.action, Number(bouton.dataset.id), bouton);
  });
  $('btn-detail-pieces').addEventListener('click', (evenement) => ouvrirPieces(Number(evenement.target.dataset.id)));
  $('btn-detail-commenter').addEventListener('click', (evenement) => ouvrirCommentaire(Number(evenement.target.dataset.id)));
  $('btn-detail-historique').addEventListener('click', (evenement) => tenter(() => ouvrirHistorique(Number(evenement.target.dataset.id))));
  $('btn-convocation').addEventListener('click', () => tenter(afficherConvocation));
  $('btn-convoquer').addEventListener('click', envoyerConvocations);

  document.querySelectorAll('[data-fermer]').forEach((bouton) => {
    bouton.addEventListener('click', () => $(bouton.dataset.fermer).close());
  });
}

async function initialiser() {
  lierEvenements();
  $('planning-annee').value = String(etat.periode.annee);
  $('planning-mois').value = String(etat.periode.mois);
  ajusterChampsCompte();
  try {
    const session = await api('GET', '/api/session');
    etat.csrf = session.csrfToken;
    if (session.authenticated) {
      etat.user = session.user;
      await demarrerApplication();
    } else {
      afficherConnexion();
    }
  } catch (erreur) {
    afficherConnexion();
    afficherBandeau(erreur.message, 'erreur');
  }
}

document.addEventListener('DOMContentLoaded', initialiser);
