<?php
// Script de diagnostic du flux de permissions
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "=== DIAGNOSTIC FLUX DE PERMISSIONS ===\n\n";

// Simuler le flux de requirePermission()
$permission = 'view_stock_movements';

echo "Permission demandée: '$permission'\n\n";

// 1. Vérifier si connecté
echo "1. VÉRIFICATION CONNEXION:\n";
echo "----------------------------------------\n";
if (!isset($_SESSION['user'])) {
    echo "✗ Utilisateur non connecté\n";
    echo "→ Redirection vers /login\n";
    exit;
}
echo "✓ Utilisateur connecté\n\n";

$user = $_SESSION['user'];
$userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
$roleId = (int)($user['role_id'] ?? 0);
$roleCode = $user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '';

echo "User ID: $userId\n";
echo "Role ID: $roleId\n";
echo "Role Code brut: '$roleCode'\n\n";

// 2. Normaliser le rôle
echo "2. NORMALISATION DU RÔLE:\n";
echo "----------------------------------------\n";
require __DIR__ . '/../app/Services/RoleCatalog.php';
$normalizedRole = \App\Services\RoleCatalog::normalizeCode($roleCode);
echo "Role Code normalisé: '$normalizedRole'\n\n";

// 3. Vérifier si admin
echo "3. VÉRIFICATION ADMIN:\n";
echo "----------------------------------------\n";
$isAdmin = \App\Services\RoleCatalog::isAdmin($roleId, $roleCode);
echo "Est admin: " . ($isAdmin ? 'OUI' : 'NON') . "\n";
if ($isAdmin) {
    echo "→ AUTORISÉ (admin)\n";
    exit;
}
echo "\n";

// 4. Vérifier hasDefaultRolePermission
echo "4. VÉRIFICATION DEFAULT ROLE PERMISSION:\n";
echo "----------------------------------------\n";
$salesPermissions = ['vente.view', 'vente.create', 'make_sale'];
$clientPermissions = ['client.view', 'client.create', 'client.update', 'create_client', 'edit_client'];
$stockPermissions = ['stock.view', 'stock.report', 'stock.manage', 'stock.create', 'stock.update', 'view_stock_movements'];
$chargeCommandePermissions = [
    'view_stock', 'add_stock', 'receive_products', 'create_supplier_orders', 'view_stock_movements',
    'stock.create_product', 'product.price.update', 'product.price.history', 'stock.update_product',
    'stock.adjust', 'stock.view_expiry', 'view_supplier_orders', 'edit_supplier_orders', 'send_supplier_orders',
    'stock.view', 'stock.create', 'commande.view', 'commande.create',
];

$permissionsByCode = [
    'VENDEUR' => array_merge($salesPermissions, ['client.view', 'client.create', 'create_client', 'stock.view']),
    'ASSISTANT' => array_merge($salesPermissions, $clientPermissions, $stockPermissions, ['cancel_ticket', 'apply_discount', 'close_cash_register', 'view_statistics', 'prepare_orders', 'ticket_annuler', 'remise_apply', 'caisse_arret', 'statistiques_view', 'commande_preparer', 'stock_consulter']),
    'COMMANDE' => $chargeCommandePermissions,
    'CHARGE_COMMANDE' => $chargeCommandePermissions,
    'CHARGE_DE_COMMANDE' => $chargeCommandePermissions,
];

$hasDefault = false;
if ($normalizedRole !== '') {
    $hasDefault = in_array($permission, $permissionsByCode[$normalizedRole] ?? [], true);
    echo "Role Code: '$normalizedRole'\n";
    echo "Permissions par défaut: " . count($permissionsByCode[$normalizedRole] ?? []) . "\n";
    echo "Permission '$permission' dans la liste: " . ($hasDefault ? 'OUI' : 'NON') . "\n";
}
if ($hasDefault) {
    echo "→ AUTORISÉ (default permission)\n";
    exit;
}
echo "\n";

// 5. Vérifier si CHARGE_COMMANDE
echo "5. VÉRIFICATION CHARGE_COMMANDE:\n";
echo "----------------------------------------\n";
$isChargeCommande = \App\Services\RoleCatalog::isCommande($roleId, $roleCode);
echo "Est CHARGE_COMMANDE: " . ($isChargeCommande ? 'OUI' : 'NON') . "\n";
if (!$isChargeCommande) {
    echo "→ Pas CHARGE_COMMANDE, passage au RBAC\n";
} else {
    echo "→ Est CHARGE_COMMANDE, vérification via ChargeCommandePolicy\n\n";
    
    // 6. ChargeCommandePolicy
    echo "6. CHARGECOMMANDEPOLICY:\n";
    echo "----------------------------------------\n";
    require __DIR__ . '/../app/Services/ChargeCommandePolicy.php';
    $policy = new \App\Services\ChargeCommandePolicy();
    $policyCan = $policy->can($permission);
    echo "Policy can('$permission'): " . ($policyCan ? 'OUI' : 'NON') . "\n";
    
    if ($policyCan) {
        echo "→ AUTORISÉ (ChargeCommandePolicy)\n";
        exit;
    } else {
        echo "→ REFUSÉ (ChargeCommandePolicy)\n";
        echo "→ Affichage: 'Acces refuse'\n";
        echo "→ Exit\n";
        exit;
    }
}
echo "\n";

// 7. RBACService
echo "7. RBACSERVICE:\n";
echo "----------------------------------------\n";
require __DIR__ . '/../app/Services/RBACService.php';
try {
    $rbacService = new \App\Services\RBACService($pdo);
    $hasPermission = $rbacService->hasPermission($userId, $permission);
    echo "RBACService hasPermission('$permission'): " . ($hasPermission ? 'OUI' : 'NON') . "\n";
    
    if ($hasPermission) {
        echo "→ AUTORISÉ (RBACService)\n";
        exit;
    } else {
        echo "→ REFUSÉ (RBACService)\n";
    }
} catch (Exception $e) {
    echo "Erreur RBACService: " . $e->getMessage() . "\n";
}
echo "\n";

// 8. Refus final
echo "8. REFUS FINAL:\n";
echo "----------------------------------------\n";
echo "→ Affichage: 'Acces refuse'\n";
echo "→ Exit\n";

echo "\n=== FIN DU DIAGNOSTIC ===\n";
