-- =============================================
-- PERMISSIONS ETENDUES : CHARGE DE COMMANDE
-- =============================================

INSERT IGNORE INTO permissions (nom, description, module) VALUES
('stock.update_product', 'Modifier un produit existant', 'stock'),
('stock.adjust', 'Corriger le stock avec justification', 'stock'),
('stock.view_expiry', 'Voir les produits proches de peremption', 'stock'),
('product.price.history', 'Consulter l historique des prix', 'stock'),
('view_supplier_orders', 'Consulter l historique des commandes fournisseurs', 'commandes'),
('edit_supplier_orders', 'Modifier une commande fournisseur avant validation', 'commandes'),
('send_supplier_orders', 'Envoyer une commande fournisseur', 'commandes');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'view_stock',
    'add_stock',
    'receive_products',
    'create_supplier_orders',
    'view_stock_movements',
    'stock.create_product',
    'product.price.update',
    'product.price.history',
    'stock.update_product',
    'stock.adjust',
    'stock.view_expiry',
    'view_supplier_orders',
    'edit_supplier_orders',
    'send_supplier_orders',
    'stock.view',
    'stock.create',
    'commande.view',
    'commande.create'
)
WHERE LOWER(r.nom) IN ('commande', 'charge_commande', 'charge de commande');
