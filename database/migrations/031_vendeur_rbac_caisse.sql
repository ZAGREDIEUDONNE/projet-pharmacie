-- Explicit least-privilege RBAC for the vendor dashboard. No schema change.
INSERT IGNORE INTO permissions (nom, description, module) VALUES
('client.view', 'Consulter les clients', 'clients'),
('client.create', 'Créer un client', 'clients'),
('client.update', 'Modifier un client', 'clients'),
('caisse.view', 'Consulter sa caisse', 'caisse'),
('caisse.open', 'Ouvrir sa session de caisse', 'caisse');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
  'vente.view', 'vente.create', 'client.view', 'client.create', 'client.update',
  'stock.view', 'caisse.view', 'caisse.open'
)
WHERE LOWER(r.nom) = 'vendeur'
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
