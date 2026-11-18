-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 001_schema.sql — reconstruction du schéma relationnel sur une base vide
-- Cible : MySQL 8.4 / MariaDB 11 · jeu de données entièrement fictif
--
-- Modèle retenu (chapitre 3 du cours) :
--   Étudiant 1..* — DemandeStage *..1 Entreprise
--   DemandeStage 0..1 — Soutenance          (UNIQUE sur soutenance.demande_id)
--   DemandeStage *..0..1 Encadreur          (affecté à la validation)
--   Cycle de vie : BROUILLON → SOUMISE → VALIDEE | REFUSEE, REFUSEE → BROUILLON

SET NAMES utf8mb4;

DROP VIEW IF EXISTS v_planning_soutenances;
DROP VIEW IF EXISTS v_demandes_a_traiter;
DROP TABLE IF EXISTS soutenance;
DROP TABLE IF EXISTS historique;
DROP TABLE IF EXISTS demande_stage;
DROP TABLE IF EXISTS encadreur;
DROP TABLE IF EXISTS etudiant;
DROP TABLE IF EXISTS entreprise;
DROP TABLE IF EXISTS utilisateur;

-- ---------------------------------------------------------------------------
-- Comptes : un seul point d'authentification, le rôle porte les autorisations.
-- Le mot de passe est stocké haché (password_hash), jamais en clair.
-- ---------------------------------------------------------------------------
CREATE TABLE utilisateur (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email        VARCHAR(100) NOT NULL,
  mot_de_passe VARCHAR(255) NOT NULL,
  role         ENUM('ETUDIANT','SECRETARIAT','RESPONSABLE','ENCADREUR','ADMIN') NOT NULL,
  prenom       VARCHAR(60)  NOT NULL,
  nom          VARCHAR(60)  NOT NULL,
  actif        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT uq_utilisateur_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Étudiant : une fiche pour un compte, un matricule unique.
-- ---------------------------------------------------------------------------
CREATE TABLE etudiant (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  matricule      VARCHAR(20)  NOT NULL,
  filiere        VARCHAR(60)  NOT NULL,
  promotion      VARCHAR(20)  NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT uq_etudiant_compte    UNIQUE (utilisateur_id),
  CONSTRAINT uq_etudiant_matricule UNIQUE (matricule),
  CONSTRAINT fk_etudiant_compte FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Encadreur : un enseignant, une fiche. Il consulte les dossiers qui lui sont
-- affectés après validation (aucune écriture métier de son côté).
-- ---------------------------------------------------------------------------
CREATE TABLE encadreur (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  grade          VARCHAR(60)  NOT NULL,
  departement    VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT uq_encadreur_compte UNIQUE (utilisateur_id),
  CONSTRAINT fk_encadreur_compte FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Entreprise : le nom n'est jamais recopié dans la demande (clé étrangère).
-- ---------------------------------------------------------------------------
CREATE TABLE entreprise (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  raison_sociale VARCHAR(80)  NOT NULL,
  ville          VARCHAR(60)  NOT NULL,
  email_contact  VARCHAR(100) NULL,
  PRIMARY KEY (id),
  CONSTRAINT uq_entreprise_raison_ville UNIQUE (raison_sociale, ville)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- DemandeStage : porte le statut, le motif de refus et les dates du parcours.
-- Les CHECK traduisent des règles métier que la base refuse de contourner.
-- ---------------------------------------------------------------------------
CREATE TABLE demande_stage (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  etudiant_id     INT UNSIGNED NOT NULL,
  entreprise_id   INT UNSIGNED NOT NULL,
  encadreur_id    INT UNSIGNED NULL,
  statut          VARCHAR(10)  NOT NULL DEFAULT 'BROUILLON',
  motif_refus     VARCHAR(255) NULL,
  date_soumission DATETIME     NULL,
  date_decision   DATETIME     NULL,
  PRIMARY KEY (id),
  INDEX ix_demande_etudiant   (etudiant_id),
  INDEX ix_demande_entreprise (entreprise_id),
  INDEX ix_demande_statut     (statut),
  CONSTRAINT fk_demande_etudiant FOREIGN KEY (etudiant_id)
    REFERENCES etudiant (id) ON DELETE CASCADE,
  CONSTRAINT fk_demande_entreprise FOREIGN KEY (entreprise_id)
    REFERENCES entreprise (id),
  CONSTRAINT fk_demande_encadreur FOREIGN KEY (encadreur_id)
    REFERENCES encadreur (id) ON DELETE SET NULL,
  CONSTRAINT chk_demande_statut CHECK (statut IN ('BROUILLON','SOUMISE','VALIDEE','REFUSEE')),
  CONSTRAINT chk_demande_motif CHECK (statut <> 'REFUSEE' OR motif_refus IS NOT NULL),
  CONSTRAINT chk_demande_soumission CHECK (statut = 'BROUILLON' OR date_soumission IS NOT NULL),
  CONSTRAINT chk_demande_decision CHECK (statut NOT IN ('VALIDEE','REFUSEE') OR date_decision IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Historique : journal des transitions, support de l'audit et de la preuve.
-- ---------------------------------------------------------------------------
CREATE TABLE historique (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id   INT UNSIGNED NOT NULL,
  statut_avant VARCHAR(10)  NULL,
  statut_apres VARCHAR(10)  NOT NULL,
  auteur_id    INT UNSIGNED NOT NULL,
  motif        VARCHAR(255) NULL,
  horodatage   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_historique_demande (demande_id),
  INDEX ix_historique_auteur  (auteur_id),
  CONSTRAINT fk_historique_demande FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT fk_historique_auteur FOREIGN KEY (auteur_id)
    REFERENCES utilisateur (id),
  CONSTRAINT chk_historique_statut_avant CHECK (statut_avant IS NULL OR statut_avant IN ('BROUILLON','SOUMISE','VALIDEE','REFUSEE')),
  CONSTRAINT chk_historique_statut_apres CHECK (statut_apres IN ('BROUILLON','SOUMISE','VALIDEE','REFUSEE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Soutenance : la multiplicité 0..1 devient UNIQUE(demande_id).
-- UNIQUE(salle, date_heure) interdit deux soutenances au même créneau.
-- ---------------------------------------------------------------------------
CREATE TABLE soutenance (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id INT UNSIGNED NOT NULL,
  date_heure DATETIME     NOT NULL,
  salle      VARCHAR(40)  NOT NULL,
  duree_min  INT          NOT NULL DEFAULT 30,
  PRIMARY KEY (id),
  CONSTRAINT uq_soutenance_demande UNIQUE (demande_id),
  CONSTRAINT uq_soutenance_creneau UNIQUE (salle, date_heure),
  CONSTRAINT fk_soutenance_demande FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT chk_soutenance_duree CHECK (duree_min BETWEEN 20 AND 90)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Vues de lecture : elles évitent de dupliquer les jointures dans le code.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_demandes_a_traiter AS
SELECT d.id            AS demande_id,
       u.nom           AS etudiant_nom,
       u.prenom        AS etudiant_prenom,
       e.matricule,
       en.raison_sociale AS entreprise,
       en.ville,
       d.date_soumission
FROM demande_stage d
JOIN etudiant e      ON e.id = d.etudiant_id
JOIN utilisateur u   ON u.id = e.utilisateur_id
JOIN entreprise en   ON en.id = d.entreprise_id
WHERE d.statut = 'SOUMISE';

CREATE OR REPLACE VIEW v_planning_soutenances AS
SELECT s.id         AS soutenance_id,
       s.date_heure,
       s.salle,
       s.duree_min,
       u.nom        AS etudiant_nom,
       u.prenom     AS etudiant_prenom,
       en.raison_sociale AS entreprise,
       enc.grade    AS encadreur_grade,
       ue.nom       AS encadreur_nom
FROM soutenance s
JOIN demande_stage d  ON d.id = s.demande_id
JOIN etudiant e       ON e.id = d.etudiant_id
JOIN utilisateur u    ON u.id = e.utilisateur_id
JOIN entreprise en    ON en.id = d.entreprise_id
LEFT JOIN encadreur enc ON enc.id = d.encadreur_id
LEFT JOIN utilisateur ue ON ue.id = enc.utilisateur_id;
