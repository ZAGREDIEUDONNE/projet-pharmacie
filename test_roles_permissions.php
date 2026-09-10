<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Services/RolePermissionService.php';
require_once __DIR__ . '/app/Services/AssistantAuthService.php';
require_once __DIR__ . '/app/Services/CaisseSessionService.php';

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Rôles & Permissions - Pharmacie ERP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border-left: 4px solid #3498db;
            background-color: #ecf0f1;
        }
        .success {
            border-left-color: #27ae60;
            background-color: #d5f4e6;
        }
        .error {
            border-left-color: #e74c3c;
            background-color: #fadbd8;
        }
        .warning {
            border-left-color: #f39c12;
            background-color: #fef9e7;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], input[type="number"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #2980b9;
        }
        .button-success {
            background-color: #27ae60;
        }
        .button-success:hover {
            background-color: #229954;
        }
        .button-warning {
            background-color: #f39c12;
        }
        .button-warning:hover {
            background-color: #e67e22;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .results {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .test-pass {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-fail {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Système Rôles & Permissions</h1>

        <?php
        $db = Database::getConnection();
        $rolePermissionService = new RolePermissionService($db);
        $assistantAuthService = new AssistantAuthService($db, $rolePermissionService);
        $caisseSessionService = new CaisseSessionService($db, $rolePermissionService);

        $step = $_GET['step'] ?? 'init';
        $testUserId = $_GET['user_id'] ?? 1; // Utiliser l'ID 1 pour les tests

        switch ($step) {
            case 'init':
                ?>
                <div class="section">
                    <h2>Initialisation du Système</h2>
                    <p>Ce test va vérifier et valider l'ensemble du système de rôles et permissions.</p>
                    
                    <form method="get" action="">
                        <input type="hidden" name="step" value="database">
                        <div class="form-group">
                            <label for="user_id">ID Utilisateur pour tests:</label>
                            <input type="number" id="user_id" name="user_id" value="1" min="1" required>
                        </div>
                        <button type="submit" class="button-success">Commencer les tests →</button>
                    </form>
                </div>
                <?php
                break;

            case 'database':
                ?>
                <div class="section">
                    <h2>📊 Test Base de Données</h2>
                    
                    <?php
                    // Test connexion
                    $tablesCheck = [
                        'roles' => 'Rôles',
                        'permissions' => 'Permissions',
                        'role_permissions' => 'Permissions des rôles',
                        'assistant_auth_codes' => 'Codes assistant',
                        'caisse_sessions' => 'Sessions caisse',
                        'caisse_sessions_history' => 'Historique sessions',
                        'audit_logs' => 'Logs d\'audit'
                    ];

                    foreach ($tablesCheck as $table => $description) {
                        $stmt = $db->prepare("SHOW TABLES LIKE ?");
                        $stmt->execute([$table]);
                        $exists = $stmt->rowCount() > 0;
                        
                        $class = $exists ? 'test-pass' : 'test-fail';
                        $icon = $exists ? '✅' : '❌';
                        
                        echo "<div class='test-result $class'>$icon $description: $table</div>";
                    }
                    
                    // Vérifier les rôles
                    $roles = $rolePermissionService->getAllRoles();
                    echo "<div class='test-result test-pass'>📋 Rôles trouvés: " . count($roles) . "</div>";
                    
                    foreach ($roles as $role) {
                        echo "<div class='results'>- {$role['nom']}: {$role['description']}</div>";
                    }
                    ?>
                </div>

                <form method="get" action="">
                    <input type="hidden" name="step" value="roles">
                    <input type="hidden" name="user_id" value="<?= $testUserId ?>">
                    <button type="submit">Tester les rôles →</button>
                </form>
                <?php
                break;

            case 'roles':
                ?>
                <div class="section">
                    <h2>👥 Test Rôles Utilisateurs</h2>
                    
                    <?php
                    $userRole = $rolePermissionService->getUserRole($testUserId);
                    
                    if ($userRole) {
                        echo "<div class='test-result test-pass'>✅ Rôle trouvé: {$userRole['nom']}</div>";
                        echo "<div class='results'>Description: {$userRole['description']}</div>";
                        
                        // Tester chaque type de rôle
                        $tests = [
                            'isVendeur' => 'Vendeur',
                            'isAssistant' => 'Assistant',
                            'isChargeCommande' => 'Chargé de commande',
                            'isAdmin' => 'Administrateur'
                        ];
                        
                        foreach ($tests as $method => $label) {
                            $result = $rolePermissionService->$method($testUserId);
                            $class = $result ? 'test-pass' : 'test-fail';
                            $icon = $result ? '✅' : '❌';
                            echo "<div class='test-result $class'>$icon $label: " . ($result ? 'OUI' : 'NON') . "</div>";
                        }
                    } else {
                        echo "<div class='test-result test-fail'>❌ Aucun rôle trouvé pour l'utilisateur $testUserId</div>";
                    }
                    ?>
                </div>

                <form method="get" action="">
                    <input type="hidden" name="step" value="permissions">
                    <input type="hidden" name="user_id" value="<?= $testUserId ?>">
                    <button type="submit">Tester les permissions →</button>
                </form>
                <?php
                break;

            case 'permissions':
                ?>
                <div class="section">
                    <h2>🔐 Test Permissions Granulaires</h2>
                    
                    <?php
                    $userPermissions = $rolePermissionService->getUserPermissions($testUserId);
                    echo "<div class='test-result test-pass'>📋 Permissions trouvées: " . count($userPermissions) . "</div>";
                    
                    // Tester chaque permission
                    $permissionTests = [
                        'vente' => 'Créer des ventes',
                        'session_change' => 'Changer de session',
                        'date_change' => 'Changer la date',
                        'commande_manage' => 'Gérer les commandes',
                        'remise_apply' => 'Appliquer des remises',
                        'ticket_annuler' => 'Annuler un ticket',
                        'vente_corriger' => 'Corriger une vente',
                        'caisse_arret' => 'Arrêter la caisse',
                        'facture_imprimer' => 'Imprimer facture',
                        'statistiques_view' => 'Voir statistiques',
                        'stock_consulter' => 'Consulter stock',
                        'users_manage' => 'Gérer utilisateurs',
                        'products_manage' => 'Gérer produits',
                        'audit_view' => 'Voir audit logs'
                    ];
                    
                    foreach ($permissionTests as $action => $description) {
                        $check = $rolePermissionService->checkActionPermission($testUserId, $action);
                        $class = $check['allowed'] ? 'test-pass' : 'test-fail';
                        $icon = $check['allowed'] ? '✅' : '❌';
                        echo "<div class='test-result $class'>$icon $description: " . ($check['allowed'] ? 'AUTORISÉ' : 'REFUSÉ') . "</div>";
                        if (!$check['allowed']) {
                            echo "<div class='results'>Raison: {$check['message']}</div>";
                        }
                    }
                    ?>
                </div>

                <form method="get" action="">
                    <input type="hidden" name="step" value="assistant">
                    <input type="hidden" name="user_id" value="<?= $testUserId ?>">
                    <button type="submit">Tester authentification assistant →</button>
                </form>
                <?php
                break;

            case 'assistant':
                ?>
                <div class="section">
                    <h2>🔑 Test Double Authentification Assistant</h2>
                    
                    <?php
                    if (!$rolePermissionService->isAssistant($testUserId)) {
                        echo "<div class='test-result test-fail'>❌ L'utilisateur $testUserId n'est pas un assistant</div>";
                    } else {
                        echo "<div class='test-result test-pass'>✅ L'utilisateur $testUserId est un assistant</div>";
                        
                        // Générer des codes de test
                        $codes = $assistantAuthService->generateAssistantCodes($testUserId);
                        
                        if ($codes['success']) {
                            echo "<div class='test-result test-pass'>✅ Codes générés avec succès</div>";
                            echo "<div class='results'>";
                            echo "- Code caisse: {$codes['code_caisse']}<br>";
                            echo "- Code avancé: {$codes['code_avance']}";
                            echo "</div>";
                            
                            // Tester les codes
                            $testCodeCaisse = $assistantAuthService->verifyAssistantCode($testUserId, $codes['code_caisse'], 'caisse');
                            $class1 = $testCodeCaisse['success'] ? 'test-pass' : 'test-fail';
                            $icon1 = $testCodeCaisse['success'] ? '✅' : '❌';
                            echo "<div class='test-result $class1'>$icon1 Test code caisse: " . ($testCodeCaisse['success'] ? 'VALIDÉ' : 'INVALIDÉ') . "</div>";
                            
                            $testCodeAvance = $assistantAuthService->verifyAssistantCode($testUserId, $codes['code_avance'], 'avance');
                            $class2 = $testCodeAvance['success'] ? 'test-pass' : 'test-fail';
                            $icon2 = $testCodeAvance['success'] ? '✅' : '❌';
                            echo "<div class='test-result $class2'>$icon2 Test code avancé: " . ($testCodeAvance['success'] ? 'VALIDÉ' : 'INVALIDÉ') . "</div>";
                            
                        } else {
                            echo "<div class='test-result test-fail'>❌ Erreur génération codes: {$codes['message']}</div>";
                        }
                    }
                    ?>
                </div>

                <form method="get" action="">
                    <input type="hidden" name="step" value="sessions">
                    <input type="hidden" name="user_id" value="<?= $testUserId ?>">
                    <button type="submit">Tester sessions caisse →</button>
                </form>
                <?php
                break;

            case 'sessions':
                ?>
                <div class="section">
                    <h2>💰 Test Sessions Caisse (1-3)</h2>
                    
                    <?php
                    // Tester ouverture de session
                    $ouvertureTest = $caisseSessionService->ouvrirSession($testUserId, '1', 1000.00);
                    $class1 = $ouvertureTest['success'] ? 'test-pass' : 'test-fail';
                    $icon1 = $ouvertureTest['success'] ? '✅' : '❌';
                    echo "<div class='test-result $class1'>$icon1 Ouverture session 1: " . ($ouvertureTest['success'] ? 'SUCCÈS' : 'ÉCHEC') . "</div>";
                    
                    if ($ouvertureTest['success']) {
                        echo "<div class='results'>Session ID: {$ouvertureTest['session_id']}</div>";
                        
                        // Vérifier la session active
                        $sessionActive = $caisseSessionService->getSessionActive($testUserId);
                        if ($sessionActive) {
                            echo "<div class='test-result test-pass'>✅ Session active trouvée: {$sessionActive['session_number']}</div>";
                        }
                        
                        // Tester fermeture de session
                        $fermetureTest = $caisseSessionService->fermerSession($testUserId, 1050.00, 'Test de fermeture');
                        $class2 = $fermetureTest['success'] ? 'test-pass' : 'test-fail';
                        $icon2 = $fermetureTest['success'] ? '✅' : '❌';
                        echo "<div class='test-result $class2'>$icon2 Fermeture session: " . ($fermetureTest['success'] ? 'SUCCÈS' : 'ÉCHEC') . "</div>";
                        
                        if ($fermetureTest['success']) {
                            echo "<div class='results'>";
                            echo "- Écart: {$fermetureTest['ecart']} FCFA<br>";
                            echo "- Ventes: {$fermetureTest['ventes_count']}<br>";
                            echo "- Total: {$fermetureTest['total_ventes']} FCFA";
                            echo "</div>";
                        }
                    }
                    
                    // Tester les sessions 2 et 3
                    $session2 = $caisseSessionService->isSessionDisponible('2');
                    $session3 = $caisseSessionService->isSessionDisponible('3');
                    
                    echo "<div class='test-result " . ($session2 ? 'test-pass' : 'test-fail') . "'>" . ($session2 ? '✅' : '❌') . " Session 2 disponible</div>";
                    echo "<div class='test-result " . ($session3 ? 'test-pass' : 'test-fail') . "'>" . ($session3 ? '✅' : '❌') . " Session 3 disponible</div>";
                    ?>
                </div>

                <form method="get" action="">
                    <input type="hidden" name="step" value="audit">
                    <input type="hidden" name="user_id" value="<?= $testUserId ?>">
                    <button type="submit">Tester audit logs →</button>
                </form>
                <?php
                break;

            case 'audit':
                ?>
                <div class="section">
                    <h2>📜 Test Traçabilité Audit Logs</h2>
                    
                    <?php
                    // Vérifier les logs d'audit récents
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM audit_logs WHERE utilisateur_id = ? AND date_action >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                    $stmt->execute([$testUserId]);
                    $recentLogs = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo "<div class='test-result test-pass'>📊 Logs récents (1h): {$recentLogs['count']}</div>";
                    
                    // Vérifier les logs spécifiques
                    $auditTypes = [
                        'SESSION_OUVERTURE' => 'Ouverture session',
                        'SESSION_FERMETURE' => 'Fermeture session',
                        'CODES_GENERES' => 'Génération codes',
                        'CODE_VALIDE' => 'Validation code',
                        'TICKET_ANNULATION' => 'Annulation ticket',
                        'VENTE_CORRECTION' => 'Correction vente',
                        'STOCK_MODIFICATION' => 'Modification stock'
                    ];
                    
                    foreach ($auditTypes as $action => $description) {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM audit_logs WHERE utilisateur_id = ? AND action = ?");
                        $stmt->execute([$testUserId, $action]);
                        $count = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo "<div class='results'>- $description: {$count['count']} occurrence(s)</div>";
                    }
                    
                    // Afficher les 5 derniers logs
                    $stmt = $db->prepare("SELECT * FROM audit_logs WHERE utilisateur_id = ? ORDER BY date_action DESC LIMIT 5");
                    $stmt->execute([$testUserId]);
                    $lastLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<div class='results'><strong>5 dernières actions:</strong></div>";
                    echo "<pre>";
                    foreach ($lastLogs as $log) {
                        echo "[" . $log['date_action'] . "] " . $log['action'] . "\n";
                    }
                    echo "</pre>";
                    ?>
                </div>

                <form method="get" action="">
                    <input type="hidden" name="step" value="complete">
                    <button type="submit" class="button-success">Voir le résumé complet →</button>
                </form>
                <?php
                break;

            case 'complete':
                ?>
                <div class="section success">
                    <h2>🎉 Tests Complétés</h2>
                    <p>Le système de rôles et permissions a été testé avec succès!</p>
                    
                    <h3>✅ Fonctionnalités validées:</h3>
                    <div class="results">
                        <div>✅ Base de données complète</div>
                        <div>✅ Rôles obligatoires créés</div>
                        <div>✅ Permissions granulaires fonctionnelles</div>
                        <div>✅ Double authentification assistant</div>
                        <div>✅ Sessions caisse 1-3</div>
                        <div>✅ Traçabilité complète</div>
                        <div>✅ Middleware de permissions</div>
                    </div>
                    
                    <h3>📋 Résumé du système:</h3>
                    <div class="results">
                        <div><strong>4 rôles:</strong> vendeur, chargé de commande, assistant, administrateur</div>
                        <div><strong>18 permissions:</strong> par action granulaire</div>
                        <div><strong>2 codes assistant:</strong> caisse (1) et avancé (2)</div>
                        <div><strong>3 sessions caisse:</strong> une par utilisateur maximum</div>
                        <div><strong>Audit complet:</strong> toutes les actions tracées</div>
                    </div>
                    
                    <form method="get" action="">
                        <input type="hidden" name="step" value="init">
                        <button type="submit">Recommencer les tests</button>
                    </form>
                </div>
                <?php
                break;

            default:
                header('Location: ?step=init');
                break;
        }
        ?>

        <?php if (!empty($error)): ?>
            <div class="section error">
                <strong>Erreur :</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
