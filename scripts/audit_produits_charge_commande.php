<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION TABLE PRODUITS - CHARGE_COMMANDE ===\n\n";

    // Colonnes nécessaires pour CHARGE_COMMANDE
    $requiredColumns = [
        'id' => 'Identifiant unique',
        'code_cip' => 'Code CIP du produit',
        'code_barre' => 'Code barre',
        'nom' => 'Nom du produit',
        'description' => 'Description',
        'categorie_id' => 'Catégorie du produit',
        'fournisseur_id' => 'Fournisseur principal',
        'prix_achat' => 'Prix d\'achat',
        'prix_vente' => 'Prix de vente',
        'prix_vente_assure' => 'Prix vente assuré',
        'unite_mesure' => 'Unité de mesure',
        'stock_securite' => 'Seuil de sécurité',
        'stock_alerte' => 'Seuil d\'alerte',
        'is_actif' => 'Produit actif',
        'requires_prescription' => 'Requiert ordonnance',
        'date_peremption_default' => 'Date péremption par défaut',
        'created_at' => 'Date de création',
        'updated_at' => 'Date de modification',
        'deleted_at' => 'Date de suppression'
    ];

    // Colonnes spécifiques Burkina (migration 014)
    $burkinaColumns = [
        'rayon' => 'Rayon du produit',
        'dci' => 'DCI',
        'classe' => 'Classe thérapeutique',
        'forme' => 'Forme pharmaceutique',
        'type_delivrance' => 'Type de délivrance'
    ];

    // Vérifier la structure de la table
    $stmt = $pdo->query("DESCRIBE produits");
    $dbColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dbColumns[$row['Field']] = $row;
    }

    echo "=== COLONNES NÉCESSAIRES ===\n";
    foreach ($requiredColumns as $col => $desc) {
        $exists = isset($dbColumns[$col]);
        echo "$col: " . ($exists ? 'OK' : 'MANQUANTE') . " - $desc\n";
        if ($exists) {
            echo "  Type: {$dbColumns[$col]['Type']}, Null: {$dbColumns[$col]['Null']}, Key: {$dbColumns[$col]['Key']}\n";
        }
    }

    echo "\n=== COLONNES BURKINA ===\n";
    foreach ($burkinaColumns as $col => $desc) {
        $exists = isset($dbColumns[$col]);
        echo "$col: " . ($exists ? 'OK' : 'MANQUANTE') . " - $desc\n";
        if ($exists) {
            echo "  Type: {$dbColumns[$col]['Type']}, Null: {$dbColumns[$col]['Null']}, Key: {$dbColumns[$col]['Key']}\n";
        }
    }

    echo "\n=== COLONNES SUPPLÉMENTAIRES (NON NÉCESSAIRES) ===\n";
    foreach ($dbColumns as $col => $info) {
        if (!isset($requiredColumns[$col]) && !isset($burkinaColumns[$col])) {
            echo "$col: {$info['Type']}\n";
        }
    }

    // Vérifier les clés étrangères
    echo "\n=== CLÉS ÉTRANGÈRES ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'medecin' 
        AND TABLE_NAME = 'produits'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute();
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($fks) {
        foreach ($fks as $fk) {
            echo "{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "Aucune clé étrangère définie\n";
    }

    // Vérifier les index
    echo "\n=== INDEX ===\n";
    $stmt = $pdo->prepare("
        SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = 'medecin' 
        AND TABLE_NAME = 'produits'
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ");
    $stmt->execute();
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexGroups = [];
    foreach ($indexes as $idx) {
        $indexGroups[$idx['INDEX_NAME']][] = $idx['COLUMN_NAME'];
    }

    foreach ($indexGroups as $idxName => $cols) {
        $unique = $idxName === 'PRIMARY' || strpos($idxName, 'UNIQUE') !== false;
        echo "$idxName (" . implode(', ', $cols) . ") " . ($unique ? '[UNIQUE]' : '') . "\n";
    }

    // Vérifier les contraintes CHECK
    echo "\n=== CONTRAINTES CHECK ===\n";
    $stmt = $pdo->query("SHOW CREATE TABLE produits");
    $createTable = $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'];
    
    if (strpos($createTable, 'CONSTRAINT') !== false) {
        preg_match_all('/CONSTRAINT\s+\w+\s+CHECK\s*\((.*?)\)/i', $createTable, $constraints);
        if (!empty($constraints[1])) {
            foreach ($constraints[1] as $constraint) {
                echo "CHECK ($constraint)\n";
            }
        }
    } else {
        echo "Aucune contrainte CHECK\n";
    }

    // Vérifier les données
    echo "\n=== DONNÉES ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total produits: $total\n";

    $stmt = $pdo->query("SELECT COUNT(*) as actif FROM produits WHERE is_actif = 1 AND deleted_at IS NULL");
    $actif = $stmt->fetch(PDO::FETCH_ASSOC)['actif'];
    echo "Produits actifs: $actif\n";

    $stmt = $pdo->query("SELECT COUNT(*) as sans_fournisseur FROM produits WHERE fournisseur_id IS NULL");
    $sansFournisseur = $stmt->fetch(PDO::FETCH_ASSOC)['sans_fournisseur'];
    echo "Produits sans fournisseur: $sansFournisseur\n";

    $stmt = $pdo->query("SELECT COUNT(*) as sans_categorie FROM produits WHERE categorie_id IS NULL");
    $sansCategorie = $stmt->fetch(PDO::FETCH_ASSOC)['sans_categorie'];
    echo "Produits sans catégorie: $sansCategorie\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
