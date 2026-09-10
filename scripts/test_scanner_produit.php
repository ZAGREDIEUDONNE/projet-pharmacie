<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ProduitScannerService;

$pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$service = new ProduitScannerService($pdo);
$produit = $pdo->query('SELECT p.id FROM produits p JOIN stock s ON s.produit_id = p.id WHERE p.deleted_at IS NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$produit) {
    fwrite(STDERR, "Aucun produit avec stock pour le test.\n");
    exit(1);
}

$id = (int)$produit['id'];
$code = 'TEST-SCANNER-' . bin2hex(random_bytes(4));
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE produits SET code_barre = ?, is_actif = 1 WHERE id = ?')->execute([$code, $id]);
    $pdo->prepare('UPDATE stock SET quantite_disponible = 5, date_peremption = NULL WHERE produit_id = ?')->execute([$id]);
    $trouve = $service->rechercherParCodeBarres($code);
    if (!$trouve || !$trouve['vendable']) throw new RuntimeException('Produit existant non trouvé ou non vendable');

    $pdo->prepare('UPDATE stock SET quantite_disponible = 0 WHERE produit_id = ?')->execute([$id]);
    if (($service->rechercherParCodeBarres($code)['statut'] ?? '') !== 'STOCK_INSUFFISANT') throw new RuntimeException('Rupture non détectée');

    $pdo->prepare('UPDATE produits SET is_actif = 0 WHERE id = ?')->execute([$id]);
    if (($service->rechercherParCodeBarres($code)['statut'] ?? '') !== 'INACTIF') throw new RuntimeException('Produit inactif non détecté');

    $pdo->prepare('UPDATE produits SET is_actif = 1 WHERE id = ?')->execute([$id]);
    $pdo->prepare('UPDATE stock SET quantite_disponible = 5, date_peremption = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE produit_id = ?')->execute([$id]);
    if (($service->rechercherParCodeBarres($code)['statut'] ?? '') !== 'PERIME') throw new RuntimeException('Produit périmé non détecté');
    if ($service->rechercherParCodeBarres('INEXISTANT-' . $code) !== null) throw new RuntimeException('Code absent détecté à tort');

    $pdo->rollBack();
    echo "Tests scanner OK (transaction annulée, aucune donnée métier modifiée).\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Échec: {$e->getMessage()}\n");
    exit(1);
}
