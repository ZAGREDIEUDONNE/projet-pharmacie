<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
foreach (['commandes', 'commande_items', 'supplier_orders', 'supplier_order_items', 'produits', 'stock'] as $table) {
    try {
        $cols = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        echo $table . ': ' . implode(', ', $cols) . PHP_EOL;
    } catch (Throwable $e) {
        echo $table . ': MISSING' . PHP_EOL;
    }
}
