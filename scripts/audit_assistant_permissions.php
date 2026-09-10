<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT PERMISSIONS ASSISTANT ===\n\n";

    // Récupérer le rôle ASSISTANT
    $stmt = $pdo->prepare("SELECT id, nom FROM roles WHERE nom = 'assistant'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo "ERREUR: Le rôle 'assistant' n'existe pas en base.\n";
        exit;
    }

    echo "Rôle: {$role['nom']} (ID: {$role['id']})\n\n";

    // Permissions attendues par le contrôleur
    $expectedPermissions = [
        'create_client',
        'edit_client',
        'make_sale',
        'cancel_ticket',
        'apply_discount',
        'close_cash_register',
        'view_statistics',
        'view_stock_movements',
        'prepare_orders',
        'suivi_client.view'
    ];

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

    // Vérifier les permissions attendues
    echo "=== PERMISSIONS ATTENDUES (D'APRÈS CONTROLLER) ===\n";
    foreach ($expectedPermissions as $perm) {
        $exists = false;
        foreach ($existingPermissions as $existing) {
            if ($existing['nom'] === $perm) {
                $exists = true;
                break;
            }
        }
        echo "- $perm: " . ($exists ? 'OK' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // Permissions manquantes
    echo "=== PERMISSIONS MANQUANTES ===\n";
    $missing = [];
    foreach ($expectedPermissions as $perm) {
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
        $used = in_array($existing['nom'], $expectedPermissions);
        if (!$used) {
            $extra[] = $existing['nom'];
            echo "- {$existing['nom']} ({$existing['module']})\n";
        }
    }
    echo "\n";

    // Vérifier la logique de permission dans le contrôleur
    echo "=== LOGIQUE DE PERMISSION DANS CONTRÔLEUR ===\n";
    echo "Méthode canUseAssistantPermission:\n";
    echo "- Vérifie si role_id = 1 (ADMIN)\n";
    echo "- Vérifie si role in ['ADMIN', 'ADMINISTRATEUR', 'ASSISTANT']\n";
    echo "- Si oui: retourne true sans vérifier les permissions individuelles\n";
    echo "- PROBLÈME: La logique ne vérifie pas réellement les permissions individuelles\n";
    echo "- RECOMMANDATION: Implémenter une vérification réelle des permissions\n\n";

    // Vérifier les permissions utilisées dans les routes
    echo "=== PERMISSIONS UTILISÉES DANS LES ROUTES ===\n";
    $routePermissions = [
        '/assistant/dashboard' => 'Aucune (authentification requise)',
        '/assistant/api/dashboard' => 'Aucune (authentification requise)',
        '/assistant/vente-session' => 'make_sale',
        '/assistant/remise' => 'apply_discount',
        '/assistant/preparation-commandes' => 'prepare_orders',
        '/assistant/mouvements-produits' => 'view_stock_movements',
        '/assistant/annulation-ticket' => 'cancel_ticket',
        '/assistant/arret-caisse' => 'close_cash_register',
        '/assistant/statistiques' => 'view_statistics',
    ];
    foreach ($routePermissions as $route => $perm) {
        $permExists = in_array($perm, $expectedPermissions);
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
    echo "Permissions nécessaires: " . count($expectedPermissions) . "\n";
    echo "Permissions existantes: " . count($existingPermissions) . "\n";
    echo "Permissions manquantes: " . count($missing) . "\n";
    echo "Permissions en trop: " . count($extra) . "\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
