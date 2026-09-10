<?php
require_once __DIR__ . '/../config/database.php';
$db = db();
foreach ([1, 2, 3, 4, 5, 6] as $rid) {
    $stmt = $db->prepare('SELECT id, nom, is_actif, statut FROM roles WHERE id = ?');
    $stmt->execute([$rid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $c = (int)$db->query('SELECT COUNT(*) FROM role_permissions WHERE role_id = ' . (int)$rid)->fetchColumn();
    echo 'Role ' . $rid . ': ' . json_encode($r) . ' perms=' . $c . PHP_EOL;
}
$adminPerms = $db->query("SELECT p.nom FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = 1 ORDER BY p.nom")->fetchAll(PDO::FETCH_COLUMN);
echo 'ADMIN permissions sample: ' . implode(', ', array_slice($adminPerms, 0, 20)) . '... total=' . count($adminPerms) . PHP_EOL;
$needed = ['reports.view','user.manage','audit.view','vente.cancel','settings.manage','cancel_ticket'];
foreach ($needed as $perm) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = 1 AND p.nom = ?");
    $stmt->execute([$perm]);
    echo ($stmt->fetchColumn() ? 'OK' : 'MISSING') . ': admin.' . $perm . PHP_EOL;
}
