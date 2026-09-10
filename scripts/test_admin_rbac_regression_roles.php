<?php
/**
 * Vérifie que les autres rôles n'ont pas été modifiés par la migration 034.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/RBACService.php';

$db = db();
$rbac = new App\Services\RBACService($db);

$checks = [
    ['role' => 'vendeur', 'id' => 2, 'allow' => ['vente.create', 'caisse.view', 'client.view'], 'deny' => ['user.manage', 'finance.clients', 'discount.manage_limits']],
    ['role' => 'assistant', 'id' => 3, 'allow' => ['make_sale', 'view_stock_movements'], 'deny' => ['user.manage', 'finance.clients']],
    ['role' => 'charge_commande', 'id' => 4, 'allow' => ['view_supplier_orders', 'receive_products'], 'deny' => ['user.manage', 'discount.manage_limits']],
    ['role' => 'comptable', 'id' => 6, 'allow' => ['comptabilite_view', 'ecritures_view'], 'deny' => ['user.manage', 'vente.create']],
];

$userQuery = $db->prepare('SELECT id FROM utilisateurs WHERE role_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1');

$fail = 0;
foreach ($checks as $c) {
    $userQuery->execute([$c['id']]);
    $uid = (int)$userQuery->fetchColumn();
    echo "=== {$c['role']} (user {$uid}) ===\n";
    if ($uid <= 0) {
        echo "SKIP: no active user\n";
        continue;
    }
    foreach ($c['allow'] as $p) {
        $ok = $rbac->hasPermission($uid, $p);
        echo ($ok ? 'OK' : 'FAIL') . " allow {$p}\n";
        if (!$ok) {
            $fail++;
        }
    }
    foreach ($c['deny'] as $p) {
        $ok = !$rbac->hasPermission($uid, $p);
        echo ($ok ? 'OK' : 'FAIL') . " deny {$p}\n";
        if (!$ok) {
            $fail++;
        }
    }
}

$permCounts = [];
foreach ([1, 2, 3, 4, 6] as $rid) {
    $permCounts[$rid] = (int)$db->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = {$rid}")->fetchColumn();
}
echo "\nCounts: admin={$permCounts[1]}, vendeur={$permCounts[2]}, assistant={$permCounts[3]}, charge={$permCounts[4]}, comptable={$permCounts[6]}\n";
exit($fail > 0 ? 1 : 0);
