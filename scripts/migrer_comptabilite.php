<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$audit = new App\Services\AuditService($db);
$journal = new App\Services\JournalComptableService($db, $audit);
$plan = new App\Services\PlanComptableService($db);
$ecriture = new App\Services\EcritureComptableService($db, $audit, $journal);

echo "=== Verification SYSCOHADA ===\n\n";

try {
    $journal->ensureComptabiliteReady();
    
    $counts = [
        'journaux_comptables' => $db->query('SELECT COUNT(*) FROM journaux_comptables WHERE is_actif = 1')->fetchColumn(),
        'lignes_ecritures' => $db->query('SELECT COUNT(*) FROM lignes_ecritures')->fetchColumn(),
        'ecritures_headers' => $db->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn(),
        'ventes_avec_ecriture' => $db->query('SELECT COUNT(*) FROM ventes WHERE ecriture_id IS NOT NULL')->fetchColumn(),
        'plan_comptable' => $db->query('SELECT COUNT(*) FROM plan_comptable WHERE is_actif = 1')->fetchColumn(),
    ];
    echo "Verification:\n";
    foreach ($counts as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
    echo "\nArchitecture SYSCOHADA active - journaux_comptables uniquement.\n";
} catch (Throwable $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
