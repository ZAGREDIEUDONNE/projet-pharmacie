<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

    foreach ($tables as $table) {
        echo "-- =============================================\n";
        echo "-- TABLE: $table\n";
        echo "-- =============================================\n\n";
        
        // Obtenir la structure CREATE TABLE
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . ";\n\n";
        
        // Obtenir les index
        $stmt = $pdo->query("SHOW INDEX FROM $table");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($indexes) {
            echo "-- Index:\n";
            foreach ($indexes as $idx) {
                echo "-- {$idx['Key_name']} ({$idx['Column_name']}) - {$idx['Index_type']}\n";
            }
            echo "\n";
        }
        
        echo "\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
