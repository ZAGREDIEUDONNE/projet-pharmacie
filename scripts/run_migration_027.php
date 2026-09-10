<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Exécution de la migration 027...\n";

    $migrationFile = 'database/migrations/027_add_export_supplier_orders_permission.sql';
    $sql = file_get_contents($migrationFile);

    $pdo->exec($sql);

    echo "Migration 027 exécutée avec succès.\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
