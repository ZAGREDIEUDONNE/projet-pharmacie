<?php
require_once __DIR__ . '/../config/database.php';
$db = db();

$perms = ['stock.flux', 'finance.clients', 'finance.suppliers', 'discount.manage_limits', 'caisse.view', 'client.view', 'client.create', 'stock.inventory'];
foreach ($perms as $p) {
    $stmt = $db->prepare('SELECT id FROM permissions WHERE nom = ?');
    $stmt->execute([$p]);
    $id = $stmt->fetchColumn();
    $has = 'no perm';
    if ($id) {
        $rp = $db->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = 1 AND permission_id = ?');
        $rp->execute([$id]);
        $has = (int)$rp->fetchColumn() > 0 ? 'admin YES' : 'admin NO';
    }
    echo "$p => perm_id=" . ($id ?: 'NULL') . " $has\n";
}

// Try manual insert for one permission
echo "\nTrying manual insert caisse.view for admin...\n";
$pid = $db->query("SELECT id FROM permissions WHERE nom = 'caisse.view'")->fetchColumn();
echo "caisse.view id = $pid\n";
if ($pid) {
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (1, $pid, NOW())");
    $check = $db->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = 1 AND permission_id = $pid")->fetchColumn();
    echo "After insert: $check\n";
}
