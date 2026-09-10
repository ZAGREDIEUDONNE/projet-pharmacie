<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TABLES COMMANDES FOURNISSEURS ===\n\n";

    // Vérifier les tables commandes
    $stmt = $pdo->query("SHOW TABLES LIKE 'commande%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables commençant par 'commande':\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";

    // Vérifier les tables supplier
    $stmt = $pdo->query("SHOW TABLES LIKE 'supplier%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables commençant par 'supplier':\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";

    // Vérifier les tables reception
    $stmt = $pdo->query("SHOW TABLES LIKE 'reception%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables commençant par 'reception':\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";

    // Structure de la table commandes
    echo "=== STRUCTURE TABLE 'commandes' ===\n";
    $stmt = $pdo->query("DESCRIBE commandes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    // Structure de la table supplier_orders
    echo "=== STRUCTURE TABLE 'supplier_orders' ===\n";
    $stmt = $pdo->query("DESCRIBE supplier_orders");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    // Structure de la table commande_items
    echo "=== STRUCTURE TABLE 'commande_items' ===\n";
    $stmt = $pdo->query("DESCRIBE commande_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    // Structure de la table supplier_order_items
    echo "=== STRUCTURE TABLE 'supplier_order_items' ===\n";
    $stmt = $pdo->query("DESCRIBE supplier_order_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    // Structure de la table receptions
    echo "=== STRUCTURE TABLE 'receptions' ===\n";
    $stmt = $pdo->query("DESCRIBE receptions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    // Structure de la table reception_items
    echo "=== STRUCTURE TABLE 'reception_items' ===\n";
    $stmt = $pdo->query("DESCRIBE reception_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    // Données dans commandes
    echo "=== DONNÉES TABLE 'commandes' ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM commandes WHERE deleted_at IS NULL");
    echo "Nombre de commandes: " . $stmt->fetch(PDO::FETCH_ASSOC)['count'] . "\n\n";

    // Données dans supplier_orders
    echo "=== DONNÉES TABLE 'supplier_orders' ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM supplier_orders");
    echo "Nombre de supplier_orders: " . $stmt->fetch(PDO::FETCH_ASSOC)['count'] . "\n\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
