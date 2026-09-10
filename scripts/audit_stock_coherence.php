<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION COHÉRENCE STOCK / STOCK_ENTRIES / MOUVEMENTS_STOCK ===\n\n";

    // Vérifier la structure de chaque table
    $tables = ['stock', 'stock_entries', 'mouvements_stock'];

    foreach ($tables as $table) {
        echo "=== TABLE: $table ===\n";
        
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Colonnes (" . count($columns) . "):\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
        }
        
        // Vérifier les clés étrangères
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([$table]);
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($fks) {
            echo "Clés étrangères:\n";
            foreach ($fks as $fk) {
                echo "  - {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        } else {
            echo "Clés étrangères: Aucune\n";
        }
        
        echo "\n";
    }

    // Vérifier les données
    echo "=== VÉRIFICATION DES DONNÉES ===\n\n";

    // Stock
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stock");
    $stockTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Stock: $stockTotal enregistrements\n";

    // Stock entries
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stock_entries");
    $entriesTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Stock entries: $entriesTotal enregistrements\n";

    // Mouvements stock
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mouvements_stock");
    $mouvementsTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Mouvements stock: $mouvementsTotal enregistrements\n";

    // Vérifier la cohérence des quantités
    echo "\n=== VÉRIFICATION COHÉRENCE QUANTITÉS ===\n\n";

    // Pour chaque produit, vérifier: stock_actuel = sum(stock_entries) - sum(sorties)
    $stmt = $pdo->query("
        SELECT 
            s.produit_id,
            p.nom as produit_nom,
            s.quantite_disponible as stock_actuel,
            s.quantite_theorique as stock_theorique,
            (SELECT COALESCE(SUM(quantite), 0) FROM stock_entries WHERE produit_id = s.produit_id) as total_entrees,
            (SELECT COALESCE(SUM(quantite), 0) FROM mouvements_stock 
             WHERE produit_id = s.produit_id AND type_mouvement = 'SORTIE') as total_sorties
        FROM stock s
        LEFT JOIN produits p ON s.produit_id = p.id
        LIMIT 10
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $calcule = $row['total_entrees'] - $row['total_sorties'];
        $ecart = abs($row['stock_actuel'] - $calcule);
        
        echo "Produit {$row['produit_id']} ({$row['produit_nom']}):\n";
        echo "  Stock actuel: {$row['stock_actuel']}\n";
        echo "  Stock théorique: {$row['stock_theorique']}\n";
        echo "  Total entrées: {$row['total_entrees']}\n";
        echo "  Total sorties: {$row['total_sorties']}\n";
        echo "  Calculé (entrées - sorties): $calcule\n";
        echo "  Écart: $ecart " . ($ecart > 0.01 ? "[INCOHÉRENT]" : "[OK]") . "\n\n";
    }

    // Vérifier les mouvements avec quantite_avant et quantite_apres
    echo "=== VÉRIFICATION MOUVEMENTS (avant/après) ===\n\n";

    $stmt = $pdo->query("
        SELECT 
            id,
            produit_id,
            type_mouvement,
            quantite,
            quantite_avant,
            quantite_apres,
            (quantite_apres - quantite_avant) as delta
        FROM mouvements_stock
        ORDER BY date_mouvement DESC
        LIMIT 5
    ");
    $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mouvements as $mvt) {
        $expectedDelta = $mvt['type_mouvement'] === 'ENTREE' ? $mvt['quantite'] : -$mvt['quantite'];
        $coherent = abs($mvt['delta'] - $expectedDelta) < 0.01;
        
        echo "Mouvement {$mvt['id']}:\n";
        echo "  Type: {$mvt['type_mouvement']}\n";
        echo "  Quantité: {$mvt['quantite']}\n";
        echo "  Avant: {$mvt['quantite_avant']}\n";
        echo "  Après: {$mvt['quantite_apres']}\n";
        echo "  Delta réel: {$mvt['delta']}\n";
        echo "  Delta attendu: $expectedDelta\n";
        echo "  Cohérent: " . ($coherent ? "OUI" : "NON") . "\n\n";
    }

    // Vérifier les relations
    echo "=== VÉRIFICATION RELATIONS ===\n\n";

    // Vérifier si stock_entries.reception_id existe
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stock_entries WHERE reception_id IS NOT NULL");
    $receptionLinks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Stock entries liés à des réceptions: $receptionLinks\n";

    // Vérifier si mouvements_stock.reference_type est utilisé
    $stmt = $pdo->query("SELECT reference_type, COUNT(*) as total FROM mouvements_stock GROUP BY reference_type");
    $refTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Types de référence dans mouvements_stock:\n";
    foreach ($refTypes as $ref) {
        echo "  - {$ref['reference_type']}: {$ref['total']}\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
