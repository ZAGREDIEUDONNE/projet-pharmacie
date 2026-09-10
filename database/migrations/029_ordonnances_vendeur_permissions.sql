-- Permissions ciblées pour le parcours Ordonnances du vendeur.
INSERT IGNORE INTO permissions (nom, description, module) VALUES
('ordonnance.view', 'Consulter les ordonnances', 'ordonnances'),
('ordonnance.process', 'Préparer une vente à partir d une ordonnance', 'ordonnances');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN ('ordonnance.view', 'ordonnance.process')
WHERE LOWER(r.nom) = 'vendeur'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
