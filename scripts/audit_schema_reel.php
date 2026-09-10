<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT DU SCHÉMA RÉEL - BASE DE DONNÉES MEDECIN ===\n\n";

    // 1. Lister toutes les tables
    echo "=== 1. TABLES PRÉSENTES EN BASE ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    echo "\nTotal tables: " . count($tables) . "\n\n";

    // 2. Pour chaque table, détailler la structure
    echo "=== 2. STRUCTURE DÉTAILLÉE DES TABLES ===\n\n";
    
    foreach ($tables as $table) {
        echo "--- TABLE: $table ---\n";
        
        // Colonnes
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Colonnes:\n";
        foreach ($columns as $col) {
            echo "  {$col['Field']} : {$col['Type']} {$col['Null']} {$col['Key']} {$col['Default']}\n";
        }
        
        // Index
        $stmt = $pdo->query("SHOW INDEX FROM $table");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($indexes) {
            echo "\nIndex:\n";
            foreach ($indexes as $idx) {
                echo "  {$idx['Key_name']} ({$idx['Column_name']}) - {$idx['Index_type']}\n";
            }
        }
        
        // Contraintes étrangères
        $stmt = $pdo->query("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'medecin'
            AND TABLE_NAME = '$table'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($fks) {
            echo "\nClés étrangères:\n";
            foreach ($fks as $fk) {
                echo "  {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        }
        
        echo "\n";
    }

    // 3. Tables spécifiques à vérifier
    echo "=== 3. TABLES CRITIQUES À VÉRIFIER ===\n\n";
    
    $criticalTables = [
        'supplier_orders',
        'supplier_order_items',
        'receptions',
        'reception_items',
        'stock_entries',
        'fournisseur_reglements',
        'client_reglements',
        'remises_commerciales',
        'ecritures_comptables',
        'lignes_ecritures',
        'journaux_comptables',
        'plan_comptable'
    ];
    
    foreach ($criticalTables as $table) {
        if (in_array($table, $tables)) {
            echo "[OK] $table existe\n";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "  Colonnes: " . count($columns) . "\n";
        } else {
            echo "[MANQUANT] $table n'existe pas\n";
        }
    }

    // 4. Colonnes comptables spécifiques
    echo "\n=== 4. COLONNES COMPTABLES SPÉCIFIQUES ===\n\n";
    
    $comptableColumns = [
        'ventes' => ['montant_ht', 'montant_tva', 'ecriture_id'],
        'commandes' => ['montant_ht', 'montant_tva', 'ecriture_id'],
        'mouvements_caisse' => ['ecriture_id', 'vente_id', 'client_id', 'fournisseur_id', 'type_depense', 'supprime'],
        'caisse_sessions' => ['montant_ventes', 'montant_theorique', 'ecart'],
        'clients' => ['solde_credit', 'matricule'],
        'stock' => ['date_peremption']
    ];
    
    foreach ($comptableColumns as $table => $columns) {
        if (in_array($table, $tables)) {
            echo "--- $table ---\n";
            $stmt = $pdo->query("DESCRIBE $table");
            $existingColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
            foreach ($columns as $col) {
                if (in_array($col, $existingColumns)) {
                    echo "  [OK] $col existe\n";
                } else {
                    echo "  [MANQUANT] $col n'existe pas\n";
                }
            }
        } else {
            echo "--- $table ---\n";
            echo "  [ERREUR] Table n'existe pas\n";
        }
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
