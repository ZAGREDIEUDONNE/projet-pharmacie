<?php

require 'config/database.php';

$pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$indexes = $pdo->query("SHOW INDEX FROM vente_ordonnance_items WHERE Key_name = 'unique_ordonnance_produit'")->fetchAll(PDO::FETCH_ASSOC);
if ($indexes) {
    $pdo->exec('ALTER TABLE vente_ordonnance_items DROP INDEX unique_ordonnance_produit');
}
$indexes = $pdo->query("SHOW INDEX FROM vente_ordonnance_items WHERE Key_name = 'unique_ordonnance_vente_produit'")->fetchAll(PDO::FETCH_ASSOC);
if (!$indexes) {
    $pdo->exec('ALTER TABLE vente_ordonnance_items ADD UNIQUE KEY unique_ordonnance_vente_produit (ordonnance_id, vente_id, produit_id)');
}
echo "Migration 030 exécutée.\n";
