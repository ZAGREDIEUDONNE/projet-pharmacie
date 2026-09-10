<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$tables = $pdo->query("SHOW TABLES LIKE '%ordonnance%'")->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables: ' . json_encode($tables) . PHP_EOL;

foreach ($tables as $table) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;
    $cols = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "{$col['Field']} | {$col['Type']} | {$col['Null']} | {$col['Key']}" . PHP_EOL;
    }
}

$extra = ['vente_ordonnance_items', 'produits'];
foreach ($extra as $table) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;
    try {
        $cols = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "{$col['Field']} | {$col['Type']} | {$col['Null']} | {$col['Key']}" . PHP_EOL;
        }
    } catch (Throwable $e) {
        echo 'MISSING: ' . $e->getMessage() . PHP_EOL;
    }
}

$fk = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('vente_ordonnances', 'ordonnances')
     AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . 'Foreign keys:' . PHP_EOL;
print_r($fk);
