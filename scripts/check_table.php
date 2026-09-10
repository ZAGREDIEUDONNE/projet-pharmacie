<?php

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "📋 Structure de la table 'utilisateurs':\n";
echo "======================================\n";

$stmt = $db->query('DESCRIBE utilisateurs');
while ($row = $stmt->fetch()) {
    echo sprintf("%-20s %-30s %s\n", $row['Field'], $row['Type'], $row['Null']);
}

echo "\n📄 Exemple de données:\n";
echo "====================\n";

$stmt = $db->query('SELECT * FROM utilisateurs LIMIT 3');
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Username: " . $row['username'] . "\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Password field: " . (isset($row['password']) ? 'EXISTS' : 'NOT FOUND') . "\n";
    echo "---\n";
}
