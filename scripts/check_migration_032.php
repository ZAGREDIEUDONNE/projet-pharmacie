<?php
require_once __DIR__ . '/../config/database.php';
$db = db();

$refType = $db->query("SHOW COLUMNS FROM mouvements_stock LIKE 'reference_type'")->fetch(PDO::FETCH_ASSOC);
$origine = $db->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'ecriture_origine_id'")->fetch(PDO::FETCH_ASSOC);
$role = $db->query('SELECT id, nom, is_actif, statut FROM roles WHERE id = 6')->fetch(PDO::FETCH_ASSOC);
$perms = (int)$db->query('SELECT COUNT(*) FROM role_permissions WHERE role_id = 6')->fetchColumn();
$cancelTicket = (int)$db->query("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = 1 AND p.nom = 'cancel_ticket'")->fetchColumn();

echo "reference_type: " . ($refType['Type'] ?? 'MISSING') . PHP_EOL;
echo "ecriture_origine_id: " . ($origine ? 'YES' : 'NO') . PHP_EOL;
echo "COMPTABLE role: " . json_encode($role) . PHP_EOL;
echo "COMPTABLE permissions: $perms" . PHP_EOL;
echo "ADMIN cancel_ticket: $cancelTicket" . PHP_EOL;
