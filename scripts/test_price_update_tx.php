<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$produitId = (int)$db->query('SELECT id FROM produits WHERE deleted_at IS NULL LIMIT 1')->fetchColumn();

$db->exec(
    "CREATE TABLE IF NOT EXISTS product_price_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produit_id INT NOT NULL,
        old_prix_achat DECIMAL(12,2) NOT NULL,
        new_prix_achat DECIMAL(12,2) NOT NULL,
        old_prix_vente DECIMAL(12,2) NOT NULL,
        new_prix_vente DECIMAL(12,2) NOT NULL,
        motif TEXT NOT NULL,
        utilisateur_id INT NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

$db->beginTransaction();
$stmt = $db->prepare('SELECT prix_achat, prix_vente FROM produits WHERE id = ? FOR UPDATE');
$stmt->execute([$produitId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$newAchat = (float)$row['prix_achat'] + 1;
$newVente = round($newAchat * 1.48, 2);

$hist = $db->prepare(
    'INSERT INTO product_price_history (produit_id, old_prix_achat, new_prix_achat, old_prix_vente, new_prix_vente, motif, utilisateur_id, changed_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
);
$hist->execute([$produitId, $row['prix_achat'], $newAchat, $row['prix_vente'], $newVente, 'test script']);

$upd = $db->prepare('UPDATE produits SET prix_achat = ?, prix_vente = ? WHERE id = ?');
$upd->execute([$newAchat, $newVente, $produitId]);

if ($db->inTransaction()) {
    $db->commit();
    echo "OK: transaction committed for produit #$produitId\n";
} else {
    echo "FAIL: no active transaction\n";
}

// restore
$db->prepare('UPDATE produits SET prix_achat = ?, prix_vente = ? WHERE id = ?')->execute([$row['prix_achat'], $row['prix_vente'], $produitId]);
