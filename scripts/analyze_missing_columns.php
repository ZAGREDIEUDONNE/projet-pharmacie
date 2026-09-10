<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ÉTAPE 3 - ANALYSE COLONNES ABSENTES DE MYSQL ===\n\n";

    $columnsToAnalyze = [
        'commandes' => ['montant_ht', 'montant_tva'],
        'mouvements_caisse' => ['vente_id', 'client_id', 'fournisseur_id', 'type_depense'],
        'stock' => ['date_peremption']
    ];

    echo str_pad("TABLE", 20, " ") . " | ";
    echo str_pad("COLONNE", 20, " ") . " | ";
    echo str_pad("TYPE PROPOSÉ", 20, " ") . " | ";
    echo str_pad("NULL", 8, " ") . " | ";
    echo str_pad("DEFAULT", 15, " ") . " | ";
    echo str_pad("INDEX", 10, " ") . " | ";
    echo str_pad("FOREIGN KEY", 15, " ") . " | ";
    echo "UTILISATION\n";
    echo str_repeat("-", 150) . "\n";

    foreach ($columnsToAnalyze as $table => $columns) {
        foreach ($columns as $col) {
            // Vérifier si la colonne existe déjà
            $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
            $exists = $stmt->fetch();
            
            if ($exists) {
                echo str_pad($table, 20, " ") . " | ";
                echo str_pad($col, 20, " ") . " | ";
                echo str_pad($exists['Type'], 20, " ") . " | ";
                echo str_pad($exists['Null'], 8, " ") . " | ";
                echo str_pad($exists['Default'] ?? 'NULL', 15, " ") . " | ";
                echo str_pad($exists['Key'], 10, " ") . " | ";
                echo str_pad("EXISTE DEJA", 15, " ") . " | ";
                echo "Ne pas ajouter\n";
            } else {
                // Proposer un type basé sur l'usage
                $type = 'DECIMAL(10,2)';
                $null = 'YES';
                $default = '0.00';
                $index = 'INDEX';
                $fk = '';
                $usage = '';
                
                if ($table === 'commandes' && ($col === 'montant_ht' || $col === 'montant_tva')) {
                    $type = 'DECIMAL(10,2)';
                    $null = 'YES';
                    $default = '0.00';
                    $index = '';
                    $fk = '';
                    $usage = 'Calcul TVA, écritures comptables';
                } elseif ($table === 'mouvements_caisse') {
                    if ($col === 'vente_id') {
                        $type = 'INT';
                        $null = 'YES';
                        $default = 'NULL';
                        $index = 'INDEX';
                        $fk = 'ventes(id)';
                        $usage = 'Lien avec vente, annulation';
                    } elseif ($col === 'client_id') {
                        $type = 'INT';
                        $null = 'YES';
                        $default = 'NULL';
                        $index = 'INDEX';
                        $fk = 'clients(id)';
                        $usage = 'Lien avec client, règlements';
                    } elseif ($col === 'fournisseur_id') {
                        $type = 'INT';
                        $null = 'YES';
                        $default = 'NULL';
                        $index = 'INDEX';
                        $fk = 'fournisseurs(id)';
                        $usage = 'Lien avec fournisseur, règlements';
                    } elseif ($col === 'type_depense') {
                        $type = 'VARCHAR(50)';
                        $null = 'YES';
                        $default = 'NULL';
                        $index = '';
                        $fk = '';
                        $usage = 'Type de dépense pour comptabilité';
                    }
                } elseif ($table === 'stock' && $col === 'date_peremption') {
                    $type = 'DATE';
                    $null = 'YES';
                    $default = 'NULL';
                    $index = 'INDEX';
                    $fk = '';
                    $usage = 'Alertes péremption, inventaire';
                }
                
                echo str_pad($table, 20, " ") . " | ";
                echo str_pad($col, 20, " ") . " | ";
                echo str_pad($type, 20, " ") . " | ";
                echo str_pad($null, 8, " ") . " | ";
                echo str_pad($default, 15, " ") . " | ";
                echo str_pad($index, 10, " ") . " | ";
                echo str_pad($fk, 15, " ") . " | ";
                echo $usage . "\n";
            }
        }
    }

    // Vérifier les migrations existantes
    echo "\n\n=== VÉRIFICATION DES MIGRATIONS EXISTANTES ===\n\n";
    
    $migrationDir = 'database/migrations';
    if (is_dir($migrationDir)) {
        $files = scandir($migrationDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                echo "- $file\n";
            }
        }
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
