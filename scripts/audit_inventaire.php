<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION INVENTAIRE ===\n\n";

    // Vérifier si les tables d'inventaire existent
    $tables = ['inventaires', 'inventaire_items'];
    
    foreach ($tables as $table) {
        echo "=== TABLE: $table ===\n";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        
        if ($exists) {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Colonnes (" . count($columns) . "):\n";
            foreach ($columns as $col) {
                echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo "Total enregistrements: $total\n";
            
            // Vérifier les statuts
            if ($table === 'inventaires') {
                $stmt = $pdo->query("SELECT DISTINCT statut, COUNT(*) as total FROM $table GROUP BY statut");
                $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "Statuts:\n";
                foreach ($statuts as $statut) {
                    echo "  - {$statut['statut']}: {$statut['total']}\n";
                }
            }
        } else {
            echo "Table manquante\n";
        }
        
        echo "\n";
    }

    // Vérifier l'utilisation dans le code
    echo "=== UTILISATION DANS LE CODE ===\n";
    
    $serviceFile = 'app/Services/ChargeCommandeService.php';
    $content = file_get_contents($serviceFile);
    
    $inventaireUsage = substr_count($content, 'inventaire');
    echo "Références à 'inventaire' dans ChargeCommandeService: $inventaireUsage\n";
    
    // Vérifier les méthodes d'inventaire
    $methods = [
        'createInventory',
        'validateInventory',
        'cancelInventory'
    ];
    
    echo "\nMéthodes d'inventaire:\n";
    foreach ($methods as $method) {
        $exists = strpos($content, $method) !== false;
        echo "$method: " . ($exists ? 'OUI' : 'NON') . "\n";
    }

    // Vérifier si le module inventaire est utilisé par CHARGE_COMMANDE
    echo "\n=== CONCLUSION ===\n";
    
    if ($inventaireUsage > 0) {
        echo "Le module inventaire est utilisé par ChargeCommandeService.\n";
    } else {
        echo "Le module inventaire n'est pas utilisé par ChargeCommandeService.\n";
        echo "La gestion d'inventaire est probablement gérée par un autre contrôleur (InventaireController).\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
