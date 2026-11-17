-- Projet informatique 2 — L3 SIL · Application de gestion des stages
-- 003_verification.sql — contrôles après chargement du jeu d'essai
-- Exécution : mysql -u root -p stages < 003_verification.sql

SELECT 'utilisateur'   AS table_, COUNT(*) AS lignes FROM utilisateur
UNION ALL SELECT 'etudiant',      COUNT(*) FROM etudiant
UNION ALL SELECT 'encadreur',     COUNT(*) FROM encadreur
UNION ALL SELECT 'entreprise',    COUNT(*) FROM entreprise
UNION ALL SELECT 'demande_stage', COUNT(*) FROM demande_stage
UNION ALL SELECT 'piece_jointe',  COUNT(*) FROM piece_jointe
UNION ALL SELECT 'historique',    COUNT(*) FROM historique
UNION ALL SELECT 'soutenance',    COUNT(*) FROM soutenance;

SELECT statut, COUNT(*) AS demandes
FROM demande_stage
GROUP BY statut
ORDER BY FIELD(statut, 'BROUILLON', 'DEPOSEE', 'INCOMPLETE', 'VALIDEE', 'REFUSEE');

SELECT 'dossiers_incomplets' AS indicateur, COUNT(*) AS valeur FROM v_dossiers_incomplets
UNION ALL SELECT 'soutenances_planifiees', COUNT(*) FROM v_soutenances_planifiees;

SELECT h.id, h.demande_id, h.nouveau_statut, u.email AS auteur
FROM historique h
JOIN utilisateur u ON u.id = h.auteur_id
ORDER BY h.id;
