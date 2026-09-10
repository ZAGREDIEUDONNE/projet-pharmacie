<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT COMPLET - DASHBOARD ADMIN ===\n\n";

    // 1. Vérification des permissions admin
    echo "=== 1. PERMISSIONS ADMIN ===\n";
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE nom = 'administrateur'");
    $stmt->execute();
    $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adminRole) {
        $stmt = $pdo->prepare("
            SELECT p.nom, p.module, p.description
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.module, p.nom
        ");
        $stmt->execute([$adminRole['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Permissions administrateur (" . count($permissions) . "):\n";
        foreach ($permissions as $perm) {
            echo "- {$perm['nom']} ({$perm['module']})";
            if ($perm['description']) {
                echo " - {$perm['description']}";
            }
            echo "\n";
        }
    } else {
        echo "Rôle administrateur non trouvé\n";
    }
    echo "\n";

    // 2. Vérification des services utilisés par AdminController
    echo "=== 2. SERVICES UTILISÉS PAR ADMINCONTROLLER ===\n";
    $services = [
        'RoleService' => 'app/Services/RoleService.php',
        'VenteService' => 'app/Services/VenteService.php',
        'PharmacyDashboardService' => 'app/Services/PharmacyDashboardService.php',
        'AuditService' => 'app/Services/AuditService.php',
        'StockService' => 'app/Services/StockService.php',
        'CaisseService' => 'app/Services/CaisseService.php',
        'ComptabiliteService' => 'app/Services/ComptabiliteService.php',
    ];

    foreach ($services as $service => $file) {
        $path = __DIR__ . '/../' . $file;
        $exists = file_exists($path);
        echo "$service: " . ($exists ? 'EXISTE' : 'MANQUANT') . "\n";
    }
    echo "\n";

    // 3. Vérification des tables utilisées par AdminController
    echo "=== 3. TABLES UTILISÉES PAR ADMINCONTROLLER ===\n";
    $tables = [
        'utilisateurs', 'roles', 'permissions', 'role_permissions',
        'ventes', 'ventes_items', 'clients', 'produits', 'stock',
        'caisse_sessions', 'audit_logs', 'ecritures_comptables',
        'fournisseurs', 'commandes', 'supplier_orders', 'receptions',
        'trace_corrections_stock', 'trace_annulations'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "$table: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 4. Vérification des méthodes AdminController
    echo "=== 4. MÉTHODES ADMINCONTROLLER ===\n";
    $controllerFile = file_get_contents('app/Controllers/AdminController.php');
    $methods = [
        'adminDashboard', 'index', 'dashboard', 'statistiques', 'statistiquesLive',
        'annulationTickets', 'users', 'createUser', 'storeUser', 'editUser', 'updateUser', 'deleteUser',
        'roles', 'audit', 'auditLive', 'system'
    ];

    foreach ($methods as $method) {
        $exists = strpos($controllerFile, "function $method") !== false;
        echo "$method(): " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 5. Vérification des routes admin
    echo "=== 5. ROUTES ADMIN ===\n";
    $routesFile = file_get_contents('config/routes.php');
    $adminRoutes = [
        '/admin' => 'AdminController@adminDashboard',
        '/admin/dashboard' => 'AdminController@dashboard',
        '/admin/statistiques' => 'AdminController@statistiques',
        '/admin/statistiques/live' => 'AdminController@statistiquesLive',
        '/admin/annulation-tickets' => 'AdminController@annulationTickets',
        '/admin/users' => 'AdminController@users',
        '/admin/users/create' => 'AdminController@createUser',
        '/admin/users/store' => 'AdminController@storeUser',
        '/admin/users/{id}/edit' => 'AdminController@editUser',
        '/admin/users/{id}/update' => 'AdminController@updateUser',
        '/admin/users/{id}/delete' => 'AdminController@deleteUser',
        '/admin/roles' => 'AdminController@roles',
        '/admin/audit' => 'AdminController@audit',
        '/admin/audit/live' => 'AdminController@auditLive',
        '/admin/system' => 'AdminController@system',
    ];

    foreach ($adminRoutes as $route => $controller) {
        $exists = strpos($routesFile, "'$route'") !== false;
        echo "$route → $controller: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 6. Vérification des vues admin
    echo "=== 6. VUES ADMIN ===\n";
    $views = [
        'admin/dashboard.php',
        'admin/users.php',
        'admin/user_form.php',
        'admin/roles.php',
        'admin/audit.php',
        'admin/statistiques.php',
        'admin/system.php',
    ];

    foreach ($views as $view) {
        $path = __DIR__ . '/../app/Views/' . $view;
        $exists = file_exists($path);
        echo "$view: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 7. Vérification des données admin
    echo "=== 7. DONNÉES ADMIN ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs");
    $stmt->execute();
    $utilisateurs = $stmt->fetchColumn();
    echo "Utilisateurs: $utilisateurs\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles");
    $stmt->execute();
    $roles = $stmt->fetchColumn();
    echo "Rôles: $roles\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions");
    $stmt->execute();
    $permissions = $stmt->fetchColumn();
    echo "Permissions: $permissions\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs");
    $stmt->execute();
    $auditLogs = $stmt->fetchColumn();
    echo "Logs audit: $auditLogs\n";
    echo "\n";

    echo "=== FIN DE L'AUDIT ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
