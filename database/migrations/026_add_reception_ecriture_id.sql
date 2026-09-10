-- =============================================
-- MIGRATION: Ajout ecriture_id à receptions
-- Version: 026
-- Date: 2026-08-14
-- Description: Ajout de la colonne ecriture_id pour lier
--              les réceptions aux écritures comptables
-- =============================================

-- Ajout de ecriture_id dans receptions (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'receptions' 
                   AND COLUMN_NAME = 'ecriture_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE receptions ADD COLUMN ecriture_id INT NULL AFTER ecart_detecte',
    'SELECT "Column receptions.ecriture_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de l'index sur ecriture_id (si n'existe pas déjà)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'receptions' 
                   AND INDEX_NAME = 'idx_receptions_ecriture_id');

SET @sql = IF(@idx_exists = 0, 
    'CREATE INDEX idx_receptions_ecriture_id ON receptions(ecriture_id)',
    'SELECT "Index idx_receptions_ecriture_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rapport de la migration
SELECT 'Migration 026 completed successfully' AS status;
