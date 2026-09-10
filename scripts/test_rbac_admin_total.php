<?php
/**
 * SCRIPT DE TEST RBAC - ADMINISTRATEUR ACCÈS TOTAL
 * 
 * Ce script teste que l'administrateur a accès à toutes les permissions
 * et que les autres rôles conservent leurs restrictions.
 */

// Chargement manuel des classes
require_once __DIR__ . '/../app/Services/RBACService.php';
require_once __DIR__ . '/../app/Services/RoleCatalog.php';

use App\Services\RBACService;
use App\Services\RoleCatalog;

try {
    // Connexion directe PDO
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $database = $_ENV['DB_DATABASE'] ?? 'medecin';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $rbac = new RBACService($db);
    
    echo "=== TEST RBAC - ADMINISTRATEUR ACCÈS TOTAL ===\n\n";
    
    // Récupérer les rôles depuis la base
    $stmt = $db->query("SELECT id, nom FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Rôles dans la base:\n";
    foreach ($roles as $role) {
        echo "  - ID {$role['id']}: {$role['nom']}\n";
    }
    echo "\n";
    
    // Récupérer un utilisateur admin pour le test
    $stmt = $db->prepare("SELECT id, username, role_id FROM utilisateurs WHERE role_id = 1 LIMIT 1");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        echo "⚠️  Aucun utilisateur admin trouvé. Création d'un test...\n";
        // Pour le test, on utilise l'ID 1 si existe
        $adminUserId = 1;
    } else {
        echo "✅ Utilisateur admin trouvé: {$adminUser['username']} (ID: {$adminUser['id']})\n";
        $adminUserId = $adminUser['id'];
    }
    
    // Récupérer les permissions disponibles
    $allPermissions = $rbac->getAllPermissions();
    echo "\nPermissions disponibles: " . count($allPermissions) . "\n";
    
    // Tester l'accès admin à toutes les permissions
    echo "\n=== TEST ADMINISTRATEUR ===\n";
    $adminAccessCount = 0;
    $adminDenyCount = 0;
    
    foreach ($allPermissions as $permission) {
        $hasAccess = $rbac->hasPermission($adminUserId, $permission['nom']);
        if ($hasAccess) {
            $adminAccessCount++;
        } else {
            $adminDenyCount++;
            echo "❌ Admin refusé pour: {$permission['nom']}\n";
        }
    }
    
    echo "\nRésultat Admin:\n";
    echo "  - Accès accordés: {$adminAccessCount}\n";
    echo "  - Accès refusés: {$adminDenyCount}\n";
    
    if ($adminDenyCount === 0) {
        echo "  ✅ ADMINISTRATEUR A ACCÈS TOTAL\n";
    } else {
        echo "  ❌ ADMINISTRATEUR N'A PAS ACCÈS TOTAL\n";
    }
    
    // Tester les autres rôles pour vérifier la régression
    echo "\n=== TEST RÉGRESSION AUTRES RÔLES ===\n";
    
    $testRoles = [
        2 => 'VENDEUR',
        3 => 'ASSISTANT',
        4 => 'CHARGE_COMMANDE',
        6 => 'COMPTABLE'
    ];
    
    $testPermissions = [
        'vente.view',
        'vente.create',
        'client.view',
        'client.create',
        'stock.view',
        'stock.manage',
        'comptabilite_view',
        'user.manage',
        'caisse.open',
        'cancel_ticket'
    ];
    
    foreach ($testRoles as $roleId => $roleName) {
        // Récupérer un utilisateur de ce rôle
        $stmt = $db->prepare("SELECT id, username FROM utilisateurs WHERE role_id = ? LIMIT 1");
        $stmt->execute([$roleId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "⚠️  Aucun utilisateur trouvé pour {$roleName}\n";
            continue;
        }
        
        echo "\n--- {$roleName} ({$user['username']}) ---\n";
        
        foreach ($testPermissions as $permission) {
            $hasAccess = $rbac->hasPermission($user['id'], $permission);
            $status = $hasAccess ? '✅' : '❌';
            echo "  {$status} {$permission}\n";
        }
    }
    
    echo "\n=== TEST TERMINÉ ===\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
