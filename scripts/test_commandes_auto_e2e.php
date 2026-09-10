<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Mettre un produit sous seuil pour test
$db->prepare('UPDATE stock SET quantite_disponible = 2 WHERE produit_id = 3')->execute();

$svc = new App\Services\CommandeAutomatiqueService($db, new App\Services\AuditService($db));
$preview = $svc->getApercuProduitsACommander();
echo "Preview count: " . count($preview) . PHP_EOL;
echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

$result = $svc->genererCommandesAutomatiques(1);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Verifier en base
$orders = $db->query(
    "SELECT so.id, so.numero_commande, so.statut, so.montant_total, so.observations, f.nom
     FROM supplier_orders so
     JOIN fournisseurs f ON f.id = so.fournisseur_id
     WHERE so.observations LIKE '%Commande automatique%'
     ORDER BY so.id DESC LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);
echo "Orders: " . json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

$items = $db->query(
    "SELECT * FROM supplier_order_items ORDER BY id DESC LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);
echo "Items: " . json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Restaurer stock
$db->prepare('UPDATE stock SET quantite_disponible = 41 WHERE produit_id = 3')->execute();
