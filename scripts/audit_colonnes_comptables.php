<?php
echo "=== ÉTAPE 3 - AUDIT COLONNES COMPTABLES ===\n\n";

// Colonnes à vérifier
$columnsToCheck = [
    'ventes' => ['montant_ht', 'montant_tva', 'ecriture_id'],
    'commandes' => ['montant_ht', 'montant_tva', 'ecriture_id'],
    'mouvements_caisse' => ['ecriture_id', 'vente_id', 'client_id', 'fournisseur_id', 'type_depense', 'supprime'],
    'caisse_sessions' => ['montant_ventes', 'montant_theorique', 'ecart'],
    'clients' => ['solde_credit', 'matricule'],
    'stock' => ['date_peremption']
];

// Scanner les fichiers PHP
$directories = ['app/Services', 'app/Controllers', 'app/Models'];

foreach ($columnsToCheck as $table => $columns) {
    echo "--- TABLE: $table ---\n";
    
    foreach ($columns as $col) {
        echo "Colonne: $col\n";
        
        $usage = [];
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    
                    // Chercher les références à la colonne
                    $patterns = [
                        "/$table\.$col/i",
                        "/$table\`?\s*\.\s*`?$col`?/i",
                        "/$col\s*=>/i",
                        "/$col\s*=/i",
                        "/:$col/i",
                        "/'$col'/i",
                        "/\"$col\"/i"
                    ];
                    
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $content)) {
                            $usage[] = str_replace('c:\\wamp64\\www\\medecin\\', '', $file->getPathname());
                            break;
                        }
                    }
                }
            }
        }
        
        if ($usage) {
            echo "  Utilisée dans le code:\n";
            foreach (array_unique($usage) as $file) {
                echo "    - $file\n";
            }
        } else {
            echo "  NON utilisée dans le code\n";
        }
        echo "\n";
    }
}
