-- Migration pour synchroniser les permissions du rôle CHARGE_COMMANDE
-- Basé sur ChargeCommandePolicy.php

-- Récupérer l'ID du rôle CHARGE_COMMANDE
SET @role_id = (SELECT id FROM roles WHERE nom = 'CHARGE_COMMANDE' LIMIT 1);

-- Permissions autorisées pour CHARGE_COMMANDE
INSERT IGNORE INTO permissions (nom, description, module, created_at) VALUES
('view_stock', 'Voir le stock', 'stock', NOW()),
('add_stock', 'Ajouter du stock', 'stock', NOW()),
('receive_products', 'Réceptionner les produits', 'stock', NOW()),
('create_supplier_orders', 'Créer des commandes fournisseurs', 'commande', NOW()),
('view_stock_movements', 'Voir les mouvements de stock', 'stock', NOW()),
('sortie_stock', 'Sortie de stock', 'stock', NOW()),
('stock.create_product', 'Créer un produit', 'stock', NOW()),
('product.price.update', 'Mettre à jour les prix', 'produit', NOW()),
('product.price.history', 'Voir l''historique des prix', 'produit', NOW()),
('stock.update_product', 'Mettre à jour un produit', 'stock', NOW()),
('stock.adjust', 'Ajuster le stock', 'stock', NOW()),
('stock.view_expiry', 'Voir les péremptions', 'stock', NOW()),
('stock.inventory', 'Gérer l''inventaire', 'stock', NOW()),
('view_supplier_orders', 'Voir les commandes fournisseurs', 'commande', NOW()),
('edit_supplier_orders', 'Modifier les commandes fournisseurs', 'commande', NOW()),
('send_supplier_orders', 'Envoyer les commandes fournisseurs', 'commande', NOW()),
('stock.view', 'Voir le stock', 'stock', NOW()),
('stock.create', 'Créer du stock', 'stock', NOW()),
('commande.view', 'Voir les commandes', 'commande', NOW()),
('commande.create', 'Créer des commandes', 'commande', NOW());

-- Associer les permissions au rôle CHARGE_COMMANDE
INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT @role_id, p.id, NOW()
FROM permissions p
WHERE p.nom IN (
    'view_stock',
    'add_stock',
    'receive_products',
    'create_supplier_orders',
    'view_stock_movements',
    'sortie_stock',
    'stock.create_product',
    'product.price.update',
    'product.price.history',
    'stock.update_product',
    'stock.adjust',
    'stock.view_expiry',
    'stock.inventory',
    'view_supplier_orders',
    'edit_supplier_orders',
    'send_supplier_orders',
    'stock.view',
    'stock.create',
    'commande.view',
    'commande.create'
)
AND @role_id IS NOT NULL;

-- Supprimer les permissions non autorisées pour CHARGE_COMMANDE
DELETE rp FROM role_permissions rp
JOIN permissions p ON rp.permission_id = p.id
WHERE rp.role_id = @role_id
AND p.nom IN (
    'vente.view',
    'vente.create',
    'vente.cancel',
    'vente.print',
    'vente.history',
    'make_sale',
    'cancel_ticket',
    'ticket_annuler',
    'apply_discount',
    'remise.apply',
    'remise_apply',
    'finance.view',
    'benefice.view',
    'user.manage',
    'users_manage',
    'caisse.close',
    'caisse_arret',
    'close_cash_register',
    'client.view',
    'client.create',
    'client.update',
    'client.manage',
    'create_client',
    'edit_client',
    'system.config',
    'audit_view'
)
AND @role_id IS NOT NULL;
