-- Phase audit accès Dashboard Administrateur : permissions manquantes pour le rôle ADMINISTRATEUR.
-- Idempotent : n'ajoute que les permissions absentes et ne modifie pas les autres rôles.

-- Permissions métier absentes de la table (migration 014 non appliquée ou écrasée)
INSERT INTO permissions (nom, code, description, module, action, statut, date_creation, created_at)
SELECT x.nom, x.nom, x.description, x.module, x.action, 1, NOW(), NOW()
FROM (
    SELECT 'stock.flux' AS nom, 'Consulter les flux détaillés de stock' AS description, 'stock' AS module, 'view' AS action UNION ALL
    SELECT 'finance.clients', 'Consulter les soldes et règlements clients', 'finance', 'view' UNION ALL
    SELECT 'finance.suppliers', 'Consulter les soldes et règlements fournisseurs', 'finance', 'view' UNION ALL
    SELECT 'discount.manage_limits', 'Gérer les plafonds de remise par rôle', 'vente', 'manage'
) x
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.nom = x.nom);

-- Permissions existantes mais non attribuées à l'administrateur (role_id = 1)
INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT 1, p.id, NOW()
FROM permissions p
LEFT JOIN role_permissions rp ON rp.role_id = 1 AND rp.permission_id = p.id
WHERE p.nom IN (
    'caisse.view',
    'client.view',
    'client.create',
    'stock.inventory',
    'stock.flux',
    'finance.clients',
    'finance.suppliers',
    'discount.manage_limits'
)
AND rp.id IS NULL;
