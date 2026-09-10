<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ÉTAPE 1 - AUDIT DU SCHÉMA RÉEL ===\n\n";

    // Tables présentes dans schema.sql
    $schemaTables = [
        'utilisateurs', 'roles', 'permissions', 'role_permissions',
        'produits', 'categories', 'fournisseurs', 'lots', 'stock', 'mouvements_stock',
        'clients',
        'ventes', 'ventes_items', 'product_price_history', 'vente_ordonnances', 'vente_ordonnance_items',
        'commandes', 'commande_items',
        'caisse_sessions', 'mouvements_caisse',
        'plan_comptable', 'journal_comptable', 'ecritures_comptables',
        'audit_logs', 'events'
    ];

    // Tables créées par migrations
    $migrationTables = [
        'supplier_orders' => '009_charge_commande_role.sql',
        'supplier_order_items' => '009_charge_commande_role.sql',
        'receptions' => '009_charge_commande_role.sql',
        'reception_items' => '009_charge_commande_role.sql',
        'stock_entries' => '009_charge_commande_role.sql',
        'fournisseur_reglements' => '014_burkina_pharmacy_business_requirements.sql',
        'client_reglements' => '014_burkina_pharmacy_business_requirements.sql',
        'remises_commerciales' => '014_burkina_pharmacy_business_requirements.sql',
        'journaux_comptables' => '007_create_comptabilite_tables.sql',
        'exercices_comptables' => '007_create_comptabilite_tables.sql',
        'rapports_comptables' => '007_create_comptabilite_tables.sql',
        'soldes_comptables' => '007_create_comptabilite_tables.sql',
        'lignes_ecritures' => '013_unify_comptabilite.sql',
        'reglements_clients' => '018_create_reglements_clients_table.sql',
        'role_discount_limits' => '014_burkina_pharmacy_business_requirements.sql'
    ];

    // Tables réellement en base
    $stmt = $pdo->query("SHOW TABLES");
    $realTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Tables utilisées par le code (résultat du scan précédent)
    $codeTables = [
        'utilisateurs', 'roles', 'permissions', 'role_permissions',
        'produits', 'categories', 'fournisseurs', 'lots', 'stock', 'mouvements_stock',
        'clients',
        'ventes', 'ventes_items', 'product_price_history', 'vente_ordonnances', 'vente_ordonnance_items',
        'commandes', 'commande_items',
        'supplier_orders', 'supplier_order_items',
        'receptions', 'reception_items', 'stock_entries',
        'caisse_sessions', 'mouvements_caisse',
        'plan_comptable', 'ecritures_comptables', 'lignes_ecritures', 'journaux_comptables',
        'audit_logs', 'events',
        'client_reglements', 'fournisseur_reglements', 'remises_commerciales',
        'bons'
    ];

    // Toutes les tables à analyser
    $allTables = array_unique(array_merge($schemaTables, array_keys($migrationTables), $realTables));

    // En-tête du tableau
    echo str_pad("TABLE", 35, " ") . " | ";
    echo str_pad("Schema.sql", 12, " ") . " | ";
    echo str_pad("Migration", 12, " ") . " | ";
    echo str_pad("Base MySQL", 12, " ") . " | ";
    echo str_pad("Code PHP", 12, " ") . " | ";
    echo "Structure différente ?\n";
    echo str_repeat("-", 120) . "\n";

    // Pour chaque table
    $sortedTables = $allTables;
    sort($sortedTables);
    foreach ($sortedTables as $table) {
        $inSchema = in_array($table, $schemaTables) ? "OUI" : "NON";
        $migration = isset($migrationTables[$table]) ? "OUI (" . $migrationTables[$table] . ")" : "NON";
        $inBase = in_array($table, $realTables) ? "OUI" : "NON";
        $inCode = in_array($table, $codeTables) ? "OUI" : "NON";
        
        echo str_pad($table, 35, " ") . " | ";
        echo str_pad($inSchema, 12, " ") . " | ";
        echo str_pad($migration, 12, " ") . " | ";
        echo str_pad($inBase, 12, " ") . " | ";
        echo str_pad($inCode, 12, " ") . " | ";
        
        // Vérifier si la structure est différente
        $diff = "";
        if ($inBase === "OUI") {
            if ($inSchema === "NON" && isset($migrationTables[$table])) {
                $diff = "ABSENTE de schema.sql";
            } elseif ($inSchema === "OUI" && !isset($migrationTables[$table])) {
                // Table dans schema mais pas de migration - vérifier colonnes
                $stmt = $pdo->query("DESCRIBE $table");
                $realColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                // Comparaison simplifiée - à affiner
                $diff = "À vérifier";
            }
        }
        echo $diff . "\n";
    }

    echo "\n\n=== RÉSUMÉ ===\n\n";
    echo "Tables dans schema.sql: " . count($schemaTables) . "\n";
    echo "Tables créées par migrations: " . count($migrationTables) . "\n";
    echo "Tables réellement en base: " . count($realTables) . "\n";
    echo "Tables utilisées par le code: " . count($codeTables) . "\n";

    echo "\n=== TABLES PRÉSENTES EN BASE MAIS ABSENTES DE schema.sql ===\n";
    $missingInSchema = array_diff($realTables, $schemaTables);
    $sortedMissing = $missingInSchema;
    sort($sortedMissing);
    foreach ($sortedMissing as $table) {
        if (!str_starts_with($table, 'v_')) { // Exclure les vues
            echo "- $table";
            if (isset($migrationTables[$table])) {
                echo " (migration: " . $migrationTables[$table] . ")";
            }
            echo "\n";
        }
    }

    echo "\n=== TABLES DANS schema.sql MAIS ABSENTES DE LA BASE ===\n";
    $missingInBase = array_diff($schemaTables, $realTables);
    $sortedMissingBase = $missingInBase;
    sort($sortedMissingBase);
    foreach ($sortedMissingBase as $table) {
        echo "- $table\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
