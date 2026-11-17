-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 001_schema.sql — reconstruction du schéma relationnel (base vide)
-- Cible : MySQL 8 / MariaDB 11 · jeu de données entièrement fictif

SET NAMES utf8mb4;

DROP VIEW IF EXISTS v_planning_soutenances;
DROP VIEW IF EXISTS v_dossiers_a_completer;
DROP TABLE IF EXISTS soutenance;
DROP TABLE IF EXISTS historique;
DROP TABLE IF EXISTS piece_jointe;
DROP TABLE IF EXISTS demande_stage;
DROP TABLE IF EXISTS encadreur;
DROP TABLE IF EXISTS etudiant;
DROP TABLE IF EXISTS entreprise;
DROP TABLE IF EXISTS utilisateur;

-- Identité unique : le rôle détermine les droits, un seul mot de passe haché par compte.
CREATE TABLE utilisateur (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(100) NOT NULL,
  mot_de_passe  VARCHAR(255) NOT NULL,
  role          ENUM('ETUDIANT','SECRETARIAT','RESPONSABLE','ENCADREUR','ADMIN') NOT NULL,
  prenom        VARCHAR(60)  NOT NULL,
  nom           VARCHAR(60)  NOT NULL,
  actif         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT uq_compte_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un compte d'étudiant porte au plus une fiche ; matricule unique au niveau du département.
CREATE TABLE etudiant (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  matricule      VARCHAR(20)  NOT NULL,
  filiere        VARCHAR(60)  NOT NULL,
  promotion      VARCHAR(20)  NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT uq_etudiant_compte UNIQUE (utilisateur_id),
  CONSTRAINT uq_etudiant_matricule UNIQUE (matricule),
  CONSTRAINT etudiant_fk_compte FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un enseignant peut encadrer plusieurs stages, mais n'a qu'une fiche d'encadreur.
CREATE TABLE encadreur (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  grade          VARCHAR(60)  NOT NULL,
  departement    VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT uq_encadreur_compte UNIQUE (utilisateur_id),
  CONSTRAINT encadreur_fk_compte FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entreprise (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  raison_sociale VARCHAR(80)  NOT NULL,
  ville          VARCHAR(60)  NOT NULL,
  email_contact  VARCHAR(100) NULL,
  PRIMARY KEY (id),
  CONSTRAINT uq_entreprise_raison_ville UNIQUE (raison_sociale, ville)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cycle de vie retenu : BROUILLON → DEPOSEE → INCOMPLETE | VALIDEE | REFUSEE.
-- L'encadreur n'est renseigné qu'après la décision de validation (règle applicative, TP6).
CREATE TABLE demande_stage (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  etudiant_id   INT UNSIGNED NOT NULL,
  entreprise_id INT UNSIGNED NOT NULL,
  encadreur_id  INT UNSIGNED NULL,
  statut        ENUM('BROUILLON','DEPOSEE','INCOMPLETE','VALIDEE','REFUSEE') NOT NULL DEFAULT 'BROUILLON',
  motif         VARCHAR(255) NULL,
  date_depot    DATETIME NULL,
  date_decision DATETIME NULL,
  PRIMARY KEY (id),
  INDEX ix_dossier_etudiant (etudiant_id),
  INDEX ix_dossier_entreprise (entreprise_id),
  INDEX ix_dossier_statut (statut),
  CONSTRAINT demande_fk_etudiant FOREIGN KEY (etudiant_id)
    REFERENCES etudiant (id) ON DELETE CASCADE,
  CONSTRAINT demande_fk_entreprise FOREIGN KEY (entreprise_id)
    REFERENCES entreprise (id),
  CONSTRAINT demande_fk_encadreur FOREIGN KEY (encadreur_id)
    REFERENCES encadreur (id) ON DELETE SET NULL,
  CONSTRAINT chk_motif_requis CHECK (statut NOT IN ('INCOMPLETE','REFUSEE') OR motif IS NOT NULL),
  CONSTRAINT chk_date_depot_requise CHECK (statut = 'BROUILLON' OR date_depot IS NOT NULL),
  CONSTRAINT chk_date_decision_requise CHECK (statut NOT IN ('VALIDEE','REFUSEE') OR date_decision IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deux pièces maximum par dossier (convention + attestation), 5 Mo par fichier.
CREATE TABLE piece_jointe (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id      INT UNSIGNED NOT NULL,
  categorie       ENUM('CONVENTION','ATTESTATION_ASSURANCE') NOT NULL,
  chemin_fichier  VARCHAR(180) NOT NULL,
  octets          INT UNSIGNED NOT NULL,
  controlee       TINYINT(1)   NOT NULL DEFAULT 0,
  date_transmission DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uq_piece_demande_categorie UNIQUE (demande_id, categorie),
  CONSTRAINT piece_fk_dossier FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT chk_piece_taille CHECK (octets <= 5242880)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des transitions : qui a décidé quoi, et quand.
CREATE TABLE historique (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id    INT UNSIGNED NOT NULL,
  statut_avant  ENUM('BROUILLON','DEPOSEE','INCOMPLETE','VALIDEE','REFUSEE') NULL,
  statut_apres  ENUM('BROUILLON','DEPOSEE','INCOMPLETE','VALIDEE','REFUSEE') NOT NULL,
  auteur_id     INT UNSIGNED NOT NULL,
  motif         VARCHAR(255) NULL,
  horodatage    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_journal_dossier (demande_id),
  INDEX ix_journal_auteur (auteur_id),
  CONSTRAINT journal_fk_dossier FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT journal_fk_auteur FOREIGN KEY (auteur_id)
    REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une soutenance par dossier validé ; salle + créneau forment une clé anti-doublon.
CREATE TABLE soutenance (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id  INT UNSIGNED NOT NULL,
  date_heure  DATETIME NOT NULL,
  salle       VARCHAR(40) NOT NULL,
  duree_min   INT NOT NULL DEFAULT 30,
  PRIMARY KEY (id),
  CONSTRAINT uq_soutenance_dossier UNIQUE (demande_id),
  CONSTRAINT uq_creneau_salle UNIQUE (salle, date_heure),
  CONSTRAINT soutenance_fk_dossier FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT chk_duree_soutenance CHECK (duree_min BETWEEN 20 AND 90)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_dossiers_a_completer AS
SELECT d.id          AS dossier_id,
       u.nom         AS etudiant,
       d.motif       AS motif,
       d.date_depot  AS date_depot
FROM demande_stage d
JOIN etudiant e    ON e.id = d.etudiant_id
JOIN utilisateur u ON u.id = e.utilisateur_id
WHERE d.statut = 'INCOMPLETE';

CREATE OR REPLACE VIEW v_planning_soutenances AS
SELECT s.id          AS soutenance_id,
       s.date_heure,
       s.salle,
       s.duree_min,
       u.nom         AS etudiant,
       en.raison_sociale AS entreprise
FROM soutenance s
JOIN demande_stage d ON d.id = s.demande_id
JOIN etudiant e      ON e.id = d.etudiant_id
JOIN utilisateur u   ON u.id = e.utilisateur_id
JOIN entreprise en   ON en.id = d.entreprise_id;
