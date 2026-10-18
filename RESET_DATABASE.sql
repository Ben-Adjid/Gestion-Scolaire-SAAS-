-- ============================================================
-- RÉINITIALISER LA BASE DE DONNÉES COMPLÈTEMENT
-- Copier-coller TOUT ceci dans MySQL Workbench et exécuter
-- ============================================================

-- 1. Supprimer la BD existante
DROP DATABASE IF EXISTS gestion_scolaire;

-- 2. Créer la nouvelle BD
CREATE DATABASE gestion_scolaire
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 3. Sélectionner la BD
USE gestion_scolaire;

-- 4. Vérifier que c'est vide
SHOW TABLES;
-- Doit être vide

-- ✅ DONE! La BD est prête pour les migrations Laravel
