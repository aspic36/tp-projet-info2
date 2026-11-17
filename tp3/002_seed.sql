-- Projet informatique 2 — L3 SIL · Application de gestion des stages
-- 002_seed.sql — jeu d'essai fictif (aucune donnée réelle)
-- Attendu : 8 comptes, 3 étudiants, 2 encadreurs, 2 entreprises,
--           4 demandes, 5 pièces, 7 lignes d'historique, 1 soutenance.
-- Mot de passe commun des comptes de démonstration : Demo1234!
-- Empreinte produite par password_hash('Demo1234!', PASSWORD_DEFAULT) sous PHP 8.

SET NAMES utf8mb4;

INSERT INTO utilisateur (id, email, mot_de_passe_hash, role, nom, prenom, actif) VALUES
  (1, 'koffi.adjovi@univ.test',  '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'ETUDIANT',    'ADJOVI',    'Koffi', 1),
  (2, 'aicha.bello@univ.test',   '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'ETUDIANT',    'BELLO',     'Aicha', 1),
  (3, 'marc.dossou@univ.test',   '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'ETUDIANT',    'DOSSOU',    'Marc',  1),
  (4, 'secretariat@univ.test',   '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'SECRETARIAT', 'AGOSSOU',   'Rita',  1),
  (5, 'resp.pedago@univ.test',   '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'RESPONSABLE', 'TOSSOU',    'Bertin',1),
  (6, 'lea.hounkpati@univ.test', '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'ENCADREUR',   'HOUNKPATI', 'Léa',   1),
  (7, 'paul.sossou@univ.test',   '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'ENCADREUR',   'SOSSOU',    'Paul',  1),
  (8, 'admin@univ.test',         '$2y$10$w8vj0PJ5HJkVug46z0gCgeQbKFAUvmEMRLbgCLqNUQ5VDvoiPCIXm', 'ADMIN',       'GBAGUIDI',  'Noël',  1);

INSERT INTO etudiant (id, utilisateur_id, numero_etudiant, filiere) VALUES
  (1, 1, 'ETU-2026-001', 'SIL'),
  (2, 2, 'ETU-2026-002', 'SIL'),
  (3, 3, 'ETU-2026-003', 'SIL');

INSERT INTO encadreur (id, utilisateur_id, grade) VALUES
  (1, 6, 'Maître de conférences'),
  (2, 7, 'Assistant');

INSERT INTO entreprise (id, nom, ville, contact) VALUES
  (1, 'Atelier Numerique', 'Porto-Novo', 'contact@atelier-numerique.test'),
  (2, 'Reseau Plus',       'Cotonou',    'rh@reseau-plus.test');

-- Une demande par statut : DEPOSEE, INCOMPLETE, VALIDEE, REFUSEE.
INSERT INTO demande_stage (id, etudiant_id, entreprise_id, encadreur_id, statut, motif, date_depot, date_decision) VALUES
  (1, 1, 1, NULL, 'INCOMPLETE', 'Attestation d''assurance manquante', '2026-11-03 10:00:00', NULL),
  (2, 2, 1, NULL, 'DEPOSEE',    NULL, '2026-11-10 08:30:00', NULL),
  (3, 3, 2, 1,    'VALIDEE',    NULL, '2026-10-20 09:15:00', '2026-10-27 16:40:00'),
  (4, 1, 2, NULL, 'REFUSEE',    'Convention incomplète : signature de l''entreprise absente',
                                '2026-10-05 11:00:00', '2026-10-09 14:20:00');

INSERT INTO piece_jointe (demande_id, type, nom_fichier, taille_octets, verifie, date_depot) VALUES
  (1, 'CONVENTION',           'convention_d1.pdf',  245760, 1, '2026-11-03 10:00:00'),
  (1, 'ATTESTATION_ASSURANCE','assurance_d1.pdf',    98304, 0, '2026-11-03 10:00:00'),
  (2, 'CONVENTION',           'convention_d2.pdf',  262144, 1, '2026-11-10 08:30:00'),
  (3, 'CONVENTION',           'convention_d3.pdf',  301056, 1, '2026-10-20 09:15:00'),
  (4, 'CONVENTION',           'convention_d4.pdf',  220672, 0, '2026-10-05 11:00:00');

INSERT INTO historique (demande_id, ancien_statut, nouveau_statut, auteur_id, motif, date_evenement) VALUES
  (1, NULL,      'DEPOSEE',    1, NULL, '2026-11-03 10:00:00'),
  (1, 'DEPOSEE', 'INCOMPLETE', 4, 'Attestation d''assurance manquante', '2026-11-04 14:10:00'),
  (2, NULL,      'DEPOSEE',    2, NULL, '2026-11-10 08:30:00'),
  (3, NULL,      'DEPOSEE',    3, NULL, '2026-10-20 09:15:00'),
  (3, 'DEPOSEE', 'VALIDEE',    5, NULL, '2026-10-27 16:40:00'),
  (4, NULL,      'DEPOSEE',    1, NULL, '2026-10-05 11:00:00'),
  (4, 'DEPOSEE', 'REFUSEE',    5, 'Convention incomplète', '2026-10-09 14:20:00');

INSERT INTO soutenance (demande_id, date_heure, salle, duree_minutes) VALUES
  (3, '2026-12-18 09:00:00', 'Salle A', 30);
