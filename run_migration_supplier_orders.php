<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Exécution de la migration update_supplier_orders.sql...\n\n";

    $sql = file_get_contents('migrations/update_supplier_orders.sql');
    
    // Séparer les requêtes
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Requête exécutée avec succès\n";
        } catch (PDOException $e) {
            echo "✗ Erreur: " . $e->getMessage() . "\n";
            echo "  Requête: " . substr($statement, 0, 100) . "...\n";
        }
    }

    echo "\n=== Migration terminée ===\n";
    
    // Vérifier la structure mise à jour
    echo "\nVérification de la structure de supplier_orders:\n";
    $stmt = $pdo->query("DESCRIBE supplier_orders");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} : {$row['Type']}\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
