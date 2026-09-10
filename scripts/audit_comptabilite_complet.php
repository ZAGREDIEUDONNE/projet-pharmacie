<?php
/**
 * AUDIT COMPLET DU MODULE COMPTABLE
 * Collecte systématique de toutes les informations pour le rapport d'audit
 */

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $database = $_ENV['DB_DATABASE'] ?? 'medecin';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion DB: " . $e->getMessage() . "\n");
}

$audit = [];

// 1. Tables comptables
$audit['tables'] = [];
$tablesComptables = [
    'plan_comptable', 'journaux_comptables', 'ecritures_comptables', 'lignes_ecritures',
    'tva_taux', 'exercices_comptables', 'classes_comptes', 'rapports_comptables',
    'soldes_comptables', 'clients', 'fournisseurs', 'ventes', 'ventes_items',
    'commandes', 'commande_items', 'mouvements_caisse', 'caisse_sessions',
    'stock', 'produits', 'audit_logs', 'reglements_clients', 'fournisseur_reglements'
];

foreach ($tablesComptables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $audit['tables'][$table] = $stmt->fetch() !== false;
        
        if ($audit['tables'][$table]) {
            $stmt = $pdo->query("SHOW COLUMNS FROM $table");
            $audit['tables_structure'][$table] = array_column($stmt->fetchAll(), 'Field');
        }
    } catch (Exception $e) {
        $audit['tables'][$table] = false;
    }
}

// 2. Colonnes spécifiques pour intégration
$audit['integration_columns'] = [];
$tablesIntegration = ['ventes', 'commandes', 'mouvements_caisse'];
foreach ($tablesIntegration as $table) {
    if (isset($audit['tables'][$table]) && $audit['tables'][$table]) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'ecriture_id'");
            $audit['integration_columns'][$table] = $stmt->fetch() !== false;
        } catch (Exception $e) {
            $audit['integration_columns'][$table] = false;
        }
    }
}

// 3. Plan comptable SYSCOHADA
$audit['plan_comptable'] = [];
$comptesSyscohada = ['101', '401', '411', '44561', '44571', '571', '701', '311', '31', '521', '6031', '581', '75'];
foreach ($comptesSyscohada as $compte) {
    $stmt = $pdo->query("SELECT * FROM plan_comptable WHERE numero_compte = '$compte' OR code = '$compte'");
    $audit['plan_comptable'][$compte] = $stmt->fetch();
}

// 4. Classes comptables
$audit['classes'] = null;
if (isset($audit['tables']['classes_comptes']) && $audit['tables']['classes_comptes']) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM classes_comptables");
        $columns = array_column($stmt->fetchAll(), 'Field');
        $orderCol = in_array('classe', $columns) ? 'classe' : (in_array('numero', $columns) ? 'numero' : 'id');
        $stmt = $pdo->query("SELECT * FROM classes_comptes ORDER BY $orderCol");
        $audit['classes'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $audit['classes'] = null;
    }
}

// 5. Journaux comptables
$audit['journaux'] = [];
if (isset($audit['tables']['journaux_comptables']) && $audit['tables']['journaux_comptables']) {
    try {
        $stmt = $pdo->query("SELECT * FROM journaux_comptables");
        $audit['journaux'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $audit['journaux'] = [];
    }
}

// 6. Écritures comptables
$audit['ecritures'] = [];
if (isset($audit['tables']['ecritures_comptables']) && $audit['tables']['ecritures_comptables']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecritures_comptables");
        $audit['ecritures']['total'] = $stmt->fetch()['total'];
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as non_equilibrees FROM ecritures_comptables WHERE is_equilibree = 0 OR is_equilibree IS NULL");
            $audit['ecritures']['non_equilibrees'] = $stmt->fetch()['non_equilibrees'];
        } catch (Exception $e) {
            $audit['ecritures']['non_equilibrees'] = 'N/A (colonne manquante)';
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as validees FROM ecritures_comptables WHERE statut = 'VALIDEE'");
            $audit['ecritures']['validees'] = $stmt->fetch()['validees'];
        } catch (Exception $e) {
            $audit['ecritures']['validees'] = 'N/A (colonne manquante)';
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as brouillons FROM ecritures_comptables WHERE statut = 'BROUILLON' OR statut IS NULL");
            $audit['ecritures']['brouillons'] = $stmt->fetch()['brouillons'];
        } catch (Exception $e) {
            $audit['ecritures']['brouillons'] = 'N/A (colonne manquante)';
        }
        
        // Vérification équilibre débit/crédit
        try {
            $stmt = $pdo->query("
                SELECT COUNT(*) as desequilibrees
                FROM ecritures_comptables ec
                WHERE ABS(ec.total_debit - ec.total_credit) > 0.01
            ");
            $audit['ecritures']['desequilibrees'] = $stmt->fetch()['desequilibrees'];
        } catch (Exception $e) {
            $audit['ecritures']['desequilibrees'] = 'N/A (colonnes manquantes)';
        }
    } catch (Exception $e) {
        $audit['ecritures']['error'] = $e->getMessage();
    }
}

// 7. Lignes d'écritures
$audit['lignes_ecritures'] = [];
if (isset($audit['tables']['lignes_ecritures']) && $audit['tables']['lignes_ecritures']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM lignes_ecritures");
        $audit['lignes_ecritures']['total'] = $stmt->fetch()['total'];
        
        try {
            $stmt = $pdo->query("
                SELECT COUNT(*) as desequilibrees
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                WHERE ABS(ec.total_debit - ec.total_credit) > 0.01
            ");
            $audit['lignes_ecritures']['desequilibrees'] = $stmt->fetch()['desequilibrees'];
        } catch (Exception $e) {
            $audit['lignes_ecritures']['desequilibrees'] = 'N/A (colonnes manquantes)';
        }
    } catch (Exception $e) {
        $audit['lignes_ecritures']['error'] = $e->getMessage();
    }
}

// 8. TVA
$audit['tva'] = [];
if (isset($audit['tables']['tva_taux']) && $audit['tables']['tva_taux']) {
    try {
        $stmt = $pdo->query("SELECT * FROM tva_taux");
        $audit['tva']['taux'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $audit['tva']['error'] = $e->getMessage();
    }
}

// 9. Exercices comptables
$audit['exercices'] = [];
if (isset($audit['tables']['exercices_comptables']) && $audit['tables']['exercices_comptables']) {
    try {
        $stmt = $pdo->query("SELECT * FROM exercices_comptables ORDER BY exercice DESC");
        $audit['exercices'] = $stmt->fetchAll();
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as ouverts FROM exercices_comptables WHERE statut = 'ouvert'");
            $audit['exercices']['ouverts'] = $stmt->fetch()['ouverts'];
        } catch (Exception $e) {
            $audit['exercices']['ouverts'] = 'N/A (colonne manquante)';
        }
    } catch (Exception $e) {
        $audit['exercices']['error'] = $e->getMessage();
    }
}

// 10. Intégration ventes
$audit['integration_ventes'] = [];
if (isset($audit['tables']['ventes']) && $audit['tables']['ventes']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ventes");
        $audit['integration_ventes']['total_ventes'] = $stmt->fetch()['total'];
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as sans_ecriture FROM ventes WHERE ecriture_id IS NULL AND statut_vente != 'ANNULEE'");
            $audit['integration_ventes']['sans_ecriture'] = $stmt->fetch()['sans_ecriture'];
        } catch (Exception $e) {
            $audit['integration_ventes']['sans_ecriture'] = 'N/A (colonnes manquantes)';
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as avec_ecriture FROM ventes WHERE ecriture_id IS NOT NULL");
            $audit['integration_ventes']['avec_ecriture'] = $stmt->fetch()['avec_ecriture'];
        } catch (Exception $e) {
            $audit['integration_ventes']['avec_ecriture'] = 'N/A (colonnes manquantes)';
        }
    } catch (Exception $e) {
        $audit['integration_ventes']['error'] = $e->getMessage();
    }
}

// 11. Intégration commandes
$audit['integration_commandes'] = [];
if (isset($audit['tables']['commandes']) && $audit['tables']['commandes']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM commandes");
        $audit['integration_commandes']['total_commandes'] = $stmt->fetch()['total'];
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as sans_ecriture FROM commandes WHERE ecriture_id IS NULL AND statut_commande = 'LIVREE'");
            $audit['integration_commandes']['sans_ecriture'] = $stmt->fetch()['sans_ecriture'];
        } catch (Exception $e) {
            $audit['integration_commandes']['sans_ecriture'] = 'N/A (colonnes manquantes)';
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as avec_ecriture FROM commandes WHERE ecriture_id IS NOT NULL");
            $audit['integration_commandes']['avec_ecriture'] = $stmt->fetch()['avec_ecriture'];
        } catch (Exception $e) {
            $audit['integration_commandes']['avec_ecriture'] = 'N/A (colonnes manquantes)';
        }
    } catch (Exception $e) {
        $audit['integration_commandes']['error'] = $e->getMessage();
    }
}

// 12. Intégration caisse
$audit['integration_caisse'] = [];
if (isset($audit['tables']['mouvements_caisse']) && $audit['tables']['mouvements_caisse']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM mouvements_caisse WHERE supprime = 0");
        $audit['integration_caisse']['total_mouvements'] = $stmt->fetch()['total'];
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as sans_ecriture FROM mouvements_caisse WHERE ecriture_id IS NULL AND supprime = 0");
            $audit['integration_caisse']['sans_ecriture'] = $stmt->fetch()['sans_ecriture'];
        } catch (Exception $e) {
            $audit['integration_caisse']['sans_ecriture'] = 'N/A (colonnes manquantes)';
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as avec_ecriture FROM mouvements_caisse WHERE ecriture_id IS NOT NULL AND supprime = 0");
            $audit['integration_caisse']['avec_ecriture'] = $stmt->fetch()['avec_ecriture'];
        } catch (Exception $e) {
            $audit['integration_caisse']['avec_ecriture'] = 'N/A (colonnes manquantes)';
        }
    } catch (Exception $e) {
        $audit['integration_caisse']['error'] = $e->getMessage();
    }
}

// 13. Rôle COMPTABLE
$audit['role_comptable'] = [];
try {
    $stmt = $pdo->query("SELECT * FROM roles WHERE code = 'COMPTABLE' OR nom = 'COMPTABLE'");
    $audit['role_comptable'] = $stmt->fetch();
} catch (Exception $e) {
    $audit['role_comptable']['error'] = $e->getMessage();
}

// 14. Permissions COMPTABLE
$audit['permissions_comptable'] = [];
if (isset($audit['role_comptable']) && $audit['role_comptable'] && !isset($audit['role_comptable']['error'])) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM permissions");
        $permissionColumns = array_column($stmt->fetchAll(), 'Field');
        
        $selectCols = ['code'];
        foreach (['name', 'libelle', 'description'] as $col) {
            if (in_array($col, $permissionColumns)) $selectCols[] = $col;
        }
        
        $selectStr = implode(', ', array_map(fn($c) => "p.$c", $selectCols));
        
        $stmt = $pdo->query("
            SELECT $selectStr
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = {$audit['role_comptable']['id']}
        ");
        $audit['permissions_comptable'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $audit['permissions_comptable']['error'] = $e->getMessage();
    }
}

// 15. Audit logs comptables
$audit['audit_logs'] = [];
if (isset($audit['tables']['audit_logs']) && $audit['tables']['audit_logs']) {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total FROM audit_logs
            WHERE table_name IN ('ecritures_comptables', 'lignes_ecritures', 'plan_comptable', 'journaux_comptables')
            OR action LIKE '%COMPTABILITE%'
        ");
        $audit['audit_logs']['total'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $audit['audit_logs']['error'] = $e->getMessage();
    }
}

// 16. Règlements clients
$audit['reglements_clients'] = [];
if (isset($audit['tables']['reglements_clients']) && $audit['tables']['reglements_clients']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM reglements_clients");
        $audit['reglements_clients']['total'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $audit['reglements_clients']['error'] = $e->getMessage();
    }
}

// 17. Règlements fournisseurs
$audit['reglements_fournisseurs'] = [];
if (isset($audit['tables']['fournisseur_reglements']) && $audit['tables']['fournisseur_reglements']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM fournisseur_reglements");
        $audit['reglements_fournisseurs']['total'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $audit['reglements_fournisseurs']['error'] = $e->getMessage();
    }
}

// 18. Colonnes validation
$audit['colonnes_validation'] = [];
if (isset($audit['tables']['ecritures_comptables']) && $audit['tables']['ecritures_comptables']) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'statut'");
        $audit['colonnes_validation']['statut'] = $stmt->fetch() !== false;
        
        $stmt = $pdo->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'date_validation'");
        $audit['colonnes_validation']['date_validation'] = $stmt->fetch() !== false;
        
        $stmt = $pdo->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'utilisateur_validation_id'");
        $audit['colonnes_validation']['utilisateur_validation_id'] = $stmt->fetch() !== false;
    } catch (Exception $e) {
        $audit['colonnes_validation']['error'] = $e->getMessage();
    }
}

// 19. Colonnes contre-passation
$audit['colonnes_contrepassation'] = [];
if (isset($audit['tables']['ecritures_comptables']) && $audit['tables']['ecritures_comptables']) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'ecriture_origine_id'");
        $audit['colonnes_contrepassation']['ecriture_origine_id'] = $stmt->fetch() !== false;
        
        $stmt = $pdo->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'reference_type'");
        $audit['colonnes_contrepassation']['reference_type'] = $stmt->fetch() !== false;
    } catch (Exception $e) {
        $audit['colonnes_contrepassation']['error'] = $e->getMessage();
    }
}

// 20. Clients et fournisseurs avec soldes
$audit['tiers'] = [];
if (isset($audit['tables']['clients']) && $audit['tables']['clients']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE deleted_at IS NULL AND is_actif = 1");
        $audit['tiers']['clients_actifs'] = $stmt->fetch()['total'];
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as avec_credit FROM clients WHERE solde_credit > 0 AND deleted_at IS NULL AND is_actif = 1");
            $audit['tiers']['clients_avec_credit'] = $stmt->fetch()['avec_credit'];
        } catch (Exception $e) {
            $audit['tiers']['clients_avec_credit'] = 'N/A (colonne manquante)';
        }
    } catch (Exception $e) {
        $audit['tiers']['clients_error'] = $e->getMessage();
    }
}

if (isset($audit['tables']['fournisseurs']) && $audit['tables']['fournisseurs']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM fournisseurs WHERE deleted_at IS NULL AND is_actif = 1");
        $audit['tiers']['fournisseurs_actifs'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $audit['tiers']['fournisseurs_error'] = $e->getMessage();
    }
}

// Sortie JSON
echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
