<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$audit = new App\Services\AuditService($db);
$svc = new App\Services\ClientService($db, $audit);
$clients = $svc->exporterClients([]);
echo 'Clients exportables: ' . count($clients) . PHP_EOL;
if (!empty($clients[0])) {
    echo 'Exemple: ' . ($clients[0]['nom'] ?? '') . ' ' . ($clients[0]['prenom'] ?? '') . PHP_EOL;
}

$ref = new ReflectionClass(App\Controllers\ClientController::class);
echo 'export method exists: ' . ($ref->hasMethod('export') ? 'yes' : 'no') . PHP_EOL;
