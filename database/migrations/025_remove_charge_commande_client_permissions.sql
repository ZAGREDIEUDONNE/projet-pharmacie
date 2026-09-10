-- Le chargé de commande ne traite ni le suivi ni les relevés clients.
-- Les permissions commande, réception et stock ne sont pas modifiées.
DELETE rp
FROM role_permissions rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE r.code = 'CHARGE_COMMANDE'
  AND p.nom IN ('suivi_client.view', 'suivi_client.solde', 'suivi_client.releve');
