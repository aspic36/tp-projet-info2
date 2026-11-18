-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 003_verification.sql — preuve du jeu d'essai : comptages attendus
-- Attendu : 3 étudiants, 2 entreprises, 4 demandes, 1 soutenance, 5 événements.

SELECT 'etudiant'       AS table_name, COUNT(*) AS nb_lignes FROM etudiant
UNION ALL
SELECT 'entreprise',      COUNT(*) FROM entreprise
UNION ALL
SELECT 'demande_stage',   COUNT(*) FROM demande_stage
UNION ALL
SELECT 'soutenance',      COUNT(*) FROM soutenance
UNION ALL
SELECT 'historique',      COUNT(*) FROM historique
UNION ALL
SELECT 'utilisateur',     COUNT(*) FROM utilisateur;

-- Une demande par statut : la répartition doit montrer les quatre états.
SELECT statut, COUNT(*) AS nb_demandes
FROM demande_stage
GROUP BY statut
ORDER BY FIELD(statut, 'BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE');

-- File d'attente du responsable (vue v_demandes_a_traiter).
SELECT * FROM v_demandes_a_traiter;

-- Planning des soutenances (vue v_planning_soutenances).
SELECT * FROM v_planning_soutenances;
