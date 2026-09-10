<?php
/**
 * Applique la migration 034 (permissions admin dashboard).
 */
require_once __DIR__ . '/../config/database.php';

$db = db();

$newPermissions = [
    ['stock.flux', 'Consulter les flux détaillés de stock', 'stock', 'view'],
    ['finance.clients', 'Consulter les soldes et règlements clients', 'finance', 'view'],
    ['finance.suppliers', 'Consulter les soldes et règlements fournisseurs', 'finance', 'view'],
    ['discount.manage_limits', 'Gérer les plafonds de remise par rôle', 'vente', 'manage'],
];

$assignToAdmin = [
    'caisse.view',
    'client.view',
    'client.create',
    'stock.inventory',
    'stock.flux',
    'finance.clients',
    'finance.suppliers',
    'discount.manage_limits',
];

$db->beginTransaction();
try {
    $insertPerm = $db->prepare(
        'INSERT INTO permissions (nom, code, description, module, action, statut, date_creation, created_at)
         SELECT ?, ?, ?, ?, ?, 1, NOW(), NOW()
         FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.nom = ?)'
    );

    foreach ($newPermissions as [$nom, $desc, $module, $action]) {
        $insertPerm->execute([$nom, $nom, $desc, $module, $action, $nom]);
    }

    $assign = $db->prepare(
        'INSERT INTO role_permissions (role_id, permission_id, created_at)
         SELECT 1, p.id, NOW()
         FROM permissions p
         LEFT JOIN role_permissions rp ON rp.role_id = 1 AND rp.permission_id = p.id
         WHERE p.nom = ? AND rp.id IS NULL'
    );

    foreach ($assignToAdmin as $nom) {
        $assign->execute([$nom]);
    }

    $db->commit();
    $count = (int)$db->query('SELECT COUNT(*) FROM role_permissions WHERE role_id = 1')->fetchColumn();
    echo "Migration 034 appliquée. Permissions admin: {$count}\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Erreur: ' . $e->getMessage() . "\n");
    exit(1);
}
