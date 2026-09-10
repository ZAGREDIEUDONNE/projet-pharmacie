<?php
/**
 * Inventaire complet des tables et colonnes de la base active.
 * Usage: php scripts/inventaire_tables.php
 */
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$db = $pdo->query('SELECT DATABASE()')->fetchColumn();

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$inventory = [];
foreach ($tables as $table) {
    $cols = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $inventory[$table] = array_map(static function ($c) {
        return [
            'type' => $c['Type'],
            'null' => $c['Null'],
            'key' => $c['Key'],
            'default' => $c['Default'],
            'extra' => $c['Extra'],
        ];
    }, $cols);
}

echo json_encode([
    'database' => $db,
    'table_count' => count($tables),
    'tables' => $inventory,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
