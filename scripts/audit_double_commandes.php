<?php
echo "=== ÉTAPE 4 - ANALYSE DOUBLE SYSTÈME DE COMMANDES ===\n\n";

// Scanner les fichiers PHP pour l'usage des tables de commandes
$directories = ['app/Services', 'app/Controllers', 'app/Views', 'app/Models', 'routes'];

$ancienSystem = ['commandes', 'commande_items'];
$nouveauSystem = ['supplier_orders', 'supplier_order_items'];

$usageAncien = [];
$usageNouveau = [];

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $filePath = str_replace('c:\\wamp64\\www\\medecin\\', '', $file->getPathname());
            
            // Vérifier ancien système
            foreach ($ancienSystem as $table) {
                $patterns = [
                    "/FROM\s+`?$table`?/i",
                    "/JOIN\s+`?$table`?/i",
                    "/INSERT INTO\s+`?$table`?/i",
                    "/UPDATE\s+`?$table`?/i",
                    "/DELETE FROM\s+`?$table`?/i"
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $usageAncien[$table][] = $filePath;
                        break;
                    }
                }
            }
            
            // Vérifier nouveau système
            foreach ($nouveauSystem as $table) {
                $patterns = [
                    "/FROM\s+`?$table`?/i",
                    "/JOIN\s+`?$table`?/i",
                    "/INSERT INTO\s+`?$table`?/i",
                    "/UPDATE\s+`?$table`?/i",
                    "/DELETE FROM\s+`?$table`?/i"
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $usageNouveau[$table][] = $filePath;
                        break;
                    }
                }
            }
        }
    }
}

echo "=== ANCIEN SYSTÈME (commandes, commande_items) ===\n\n";
foreach ($ancienSystem as $table) {
    echo "--- TABLE: $table ---\n";
    if (isset($usageAncien[$table])) {
        echo "Utilisée dans " . count(array_unique($usageAncien[$table])) . " fichiers:\n";
        foreach (array_unique($usageAncien[$table]) as $file) {
            echo "  - $file\n";
        }
    } else {
        echo "NON utilisée dans le code\n";
    }
    echo "\n";
}

echo "=== NOUVEAU SYSTÈME (supplier_orders, supplier_order_items) ===\n\n";
foreach ($nouveauSystem as $table) {
    echo "--- TABLE: $table ---\n";
    if (isset($usageNouveau[$table])) {
        echo "Utilisée dans " . count(array_unique($usageNouveau[$table])) . " fichiers:\n";
        foreach (array_unique($usageNouveau[$table]) as $file) {
            echo "  - $file\n";
        }
    } else {
        echo "NON utilisée dans le code\n";
    }
    echo "\n";
}

// Vérifier les fonctions de réception
echo "=== ANALYSE DES FONCTIONS DE RÉCEPTION ===\n\n";

$receptionFunctions = [
    'receiveOrder' => 'ChargeCommandeService.php',
    'recevoirCommande' => 'CommandeService.php'
];

foreach ($receptionFunctions as $func => $file) {
    echo "Fonction: $func dans $file\n";
    $filePath = 'app/Services/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        // Vérifier quel système est utilisé
        $usesAncien = preg_match('/commandes|commande_items/i', $content);
        $usesNouveau = preg_match('/supplier_orders|supplier_order_items/i', $content);
        
        echo "  - Ancien système (commandes): " . ($usesAncien ? "OUI" : "NON") . "\n";
        echo "  - Nouveau système (supplier_orders): " . ($usesNouveau ? "OUI" : "NON") . "\n";
    }
    echo "\n";
}

// Vérifier les fonctions comptables
echo "=== ANALYSE DES FONCTIONS COMPTABLES ===\n\n";

$comptableFiles = [
    'EcritureComptableService.php',
    'JournalComptableService.php',
    'ComptabiliteService.php'
];

foreach ($comptableFiles as $file) {
    echo "Fichier: $file\n";
    $filePath = 'app/Services/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        $usesAncien = preg_match('/commandes|commande_items/i', $content);
        $usesNouveau = preg_match('/supplier_orders|supplier_order_items/i', $content);
        
        echo "  - Ancien système (commandes): " . ($usesAncien ? "OUI" : "NON") . "\n";
        echo "  - Nouveau système (supplier_orders): " . ($usesNouveau ? "OUI" : "NON") . "\n";
    }
    echo "\n";
}
