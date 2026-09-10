<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=pharmacie;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Structure de la table caisse_sessions ===\n";
    $stmt = $db->query("DESCRIBE caisse_sessions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n=== Structure de la table mouvements_caisse ===\n";
    $stmt = $db->query("DESCRIBE mouvements_caisse");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
