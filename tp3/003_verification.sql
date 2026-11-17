-- Projet informatique 2 — L3 SIL · Gestion des stages universitaires
-- 003_verification.sql — contrôles après chargement de 001 et 002

SET NAMES utf8mb4;

-- 1. Volumétrie des huit tables
SELECT 'utilisateur'   AS element, COUNT(*) AS nb FROM utilisateur
UNION ALL SELECT 'etudiant',        COUNT(*) FROM etudiant
UNION ALL SELECT 'encadreur',       COUNT(*) FROM encadreur
UNION ALL SELECT 'entreprise',      COUNT(*) FROM entreprise
UNION ALL SELECT 'demande_stage',   COUNT(*) FROM demande_stage
UNION ALL SELECT 'piece_jointe',    COUNT(*) FROM piece_jointe
UNION ALL SELECT 'historique',      COUNT(*) FROM historique
UNION ALL SELECT 'soutenance',      COUNT(*) FROM soutenance
ORDER BY element;

-- 2. Répartition des dossiers par statut
SELECT statut AS statut_dossier, COUNT(*) AS nb
FROM demande_stage
GROUP BY statut
ORDER BY statut;

-- 3. Indicateurs calculés par les vues
SELECT 'dossiers_a_completer' AS indicateur, COUNT(*) AS nb FROM v_dossiers_a_completer
UNION ALL
SELECT 'soutenances_prevues', COUNT(*) FROM v_planning_soutenances;

-- 4. Détail des dossiers incomplets
SELECT dossier_id, etudiant, motif, date_depot
FROM v_dossiers_a_completer
ORDER BY dossier_id;

-- 5. Suivi des transitions enregistrées
SELECT h.demande_id, h.statut_avant, h.statut_apres,
       u.nom AS auteur, DATE_FORMAT(h.horodatage, '%Y-%m-%d %H:%i') AS horodatage
FROM historique h
JOIN utilisateur u ON u.id = h.auteur_id
ORDER BY h.horodatage;

-- 6. Planning des soutenances
SELECT date_heure, salle, duree_min AS minutes, etudiant, entreprise
FROM v_planning_soutenances
ORDER BY date_heure;
