<?php
require __DIR__ . '/vendor/autoload.php';

$db = new PDO('mysql:host=localhost;dbname=pharmacie;charset=utf8mb4', 'root', '');

$stmt = $db->query("DESCRIBE clients");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Structure de la table clients ===\n\n";
foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== Données d'exemple ===\n\n";
$stmt = $db->query("SELECT * FROM clients LIMIT 1");
$sample = $stmt->fetch(PDO::FETCH_ASSOC);
if ($sample) {
    foreach ($sample as $key => $value) {
        echo "$key: $value\n";
    }
} else {
    echo "Aucune donnée dans la table clients\n";
}
