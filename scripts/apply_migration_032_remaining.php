<?php
/**
 * Applique la partie manquante de la migration 032 (ENUM ANNULATION_VENTE).
 * Idempotent : vérifie l'état avant modification.
 */
require_once __DIR__ . '/../config/database.php';

$db = db();
$row = $db->query("SHOW COLUMNS FROM mouvements_stock LIKE 'reference_type'")->fetch(PDO::FETCH_ASSOC);
$type = (string)($row['Type'] ?? '');

if (str_contains($type, 'ANNULATION_VENTE')) {
    echo "SKIP: reference_type contient deja ANNULATION_VENTE\n";
    exit(0);
}

$db->exec(
    "ALTER TABLE mouvements_stock
     MODIFY COLUMN reference_type
     ENUM('VENTE','COMMAND','INVENTAIRE','AJUSTEMENT','TRANSFERT','ANNULATION_VENTE') NOT NULL"
);

$row = $db->query("SHOW COLUMNS FROM mouvements_stock LIKE 'reference_type'")->fetch(PDO::FETCH_ASSOC);
echo 'APPLIED: ' . ($row['Type'] ?? 'UNKNOWN') . PHP_EOL;
