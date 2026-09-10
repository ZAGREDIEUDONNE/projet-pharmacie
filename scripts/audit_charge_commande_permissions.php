<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT PERMISSIONS - RÔLE CHARGE_COMMANDE ===\n\n";

    // Permissions utilisées dans ChargeCommandeController
    $controllerPermissions = [
        'view_stock' => 'dashboard, apiDashboard',
        'create_supplier_orders' => 'orderForm, storeOrder, duplicateOrder, commandesAutomatiques',
        'edit_supplier_orders' => 'editOrder, updateOrder, cancelOrder, updateOrderStatus',
        'view_supplier_orders' => 'showOrder, orderHistory, printOrder',
        'export_supplier_orders' => 'exportPdf, exportExcel',
        'send_supplier_orders' => 'sendOrder',
        'receive_products' => 'receptionForm, storeReception, orderItems',
        'view_stock_movements' => 'mouvements'
    ];

    // Récupérer le rôle CHARGE_COMMANDE
    $stmt = $pdo->prepare("SELECT id, nom FROM roles WHERE nom = 'charge_commande'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo "ERREUR: Le rôle 'charge_commande' n'existe pas en base.\n";
        exit;
    }

    echo "Rôle: {$role['nom']} (ID: {$role['id']})\n\n";

    // Récupérer les permissions actuelles du rôle
    $stmt = $pdo->prepare("
        SELECT p.nom, p.description, p.module
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.module, p.nom
    ");
    $stmt->execute([$role['id']]);
    $existingPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== PERMISSIONS ACTUELLES DU RÔLE ===\n";
    foreach ($existingPermissions as $perm) {
        echo "- {$perm['nom']} ({$perm['module']})\n";
    }
    echo "\n";

    // Vérifier les permissions utilisées dans le contrôleur
    echo "=== PERMISSIONS NÉCESSAIRES (D'APRÈS CONTROLLER) ===\n";
    foreach ($controllerPermissions as $perm => $methods) {
        $exists = false;
        foreach ($existingPermissions as $existing) {
            if ($existing['nom'] === $perm) {
                $exists = true;
                break;
            }
        }
        echo "- $perm: " . ($exists ? 'OK' : 'MANQUANTE') . " (méthodes: $methods)\n";
    }
    echo "\n";

    // Permissions manquantes
    echo "=== PERMISSIONS MANQUANTES ===\n";
    $missing = [];
    foreach ($controllerPermissions as $perm => $methods) {
        $exists = false;
        foreach ($existingPermissions as $existing) {
            if ($existing['nom'] === $perm) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $missing[] = $perm;
            echo "- $perm\n";
        }
    }
    echo "\n";

    // Permissions en trop
    echo "=== PERMISSIONS EN TROP (NON UTILISÉES DANS CONTROLLER) ===\n";
    $extra = [];
    foreach ($existingPermissions as $existing) {
        $used = false;
        foreach ($controllerPermissions as $perm => $methods) {
            if ($existing['nom'] === $perm) {
                $used = true;
                break;
            }
        }
        if (!$used) {
            $extra[] = $existing['nom'];
            echo "- {$existing['nom']} ({$existing['module']})\n";
        }
    }
    echo "\n";

    // Routes CHARGE_COMMANDE
    echo "=== ROUTES CHARGE_COMMANDE ===\n";
    $routes = [
        'GET /commande/dashboard' => 'view_stock',
        'GET /commande/api/dashboard' => 'view_stock',
        'GET /commande/reception' => 'receive_products',
        'POST /commande/reception' => 'receive_products',
        'GET /commande/order-items' => 'receive_products',
        'GET /commande/saisie' => 'create_supplier_orders',
        'POST /commande/saisie' => 'create_supplier_orders',
        'GET /commande/edit' => 'edit_supplier_orders',
        'POST /commande/update' => 'edit_supplier_orders',
        'GET /commande/show' => 'view_supplier_orders',
        'GET /commande/print' => 'view_supplier_orders',
        'GET /commande/export-pdf' => 'export_supplier_orders',
        'GET /commande/export-excel' => 'export_supplier_orders',
        'POST /commande/cancel' => 'edit_supplier_orders',
        'POST /commande/duplicate' => 'create_supplier_orders',
        'GET /commande/mouvements' => 'view_stock_movements',
        'GET /commande/historique' => 'view_supplier_orders',
        'POST /commande/update-status' => 'edit_supplier_orders',
        'POST /commande/send' => 'send_supplier_orders'
    ];

    foreach ($routes as $route => $perm) {
        $permExists = in_array($perm, array_keys($controllerPermissions));
        $roleHasPerm = false;
        foreach ($existingPermissions as $existing) {
            if ($existing['nom'] === $perm) {
                $roleHasPerm = true;
                break;
            }
        }
        echo "$route → $perm: " . ($roleHasPerm ? 'OK' : 'MANQUANTE') . "\n";
    }

    echo "\n=== RÉSUMÉ ===\n";
    echo "Permissions nécessaires: " . count($controllerPermissions) . "\n";
    echo "Permissions existantes: " . count($existingPermissions) . "\n";
    echo "Permissions manquantes: " . count($missing) . "\n";
    echo "Permissions en trop: " . count($extra) . "\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
