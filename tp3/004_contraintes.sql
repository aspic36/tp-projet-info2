-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 004_contraintes.sql — preuve que la base refuse les états incohérents.
-- Chaque bloc doit ÉCHOUER : c'est le résultat attendu.

-- 1) Statut hors du cycle de vie → violation de chk_demande_statut.
--    (la date est fournie pour ne pas déclencher d'abord chk_demande_soumission)
INSERT INTO demande_stage (etudiant_id, entreprise_id, statut, date_soumission)
VALUES (1, 1, 'ARCHIVEE', NOW());

-- 2) Refus sans motif → violation de chk_demande_motif.
INSERT INTO demande_stage (etudiant_id, entreprise_id, statut, date_soumission, date_decision)
VALUES (1, 1, 'REFUSEE', NOW(), NOW());

-- 3) Deuxième soutenance pour la même demande → violation de uq_soutenance_demande.
INSERT INTO soutenance (demande_id, date_heure, salle)
VALUES (2, '2026-12-11 14:00:00', 'B12');

-- 4) Deux soutenances au même créneau et dans la même salle
--    → violation de uq_soutenance_creneau.
INSERT INTO soutenance (demande_id, date_heure, salle)
VALUES (1, '2026-12-10 09:00:00', 'B12');

-- 5) Référence vers une entreprise inexistante → violation de fk_demande_entreprise.
INSERT INTO demande_stage (etudiant_id, entreprise_id, statut)
VALUES (1, 999, 'BROUILLON');

-- Contrôle final : les comptages n'ont pas bougé (tout a été rejeté).
SELECT 'demande_stage' AS table_name, COUNT(*) AS nb_lignes FROM demande_stage
UNION ALL
SELECT 'soutenance', COUNT(*) FROM soutenance;
