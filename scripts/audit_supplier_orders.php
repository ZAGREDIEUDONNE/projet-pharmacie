<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION SUPPLIER_ORDERS / SUPPLIER_ORDER_ITEMS ===\n\n";

    // Vérifier supplier_orders
    echo "=== TABLE: supplier_orders ===\n";
    
    $stmt = $pdo->query("DESCRIBE supplier_orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    // Vérifier les statuts utilisés
    echo "\nStatuts utilisés:\n";
    $stmt = $pdo->query("SELECT DISTINCT statut, COUNT(*) as total FROM supplier_orders GROUP BY statut");
    $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuts as $statut) {
        echo "  - {$statut['statut']}: {$statut['total']}\n";
    }
    
    // Vérifier les données
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM supplier_orders");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nTotal commandes: $total\n";
    
    // Vérifier supplier_order_items
    echo "\n=== TABLE: supplier_order_items ===\n";
    
    $stmt = $pdo->query("DESCRIBE supplier_order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    // Vérifier les données
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM supplier_order_items");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nTotal items: $total\n";
    
    // Vérifier la cohérence entre commandes et items
    echo "\n=== VÉRIFICATION COHÉRENCE ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            so.id,
            so.numero_commande,
            so.statut,
            so.montant_ht,
            so.montant_ttc,
            (SELECT COUNT(*) FROM supplier_order_items WHERE supplier_order_id = so.id) as nb_items,
            (SELECT SUM(quantite_commandee) FROM supplier_order_items WHERE supplier_order_id = so.id) as total_qte_cmd,
            (SELECT SUM(quantite_recue) FROM supplier_order_items WHERE supplier_order_id = so.id) as total_qte_recue
        FROM supplier_orders so
        LIMIT 5
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as $order) {
        echo "Commande {$order['id']} ({$order['numero_commande']}):\n";
        echo "  Statut: {$order['statut']}\n";
        echo "  Montant HT: {$order['montant_ht']}\n";
        echo "  Montant TTC: {$order['montant_ttc']}\n";
        echo "  Nombre items: {$order['nb_items']}\n";
        echo "  Total quantité commandée: {$order['total_qte_cmd']}\n";
        echo "  Total quantité reçue: {$order['total_qte_recue']}\n";
        
        if ($order['statut'] === 'RECEPTION_COMPLETE' && $order['total_qte_cmd'] != $order['total_qte_recue']) {
            echo "  [INCOHÉRENT] Statut RECEPTION_COMPLETE mais quantités différentes\n";
        }
        echo "\n";
    }
    
    // Vérifier les clés étrangères
    echo "=== CLÉS ÉTRANGÈRES ===\n";
    
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    foreach (['supplier_orders', 'supplier_order_items'] as $table) {
        echo "\n$table:\n";
        $stmt->execute([$table]);
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($fks) {
            foreach ($fks as $fk) {
                echo "  - {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        } else {
            echo "  Aucune clé étrangère\n";
        }
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
