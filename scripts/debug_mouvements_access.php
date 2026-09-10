<?php
// Script de diagnostic pour l'accès aux mouvements de stock
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "=== DIAGNOSTIC ACCÈS MOUVEMENTS DE STOCK ===\n\n";

// 1. Informations de session
echo "1. SESSION:\n";
echo "----------------------------------------\n";
echo "Session active: " . (isset($_SESSION['user']) ? "OUI" : "NON") . "\n";
if (isset($_SESSION['user'])) {
    echo "User ID: " . ($_SESSION['user']['id'] ?? 'N/A') . "\n";
    echo "Username: " . ($_SESSION['user']['username'] ?? 'N/A') . "\n";
    echo "Role ID: " . ($_SESSION['user']['role_id'] ?? 'N/A') . "\n";
    echo "Role Code: " . ($_SESSION['user']['role_code'] ?? 'N/A') . "\n";
    echo "Role Name: " . ($_SESSION['user']['role'] ?? 'N/A') . "\n";
    echo "Role Label: " . ($_SESSION['user']['role_name'] ?? 'N/A') . "\n";
}
echo "\n";

// 2. Informations de la base de données
echo "2. BASE DE DONNÉES - RÔLES:\n";
echo "----------------------------------------\n";
$stmt = $pdo->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $role) {
    echo "ID: {$role['id']}, Nom: {$role['nom']}\n";
}
echo "\n";

// 3. Permissions dans la base de données
echo "3. BASE DE DONNÉES - PERMISSIONS:\n";
echo "----------------------------------------\n";
$stmt = $pdo->query("SELECT * FROM permissions WHERE nom LIKE '%mouvement%' OR nom LIKE '%stock%'");
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($permissions as $perm) {
    echo "ID: {$perm['id']}, Nom: {$perm['nom']}, Module: {$perm['module']}\n";
}
echo "\n";

// 4. Role permissions
echo "4. BASE DE DONNÉES - ROLE_PERMISSIONS:\n";
echo "----------------------------------------\n";
$stmt = $pdo->query("SELECT rp.role_id, r.nom as role_nom, rp.permission_id, p.nom as permission_nom 
                    FROM role_permissions rp 
                    JOIN roles r ON rp.role_id = r.id 
                    JOIN permissions p ON rp.permission_id = p.id 
                    WHERE p.nom LIKE '%mouvement%' OR p.nom LIKE '%stock%'");
$rolePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rolePerms as $rp) {
    echo "Role ID: {$rp['role_id']} ({$rp['role_nom']}), Permission: {$rp['permission_nom']}\n";
}
echo "\n";

// 5. Vérification spécifique pour CHARGE_COMMANDE
echo "5. VÉRIFICATION CHARGE_COMMANDE:\n";
echo "----------------------------------------\n";
$stmt = $pdo->query("SELECT * FROM roles WHERE LOWER(nom) LIKE '%commande%'");
$commandeRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($commandeRoles as $role) {
    echo "ID: {$role['id']}, Nom: {$role['nom']}\n";
    
    $stmt2 = $pdo->prepare("SELECT p.nom FROM role_permissions rp 
                            JOIN permissions p ON rp.permission_id = p.id 
                            WHERE rp.role_id = ?");
    $stmt2->execute([$role['id']]);
    $perms = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    echo "Permissions: " . implode(', ', $perms) . "\n";
    
    if (in_array('view_stock_movements', $perms)) {
        echo "✓ view_stock_movements est attribué\n";
    } else {
        echo "✗ view_stock_movements n'est PAS attribué\n";
    }
}
echo "\n";

// 6. Test RoleCatalog
echo "6. TEST ROLECATALOG:\n";
echo "----------------------------------------\n";
require __DIR__ . '/../app/Services/RoleCatalog.php';

$testCodes = ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE', 'COMMANDE', 'charge_commande'];
foreach ($testCodes as $code) {
    $normalized = \App\Services\RoleCatalog::normalizeCode($code);
    $mappedId = \App\Services\RoleCatalog::mapCodeOrNameToId($code);
    $isCommande = \App\Services\RoleCatalog::isCommande(4, $code);
    echo "Code: '$code' → Normalisé: '$normalized', ID: $mappedId, isCommande: " . ($isCommande ? 'OUI' : 'NON') . "\n";
}
echo "\n";

// 7. Test ChargeCommandePolicy
echo "7. TEST CHARGECOMMANDEPOLICY:\n";
echo "----------------------------------------\n";
require __DIR__ . '/../app/Services/ChargeCommandePolicy.php';
$policy = new \App\Services\ChargeCommandePolicy();
$testPerms = ['view_stock_movements', 'stock.view', 'stock.manage'];
foreach ($testPerms as $perm) {
    $can = $policy->can($perm);
    echo "Permission: '$perm' → " . ($can ? 'AUTORISÉ' : 'REFUSÉ') . "\n";
}
echo "\n";

echo "=== FIN DU DIAGNOSTIC ===\n";
