<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
try {
    $pdo->query('DESCRIBE product_price_history');
    echo "Table product_price_history: OK\n";
} catch (Throwable $e) {
    echo "Table product_price_history: MISSING - " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query(
        "SELECT h.*, p.nom as produit_nom, p.code_cip, u.username as utilisateur_nom
         FROM product_price_history h
         JOIN produits p ON p.id = h.produit_id
         LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
         ORDER BY h.changed_at DESC
         LIMIT 5"
    );
    echo "Query OK, rows: " . count($stmt->fetchAll(PDO::FETCH_ASSOC)) . "\n";
} catch (Throwable $e) {
    echo "Query ERR: " . $e->getMessage() . "\n";
}
