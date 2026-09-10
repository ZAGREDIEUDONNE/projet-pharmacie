-- Dashboard Assistant : aligne les permissions réellement accordées avec les
-- permissions canoniques contrôlées par les routes cibles.
-- Idempotente et limitée aux capacités historiques du rôle Assistant.

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.nom IN (
    'vente.create', 'vente.view',
    'client.view', 'client.create', 'client.update',
    'caisse.view', 'stock.view'
)
LEFT JOIN role_permissions rp ON rp.role_id = r.id AND rp.permission_id = p.id
WHERE LOWER(r.nom) = 'assistant'
  AND rp.id IS NULL;
