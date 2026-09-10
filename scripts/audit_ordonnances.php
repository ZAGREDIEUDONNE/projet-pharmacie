<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT COMPLET - MODULE ORDONNANCES ===\n\n";

    // 1. Vérification des tables
    echo "=== 1. TABLES EXISTANTES ===\n";
    $tables = ['ordonnances', 'vente_ordonnances', 'vente_ordonnance_items'];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "$table: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";

        if ($exists) {
            $stmt = $pdo->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "  Colonnes:\n";
            foreach ($columns as $col) {
                echo "    - {$col['Field']} ({$col['Type']})\n";
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "  Nombre d'enregistrements: $count\n";
        }
    }
    echo "\n";

    // 2. Vérification des permissions
    echo "=== 2. PERMISSIONS LIÉES AUX ORDONNANCES ===\n";
    $stmt = $pdo->prepare("SELECT nom, description, module FROM permissions WHERE nom LIKE '%ordonnance%' OR module LIKE '%ordonnance%'");
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($permissions)) {
        echo "Aucune permission liée aux ordonnances trouvée.\n";
    } else {
        foreach ($permissions as $perm) {
            echo "- {$perm['nom']} ({$perm['module']})\n";
            if ($perm['description']) {
                echo "  Description: {$perm['description']}\n";
            }
        }
    }
    echo "\n";

    // 3. Vérification des liens avec les ventes
    echo "=== 3. LIENS AVEC LES VENTES ===\n";
    $stmt = $pdo->prepare("SHOW TABLES LIKE '%vente%'");
    $stmt->execute();
    $venteTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tables vente:\n";
    foreach ($venteTables as $table) {
        echo "- $table\n";
    }
    echo "\n";

    // 4. Vérification des colonnes ordonnance_id dans les ventes
    echo "=== 4. COLONNES ORDONNANCE_ID DANS LES TABLES ===\n";
    foreach ($venteTables as $table) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE '%ordonnance%'");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($columns)) {
            echo "$table:\n";
            foreach ($columns as $col) {
                echo "  - {$col['Field']} ({$col['Type']})\n";
            }
        }
    }
    echo "\n";

    // 5. Vérification des données dans ordonnances
    echo "=== 5. DONNÉES DANS ORDONNANCES ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordonnances");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Nombre d'ordonnances: $count\n";

    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT * FROM ordonnances LIMIT 5");
        $stmt->execute();
        $ordonnances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Exemples d'ordonnances:\n";
        foreach ($ordonnances as $ord) {
            echo "- ID: {$ord['id']}, Numéro: {$ord['numero_ordonnance']}, Patient: {$ord['nom_patient']}, Date: {$ord['date_ordonnance']}\n";
        }
    }
    echo "\n";

    // 6. Vérification des données dans vente_ordonnances
    echo "=== 6. DONNÉES DANS VENTE_ORDONNANCES ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vente_ordonnances");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Nombre de liens vente-ordonnance: $count\n";

    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT * FROM vente_ordonnances LIMIT 5");
        $stmt->execute();
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Exemples de liens:\n";
        foreach ($links as $link) {
            echo "- ID: {$link['id']}, Vente ID: {$link['vente_id']}, Ordonnance ID: {$link['ordonnance_id']}\n";
        }
    }
    echo "\n";

    // 7. Vérification des données dans vente_ordonnance_items
    echo "=== 7. DONNÉES DANS VENTE_ORDONNANCE_ITEMS ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vente_ordonnance_items");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Nombre d'items vente-ordonnance: $count\n";

    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT * FROM vente_ordonnance_items LIMIT 5");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Exemples d'items:\n";
        foreach ($items as $item) {
            echo "- ID: {$item['id']}, Ordonnance ID: {$item['ordonnance_id']}, Vente ID: {$item['vente_id']}, Produit ID: {$item['produit_id']}\n";
        }
    }
    echo "\n";

    // 8. Vérification des colonnes ordonnance dans la table ventes
    echo "=== 8. COLONNES ORDONNANCE DANS LA TABLE VENTES ===\n";
    $stmt = $pdo->prepare("SHOW COLUMNS FROM ventes LIKE '%ordonnance%'");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($columns)) {
        echo "Aucune colonne ordonnance dans la table ventes.\n";
    } else {
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
    }
    echo "\n";

    echo "=== FIN DE L'AUDIT ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
