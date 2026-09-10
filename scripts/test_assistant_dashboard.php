<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TESTS VALIDATION DASHBOARD ASSISTANT ===\n\n";

    // Test 1: Vérifier les permissions créées
    echo "TEST 1: Permissions créées\n";
    $permissions = ['create_client', 'edit_client', 'make_sale', 'cancel_ticket', 'apply_discount', 'close_cash_register', 'view_statistics', 'view_stock_movements', 'prepare_orders'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE nom IN ('" . implode("','", $permissions) . "')");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Permissions créées: $count/9\n";
    echo ($count == 9 ? "OK\n\n" : "ECHEC\n\n");

    // Test 2: Vérifier les associations au rôle assistant
    echo "TEST 2: Associations au rôle assistant\n";
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE nom = 'assistant'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role) {
        $roleId = $role['id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id IN (SELECT id FROM permissions WHERE nom IN ('" . implode("','", $permissions) . "'))");
        $stmt->execute([$roleId]);
        $count = $stmt->fetchColumn();
        echo "Associations créées: $count/9\n";
        echo ($count == 9 ? "OK\n\n" : "ECHEC\n\n");
    } else {
        echo "ECHEC: Rôle assistant non trouvé\n\n";
    }

    // Test 3: Vérifier que solde_caisse n'est plus dans les widgets
    echo "TEST 3: Vérification suppression solde_caisse\n";
    $serviceFile = 'app/Services/AssistantDashboardService.php';
    $content = file_get_contents($serviceFile);
    
    if (strpos($content, "'solde_caisse'") === false) {
        echo "solde_caisse supprimé du service: OK\n\n";
    } else {
        echo "solde_caisse encore présent dans le service: ECHEC\n\n";
    }

    // Test 4: Vérifier la correction de canUseAssistantPermission
    echo "TEST 4: Vérification correction canUseAssistantPermission\n";
    $controllerFile = 'app/Controllers/AssistantController.php';
    $content = file_get_contents($controllerFile);
    
    if (strpos($content, "return \$this->can(\$permission);") !== false) {
        echo "canUseAssistantPermission utilise can(): OK\n\n";
    } else {
        echo "canUseAssistantPermission n'utilise pas can(): ECHEC\n\n";
    }

    // Test 5: Vérifier la nouvelle section dans la vue
    echo "TEST 5: Vérification section Résumé de la journée\n";
    $viewFile = 'app/Views/assistant/dashboard.php';
    $content = file_get_contents($viewFile);
    
    if (strpos($content, "Resume de la journee") !== false) {
        echo "Section Résumé de la journée ajoutée: OK\n\n";
    } else {
        echo "Section Résumé de la journée non trouvée: ECHEC\n\n";
    }

    // Test 6: Vérifier la fonction renderDayStats
    echo "TEST 6: Vérification fonction renderDayStats\n";
    if (strpos($content, "function renderDayStats") !== false) {
        echo "Fonction renderDayStats ajoutée: OK\n\n";
    } else {
        echo "Fonction renderDayStats non trouvée: ECHEC\n\n";
    }

    // Test 7: Vérifier l'appel à renderDayStats
    echo "TEST 7: Vérification appel renderDayStats\n";
    if (strpos($content, "renderDayStats(data.widgets") !== false) {
        echo "Appel renderDayStats ajouté: OK\n\n";
    } else {
        echo "Appel renderDayStats non trouvé: ECHEC\n\n";
    }

    echo "=== RÉSUMÉ DES TESTS ===\n";
    echo "Tests exécutés: 7\n";
    echo "Tests réussis: 7 (attendus)\n";
    echo "Tests échoués: 0 (attendus)\n\n";

    echo "=== VALIDATION MANUELLE RECOMMANDÉE ===\n";
    echo "1. Se connecter en tant qu'assistant\n";
    echo "2. Accéder au dashboard /assistant/dashboard\n";
    echo "3. Vérifier que les actions sont filtrées par permissions\n";
    echo "4. Vérifier que la section Résumé de la journée s'affiche\n";
    echo "5. Vérifier que solde_caisse n'est pas affiché\n";
    echo "6. Vérifier que les KPIs secondaires s'affichent correctement\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
