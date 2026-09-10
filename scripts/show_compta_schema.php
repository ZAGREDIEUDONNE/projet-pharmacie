<?php
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();
echo json_encode($db->query('SHOW COLUMNS FROM ecritures_comptables')->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
echo PHP_EOL;
try {
    echo json_encode($db->query('SHOW COLUMNS FROM lignes_ecritures')->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo 'lignes_ecritures: missing' . PHP_EOL;
}
try {
    echo json_encode($db->query('SHOW COLUMNS FROM journaux_comptables')->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo 'journaux_comptables: missing' . PHP_EOL;
}
