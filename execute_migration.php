<?php
require 'config/database.php';

try {
    $pdo = Database::getConnection();
    
    echo "=== EXÉCUTION MIGRATION HISTORIQUE PRIX ===\n\n";
    
    $sql = file_get_contents('database/migrations/create_historique_prix_table.sql');
    
    // Séparer les requêtes SQL et filtrer les commentaires
    $queries = array_filter(array_map('trim', explode(';', $sql)), function($query) {
        // Ignorer les lignes vides et les commentaires
        return !empty($query) && !preg_match('/^--/', $query);
    });
    
    $pdo->beginTransaction();
    
    foreach ($queries as $query) {
        if (!empty($query) && !preg_match('/^--/', $query)) {
            echo "Exécution: " . substr($query, 0, 50) . "...\n";
            $pdo->exec($query);
        }
    }
    
    $pdo->commit();
    
    echo "\n✓ Migration exécutée avec succès\n";
    echo "  - Table historique_prix créée\n";
    echo "  - Permission produit.modifier_prix créée\n";
    echo "  - Permission attribuée aux rôles Administrateur et Chargé de commande\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
