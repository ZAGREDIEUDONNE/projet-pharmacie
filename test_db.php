<?php

require_once __DIR__ . '/config/database.php';

// Test de connexion
$result = Database::testConnection();

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);

// Test des tables
$tablesCheck = Database::checkTables();
echo "\n\n=== Vérification des tables ===\n";
echo json_encode($tablesCheck, JSON_PRETTY_PRINT);

// Informations sur la base
$dbInfo = Database::getDatabaseInfo();
echo "\n\n=== Informations base de données ===\n";
echo json_encode($dbInfo, JSON_PRETTY_PRINT);

?>
