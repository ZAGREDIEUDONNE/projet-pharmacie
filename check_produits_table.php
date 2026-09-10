<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=pharmacie;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Structure de la table produits ===\n";
    $stmt = $db->query("DESCRIBE produits");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
