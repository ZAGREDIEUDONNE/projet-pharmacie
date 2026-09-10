-- Autorise le rôle Chargé de commande à enregistrer les sorties de stock.
-- Le contrôleur utilise déjà ce nom de permission, mais certaines bases anciennes
-- ne possèdent pas encore sa ligne de catalogue.
INSERT IGNORE INTO permissions (nom, description, module)
VALUES ('sortie_stock', 'Enregistrer les sorties de stock justifiées', 'stock');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom = 'sortie_stock'
WHERE LOWER(r.nom) IN ('commande', 'charge_commande', 'charge de commande');
