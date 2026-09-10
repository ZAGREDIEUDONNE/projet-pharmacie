<?php
require 'config/database.php';

try {
    $pdo = Database::getConnection();
    
    echo "=== VÉRIFICATION TABLE HISTORIQUE DES PRIX ===\n\n";
    
    // Vérifier si la table historique_prix existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'historique_prix'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'historique_prix' existe déjà\n\n";
        
        // Afficher la structure
        echo "Structure de la table :\n";
        $stmt = $pdo->query("DESCRIBE historique_prix");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            echo "  - {$column['Field']} : {$column['Type']} {$column['Null']} {$column['Key']} {$column['Default']}\n";
        }
        
        // Compter les enregistrements
        $count = $pdo->query("SELECT COUNT(*) as count FROM historique_prix")->fetch()['count'];
        echo "\nNombre d'enregistrements : $count\n";
        
    } else {
        echo "✗ Table 'historique_prix' n'existe pas\n";
        echo "→ Une migration sera nécessaire\n";
    }
    
    echo "\n=== VÉRIFICATION TABLE PRODUITS ===\n\n";
    
    // Vérifier la structure de la table produits
    $stmt = $pdo->query("DESCRIBE produits");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Structure de la table produits :\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} : {$column['Type']} {$column['Null']} {$column['Key']} {$column['Default']}\n";
    }
    
    // Vérifier les colonnes de prix
    echo "\nColonnes de prix dans produits :\n";
    $priceColumns = array_filter($columns, function($col) {
        return stripos($col['Field'], 'prix') !== false || stripos($col['Field'], 'price') !== false;
    });
    
    if (!empty($priceColumns)) {
        foreach ($priceColumns as $column) {
            echo "  - {$column['Field']} : {$column['Type']}\n";
        }
    } else {
        echo "  ✗ Aucune colonne de prix trouvée\n";
    }
    
    echo "\n=== VÉRIFICATION PERMISSIONS ===\n\n";
    
    // Vérifier la structure de la table permissions
    $stmt = $pdo->query("DESCRIBE permissions");
    $permColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Structure de la table permissions :\n";
    foreach ($permColumns as $column) {
        echo "  - {$column['Field']} : {$column['Type']}\n";
    }
    
    // Vérifier si la permission produit.modifier_prix existe
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE code = 'produit.modifier_prix'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "\n✓ Permission 'produit.modifier_prix' existe\n";
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  ID: {$perm['id']}\n";
        echo "  Nom: {$perm['nom']}\n";
        echo "  Code: {$perm['code']}\n";
        echo "  Description: {$perm['description']}\n";
    } else {
        echo "\n✗ Permission 'produit.modifier_prix' n'existe pas\n";
        echo "→ La permission sera créée\n";
    }
    
    // Vérifier les permissions existantes pour les produits
    echo "\nPermissions existantes pour produits :\n";
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE code LIKE 'produit%'");
    $stmt->execute();
    $productPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($productPerms as $perm) {
        echo "  - {$perm['code']} : {$perm['nom']}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
