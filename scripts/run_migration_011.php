<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$sql = file_get_contents(__DIR__ . '/../database/migrations/011_fix_ordonnances_link.sql');
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $query) {
    if ($query === '') {
        continue;
    }
    try {
        $pdo->exec($query);
        echo "OK: " . substr(str_replace(["\r", "\n"], ' ', $query), 0, 80) . PHP_EOL;
    } catch (Throwable $e) {
        echo "ERR: " . $e->getMessage() . PHP_EOL;
        echo "SQL: " . substr($query, 0, 120) . PHP_EOL;
    }
}
