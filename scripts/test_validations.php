<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ÉTAPE 12 - TESTS DE VALIDATION ===\n\n";

    $tests = [
        '1. Existence des nouvelles colonnes' => function($pdo) {
            $columns = [
                'commandes.montant_ht',
                'commandes.montant_tva',
                'mouvements_caisse.vente_id',
                'mouvements_caisse.client_id',
                'mouvements_caisse.fournisseur_id',
                'mouvements_caisse.type_depense',
                'stock.date_peremption',
                'receptions.ecriture_id'
            ];
            
            $results = [];
            foreach ($columns as $col) {
                list($table, $column) = explode('.', $col);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                      WHERE TABLE_SCHEMA = 'medecin' 
                                      AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$table, $column]);
                $exists = (int) $stmt->fetchColumn() > 0;
                $results[$col] = $exists ? 'OK' : 'FAIL';
            }
            return $results;
        },
        
        '2. Existence des nouvelles tables' => function($pdo) {
            $tables = [
                'supplier_orders',
                'supplier_order_items',
                'receptions',
                'reception_items',
                'stock_entries',
                'fournisseur_reglements',
                'client_reglements',
                'remises_commerciales',
                'journaux_comptables',
                'lignes_ecritures'
            ];
            
            $results = [];
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                                      WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
                $stmt->execute([$table]);
                $exists = (int) $stmt->fetchColumn() > 0;
                $results[$table] = $exists ? 'OK' : 'FAIL';
            }
            return $results;
        },
        
        '3. Comptes SYSCOHADA' => function($pdo) {
            $comptes = ['401', '445', '31', '35', '51'];
            $results = [];
            foreach ($comptes as $compte) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM plan_comptable 
                                      WHERE numero_compte LIKE ?");
                $stmt->execute([$compte . '%']);
                $exists = (int) $stmt->fetchColumn() > 0;
                $results[$compte] = $exists ? 'OK' : 'FAIL';
            }
            return $results;
        },
        
        '4. Index sur colonnes critiques' => function($pdo) {
            $indexes = [
                'mouvements_caisse.vente_id',
                'mouvements_caisse.client_id',
                'mouvements_caisse.fournisseur_id',
                'stock.date_peremption',
                'receptions.ecriture_id'
            ];
            
            $results = [];
            foreach ($indexes as $idx) {
                list($table, $column) = explode('.', $idx);
                $idxName = 'idx_' . $table . '_' . $column;
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                                      WHERE TABLE_SCHEMA = 'medecin' 
                                      AND TABLE_NAME = ? AND INDEX_NAME = ?");
                $stmt->execute([$table, $idxName]);
                $exists = (int) $stmt->fetchColumn() > 0;
                $results[$idx] = $exists ? 'OK' : 'FAIL';
            }
            return $results;
        },
        
        '5. Méthode genererEcrituresReception' => function($pdo) {
            // Vérifier si la méthode existe dans le fichier
            $file = 'app/Services/EcritureComptableService.php';
            $content = file_get_contents($file);
            $exists = strpos($content, 'genererEcrituresReception') !== false;
            return ['genererEcrituresReception' => $exists ? 'OK' : 'FAIL'];
        },
        
        '6. Mapping RECEPTION dans getTableByReferenceType' => function($pdo) {
            $file = 'app/Services/EcritureComptableService.php';
            $content = file_get_contents($file);
            $exists = strpos($content, "'RECEPTION' => 'receptions'") !== false;
            return ['RECEPTION mapping' => $exists ? 'OK' : 'FAIL'];
        },
        
        '7. Intégration dans ChargeCommandeService' => function($pdo) {
            $file = 'app/Services/ChargeCommandeService.php';
            $content = file_get_contents($file);
            $hasService = strpos($content, 'EcritureComptableService') !== false;
            $hasCall = strpos($content, 'genererEcrituresReception') !== false;
            return [
                'EcritureComptableService injection' => $hasService ? 'OK' : 'FAIL',
                'genererEcrituresReception call' => $hasCall ? 'OK' : 'FAIL'
            ];
        },
        
        '8. Schema.sql synchronisé' => function($pdo) {
            $file = 'database/schema.sql';
            $content = file_get_contents($file);
            $tables = [
                'supplier_orders',
                'receptions',
                'stock_entries',
                'fournisseur_reglements',
                'lignes_ecritures'
            ];
            $results = [];
            foreach ($tables as $table) {
                $exists = strpos($content, "CREATE TABLE $table") !== false;
                $results[$table] = $exists ? 'OK' : 'FAIL';
            }
            return $results;
        }
    ];

    $totalTests = 0;
    $passedTests = 0;
    $failedTests = 0;

    foreach ($tests as $testName => $testFunc) {
        echo "--- $testName ---\n";
        $results = $testFunc($pdo);
        
        foreach ($results as $item => $status) {
            $totalTests++;
            if ($status === 'OK') {
                $passedTests++;
                echo "  ✓ $item: $status\n";
            } else {
                $failedTests++;
                echo "  ✗ $item: $status\n";
            }
        }
        echo "\n";
    }

    echo "=== RÉSUMÉ DES TESTS ===\n";
    echo "Total: $totalTests\n";
    echo "Réussis: $passedTests\n";
    echo "Échoués: $failedTests\n";
    echo "Taux de réussite: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
