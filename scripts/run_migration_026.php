<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Exécution de la migration 026...\n";

    // Lire le fichier de migration
    $migrationFile = 'database/migrations/026_add_reception_ecriture_id.sql';
    $sql = file_get_contents($migrationFile);

    // Exécuter la migration
    $pdo->exec($sql);

    echo "Migration 026 exécutée avec succès.\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
