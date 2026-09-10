<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT COLONNES - CHARGE_COMMANDE SERVICE ===\n\n";

    // Lire le fichier ChargeCommandeService
    $serviceFile = 'app/Services/ChargeCommandeService.php';
    $content = file_get_contents($serviceFile);

    // Extraire les requêtes SQL
    $queries = [];
    
    // Pattern pour capturer les requêtes SQL
    $patterns = [
        '/query\(["\']([^"\']+)["\']\)/i',
        '/prepare\(["\']([^"\']+)["\']\)/i',
        '/execute\(\[([^\]]+)\]\)/i',
    ];

    // Extraire les SELECT
    preg_match_all('/SELECT\s+(.*?)\s+FROM/is', $content, $selectMatches);
    if (!empty($selectMatches[1])) {
        foreach ($selectMatches[1] as $select) {
            $queries[] = ['type' => 'SELECT', 'sql' => $select];
        }
    }

    // Extraire les INSERT
    preg_match_all('/INSERT INTO\s+(\w+)\s*\((.*?)\)/is', $content, $insertMatches);
    if (!empty($insertMatches[2])) {
        foreach ($insertMatches[2] as $cols) {
            $queries[] = ['type' => 'INSERT', 'sql' => $cols];
        }
    }

    // Extraire les UPDATE
    preg_match_all('/UPDATE\s+(\w+)\s+SET\s+(.*?)\s+WHERE/i', $content, $updateMatches);
    if (!empty($updateMatches[2])) {
        foreach ($updateMatches[2] as $setClause) {
            $queries[] = ['type' => 'UPDATE', 'sql' => $setClause];
        }
    }

    // Analyser chaque requête pour extraire les colonnes
    $tables = [
        'produits',
        'categories',
        'fournisseurs',
        'stock',
        'mouvements_stock',
        'stock_entries',
        'lots',
        'supplier_orders',
        'supplier_order_items',
        'receptions',
        'reception_items',
        'fournisseur_reglements'
    ];

    $columnIssues = [];

    foreach ($tables as $table) {
        echo "=== TABLE: $table ===\n\n";

        // Récupérer les colonnes de la table
        $stmt = $pdo->query("DESCRIBE $table");
        $dbColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbColumns[$row['Field']] = $row;
        }

        // Scanner le contenu pour les références à cette table
        $tablePattern = "/$table\\.(\\w+)/i";
        preg_match_all($tablePattern, $content, $matches);
        
        if (!empty($matches[1])) {
            $usedColumns = array_unique($matches[1]);
            
            echo "Colonnes utilisées dans le code:\n";
            foreach ($usedColumns as $col) {
                $exists = isset($dbColumns[$col]);
                echo "  - $col: " . ($exists ? 'OK' : 'MANQUANTE') . "\n";
                
                if (!$exists) {
                    $columnIssues[] = "$table.$col";
                }
            }
        } else {
            echo "Aucune colonne détectée dans le code pour cette table.\n";
        }

        // Vérifier les colonnes utilisées dans les requêtes sans préfixe de table
        $simplePattern = "/(?:FROM|INSERT INTO|UPDATE)\s+$table\s+(?:WHERE|SET|VALUES|,|\()/i";
        if (preg_match($simplePattern, $content)) {
            echo "  Note: Cette table est utilisée dans des requêtes sans préfixe de colonne.\n";
        }

        echo "\n";
    }

    // Vérifier les colonnes spécifiques mentionnées dans le code
    echo "=== COLONNES SPÉCIFIQUES MENTIONNÉES ===\n\n";

    // Chercher les références directes aux colonnes dans les tableaux PHP
    preg_match_all('/\[(["\'])(\w+)\1\]/', $content, $arrayMatches);
    if (!empty($arrayMatches[2])) {
        $arrayKeys = array_unique($arrayMatches[2]);
        
        echo "Clés de tableau trouvées (possibles colonnes):\n";
        foreach ($arrayKeys as $key) {
            echo "  - $key\n";
        }
    }

    echo "\n=== RÉSUMÉ ===\n";
    if (empty($columnIssues)) {
        echo "Aucune colonne manquante détectée.\n";
    } else {
        echo "Colonnes manquantes détectées:\n";
        foreach ($columnIssues as $issue) {
            echo "  - $issue\n";
        }
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
