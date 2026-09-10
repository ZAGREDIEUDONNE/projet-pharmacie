<?php

declare(strict_types=1);

/**
 * Script d'audit PHASE 1 - Dashboard Comptable
 * Inspection de la base de données réelle sans modification
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
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion DB: " . $e->getMessage() . "\n");
}

$audit = [];

// 1. Tables comptables
$audit['tables'] = [];
$tables = [
    'ecritures_comptables',
    'lignes_ecritures',
    'plan_comptable',
    'classes_comptes',
    'journaux_comptables',
    'exercices_comptables',
    'tva_taux',
    'rapports_comptables',
    'soldes_comptables',
];

foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    $audit['tables'][$table] = $stmt->fetch() !== false;
}

// 2. Colonnes des tables principales
$audit['columns'] = [];

// ecritures_comptables
if ($audit['tables']['ecritures_comptables']) {
    $stmt = $pdo->query("SHOW COLUMNS FROM ecritures_comptables");
    $audit['columns']['ecritures_comptables'] = array_column($stmt->fetchAll(), 'Field');
}

// lignes_ecritures
if ($audit['tables']['lignes_ecritures']) {
    $stmt = $pdo->query("SHOW COLUMNS FROM lignes_ecritures");
    $audit['columns']['lignes_ecritures'] = array_column($stmt->fetchAll(), 'Field');
}

// plan_comptable
if ($audit['tables']['plan_comptable']) {
    $stmt = $pdo->query("SHOW COLUMNS FROM plan_comptable");
    $audit['columns']['plan_comptable'] = array_column($stmt->fetchAll(), 'Field');
}

// journaux_comptables
if ($audit['tables']['journaux_comptables']) {
    $stmt = $pdo->query("SHOW COLUMNS FROM journaux_comptables");
    $audit['columns']['journaux_comptables'] = array_column($stmt->fetchAll(), 'Field');
}

// 3. Comptes SYSCOHADA utilisés
$audit['syscohada'] = [];
$comptesSyscohada = ['571', '411', '701', '44571', '401', '311', '31', '521', '44561'];

foreach ($comptesSyscohada as $compte) {
    $stmt = $pdo->query("SELECT * FROM plan_comptable WHERE numero_compte = '$compte' OR code = '$compte'");
    $audit['syscohada'][$compte] = $stmt->fetch();
}

// 4. Écritures comptables - équilibre
$audit['ecritures'] = [];

if ($audit['tables']['ecritures_comptables']) {
    // Nombre total d'écritures
    $audit['ecritures']['total'] = (int)$pdo->query("SELECT COUNT(*) FROM ecritures_comptables")->fetchColumn();
    
    // Écritures équilibrées
    $stmt = $pdo->query("SELECT id, total_debit, total_credit, is_equilibree FROM ecritures_comptables LIMIT 100");
    $audit['ecritures']['sample'] = $stmt->fetchAll();
    
    // Écritures non équilibrées
    $stmt = $pdo->query("SELECT COUNT(*) FROM ecritures_comptables WHERE ABS(total_debit - total_credit) > 0.01");
    $audit['ecritures']['non_equilibrees'] = (int)$stmt->fetchColumn();
}

// 5. Lignes d'écritures - équilibre par écriture
$audit['lignes'] = [];

if ($audit['tables']['lignes_ecritures']) {
    $audit['lignes']['total'] = (int)$pdo->query("SELECT COUNT(*) FROM lignes_ecritures")->fetchColumn();
    
    // Vérifier équilibre débit/crédit par écriture
    $stmt = $pdo->query("
        SELECT le.ecriture_id, 
               SUM(le.debit) as total_debit, 
               SUM(le.credit) as total_credit,
               ABS(SUM(le.debit) - SUM(le.credit)) as ecart
        FROM lignes_ecritures le
        GROUP BY le.ecriture_id
        HAVING ecart > 0.01
        LIMIT 10
    ");
    $audit['lignes']['non_equilibrees'] = $stmt->fetchAll();
}

// 6. Permissions COMPTABLE
$audit['permissions'] = [];

// D'abord vérifier les colonnes de la table permissions
$stmt = $pdo->query("SHOW COLUMNS FROM permissions");
$permissionColumns = array_column($stmt->fetchAll(), 'Field');
$audit['permissions']['columns'] = $permissionColumns;

// Sélectionner avec les colonnes existantes
$selectCols = ['code'];
if (in_array('name', $permissionColumns)) $selectCols[] = 'name';
if (in_array('libelle', $permissionColumns)) $selectCols[] = 'libelle';
if (in_array('description', $permissionColumns)) $selectCols[] = 'description';

$selectStr = implode(', ', array_map(fn($c) => "p.$c", $selectCols));

$stmt = $pdo->query("
    SELECT $selectStr
    FROM permissions p
    JOIN role_permissions rp ON p.id = rp.permission_id
    JOIN roles r ON rp.role_id = r.id
    WHERE r.code = 'COMPTABLE' OR r.nom = 'COMPTABLE'
");
$audit['permissions']['comptable'] = $stmt->fetchAll();

// 7. Rôle COMPTABLE
$audit['role_comptable'] = [];

$stmt = $pdo->query("SELECT * FROM roles WHERE code = 'COMPTABLE' OR nom = 'COMPTABLE'");
$audit['role_comptable'] = $stmt->fetch();

// 8. Utilisateurs COMPTABLE
$audit['users_comptable'] = [];

$stmt = $pdo->query("
    SELECT u.id, u.username, u.email, u.is_active
    FROM utilisateurs u
    JOIN roles r ON u.role_id = r.id
    WHERE r.code = 'COMPTABLE' OR r.nom = 'COMPTABLE'
");
$audit['users_comptable'] = $stmt->fetchAll();

// 9. Intégration automatique - colonnes de liaison
$audit['integration'] = [];

$tablesIntegration = ['ventes', 'commandes', 'mouvements_caisse', 'mouvements_stock'];
foreach ($tablesIntegration as $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'ecriture_id'");
    $audit['integration'][$table] = $stmt->fetch() !== false;
}

// 10. Journaux comptables
$audit['journaux'] = [];

if ($audit['tables']['journaux_comptables']) {
    $stmt = $pdo->query("SELECT * FROM journaux_comptables WHERE is_actif = 1");
    $audit['journaux'] = $stmt->fetchAll();
}

// 11. TVA
$audit['tva'] = [];

if ($audit['tables']['tva_taux']) {
    $stmt = $pdo->query("SELECT * FROM tva_taux WHERE is_actif = 1");
    $audit['tva'] = $stmt->fetchAll();
}

// 12. Exercices comptables
$audit['exercices'] = [];

if (isset($audit['tables']['exercices_comptables']) && $audit['tables']['exercices_comptables']) {
    $stmt = $pdo->query("SELECT * FROM exercices_comptables");
    $audit['exercices'] = $stmt->fetchAll();
}

// 13. Classes comptables (table non présente)
$audit['classes'] = null;

echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
