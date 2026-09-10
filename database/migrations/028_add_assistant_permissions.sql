-- =============================================
-- MIGRATION: Ajout permissions Assistant
-- Version: 028
-- Date: 2026-08-15
-- Description: Ajout des permissions manquantes pour le rôle assistant
-- =============================================

-- Permissions à créer
SET @permissions = [
    'create_client',
    'edit_client',
    'make_sale',
    'cancel_ticket',
    'apply_discount',
    'close_cash_register',
    'view_statistics',
    'view_stock_movements',
    'prepare_orders'
];

-- Récupérer l'ID du rôle assistant
SET @role_id = (SELECT id FROM roles WHERE nom = 'assistant');

-- Insérer les permissions manquantes et les associer au rôle
INSERT INTO permissions (nom, description, module, created_at) VALUES
    ('create_client', 'Créer un client', 'clients', NOW()),
    ('edit_client', 'Modifier un client', 'clients', NOW()),
    ('make_sale', 'Effectuer une vente', 'vente', NOW()),
    ('cancel_ticket', 'Annuler un ticket', 'vente', NOW()),
    ('apply_discount', 'Appliquer une remise', 'vente', NOW()),
    ('close_cash_register', 'Fermer la caisse', 'caisse', NOW()),
    ('view_statistics', 'Voir les statistiques', 'statistiques', NOW()),
    ('view_stock_movements', 'Voir les mouvements de stock', 'stock', NOW()),
    ('prepare_orders', 'Préparer les commandes', 'commandes', NOW())
ON DUPLICATE KEY UPDATE nom = nom;

-- Associer les permissions au rôle assistant
INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT @role_id, id, NOW()
FROM permissions
WHERE nom IN ('create_client', 'edit_client', 'make_sale', 'cancel_ticket', 'apply_discount', 
              'close_cash_register', 'view_statistics', 'view_stock_movements', 'prepare_orders')
AND id NOT IN (SELECT permission_id FROM role_permissions WHERE role_id = @role_id);

-- Rapport de la migration
SELECT 'Migration 028 completed successfully' AS status;
SELECT COUNT(*) as permissions_added FROM permissions WHERE nom IN ('create_client', 'edit_client', 'make_sale', 'cancel_ticket', 'apply_discount', 'close_cash_register', 'view_statistics', 'view_stock_movements', 'prepare_orders');
SELECT COUNT(*) as role_permissions_added FROM role_permissions WHERE role_id = @role_id;
