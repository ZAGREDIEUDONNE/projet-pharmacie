<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$audit = new App\Services\AuditService($db);
$stock = new App\Services\StockService($db, $audit);
$caisse = new App\Services\CaisseService($db, $audit);
$compta = new App\Services\ComptabiliteService($db, $audit);
$svc = new App\Services\VenteService($db, $stock, $caisse, $compta, $audit);

$tickets = $svc->getTicketsPourImpression(2, true, 5);
echo 'Tickets vendeur user 2: ' . count($tickets) . PHP_EOL;
if (!empty($tickets[0])) {
    echo 'First ticket id: ' . ($tickets[0]['id'] ?? '?') . ', articles: ' . count($tickets[0]['articles'] ?? []) . PHP_EOL;
}
