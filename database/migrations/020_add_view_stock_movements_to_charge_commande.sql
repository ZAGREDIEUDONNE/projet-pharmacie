-- =============================================
-- AJOUT PERMISSION VIEW_STOCK_MOVEMENTS AU RÔLE CHARGE_COMMANDE
-- =============================================

-- Créer la permission view_stock_movements si elle n'existe pas
INSERT IGNORE INTO permissions (nom, description, module) VALUES
('view_stock_movements', 'Consulter les mouvements de stock (entrées, sorties, ajustements)', 'stock');

-- Attribuer cette permission au rôle CHARGE_COMMANDE
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom = 'view_stock_movements'
WHERE LOWER(r.nom) IN ('charge_commande', 'charge de commande');
