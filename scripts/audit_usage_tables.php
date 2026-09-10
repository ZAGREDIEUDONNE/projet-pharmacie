<?php
echo "=== AUDIT D'USAGE DES TABLES DANS LE CODE PHP ===\n\n";

// Répertoires à scanner
$directories = [
    'app/Services',
    'app/Controllers',
    'app/Models'
];

// Tables à vérifier
$allTables = [
    'utilisateurs', 'roles', 'permissions', 'role_permissions',
    'produits', 'categories', 'fournisseurs', 'lots', 'stock', 'mouvements_stock',
    'clients',
    'ventes', 'ventes_items', 'product_price_history', 'vente_ordonnances', 'vente_ordonnance_items',
    'commandes', 'commande_items',
    'supplier_orders', 'supplier_order_items',
    'receptions', 'reception_items', 'stock_entries',
    'caisse_sessions', 'mouvements_caisse',
    'plan_comptable', 'journal_comptable', 'ecritures_comptables', 'lignes_ecritures',
    'journaux_comptables',
    'audit_logs', 'events',
    'client_reglements', 'fournisseur_reglements', 'remises_commerciales',
    'reglements_clients', 'ventes_credit', 'bons', 'bons_items'
];

// Scanner les fichiers PHP
$usage = [];
foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            
            foreach ($allTables as $table) {
                // Chercher les références à la table (FROM table, INSERT INTO table, UPDATE table, etc.)
                $patterns = [
                    "/FROM\s+`?$table`?/i",
                    "/JOIN\s+`?$table`?/i",
                    "/INSERT INTO\s+`?$table`?/i",
                    "/UPDATE\s+`?$table`?/i",
                    "/DELETE FROM\s+`?$table`?/i",
                    "/table_name\s*=\s*['\"]" . $table . "['\"]/i",
                    "/['\"]" . $table . "['\"]\s*\./i"
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $usage[$table][] = $file->getPathname();
                        break;
                    }
                }
            }
        }
    }
}

// Afficher les résultats
echo "=== TABLES UTILISÉES DANS LE CODE ===\n\n";
foreach ($usage as $table => $files) {
    echo "$table:\n";
    foreach (array_unique($files) as $file) {
        echo "  - " . str_replace('c:\\wamp64\\www\\medecin\\', '', $file) . "\n";
    }
    echo "\n";
}

// Tables non utilisées
$unused = array_diff($allTables, array_keys($usage));
if ($unused) {
    echo "=== TABLES NON UTILISÉES DANS LE CODE ===\n\n";
    foreach ($unused as $table) {
        echo "- $table\n";
    }
}
