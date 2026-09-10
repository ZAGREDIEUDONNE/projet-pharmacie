<?php
// Script de diagnostic de session (à exécuter après connexion)
session_start();

echo "=== DIAGNOSTIC SESSION ACTIVE ===\n\n";

echo "1. DONNÉES BRUTES DE SESSION:\n";
echo "----------------------------------------\n";
echo "Session ID: " . session_id() . "\n";
echo "Session active: " . (isset($_SESSION) ? "OUI" : "NON") . "\n";
echo "\n";

echo "Contenu de \$_SESSION:\n";
foreach ($_SESSION as $key => $value) {
    if (is_array($value)) {
        echo "$key:\n";
        foreach ($value as $k => $v) {
            echo "  $k: $v\n";
        }
    } else {
        echo "$key: $value\n";
    }
}
echo "\n";

echo "2. NORMALISATION DU RÔLE:\n";
echo "----------------------------------------\n";
if (isset($_SESSION['user'])) {
    $roleCode = $_SESSION['user']['role_code'] ?? $_SESSION['user']['role'] ?? $_SESSION['user']['role_name'] ?? '';
    echo "Role Code brut: '$roleCode'\n";
    
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleCode);
        echo "Après iconv: '$ascii'\n";
    }
    
    $normalized = strtoupper(str_replace([' ', '-'], '_', $roleCode));
    echo "Après normalisation: '$normalized'\n";
}
echo "\n";

echo "3. TEST DES PERMISSIONS:\n";
echo "----------------------------------------\n";
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

if (isset($_SESSION['user'])) {
    $userId = $_SESSION['user']['id'] ?? 0;
    $roleId = $_SESSION['user']['role_id'] ?? 0;
    $roleCode = $_SESSION['user']['role_code'] ?? $_SESSION['user']['role'] ?? '';
    
    echo "User ID: $userId\n";
    echo "Role ID: $roleId\n";
    echo "Role Code: '$roleCode'\n";
    
    // Vérifier les permissions de l'utilisateur
    $stmt = $pdo->prepare("SELECT p.nom FROM permissions p 
                           JOIN role_permissions rp ON p.id = rp.permission_id 
                           WHERE rp.role_id = ?");
    $stmt->execute([$roleId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nPermissions du rôle $roleId:\n";
    foreach ($permissions as $perm) {
        $has = in_array($perm, $permissions) ? '✓' : '✗';
        echo "  $has $perm\n";
    }
    
    echo "\nVérification view_stock_movements:\n";
    if (in_array('view_stock_movements', $permissions)) {
        echo "  ✓ view_stock_movements est attribué au rôle\n";
    } else {
        echo "  ✗ view_stock_movements n'est PAS attribué au rôle\n";
    }
}
echo "\n";

echo "=== FIN DU DIAGNOSTIC ===\n";
