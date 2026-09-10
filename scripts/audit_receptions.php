<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION RECEPTIONS / RECEPTION_ITEMS ===\n\n";

    // Vérifier receptions
    echo "=== TABLE: receptions ===\n";
    
    $stmt = $pdo->query("DESCRIBE receptions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    // Vérifier les statuts utilisés
    echo "\nStatuts utilisés:\n";
    $stmt = $pdo->query("SELECT DISTINCT statut, COUNT(*) as total FROM receptions GROUP BY statut");
    $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuts as $statut) {
        echo "  - {$statut['statut']}: {$statut['total']}\n";
    }
    
    // Vérifier les données
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM receptions");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nTotal réceptions: $total\n";
    
    // Vérifier reception_items
    echo "\n=== TABLE: reception_items ===\n";
    
    $stmt = $pdo->query("DESCRIBE reception_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    // Vérifier les données
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reception_items");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nTotal items: $total\n";
    
    // Vérifier la cohérence entre réceptions et commandes
    echo "\n=== VÉRIFICATION COHÉRENCE ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            r.id,
            r.numero_reception,
            r.statut,
            r.supplier_order_id,
            so.numero_commande,
            so.statut as commande_statut,
            (SELECT COUNT(*) FROM reception_items WHERE reception_id = r.id) as nb_items,
            (SELECT SUM(quantite_attendue) FROM reception_items WHERE reception_id = r.id) as total_qte_attendue,
            (SELECT SUM(quantite_recue) FROM reception_items WHERE reception_id = r.id) as total_qte_recue
        FROM receptions r
        LEFT JOIN supplier_orders so ON r.supplier_order_id = so.id
        LIMIT 5
    ");
    $receptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($receptions as $rec) {
        echo "Réception {$rec['id']} ({$rec['numero_reception']}):\n";
        echo "  Statut: {$rec['statut']}\n";
        echo "  Commande liée: {$rec['numero_commande']} (statut: {$rec['commande_statut']})\n";
        echo "  Nombre items: {$rec['nb_items']}\n";
        echo "  Total quantité attendue: {$rec['total_qte_attendue']}\n";
        echo "  Total quantité reçue: {$rec['total_qte_recue']}\n";
        
        if ($rec['statut'] === 'RECU_COMPLET' && $rec['total_qte_attendue'] != $rec['total_qte_recue']) {
            echo "  [INCOHÉRENT] Statut RECU_COMPLET mais quantités différentes\n";
        }
        
        // Vérifier les écarts
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as nb_ecarts 
            FROM reception_items 
            WHERE reception_id = ? AND ecart != 0
        ");
        $stmt2->execute([$rec['id']]);
        $ecarts = $stmt2->fetch(PDO::FETCH_ASSOC)['nb_ecarts'];
        
        if ($ecarts > 0) {
            echo "  Écarts détectés: $ecarts items\n";
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
    
    foreach (['receptions', 'reception_items'] as $table) {
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
