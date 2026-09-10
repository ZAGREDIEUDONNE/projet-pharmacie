<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== STRUCTURE TABLE 'receptions' ===\n";
    $stmt = $pdo->query("DESCRIBE receptions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    echo "=== STRUCTURE TABLE 'reception_items' ===\n";
    $stmt = $pdo->query("DESCRIBE reception_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    echo "=== STRUCTURE TABLE 'stock' ===\n";
    $stmt = $pdo->query("DESCRIBE stock");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

    echo "=== STRUCTURE TABLE 'mouvements_stock' ===\n";
    $stmt = $pdo->query("DESCRIBE mouvements_stock");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']} {$row['Null']} {$row['Key']}\n";
    }
    echo "\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
