-- Projet informatique 2 — extensions fonctionnelles
-- À exécuter après 001_schema.sql et 002_seed.sql :
--   mysql -u root -p stages < tp3/005_extensions.sql
-- Ce script est prévu pour une base issue de 001/002.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Comptes : coordonnées et suivi de création.
-- ---------------------------------------------------------------------------
ALTER TABLE utilisateur
  ADD COLUMN telephone VARCHAR(30) NULL AFTER nom,
  ADD COLUMN date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER actif;

-- ---------------------------------------------------------------------------
-- Entreprises : liste blanche, demandes de référencement et liste noire.
-- ---------------------------------------------------------------------------
ALTER TABLE entreprise
  ADD COLUMN secteur VARCHAR(80) NULL AFTER email_contact,
  ADD COLUMN contact_nom VARCHAR(100) NULL AFTER secteur,
  ADD COLUMN telephone VARCHAR(30) NULL AFTER contact_nom,
  ADD COLUMN adresse VARCHAR(255) NULL AFTER telephone,
  ADD COLUMN statut ENUM('PARTENAIRE','A_VALIDER','INTERDITE') NOT NULL DEFAULT 'PARTENAIRE' AFTER adresse,
  ADD COLUMN motif_statut VARCHAR(255) NULL AFTER statut,
  ADD COLUMN cree_par INT UNSIGNED NULL AFTER motif_statut,
  ADD COLUMN date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER cree_par,
  ADD CONSTRAINT fk_entreprise_createur FOREIGN KEY (cree_par)
    REFERENCES utilisateur (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- Encadreurs : limite de charge.
-- ---------------------------------------------------------------------------
ALTER TABLE encadreur
  ADD COLUMN charge_max SMALLINT UNSIGNED NOT NULL DEFAULT 5 AFTER departement;

-- ---------------------------------------------------------------------------
-- Demandes : fiche de stage, décision commentée, rapport, note, annulation,
-- encadreur proposé et anti-doublon actif.
-- ---------------------------------------------------------------------------
ALTER TABLE demande_stage
  ADD COLUMN sujet VARCHAR(255) NULL AFTER entreprise_id,
  ADD COLUMN missions TEXT NULL AFTER sujet,
  ADD COLUMN date_debut DATE NULL AFTER missions,
  ADD COLUMN date_fin DATE NULL AFTER date_debut,
  ADD COLUMN tuteur_entreprise VARCHAR(100) NULL AFTER date_fin,
  ADD COLUMN tuteur_email VARCHAR(100) NULL AFTER tuteur_entreprise,
  ADD COLUMN commentaire_decision VARCHAR(500) NULL AFTER motif_refus,
  ADD COLUMN encadreur_propose_id INT UNSIGNED NULL AFTER encadreur_id,
  ADD COLUMN date_proposition DATETIME NULL AFTER encadreur_propose_id,
  ADD COLUMN rapport_valide TINYINT(1) NOT NULL DEFAULT 0 AFTER date_decision,
  ADD COLUMN rapport_valide_le DATETIME NULL AFTER rapport_valide,
  ADD COLUMN rapport_valide_par INT UNSIGNED NULL AFTER rapport_valide_le,
  ADD COLUMN note DECIMAL(4,2) NULL AFTER rapport_valide_par,
  ADD COLUMN mention VARCHAR(40) NULL AFTER note,
  ADD COLUMN appreciation TEXT NULL AFTER mention,
  ADD COLUMN entreprise_active_id INT UNSIGNED
    GENERATED ALWAYS AS (
      CASE WHEN statut IN ('BROUILLON','SOUMISE','VALIDEE') THEN entreprise_id ELSE NULL END
    ) STORED AFTER statut,
  ADD CONSTRAINT fk_demande_encadreur_propose FOREIGN KEY (encadreur_propose_id)
    REFERENCES encadreur (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_demande_rapport_valide_par FOREIGN KEY (rapport_valide_par)
    REFERENCES utilisateur (id) ON DELETE SET NULL,
  ADD CONSTRAINT chk_demande_note CHECK (note IS NULL OR (note >= 0 AND note <= 20)),
  ADD UNIQUE KEY uq_demande_active_entreprise (etudiant_id, entreprise_active_id);

ALTER TABLE demande_stage
  DROP CHECK chk_demande_statut,
  DROP CHECK chk_demande_soumission,
  ADD CONSTRAINT chk_demande_statut
    CHECK (statut IN ('BROUILLON','SOUMISE','VALIDEE','REFUSEE','ANNULEE')),
  ADD CONSTRAINT chk_demande_soumission
    CHECK (statut IN ('BROUILLON','ANNULEE') OR date_soumission IS NOT NULL);

ALTER TABLE historique
  DROP CHECK chk_historique_statut_avant,
  DROP CHECK chk_historique_statut_apres,
  ADD CONSTRAINT chk_historique_statut_avant
    CHECK (statut_avant IS NULL OR statut_avant IN ('BROUILLON','SOUMISE','VALIDEE','REFUSEE','ANNULEE')),
  ADD CONSTRAINT chk_historique_statut_apres
    CHECK (statut_apres IN ('BROUILLON','SOUMISE','VALIDEE','REFUSEE','ANNULEE'));

-- ---------------------------------------------------------------------------
-- Pièces jointes : métadonnées seulement, fichiers hors du dossier public.
-- ---------------------------------------------------------------------------
CREATE TABLE piece_jointe (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id    INT UNSIGNED NOT NULL,
  type          ENUM('CONVENTION','LETTRE_ACCEPTATION','CV','RAPPORT','DOCUMENT_SOUTENANCE','AUTRE') NOT NULL,
  nom_original  VARCHAR(180) NOT NULL,
  chemin        VARCHAR(255) NOT NULL,
  mime          VARCHAR(100) NOT NULL,
  taille        INT UNSIGNED NOT NULL,
  sha256        CHAR(64) NOT NULL,
  statut        ENUM('EN_ATTENTE','VALIDE','REJETE') NOT NULL DEFAULT 'EN_ATTENTE',
  commentaire   VARCHAR(255) NULL,
  depose_par    INT UNSIGNED NOT NULL,
  depose_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_piece_demande (demande_id),
  INDEX ix_piece_type (type),
  CONSTRAINT fk_piece_demande FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT fk_piece_deposant FOREIGN KEY (depose_par)
    REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Commentaires de suivi.
-- ---------------------------------------------------------------------------
CREATE TABLE commentaire_demande (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id  INT UNSIGNED NOT NULL,
  auteur_id   INT UNSIGNED NOT NULL,
  type        VARCHAR(30) NOT NULL DEFAULT 'COMMENTAIRE',
  contenu     TEXT NOT NULL,
  cree_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_commentaire_demande (demande_id),
  CONSTRAINT fk_commentaire_demande FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE CASCADE,
  CONSTRAINT fk_commentaire_auteur FOREIGN KEY (auteur_id)
    REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Notifications internes et statut d'envoi e-mail.
-- ---------------------------------------------------------------------------
CREATE TABLE notification (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  demande_id    INT UNSIGNED NULL,
  type          VARCHAR(40) NOT NULL,
  titre         VARCHAR(160) NOT NULL,
  message       VARCHAR(500) NOT NULL,
  lue           TINYINT(1) NOT NULL DEFAULT 0,
  email_statut  ENUM('NON_ENVOYE','ENVOYE','ECHEC') NOT NULL DEFAULT 'NON_ENVOYE',
  email_erreur  VARCHAR(255) NULL,
  cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_notification_utilisateur (utilisateur_id, lue),
  CONSTRAINT fk_notification_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_demande FOREIGN KEY (demande_id)
    REFERENCES demande_stage (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Journal d'audit transversal.
-- ---------------------------------------------------------------------------
CREATE TABLE journal_audit (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NULL,
  action        VARCHAR(60) NOT NULL,
  entite        VARCHAR(40) NOT NULL,
  entite_id     INT UNSIGNED NULL,
  details       JSON NULL,
  adresse_ip    VARCHAR(45) NULL,
  agent         VARCHAR(255) NULL,
  cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_audit_utilisateur (utilisateur_id),
  INDEX ix_audit_action (action),
  INDEX ix_audit_date (cree_le),
  CONSTRAINT fk_audit_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Réinitialisation du mot de passe : seul le hachage du jeton est conservé.
-- ---------------------------------------------------------------------------
CREATE TABLE reinitialisation_mot_de_passe (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL,
  expire_le     DATETIME NOT NULL,
  utilise_le    DATETIME NULL,
  cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX ix_reinit_token (token_hash),
  CONSTRAINT fk_reinit_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Salles et jury de soutenance.
-- ---------------------------------------------------------------------------
CREATE TABLE salle (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(40) NOT NULL,
  batiment   VARCHAR(60) NULL,
  capacite   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT uq_salle_nom UNIQUE (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE soutenance
  ADD COLUMN salle_id INT UNSIGNED NULL AFTER salle,
  ADD COLUMN convoquee_le DATETIME NULL AFTER duree_min,
  ADD COLUMN convocation_envoyee TINYINT(1) NOT NULL DEFAULT 0 AFTER convoquee_le,
  ADD CONSTRAINT fk_soutenance_salle FOREIGN KEY (salle_id)
    REFERENCES salle (id) ON DELETE SET NULL;

CREATE TABLE soutenance_jury (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  soutenance_id INT UNSIGNED NOT NULL,
  utilisateur_id INT UNSIGNED NOT NULL,
  role_jury     ENUM('PRESIDENT','RAPPORTEUR','MEMBRE','ENCADREUR') NOT NULL DEFAULT 'MEMBRE',
  present       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT uq_jury_membre UNIQUE (soutenance_id, utilisateur_id),
  CONSTRAINT fk_jury_soutenance FOREIGN KEY (soutenance_id)
    REFERENCES soutenance (id) ON DELETE CASCADE,
  CONSTRAINT fk_jury_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO salle (nom, batiment, capacite) VALUES
  ('B12', 'Bâtiment B', 40),
  ('Amphi A', 'Bâtiment principal', 120),
  ('C03', 'Bâtiment C', 24);

UPDATE soutenance SET salle_id = (SELECT id FROM salle WHERE nom = 'B12')
WHERE salle = 'B12' AND salle_id IS NULL;

INSERT INTO soutenance_jury (soutenance_id, utilisateur_id, role_jury)
SELECT 1, 8, 'PRESIDENT'
WHERE EXISTS (SELECT 1 FROM soutenance WHERE id = 1)
  AND NOT EXISTS (SELECT 1 FROM soutenance_jury WHERE soutenance_id = 1 AND utilisateur_id = 8);

UPDATE encadreur SET charge_max = 6 WHERE id = 1;
UPDATE encadreur SET charge_max = 4 WHERE id = 2;

INSERT INTO entreprise (raison_sociale, ville, email_contact, secteur, statut)
VALUES
  ('Bénin Digital', 'Cotonou', 'contact@benindigital.test', 'Numérique', 'PARTENAIRE'),
  ('Atlantique Santé', 'Abomey-Calavi', 'stage@atlantiquesante.test', 'Santé', 'PARTENAIRE'),
  ('Atelier Kora', 'Porto-Novo', 'contact@atelierkora.test', 'Artisanat', 'A_VALIDER'),
  ('Savoir SARL', 'Parakou', 'contact@savoir.test', 'Éducation', 'PARTENAIRE'),
  ('Horizon Construct', 'Cotonou', 'contact@horizon.test', 'BTP', 'PARTENAIRE'),
  ('Union Bank', 'Porto-Novo', 'contact@unionbank.test', 'Finance', 'PARTENAIRE'),
  ('Terre et Mer', 'Ouidah', 'contact@terremer.test', 'Agriculture', 'PARTENAIRE'),
  ('Santé Plus', 'Cotonou', 'contact@santeplus.test', 'Santé', 'PARTENAIRE');
