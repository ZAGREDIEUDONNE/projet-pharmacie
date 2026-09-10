<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT DÉTAILLÉ - MODULE CHARGE_COMMANDE ===\n\n";

    // Tables identifiées comme utilisées par CHARGE_COMMANDE
    $tables = [
        'produits',
        'categories',
        'fournisseurs',
        'stock',
        'mouvements_stock',
        'stock_entries',
        'lots',
        'supplier_orders',
        'supplier_order_items',
        'receptions',
        'reception_items',
        'fournisseur_reglements',
        'commandes',
        'commande_items',
        'ventes',
        'ventes_items'
    ];

    // Mapping des fonctionnalités par table
    $functionalities = [
        'produits' => 'Catalogue produits, recherche produit, création produit, modification produit, prix produits',
        'categories' => 'Classification des produits',
        'fournisseurs' => 'Gestion fournisseurs, commandes fournisseurs, réceptions',
        'stock' => 'Stock actuel, alertes stock, ruptures, stock faible, stock critique',
        'mouvements_stock' => 'Historique mouvements, entrées, sorties, ajustements',
        'stock_entries' => 'Traçabilité détaillée des entrées de stock',
        'lots' => 'Gestion des lots, péremptions',
        'supplier_orders' => 'Commandes fournisseurs, création, modification, envoi, annulation',
        'supplier_order_items' => 'Détails des commandes fournisseurs',
        'receptions' => 'Réception produits, réception partielle, réception complète',
        'reception_items' => 'Détails des réceptions',
        'fournisseur_reglements' => 'Règlements fournisseurs, dettes',
        'commandes' => 'Ancien système commandes (utilisé par StockService)',
        'commande_items' => 'Ancien système commandes (utilisé par StockService)',
        'ventes' => 'Utilisé par StockService pour les sorties de stock',
        'ventes_items' => 'Utilisé par StockService pour les sorties de stock'
    ];

    // Mapping des services par table
    $services = [
        'produits' => 'ChargeCommandeService, StockService, CommandeAutomatiqueService',
        'categories' => 'ChargeCommandeService',
        'fournisseurs' => 'ChargeCommandeService, CommandeAutomatiqueService',
        'stock' => 'ChargeCommandeService, StockService, CommandeAutomatiqueService',
        'mouvements_stock' => 'ChargeCommandeService, StockService',
        'stock_entries' => 'ChargeCommandeService',
        'lots' => 'ChargeCommandeService',
        'supplier_orders' => 'ChargeCommandeService, CommandeAutomatiqueService',
        'supplier_order_items' => 'ChargeCommandeService, CommandeAutomatiqueService',
        'receptions' => 'ChargeCommandeService',
        'reception_items' => 'ChargeCommandeService',
        'fournisseur_reglements' => 'ChargeCommandeService',
        'commandes' => 'StockService',
        'commande_items' => 'StockService',
        'ventes' => 'StockService',
        'ventes_items' => 'StockService'
    ];

    // Mapping des contrôleurs par table
    $controllers = [
        'produits' => 'ChargeCommandeController',
        'categories' => 'ChargeCommandeController',
        'fournisseurs' => 'ChargeCommandeController',
        'stock' => 'ChargeCommandeController',
        'mouvements_stock' => 'ChargeCommandeController',
        'stock_entries' => 'ChargeCommandeController',
        'lots' => 'ChargeCommandeController',
        'supplier_orders' => 'ChargeCommandeController',
        'supplier_order_items' => 'ChargeCommandeController',
        'receptions' => 'ChargeCommandeController',
        'reception_items' => 'ChargeCommandeController',
        'fournisseur_reglements' => 'ChargeCommandeController',
        'commandes' => 'StockController',
        'commande_items' => 'StockController',
        'ventes' => 'VenteController',
        'ventes_items' => 'VenteController'
    ];

    foreach ($tables as $table) {
        echo "=== TABLE: $table ===\n\n";
        
        // Vérifier si la table existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                              WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        
        echo "- utilisée par : " . ($services[$table] ?? 'Non identifié') . "\n";
        echo "- contrôleur : " . ($controllers[$table] ?? 'Non identifié') . "\n";
        echo "- service : " . ($services[$table] ?? 'Non identifié') . "\n";
        echo "- modèle : Non identifié (pas de modèle explicite)\n";
        echo "- routes : /commande/* (via ChargeCommandeController)\n";
        echo "- fonctionnalités : " . ($functionalities[$table] ?? 'Non identifié') . "\n";
        echo "- état actuel : " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
        
        if ($exists) {
            // Colonnes
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "- colonnes utilisées : " . count($columns) . " colonnes\n";
            foreach ($columns as $col) {
                echo "  * {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
            }
            
            // Clés étrangères
            $stmt = $pdo->prepare("
                SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = 'medecin' 
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            $stmt->execute([$table]);
            $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($fks) {
                echo "- clés étrangères :\n";
                foreach ($fks as $fk) {
                    echo "  * {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} ({$fk['CONSTRAINT_NAME']})\n";
                }
            } else {
                echo "- clés étrangères : Aucune\n";
            }
            
            // Index
            $stmt = $pdo->prepare("
                SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = 'medecin' 
                AND TABLE_NAME = ?
                AND INDEX_NAME != 'PRIMARY'
                ORDER BY INDEX_NAME, SEQ_IN_INDEX
            ");
            $stmt->execute([$table]);
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($indexes) {
                $indexGroups = [];
                foreach ($indexes as $idx) {
                    $indexGroups[$idx['INDEX_NAME']][] = $idx['COLUMN_NAME'];
                }
                
                echo "- index :\n";
                foreach ($indexGroups as $idxName => $cols) {
                    echo "  * $idxName (" . implode(', ', $cols) . ")\n";
                }
            } else {
                echo "- index : Aucun\n";
            }
            
            // Relations (basées sur les FK)
            echo "- relations :\n";
            if ($fks) {
                foreach ($fks as $fk) {
                    echo "  * {$table}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
                }
            } else {
                echo "  * Aucune relation explicite\n";
            }
        } else {
            echo "- colonnes utilisées : TABLE MANQUANTE\n";
            echo "- clés étrangères : N/A\n";
            echo "- index : N/A\n";
            echo "- relations : N/A\n";
        }
        
        echo "\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
