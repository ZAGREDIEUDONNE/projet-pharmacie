<?php
// Diagnostic complet du flux de permissions pour les mouvements de stock
// Ce script simule exactement le flux du BaseController

session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "=== DIAGNOSTIC COMPLET - ACCÈS MOUVEMENTS STOCK ===\n\n";

$permission = 'view_stock_movements';
echo "Permission demandée: '$permission'\n\n";

// === ÉTAPE 1: Vérification connexion ===
echo "ÉTAPE 1: Vérification connexion\n";
echo str_repeat("-", 50) . "\n";
if (!isset($_SESSION['user'])) {
    echo "✗ ERREUR: Utilisateur non connecté\n";
    echo "→ Redirection vers /login\n";
    echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
    exit;
}
echo "✓ Utilisateur connecté\n\n";

$user = $_SESSION['user'];
$userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
$roleId = (int)($user['role_id'] ?? 0);
$roleCode = $user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '';

echo "Informations utilisateur:\n";
echo "  User ID: $userId\n";
echo "  Role ID: $roleId\n";
echo "  Role Code brut: '$roleCode'\n";
echo "  Role Name: " . ($user['role_name'] ?? 'N/A') . "\n\n";

// === ÉTAPE 2: Normalisation du rôle ===
echo "ÉTAPE 2: Normalisation du rôle\n";
echo str_repeat("-", 50) . "\n";
require __DIR__ . '/../app/Services/RoleCatalog.php';

$normalizedRole = \App\Services\RoleCatalog::normalizeCode($roleCode);
echo "Role Code normalisé: '$normalizedRole'\n\n";

// === ÉTAPE 3: Vérification Admin ===
echo "ÉTAPE 3: Vérification Admin\n";
echo str_repeat("-", 50) . "\n";
$isAdmin = \App\Services\RoleCatalog::isAdmin($roleId, $roleCode);
echo "Est Admin: " . ($isAdmin ? 'OUI' : 'NON') . "\n";
if ($isAdmin) {
    echo "→ RÉSULTAT: AUTORISÉ (Admin)\n";
    echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
    exit;
}
echo "→ Pas Admin, continue...\n\n";

// === ÉTAPE 4: hasDefaultRolePermission ===
echo "ÉTAPE 4: hasDefaultRolePermission\n";
echo str_repeat("-", 50) . "\n";

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

$permissionsByRole = [
    \App\Services\RoleCatalog::VENDEUR_ID => array_merge($salesPermissions, ['client.view', 'client.create', 'create_client', 'stock.view']),
    \App\Services\RoleCatalog::ASSISTANT_ID => array_merge($salesPermissions, $clientPermissions, $stockPermissions, ['cancel_ticket', 'apply_discount', 'close_cash_register', 'view_statistics', 'prepare_orders', 'ticket_annuler', 'remise_apply', 'caisse_arret', 'statistiques_view', 'commande_preparer', 'stock_consulter']),
    \App\Services\RoleCatalog::COMMANDE_ID => $chargeCommandePermissions,
];

$hasDefault = false;
if ($normalizedRole !== '') {
    $hasDefault = in_array($permission, $permissionsByCode[$normalizedRole] ?? [], true);
    echo "Vérification par Role Code '$normalizedRole':\n";
    echo "  Permissions disponibles: " . count($permissionsByCode[$normalizedRole] ?? []) . "\n";
    echo "  Permission '$permission' présente: " . ($hasDefault ? 'OUI' : 'NON') . "\n";
} else {
    $hasDefault = in_array($permission, $permissionsByRole[$roleId] ?? [], true);
    echo "Vérification par Role ID $roleId:\n";
    echo "  Permission '$permission' présente: " . ($hasDefault ? 'OUI' : 'NON') . "\n";
}

if ($hasDefault) {
    echo "→ RÉSULTAT: AUTORISÉ (Default Role Permission)\n";
    echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
    exit;
}
echo "→ Pas de permission par défaut, continue...\n\n";

// === ÉTAPE 5: isChargeCommandeUser ===
echo "ÉTAPE 5: isChargeCommandeUser\n";
echo str_repeat("-", 50) . "\n";
$isChargeCommande = \App\Services\RoleCatalog::isCommande($roleId, $roleCode);
echo "Est CHARGE_COMMANDE: " . ($isChargeCommande ? 'OUI' : 'NON') . "\n";
echo "  Role ID: $roleId\n";
echo "  Role Code: '$roleCode'\n";
echo "  RoleCatalog::isCommande($roleId, '$roleCode'): " . ($isChargeCommande ? 'true' : 'false') . "\n";

if (!$isChargeCommande) {
    echo "→ Pas CHARGE_COMMANDE, passage au RBACService\n\n";
} else {
    echo "→ Est CHARGE_COMMANDE, vérification via ChargeCommandePolicy\n\n";
    
    // === ÉTAPE 6: ChargeCommandePolicy ===
    echo "ÉTAPE 6: ChargeCommandePolicy\n";
    echo str_repeat("-", 50) . "\n";
    
    if (!class_exists(\App\Services\ChargeCommandePolicy::class)) {
        echo "✗ ChargeCommandePolicy n'existe pas\n";
        echo "→ Passage au RBACService\n\n";
    } else {
        require __DIR__ . '/../app/Services/ChargeCommandePolicy.php';
        $policy = new \App\Services\ChargeCommandePolicy();
        
        echo "Permissions autorisées par ChargeCommandePolicy:\n";
        echo "  " . implode("\n  ", \App\Services\ChargeCommandePolicy::ALLOWED_PERMISSIONS) . "\n\n";
        
        $policyCan = $policy->can($permission);
        echo "Policy->can('$permission'): " . ($policyCan ? 'OUI' : 'NON') . "\n";
        
        if ($policyCan) {
            echo "→ RÉSULTAT: AUTORISÉ (ChargeCommandePolicy)\n";
            echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
            exit;
        } else {
            echo "→ RÉSULTAT: REFUSÉ (ChargeCommandePolicy)\n";
            echo "→ Affichage: 'Acces refuse'\n";
            echo "→ Exit\n";
            echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
            exit;
        }
    }
}

// === ÉTAPE 7: RBACService ===
echo "ÉTAPE 7: RBACService\n";
echo str_repeat("-", 50) . "\n";

require __DIR__ . '/../app/Services/RBACService.php';

if (!class_exists(\App\Services\RBACService::class)) {
    echo "✗ RBACService n'existe pas\n";
    echo "→ Passage au refus final\n\n";
} else {
    try {
        $rbacService = new \App\Services\RBACService($pdo);
        echo "RBACService initialisé\n";
        
        if ($userId <= 0) {
            echo "✗ User ID invalide: $userId\n";
            echo "→ Passage au refus final\n\n";
        } else {
            $hasPermission = $rbacService->hasPermission($userId, $permission);
            echo "RBACService->hasPermission($userId, '$permission'): " . ($hasPermission ? 'OUI' : 'NON') . "\n";
            
            if ($hasPermission) {
                echo "→ RÉSULTAT: AUTORISÉ (RBACService)\n";
                echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
                exit;
            } else {
                echo "→ Permission non trouvée dans RBACService\n";
                echo "→ Passage au refus final\n\n";
            }
        }
    } catch (Exception $e) {
        echo "✗ Erreur RBACService: " . $e->getMessage() . "\n";
        echo "→ Passage au refus final (avec fallback)\n\n";
    }
}

// === ÉTAPE 8: Refus final ===
echo "ÉTAPE 8: Refus final\n";
echo str_repeat("-", 50) . "\n";
echo "→ RÉSULTAT: REFUSÉ FINAL\n";
echo "→ Affichage: 'Acces refuse'\n";
echo "→ Exit\n";

echo "\n=== DIAGNOSTIC TERMINÉ ===\n";
