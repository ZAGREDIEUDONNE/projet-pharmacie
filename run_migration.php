<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=pharmacie;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier les colonnes existantes
    $stmt = $db->query("DESCRIBE clients");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[$row['Field']] = $row['Type'];
    }
    
    echo "=== Colonnes existantes ===\n";
    foreach ($existingColumns as $col => $type) {
        echo "- $col ($type)\n";
    }
    echo "\n";
    
    // Colonnes à ajouter (avec noms compatibles ClientController)
    $columnsToAdd = [
        'code' => 'VARCHAR(20) UNIQUE',
        'prenom' => 'VARCHAR(100)',
        'age' => 'INT',
        'is_actif' => 'TINYINT(1) DEFAULT 1',
        'telephone_secondaire' => 'VARCHAR(20)',
        'email' => 'VARCHAR(100)',
        'ville' => 'VARCHAR(100)',
        'numero_ifu' => 'VARCHAR(50)',
        'numero_rccm' => 'VARCHAR(50)',
        'numero_assurance' => 'VARCHAR(50)',
        'compagnie_assurance' => 'VARCHAR(100)',
        'solde_initial' => 'DECIMAL(10,2) DEFAULT 0',
        'notes' => 'TEXT',
        'utilisateur_creation_id' => 'INT',
        'utilisateur_modification_id' => 'INT',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'deleted_at' => 'TIMESTAMP NULL'
    ];
    
    $afterMap = [
        'code' => 'id',
        'prenom' => 'nom',
        'age' => 'date_naissance',
        'is_actif' => 'type_client',
        'telephone_secondaire' => 'telephone',
        'email' => 'telephone_secondaire',
        'ville' => 'adresse',
        'numero_ifu' => 'ville',
        'numero_rccm' => 'numero_ifu',
        'numero_assurance' => 'numero_rccm',
        'compagnie_assurance' => 'numero_assurance',
        'solde_initial' => 'plafond',
        'notes' => 'solde',
        'utilisateur_creation_id' => 'notes',
        'utilisateur_modification_id' => 'utilisateur_creation_id',
        'updated_at' => 'utilisateur_modification_id',
        'deleted_at' => 'updated_at'
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        if (!array_key_exists($column, $existingColumns)) {
            $after = $afterMap[$column] ?? '';
            $sql = "ALTER TABLE clients ADD COLUMN $column $definition";
            if ($after) {
                $sql .= " AFTER $after";
            }
            echo "Ajout de $column...\n";
            $db->exec($sql);
            echo "✓ $column ajouté\n";
        } else {
            echo "⊘ $column existe déjà\n";
        }
    }
    
    // Génération des codes clients
    echo "\nGénération des codes clients...\n";
    $db->exec("UPDATE clients SET code = CONCAT('CLI', LPAD(id, 6, '0')) WHERE code IS NULL OR code = ''");
    echo "✓ Codes clients générés\n";
    
    // Génération des matricules
    echo "Génération des matricules...\n";
    $db->exec("UPDATE clients SET matricule = CONCAT('MAT', LPAD(id, 6, '0')) WHERE matricule IS NULL OR matricule = ''");
    echo "✓ Matricules générés\n";
    
    // Calcul de l'âge
    echo "Calcul de l'âge...\n";
    $db->exec("UPDATE clients SET age = TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) WHERE date_naissance IS NOT NULL AND age IS NULL");
    echo "✓ Âge calculé\n";
    
    // Initialisation du solde_initial
    echo "Initialisation du solde_initial...\n";
    $db->exec("UPDATE clients SET solde_initial = solde WHERE solde_initial IS NULL OR solde_initial = 0");
    echo "✓ Solde initial initialisé\n";
    
    // Initialisation de is_actif
    echo "Initialisation de is_actif...\n";
    $db->exec("UPDATE clients SET is_actif = 1 WHERE is_actif IS NULL");
    echo "✓ is_actif initialisé\n";
    
    echo "\n=== Migration terminée avec succès ===\n";
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
