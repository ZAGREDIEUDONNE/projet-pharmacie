<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Services\ChargeCommandeService;

$source = Database::getConnection();
$testDatabase = 'medecin_test';
$tables = [
    'fournisseurs', 'produits', 'stock', 'supplier_orders', 'supplier_order_items',
    'receptions', 'reception_items', 'stock_entries', 'mouvements_stock', 'lots', 'utilisateurs',
    'roles', 'permissions', 'role_permissions', 'utilisateur_permissions', 'audit_logs',
    'fournisseur_reglements',
];

// Toutes les écritures du flux de réception doivent rester transactionnelles.
$transactionalTables = [
    'supplier_orders', 'supplier_order_items', 'receptions', 'reception_items',
    'stock_entries', 'stock', 'mouvements_stock', 'fournisseur_reglements',
];

$exists = (bool) $source->prepare(
    'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?'
)->execute([$testDatabase]);
$check = $source->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
$check->execute([$testDatabase]);
if ((int) $check->fetchColumn() > 0) {
    throw new RuntimeException('La base medecin_test existe déjà : le test refuse de la modifier.');
}

$source->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
foreach ($tables as $table) {
    $source->exec("CREATE TABLE `{$testDatabase}`.`{$table}` LIKE `medecin`.`{$table}`");
}

$test = new PDO(
    'mysql:host=localhost;port=3306;dbname=medecin_test;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$test->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO'");

try {
    $enginePlaceholders = implode(',', array_fill(0, count($transactionalTables), '?'));
    $engineStmt = $test->prepare(
        "SELECT TABLE_NAME, ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ({$enginePlaceholders})"
    );
    $engineStmt->execute($transactionalTables);
    $engines = array_column($engineStmt->fetchAll(), 'ENGINE', 'TABLE_NAME');
    $nonTransactional = array_filter(
        $transactionalTables,
        static fn (string $table): bool => ($engines[$table] ?? null) !== 'InnoDB'
    );
    if ($nonTransactional) {
        throw new RuntimeException('Tables non transactionnelles : ' . implode(', ', $nonTransactional));
    }

    $test->beginTransaction();
    $test->exec("INSERT INTO roles (id, nom, code, libelle, description, level, is_actif, statut)
                 VALUES (4, 'charge_commande', 'CHARGE_COMMANDE', 'Chargé de commande', 'Utilisateur de recette', 1, 1, 1)");
    $test->exec("INSERT INTO utilisateurs (id, username, email, password_hash, nom, prenom, role_id, is_active)
                 VALUES (1, 'cc_recette', 'cc-recette@example.test', 'not-used-in-test', 'Recette', 'Commande', 4, 1)");
    $test->exec("INSERT INTO fournisseurs (id, code, nom, is_actif) VALUES (1, 'F-RECETTE', 'Fournisseur recette', 1)");
    $test->exec("INSERT INTO produits (id, code_cip, nom, fournisseur_id, prix_achat, prix_vente, stock_alerte, stock_securite, is_actif, stock_minimum, stock_maximum)
                 VALUES (1, 'TESTCC001', 'Produit recette commande', 1, 100, 150, 5, 2, 1, 5, 100)");
    $test->exec("INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, quantite_reservee, valeur_stock, dernier_mouvement)
                 VALUES (1, 26, 26, 0, 2600, NOW())");
    $test->commit();

    $service = new ChargeCommandeService($test);
    $snapshot = static function (PDO $connection, int $productId, int $orderItemId, int $orderId): array {
        return [
            'stock' => (int) $connection->query("SELECT quantite_disponible FROM stock WHERE produit_id = {$productId}")->fetchColumn(),
            'received' => (int) $connection->query("SELECT quantite_recue FROM supplier_order_items WHERE id = {$orderItemId}")->fetchColumn(),
            'receptions' => (int) $connection->query("SELECT COUNT(*) FROM receptions WHERE supplier_order_id = {$orderId}")->fetchColumn(),
            'reception_items' => (int) $connection->query('SELECT COUNT(*) FROM reception_items')->fetchColumn(),
            'stock_entries' => (int) $connection->query('SELECT COUNT(*) FROM stock_entries')->fetchColumn(),
            'mouvements_stock' => (int) $connection->query("SELECT COUNT(*) FROM mouvements_stock WHERE type_mouvement = 'ENTREE'")->fetchColumn(),
        ];
    };

    $order = $service->createSupplierOrder([
        'fournisseur_id' => 1,
        'date_commande' => date('Y-m-d'),
        'date_livraison_prevue' => date('Y-m-d', strtotime('+7 days')),
        'statut' => 'VALIDEE',
        'items' => [['produit_id' => 1, 'quantite' => 10, 'prix_achat' => 100]],
    ], 1);
    $orderId = (int) $order['order_id'];
    $item = $test->query("SELECT * FROM supplier_order_items WHERE supplier_order_id = {$orderId}")->fetch();

    $partial = $service->receiveOrder([
        'supplier_order_id' => $orderId,
        'date_reception' => date('Y-m-d'),
        'items' => [['supplier_order_item_id' => (int) $item['id'], 'quantite_recue' => 5]],
    ], 1);
    $afterPartial = $snapshot($test, 1, (int) $item['id'], $orderId);

    $beforeOver = $afterPartial;
    $overRefused = false;
    try {
        $service->receiveOrder([
            'supplier_order_id' => $orderId,
            'date_reception' => date('Y-m-d'),
            'items' => [['supplier_order_item_id' => (int) $item['id'], 'quantite_recue' => 6]],
        ], 1);
    } catch (Throwable $e) {
        $overRefused = str_contains($e->getMessage(), 'superieure au reste attendu');
    }
    $afterOver = $snapshot($test, 1, (int) $item['id'], $orderId);

    $complete = $service->receiveOrder([
        'supplier_order_id' => $orderId,
        'date_reception' => date('Y-m-d'),
        'items' => [['supplier_order_item_id' => (int) $item['id'], 'quantite_recue' => 5]],
    ], 1);
    $afterComplete = $snapshot($test, 1, (int) $item['id'], $orderId);
    $afterComplete['status'] = (string) $test->query("SELECT statut FROM supplier_orders WHERE id = {$orderId}")->fetchColumn();

    $fault = $service->createSupplierOrder([
        'fournisseur_id' => 1, 'statut' => 'VALIDEE',
        'items' => [['produit_id' => 1, 'quantite' => 1, 'prix_achat' => 100]],
    ], 1);
    $faultItem = (int) $test->query("SELECT id FROM supplier_order_items WHERE supplier_order_id = {$fault['order_id']}")->fetchColumn();
    $beforeFault = $snapshot($test, 1, $faultItem, (int) $fault['order_id']);
    $faultRolledBack = false;
    $faultError = '';
    $test->exec(
        "CREATE TRIGGER force_receive_order_failure
         BEFORE INSERT ON reception_items
         FOR EACH ROW
         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Erreur forcee apres en-tete reception'"
    );
    try {
        $service->receiveOrder([
            'supplier_order_id' => (int) $fault['order_id'],
            'items' => [['supplier_order_item_id' => $faultItem, 'quantite_recue' => 1]],
        ], 1);
    } catch (Throwable $e) {
        $faultError = $e->getMessage();
    }
    $afterFault = $snapshot($test, 1, $faultItem, (int) $fault['order_id']);
    $faultRolledBack = $beforeFault === $afterFault;
    $test->exec('DROP TRIGGER force_receive_order_failure');

    $dashboard = $service->getDashboardData();
    $orphans = [
        'order_items' => (int) $test->query('SELECT COUNT(*) FROM supplier_order_items i LEFT JOIN supplier_orders o ON o.id=i.supplier_order_id WHERE o.id IS NULL')->fetchColumn(),
        'receptions' => (int) $test->query('SELECT COUNT(*) FROM receptions r LEFT JOIN supplier_orders o ON o.id=r.supplier_order_id WHERE o.id IS NULL')->fetchColumn(),
        'reception_items' => (int) $test->query('SELECT COUNT(*) FROM reception_items i LEFT JOIN receptions r ON r.id=i.reception_id WHERE r.id IS NULL')->fetchColumn(),
        'stock_entries' => (int) $test->query('SELECT COUNT(*) FROM stock_entries e LEFT JOIN receptions r ON r.id=e.reception_id WHERE e.reception_id IS NOT NULL AND r.id IS NULL')->fetchColumn(),
        'movements' => (int) $test->query("SELECT COUNT(*) FROM mouvements_stock m LEFT JOIN stock_entries e ON e.id=m.reference_id WHERE m.reference_type='COMMAND' AND e.id IS NULL")->fetchColumn(),
    ];

    $checks = [
        'partial_reception' => $afterPartial === [
            'stock' => 31, 'received' => 5, 'receptions' => 1,
            'reception_items' => 1, 'stock_entries' => 1, 'mouvements_stock' => 1,
        ],
        'over_receipt_rollback' => $overRefused && $beforeOver === $afterOver,
        'complete_reception' => $afterComplete['stock'] === 36 && $afterComplete['received'] === 10
            && $afterComplete['status'] === 'RECEPTION_COMPLETE' && $afterComplete['stock_entries'] === 2
            && $afterComplete['mouvements_stock'] === 2,
        'forced_rollback_after_header' => str_contains($faultError, 'Erreur forcee apres en-tete reception') && $faultRolledBack,
        'no_orphans' => !array_filter($orphans),
    ];

    $result = [
        'engines' => $engines,
        'order' => ['id' => $orderId, 'ordered' => (int) $item['quantite_commandee'], 'status' => 'VALIDEE'],
        'partial' => $afterPartial,
        'over_receipt' => ['refused' => $overRefused, 'before' => $beforeOver, 'after' => $afterOver, 'unchanged' => $beforeOver === $afterOver],
        'complete' => $afterComplete,
        'fault_rollback' => ['passed' => $faultRolledBack, 'error' => $faultError, 'before' => $beforeFault, 'after' => $afterFault],
        'orphans' => $orphans,
        'dashboard' => ['stock_total' => $dashboard['widgets']['stock_total'], 'recent_receptions' => $dashboard['widgets']['receptions_recentes'], 'entry_months' => count($dashboard['charts']['stock_entries_monthly'])],
        'checks' => $checks,
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if (in_array(false, $checks, true)) {
        throw new RuntimeException('La validation finale ChargeCommande contient au moins un échec.');
    }
} finally {
    $source->exec("DROP DATABASE IF EXISTS `{$testDatabase}`");
}
