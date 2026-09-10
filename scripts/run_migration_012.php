<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$sql = file_get_contents(__DIR__ . '/../database/migrations/012_charge_commande_permissions.sql');
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $query) {
    if ($query === '') {
        continue;
    }
    try {
        $pdo->exec($query);
        echo "OK\n";
    } catch (Throwable $e) {
        echo 'ERR: ' . $e->getMessage() . "\n";
    }
}
