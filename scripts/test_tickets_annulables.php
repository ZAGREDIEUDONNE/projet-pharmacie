<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$audit = new App\Services\AuditService($db);
$svc = new App\Services\VenteService(
    $db,
    new App\Services\StockService($db, $audit),
    new App\Services\CaisseService($db, $audit),
    new App\Services\ComptabiliteService($db, $audit),
    $audit
);

$tickets = $svc->getTicketsAnnulables(5);
echo 'Tickets annulables: ' . count($tickets) . PHP_EOL;
if (!empty($tickets[0])) {
    echo json_encode($tickets[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
