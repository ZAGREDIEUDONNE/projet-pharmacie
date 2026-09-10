-- =============================================
-- MIGRATION: Ajout permission export_supplier_orders
-- Version: 027
-- Date: 2026-08-14
-- Description: Ajout de la permission export_supplier_orders
--              pour le rôle charge_commande
-- =============================================

-- Vérifier si la permission existe déjà
SET @perm_exists = (SELECT COUNT(*) FROM permissions 
                   WHERE nom = 'export_supplier_orders');

SET @sql = IF(@perm_exists = 0, 
    'INSERT INTO permissions (nom, description, module, created_at) 
     VALUES ("export_supplier_orders", "Exporter les commandes fournisseurs", "commandes", NOW())',
    'SELECT "Permission export_supplier_orders already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Récupérer l'ID de la permission
SET @perm_id = (SELECT id FROM permissions WHERE nom = 'export_supplier_orders');

-- Récupérer l'ID du rôle charge_commande
SET @role_id = (SELECT id FROM roles WHERE nom = 'charge_commande');

-- Associer la permission au rôle (si l'association n'existe pas déjà)
SET @assoc_exists = (SELECT COUNT(*) FROM role_permissions 
                     WHERE role_id = @role_id AND permission_id = @perm_id);

SET @sql = IF(@assoc_exists = 0 AND @perm_id IS NOT NULL AND @role_id IS NOT NULL,
    'INSERT INTO role_permissions (role_id, permission_id, created_at) 
     VALUES (@role_id, @perm_id, NOW())',
    'SELECT "Association already exists or permission/role not found" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rapport de la migration
SELECT 'Migration 027 completed successfully' AS status;
