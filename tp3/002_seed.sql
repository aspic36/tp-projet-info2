-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 002_seed.sql — jeu d'essai fictif (aucune donnée réelle)
-- Comptes de démonstration : mot de passe « Stage2026! » (hash bcrypt coût 12)

SET NAMES utf8mb4;

INSERT INTO utilisateur (id, email, mot_de_passe, role, prenom, nom, actif) VALUES
  (1, 'administrateur@campus.test', '$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'ADMIN',       'Hubert',   'KIKI',        1),
  (2, 'accueil@campus.test',        '$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'SECRETARIAT', 'Rosine',   'ATCHADE',     1),
  (3, 'responsable.stages@campus.test', '$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'RESPONSABLE', 'Firmin', 'OGOUNA', 1),
  (4, 'rachid.kpanou@campus.test',  '$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'ETUDIANT',    'Rachid',   'KPANOU',      1),
  (5, 'clarisse.sodjinou@campus.test', '$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'ETUDIANT', 'Clarisse', 'SODJINOU', 1),
  (6, 'samuel.vodounou@campus.test','$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'ETUDIANT',    'Samuel',   'VODOUNOU',    1),
  (7, 'arsene.djegba@campus.test',  '$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'ENCADREUR',   'Arsène',   'DJEGBA',      1),
  (8, 'nadine.hounkpevi@campus.test','$2y$12$ZMu62A5zML29iCxfEj1cZucv1rc6npdSV1x.Nm3WSA/zRTalgOwfK', 'ENCADREUR',  'Nadine',   'HOUNKPEVI',   1);

INSERT INTO etudiant (id, utilisateur_id, matricule, filiere, promotion) VALUES
  (1, 4, 'SIL-23117', 'Informatique',        'L3 SIL 2026'),
  (2, 5, 'SIL-23142', 'Réseaux & Télécoms',  'L3 SIL 2026'),
  (3, 6, 'SIL-23089', 'Génie logiciel',      'L3 SIL 2026');

INSERT INTO encadreur (id, utilisateur_id, grade, departement) VALUES
  (1, 7, 'Maître de conférences', 'Informatique'),
  (2, 8, 'Assistant',             'Mathématiques appliquées');

INSERT INTO entreprise (id, raison_sociale, ville, email_contact) VALUES
  (1, 'Sahara Data Services', 'Cotonou', 'contact@saharadata.test'),
  (2, 'Delta Logistique',     'Parakou', 'stage@deltalog.test');

INSERT INTO demande_stage (id, etudiant_id, entreprise_id, encadreur_id, statut, motif, date_depot, date_decision) VALUES
  (1, 1, 1, NULL, 'INCOMPLETE', 'Attestation d''assurance manquante', '2026-09-14 08:45:00', NULL),
  (2, 2, 2, NULL, 'DEPOSEE',    NULL,                                 '2026-09-28 14:20:00', NULL),
  (3, 3, 1, 1,    'VALIDEE',    NULL,                                 '2026-09-02 09:15:00', '2026-09-09 11:30:00'),
  (4, 1, 2, NULL, 'REFUSEE',    'Stage sans rapport avec le cursus SIL', '2026-08-25 16:10:00', '2026-08-30 10:00:00');

INSERT INTO piece_jointe (id, demande_id, categorie, chemin_fichier, octets, controlee, date_transmission) VALUES
  (1, 1, 'CONVENTION',           'pieces/dossier-01/convention-kpanou.pdf',   2840000, 1, '2026-09-14 08:45:00'),
  (2, 2, 'CONVENTION',           'pieces/dossier-02/convention-sodjinou.pdf', 1275000, 0, '2026-09-28 14:20:00'),
  (3, 2, 'ATTESTATION_ASSURANCE','pieces/dossier-02/assurance-sodjinou.pdf',   612000, 0, '2026-09-28 14:22:00'),
  (4, 3, 'CONVENTION',           'pieces/dossier-03/convention-vodounou.pdf', 1980000, 1, '2026-09-02 09:15:00'),
  (5, 4, 'CONVENTION',           'pieces/dossier-04/convention-kpanou-delta.pdf', 3400000, 1, '2026-08-25 16:10:00');

INSERT INTO historique (id, demande_id, statut_avant, statut_apres, auteur_id, motif, horodatage) VALUES
  (1, 1, 'BROUILLON', 'DEPOSEE',    4, NULL,                                 '2026-09-14 08:45:00'),
  (2, 1, 'DEPOSEE',   'INCOMPLETE', 2, 'Attestation d''assurance manquante', '2026-09-16 10:05:00'),
  (3, 2, 'BROUILLON', 'DEPOSEE',    5, NULL,                                 '2026-09-28 14:20:00'),
  (4, 3, 'BROUILLON', 'DEPOSEE',    6, NULL,                                 '2026-09-02 09:15:00'),
  (5, 3, 'DEPOSEE',   'VALIDEE',    3, NULL,                                 '2026-09-09 11:30:00'),
  (6, 4, 'BROUILLON', 'DEPOSEE',    4, NULL,                                 '2026-08-25 16:10:00'),
  (7, 4, 'DEPOSEE',   'REFUSEE',    3, 'Stage sans rapport avec le cursus SIL', '2026-08-30 10:00:00');

INSERT INTO soutenance (id, demande_id, date_heure, salle, duree_min) VALUES
  (1, 3, '2026-12-10 10:30:00', 'B2', 40);
