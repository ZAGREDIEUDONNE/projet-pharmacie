<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=pharmacie;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier si stock_minimum existe
    $stmt = $db->query("DESCRIBE produits");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[$row['Field']] = $row['Type'];
    }
    
    if (!array_key_exists('stock_minimum', $existingColumns)) {
        echo "Ajout de stock_minimum...\n";
        $db->exec("ALTER TABLE produits ADD COLUMN stock_minimum INT DEFAULT 0 AFTER stock");
        echo "✓ stock_minimum ajouté\n";
    } else {
        echo "⊘ stock_minimum existe déjà\n";
    }
    
    echo "\n=== Migration terminée avec succès ===\n";
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
