-- =============================================
-- MIGRATION: Ajout des colonnes manquantes
-- Version: 025
-- Date: 2026-08-14
-- Description: Ajout des colonnes comptables et de traçabilité
--              utilisées par le code mais absentes de la base
-- =============================================

-- IMPORTANT: Cette migration est NON DESTRUCTIVE
-- Elle ajoute uniquement des colonnes, ne supprime rien

-- =============================================
-- TABLE: commandes
-- =============================================

-- Ajout de montant_ht (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'commandes' 
                   AND COLUMN_NAME = 'montant_ht');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE commandes ADD COLUMN montant_ht DECIMAL(10,2) DEFAULT 0.00 AFTER date_livraison_reelle',
    'SELECT "Column commandes.montant_ht already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de montant_tva (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'commandes' 
                   AND COLUMN_NAME = 'montant_tva');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE commandes ADD COLUMN montant_tva DECIMAL(10,2) DEFAULT 0.00 AFTER montant_ht',
    'SELECT "Column commandes.montant_tva already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- TABLE: mouvements_caisse
-- =============================================

-- Ajout de vente_id (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND COLUMN_NAME = 'vente_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE mouvements_caisse ADD COLUMN vente_id INT NULL AFTER date_mouvement',
    'SELECT "Column mouvements_caisse.vente_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de l'index sur vente_id (si n'existe pas déjà)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND INDEX_NAME = 'idx_mouvements_caisse_vente_id');

SET @sql = IF(@idx_exists = 0, 
    'CREATE INDEX idx_mouvements_caisse_vente_id ON mouvements_caisse(vente_id)',
    'SELECT "Index idx_mouvements_caisse_vente_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de client_id (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND COLUMN_NAME = 'client_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE mouvements_caisse ADD COLUMN client_id INT NULL AFTER vente_id',
    'SELECT "Column mouvements_caisse.client_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de l'index sur client_id (si n'existe pas déjà)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND INDEX_NAME = 'idx_mouvements_caisse_client_id');

SET @sql = IF(@idx_exists = 0, 
    'CREATE INDEX idx_mouvements_caisse_client_id ON mouvements_caisse(client_id)',
    'SELECT "Index idx_mouvements_caisse_client_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de fournisseur_id (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND COLUMN_NAME = 'fournisseur_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE mouvements_caisse ADD COLUMN fournisseur_id INT NULL AFTER client_id',
    'SELECT "Column mouvements_caisse.fournisseur_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de l'index sur fournisseur_id (si n'existe pas déjà)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND INDEX_NAME = 'idx_mouvements_caisse_fournisseur_id');

SET @sql = IF(@idx_exists = 0, 
    'CREATE INDEX idx_mouvements_caisse_fournisseur_id ON mouvements_caisse(fournisseur_id)',
    'SELECT "Index idx_mouvements_caisse_fournisseur_id already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de type_depense (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'mouvements_caisse' 
                   AND COLUMN_NAME = 'type_depense');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE mouvements_caisse ADD COLUMN type_depense VARCHAR(50) NULL AFTER fournisseur_id',
    'SELECT "Column mouvements_caisse.type_depense already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- TABLE: stock
-- =============================================

-- Ajout de date_peremption (si n'existe pas déjà)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'stock' 
                   AND COLUMN_NAME = 'date_peremption');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE stock ADD COLUMN date_peremption DATE NULL AFTER valeur_stock',
    'SELECT "Column stock.date_peremption already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de l'index sur date_peremption (si n'existe pas déjà)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = 'medecin' 
                   AND TABLE_NAME = 'stock' 
                   AND INDEX_NAME = 'idx_stock_date_peremption');

SET @sql = IF(@idx_exists = 0, 
    'CREATE INDEX idx_stock_date_peremption ON stock(date_peremption)',
    'SELECT "Index idx_stock_date_peremption already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- NOTE: Les clés étrangères ne sont pas ajoutées automatiquement
-- pour éviter les violations de contraintes sur les données existantes.
-- Elles pourront être ajoutées manuellement après vérification des données.
-- =============================================

-- Rapport de la migration
SELECT 'Migration 025 completed successfully' AS status;
