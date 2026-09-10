<?php
require_once __DIR__ . '/../config/database.php';

try {
    $result = Database::executeScript(__DIR__ . '/../database/migrations/021_sync_charge_commande_permissions.sql');
    
    if ($result['success']) {
        echo "Migration 021 exécutée avec succès : Synchronisation des permissions CHARGE_COMMANDE.\n";
        echo "Requêtes exécutées : " . $result['queries_executed'] . "\n";
    } else {
        echo "Erreur : " . $result['message'] . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
