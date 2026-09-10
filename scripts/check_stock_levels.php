<?php
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$r = $db->query(
    "SELECT p.id, p.nom, p.stock_alerte, p.stock_securite, p.fournisseur_id, p.prix_achat,
            COALESCE(s.quantite_disponible, 0) AS q
     FROM produits p
     LEFT JOIN stock s ON s.produit_id = p.id
     WHERE p.is_actif = 1 AND p.deleted_at IS NULL
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
