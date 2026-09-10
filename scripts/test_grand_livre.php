<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$plan = new App\Services\PlanComptableService($db);

try {
    $rows = $plan->getPlanComptable();
    echo 'Plan comptable: ' . count($rows) . ' comptes' . PHP_EOL;
    if (!empty($rows[0])) {
        echo json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'FAIL plan: ' . $e->getMessage() . PHP_EOL;
}

$audit = new App\Services\AuditService($db);
$grandLivre = new App\Services\GrandLivreService($db, $audit);

try {
    $soldes = $grandLivre->getSoldesComptes();
    echo 'Soldes comptes: ' . count($soldes) . PHP_EOL;
} catch (Throwable $e) {
    echo 'FAIL grand livre: ' . $e->getMessage() . PHP_EOL;
}
