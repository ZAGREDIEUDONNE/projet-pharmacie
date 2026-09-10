<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
try {
    $stmt = $pdo->query("SELECT id, nom, code_cip FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom LIMIT 3");
    echo 'Query OK: ' . count($stmt->fetchAll(PDO::FETCH_ASSOC)) . " rows\n";
} catch (Throwable $e) {
    echo 'Query ERR: ' . $e->getMessage() . "\n";
}

$view = __DIR__ . '/../app/Views/stock/ajustement.php';
echo 'View exists: ' . (file_exists($view) ? 'yes' : 'no') . "\n";
