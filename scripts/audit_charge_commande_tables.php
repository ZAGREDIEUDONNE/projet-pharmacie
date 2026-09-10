<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT COMPLET - MODULE CHARGE_COMMANDE ===\n\n";

    // Scanner les fichiers PHP du module CHARGE_COMMANDE
    $filesToScan = [
        'app/Controllers/ChargeCommandeController.php',
        'app/Services/ChargeCommandeService.php',
        'app/Services/ChargeCommandePolicy.php',
        'app/Services/CommandeAutomatiqueService.php',
        'app/Services/StockService.php',
        'app/Services/StockAvanceService.php'
    ];

    $tablesUsed = [];
    $columnsUsed = [];

    foreach ($filesToScan as $file) {
        if (!file_exists($file)) {
            continue;
        }
        
        $content = file_get_contents($file);
        
        // Extraire les noms de tables utilisés dans les requêtes SQL
        $patterns = [
            '/FROM\s+`?(\w+)`?/i',
            '/JOIN\s+`?(\w+)`?/i',
            '/INSERT INTO\s+`?(\w+)`?/i',
            '/UPDATE\s+`?(\w+)`?/i',
            '/DELETE FROM\s+`?(\w+)`?/i',
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $table) {
                    if (!in_array($table, ['utilisateurs', 'roles', 'permissions'])) {
                        $tablesUsed[$table][] = $file;
                    }
                }
            }
        }
        
        // Extraire les colonnes utilisées
        $columnPatterns = [
            '/(\w+)\.(\w+)/i',
            '/`?(\w+)`?\.`?(\w+)`?/i',
        ];
        
        preg_match_all('/SELECT.*FROM/i', $content, $selectMatches);
        if (!empty($selectMatches[0])) {
            // Extraire les colonnes SELECT
            preg_match_all('/SELECT\s+(.*?)\s+FROM/is', $content, $selectCols);
            if (!empty($selectCols[1])) {
                foreach ($selectCols[1] as $cols) {
                    $colList = explode(',', $cols);
                    foreach ($colList as $col) {
                        $col = trim($col);
                        if (strpos($col, '.') !== false) {
                            list($table, $column) = explode('.', $col);
                            $column = trim($column, '` ');
                            $table = trim($table, '` ');
                            if (!in_array($table, ['utilisateurs', 'roles', 'permissions'])) {
                                $columnsUsed[$table][] = $column;
                            }
                        }
                    }
                }
            }
        }
    }

    // Éliminer les doublons
    foreach ($tablesUsed as $table => $files) {
        $tablesUsed[$table] = array_unique($files);
    }
    
    foreach ($columnsUsed as $table => $cols) {
        $columnsUsed[$table] = array_unique($cols);
    }

    echo "=== TABLES UTILISÉES PAR CHARGE_COMMANDE ===\n\n";
    
    $sortedTables = array_keys($tablesUsed);
    sort($sortedTables);
    foreach ($sortedTables as $table) {
        echo "TABLE: $table\n";
        echo "  Fichiers: " . implode(', ', $tablesUsed[$table]) . "\n";
        
        // Vérifier si la table existe en base
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                              WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "  Existe en base: " . ($exists ? 'OUI' : 'NON') . "\n";
        
        // Lister les colonnes de la table
        if ($exists) {
            $stmt = $pdo->query("DESCRIBE $table");
            $dbColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "  Colonnes en base: " . count($dbColumns) . "\n";
            
            // Vérifier les colonnes utilisées
            if (isset($columnsUsed[$table])) {
                echo "  Colonnes utilisées par le code:\n";
                foreach ($columnsUsed[$table] as $col) {
                    $colExists = false;
                    foreach ($dbColumns as $dbCol) {
                        if ($dbCol['Field'] === $col) {
                            $colExists = true;
                            break;
                        }
                    }
                    echo "    - $col: " . ($colExists ? 'OK' : 'MANQUANTE') . "\n";
                }
            }
        }
        
        echo "\n";
    }

    echo "\n=== RÉSUMÉ ===\n";
    echo "Tables utilisées: " . count($tablesUsed) . "\n";
    echo "Tables avec colonnes analysées: " . count($columnsUsed) . "\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
