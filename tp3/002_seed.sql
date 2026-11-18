-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 002_seed.sql — jeu d'essai entièrement fictif (aucune donnée réelle)
-- Comptes de démonstration : mot de passe commun « Stage2026! » (bcrypt, coût 12)

SET NAMES utf8mb4;

INSERT INTO utilisateur (id, email, mot_de_passe, role, prenom, nom, actif) VALUES
  (1, 'administrateur@campus.test',        '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'ADMIN',       'Hubert',   'KIKI',       1),
  (2, 'accueil@campus.test',               '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'SECRETARIAT', 'Rosine',   'ATCHADE',    1),
  (3, 'responsable.stages@campus.test',    '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'RESPONSABLE', 'Firmin',   'OGOUNA',     1),
  (4, 'rachid.kpanou@campus.test',         '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'ETUDIANT',    'Rachid',   'KPANOU',     1),
  (5, 'clarisse.sodjinou@campus.test',     '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'ETUDIANT',    'Clarisse', 'SODJINOU',   1),
  (6, 'samuel.vodounou@campus.test',       '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'ETUDIANT',    'Samuel',   'VODOUNOU',   1),
  (7, 'arsene.djegba@campus.test',         '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'ENCADREUR',   'Arsène',   'DJEGBA',     1),
  (8, 'nadine.hounkpevi@campus.test',      '$2y$12$Dqjme2CQNym9RQr8AvTbveyjfhwJxWTM51dVdZxetLC7qJjdUtqeO', 'ENCADREUR',   'Nadine',   'HOUNKPEVI',  1);

INSERT INTO etudiant (id, utilisateur_id, matricule, filiere, promotion) VALUES
  (1, 4, 'SIL-23117', 'Informatique',       'L3 SIL 2026'),
  (2, 5, 'SIL-23142', 'Réseaux & Télécoms', 'L3 SIL 2026'),
  (3, 6, 'SIL-23089', 'Génie logiciel',     'L3 SIL 2026');

INSERT INTO encadreur (id, utilisateur_id, grade, departement) VALUES
  (1, 7, 'Maître de conférences', 'Informatique'),
  (2, 8, 'Assistant',             'Mathématiques appliquées');

INSERT INTO entreprise (id, raison_sociale, ville, email_contact) VALUES
  (1, 'Sahara Data Services', 'Cotonou', 'contact@saharadata.test'),
  (2, 'Delta Logistique',     'Parakou', 'stage@deltalog.test');

-- Une demande par statut du cycle de vie (4 lignes).
INSERT INTO demande_stage (id, etudiant_id, entreprise_id, encadreur_id, statut, motif_refus, date_soumission, date_decision) VALUES
  (1, 1, 1, NULL, 'SOUMISE',  NULL,                                  '2026-09-14 08:45:00', NULL),
  (2, 2, 2, 1,    'VALIDEE',  NULL,                                  '2026-09-02 09:15:00', '2026-09-09 11:30:00'),
  (3, 3, 1, NULL, 'BROUILLON', NULL,                                 NULL,                  NULL),
  (4, 1, 2, NULL, 'REFUSEE',  'Stage sans rapport avec le cursus SIL', '2026-08-25 16:10:00', '2026-08-30 10:00:00');

INSERT INTO historique (id, demande_id, statut_avant, statut_apres, auteur_id, motif, horodatage) VALUES
  (1, 1, 'BROUILLON', 'SOUMISE',  4, NULL,                                  '2026-09-14 08:45:00'),
  (2, 2, 'BROUILLON', 'SOUMISE',  5, NULL,                                  '2026-09-02 09:15:00'),
  (3, 2, 'SOUMISE',   'VALIDEE',  3, NULL,                                  '2026-09-09 11:30:00'),
  (4, 4, 'BROUILLON', 'SOUMISE',  4, NULL,                                  '2026-08-25 16:10:00'),
  (5, 4, 'SOUMISE',   'REFUSEE',  3, 'Stage sans rapport avec le cursus SIL', '2026-08-30 10:00:00');

INSERT INTO soutenance (id, demande_id, date_heure, salle, duree_min) VALUES
  (1, 2, '2026-12-10 09:00:00', 'B12', 40);
