<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$sql = file_get_contents(__DIR__ . '/../database/migrations/020_add_view_stock_movements_to_charge_commande.sql');
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $query) {
    if ($query === '') {
        continue;
    }
    try {
        $pdo->exec($query);
        echo "OK: " . substr($query, 0, 50) . "...\n";
    } catch (Throwable $e) {
        echo 'ERR: ' . $e->getMessage() . "\n";
    }
}
