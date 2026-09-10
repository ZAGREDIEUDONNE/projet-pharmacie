<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION TABLE FOURNISSEURS ===\n\n";

    // Vérifier la structure
    $stmt = $pdo->query("DESCRIBE fournisseurs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    // Vérifier les données
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fournisseurs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nTotal fournisseurs: $total\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as actif FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL");
    $actif = $stmt->fetch(PDO::FETCH_ASSOC)['actif'];
    echo "Fournisseurs actifs: $actif\n";
    
    // Vérifier les relations avec les commandes
    echo "\n=== RELATIONS AVEC COMMANDES ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            f.id,
            f.nom,
            (SELECT COUNT(*) FROM supplier_orders WHERE fournisseur_id = f.id) as nb_supplier_orders,
            (SELECT COUNT(*) FROM commandes WHERE fournisseur_id = f.id) as nb_commandes,
            (SELECT COUNT(*) FROM receptions WHERE fournisseur_id = f.id) as nb_receptions,
            (SELECT COUNT(*) FROM fournisseur_reglements WHERE fournisseur_id = f.id) as nb_reglements
        FROM fournisseurs f
        LIMIT 5
    ");
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($fournisseurs as $f) {
        echo "Fournisseur {$f['id']} ({$f['nom']}):\n";
        echo "  Supplier orders: {$f['nb_supplier_orders']}\n";
        echo "  Commandes (ancien système): {$f['nb_commandes']}\n";
        echo "  Réceptions: {$f['nb_receptions']}\n";
        echo "  Règlements: {$f['nb_reglements']}\n";
        echo "\n";
    }
    
    // Vérifier les clés étrangères
    echo "=== CLÉS ÉTRANGÈRES ===\n";
    
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = 'fournisseurs'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute();
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($fks) {
        foreach ($fks as $fk) {
            echo "{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "Aucune clé étrangère\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
