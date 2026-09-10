<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$db->prepare('UPDATE stock SET quantite_disponible = 2 WHERE produit_id = 3')->execute();
$svc = new App\Services\CommandeAutomatiqueService($db, new App\Services\AuditService($db));
echo json_encode($svc->genererCommandesAutomatiques(1), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$db->prepare('UPDATE stock SET quantite_disponible = 41 WHERE produit_id = 3')->execute();
