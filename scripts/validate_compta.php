<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$audit = new App\Services\AuditService($db);
$balance = new App\Services\BalanceGeneraleService($db, $audit);
$b = $balance->genererBalance(date('Y-m-d'));
echo "Debit: " . $b['totaux_generaux']['total_debit'] . "\n";
echo "Credit: " . $b['totaux_generaux']['total_credit'] . "\n";
echo "Equilibre: " . ($b['totaux_generaux']['equilibree'] ? 'OUI' : 'NON') . "\n";
echo "Comptes: " . $b['totaux_generaux']['nombre_comptes'] . "\n";
$journal = new App\Services\JournalComptableService($db, $audit);
$ecritures = $journal->getEcrituresJournal('VT', '2020-01-01', date('Y-m-d'));
echo "Ecritures VT: " . count($ecritures) . "\n";
