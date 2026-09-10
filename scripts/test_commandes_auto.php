<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$svc = new App\Services\CommandeAutomatiqueService($db, new App\Services\AuditService($db));

$preview = $svc->getApercuProduitsACommander();
echo 'Produits sous seuil: ' . count($preview) . PHP_EOL;

if (count($preview) > 0) {
    echo json_encode(array_slice($preview, 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$result = $svc->genererCommandesAutomatiques(1);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
