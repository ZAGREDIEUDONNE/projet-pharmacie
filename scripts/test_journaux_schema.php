<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$audit = new App\Services\AuditService($db);
$svc = new App\Services\JournalComptableService($db, $audit);

try {
    $journaux = $svc->getJournaux();
    echo 'Journaux: ' . count($journaux) . PHP_EOL;
    if (!empty($journaux[0])) {
        echo json_encode($journaux[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    $cols = $db->query('SHOW COLUMNS FROM ecritures_comptables')->fetchAll(PDO::FETCH_COLUMN);
    echo 'ecritures columns: ' . implode(', ', $cols) . PHP_EOL;
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
}
