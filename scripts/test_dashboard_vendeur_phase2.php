<?php
/**
 * Integration smoke test for the seller flow.
 * It uses the local database but always rolls back the outer transaction.
 */
require __DIR__ . '/../config/database.php';
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});

use App\Services\AuditService;
use App\Services\CaisseService;
use App\Services\ComptabiliteService;
use App\Services\StockService;
use App\Services\VenteService;
use App\Services\ProduitScannerService;
use App\Services\RBACService;

$db = db();
$result = ['sale_flow' => 'FAIL', 'credit_flow' => 'FAIL', 'suspension_flow' => 'FAIL', 'legacy_ticket_protection' => 'NON_TESTE', 'scanner_flow' => 'FAIL', 'rbac_flow' => 'FAIL', 'details' => []];

try {
    $seller = $db->query("SELECT u.id, u.role_id, r.nom AS role_code FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE LOWER(r.nom) = 'vendeur' AND u.deleted_at IS NULL LIMIT 1")->fetch();
    $product = $db->query("SELECT p.id, p.prix_vente, s.quantite_disponible FROM produits p JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND s.quantite_disponible >= 2 AND (s.date_peremption IS NULL OR s.date_peremption >= CURDATE()) LIMIT 1")->fetch();
    $session = $seller ? $db->prepare("SELECT id FROM caisse_sessions WHERE caissier_id = ? AND statut_session = 'OUVERTE' LIMIT 1") : null;
    if ($session) $session->execute([$seller['id']]);
    $sessionId = $session ? (int)$session->fetchColumn() : 0;
    if (!$seller || !$product || !$sessionId) throw new RuntimeException('Préconditions de test absentes (vendeur, produit ou session caisse).');

    $_SESSION['user'] = ['id' => (int)$seller['id'], 'role_id' => (int)$seller['role_id'], 'role_code' => $seller['role_code']];
    $audit = new AuditService($db);
    $stock = new StockService($db, $audit);
    $caisse = new CaisseService($db, $audit);
    $vente = new VenteService($db, $stock, $caisse, new ComptabiliteService($db, $audit), $audit);
    $base = [
        'utilisateur_id' => (int)$seller['id'], 'caisse_session_id' => $sessionId,
        'articles' => [['produit_id' => (int)$product['id'], 'quantite' => 1]],
        'montant_total' => (float)$product['prix_vente'], 'montant_paye' => (float)$product['prix_vente'],
        'type_paiement' => 'ESPECE', 'is_credit' => false, 'paiement_details' => [],
        'utilisateur' => $_SESSION['user'],
    ];

    $db->beginTransaction();
    $stmt = $db->prepare('SELECT quantite_disponible FROM stock WHERE produit_id = ?'); $stmt->execute([(int)$product['id']]); $before = (int)$stmt->fetchColumn();

    $paid = $vente->creerVente($base);
    $stmt->execute([(int)$product['id']]); $afterPaid = (int)$stmt->fetchColumn();
    $cash = $db->prepare('SELECT vente_id FROM mouvements_caisse WHERE vente_id = ?'); $cash->execute([$paid['vente_id']]);
    $stockMovement = $db->prepare("SELECT COUNT(*) FROM mouvements_stock WHERE reference_type = 'VENTE' AND reference_id = ? AND produit_id = ?"); $stockMovement->execute([$paid['vente_id'], $product['id']]);
    $entry = $db->prepare('SELECT montant_ht, montant_tva, montant_ttc, ecriture_id FROM ventes WHERE id = ?'); $entry->execute([$paid['vente_id']]); $amounts = $entry->fetch();
    $balance = $db->prepare('SELECT total_debit, total_credit FROM ecritures_comptables WHERE id = ?'); $balance->execute([$amounts['ecriture_id']]); $balance = $balance->fetch();
    $accounts = $db->prepare('SELECT compte_code, debit, credit FROM lignes_ecritures WHERE ecriture_id = ?'); $accounts->execute([$amounts['ecriture_id']]); $accounts = $accounts->fetchAll();
    $item = $db->prepare('SELECT prix_unitaire FROM ventes_items WHERE vente_id = ? LIMIT 1'); $item->execute([$paid['vente_id']]); $itemPrice = (float)$item->fetchColumn();
    $accountMap = [];
    foreach ($accounts as $account) $accountMap[(string)$account['compte_code']] = $account;
    if ($afterPaid === $before - 1 && (int)$cash->fetchColumn() === (int)$paid['vente_id'] && (int)$stockMovement->fetchColumn() === 1 && !empty($amounts['ecriture_id']) && abs(((float)$amounts['montant_ht'] + (float)$amounts['montant_tva']) - (float)$amounts['montant_ttc']) < 0.01 && abs((float)$balance['total_debit'] - (float)$balance['total_credit']) < 0.01 && isset($accountMap['571'], $accountMap['701'], $accountMap['44571']) && (float)$accountMap['571']['debit'] > 0 && (float)$accountMap['701']['credit'] > 0 && (float)$accountMap['44571']['credit'] > 0 && abs($itemPrice - (float)$product['prix_vente']) < 0.01) $result['sale_flow'] = 'PASS';

    // Credit sale: a client receivable is created, the ceiling is respected and
    // accounting debits account 411 without creating a cash movement.
    $creditClient = $db->prepare('SELECT id, solde_credit FROM clients WHERE is_actif = 1 AND deleted_at IS NULL AND plafond_credit - solde_credit >= ? LIMIT 1');
    $creditClient->execute([(float)$product['prix_vente']]);
    $creditClient = $creditClient->fetch();
    if (!$creditClient) throw new RuntimeException('Précondition de test crédit absente (client avec plafond disponible).');
    $credit = $vente->creerVente(array_merge($base, [
        'client_id' => (int)$creditClient['id'], 'montant_paye' => 0,
        'type_paiement' => 'CREDIT', 'is_credit' => true,
    ]));
    $creditRow = $db->prepare('SELECT montant, montant_restant FROM ventes_credit WHERE vente_id = ?'); $creditRow->execute([$credit['vente_id']]); $creditRow = $creditRow->fetch();
    $clientBalance = $db->prepare('SELECT solde_credit FROM clients WHERE id = ?'); $clientBalance->execute([$creditClient['id']]);
    $creditCash = $db->prepare('SELECT COUNT(*) FROM mouvements_caisse WHERE vente_id = ?'); $creditCash->execute([$credit['vente_id']]);
    $creditEntry = $db->prepare('SELECT ecriture_id FROM ventes WHERE id = ?'); $creditEntry->execute([$credit['vente_id']]);
    $creditAccounts = $db->prepare('SELECT compte_code, debit FROM lignes_ecritures WHERE ecriture_id = ?'); $creditAccounts->execute([$creditEntry->fetchColumn()]);
    $creditAccountMap = []; foreach ($creditAccounts as $account) $creditAccountMap[(string)$account['compte_code']] = $account;
    if ($creditRow && abs((float)$creditRow['montant'] - (float)$product['prix_vente']) < 0.01 && abs((float)$creditRow['montant_restant'] - (float)$product['prix_vente']) < 0.01 && abs((float)$clientBalance->fetchColumn() - ((float)$creditClient['solde_credit'] + (float)$product['prix_vente'])) < 0.01 && (int)$creditCash->fetchColumn() === 0 && isset($creditAccountMap['411']) && (float)$creditAccountMap['411']['debit'] > 0) $result['credit_flow'] = 'PASS';
    $stmt->execute([(int)$product['id']]); $afterCredit = (int)$stmt->fetchColumn();

    // Simulate each scanner state inside the enclosing transaction: no fixture is persisted.
    $scanner = new ProduitScannerService($db);
    $barcode = 'PH2-SCAN-' . $product['id'];
    $productState = $db->prepare('SELECT code_barre, is_actif FROM produits WHERE id = ? FOR UPDATE'); $productState->execute([$product['id']]); $productState = $productState->fetch();
    $stockState = $db->prepare('SELECT quantite_disponible, date_peremption FROM stock WHERE produit_id = ? FOR UPDATE'); $stockState->execute([$product['id']]); $stockState = $stockState->fetch();
    $db->prepare('UPDATE produits SET code_barre = ?, is_actif = 1 WHERE id = ?')->execute([$barcode, $product['id']]);
    $db->prepare('UPDATE stock SET quantite_disponible = 2, date_peremption = NULL WHERE produit_id = ?')->execute([$product['id']]);
    $scannerAvailable = $scanner->rechercherParCodeBarres($barcode);
    $scannerUnknown = $scanner->rechercherParCodeBarres($barcode . '-UNKNOWN');
    $db->prepare('UPDATE produits SET is_actif = 0 WHERE id = ?')->execute([$product['id']]);
    $scannerInactive = $scanner->rechercherParCodeBarres($barcode);
    $db->prepare('UPDATE produits SET is_actif = 1 WHERE id = ?')->execute([$product['id']]);
    $db->prepare('UPDATE stock SET quantite_disponible = 0 WHERE produit_id = ?')->execute([$product['id']]);
    $scannerOutOfStock = $scanner->rechercherParCodeBarres($barcode);
    $db->prepare("UPDATE stock SET quantite_disponible = 1, date_peremption = '2000-01-01' WHERE produit_id = ?")->execute([$product['id']]);
    $scannerExpired = $scanner->rechercherParCodeBarres($barcode);
    $db->prepare('UPDATE produits SET code_barre = ?, is_actif = ? WHERE id = ?')->execute([$productState['code_barre'], $productState['is_actif'], $product['id']]);
    $db->prepare('UPDATE stock SET quantite_disponible = ?, date_peremption = ? WHERE produit_id = ?')->execute([$stockState['quantite_disponible'], $stockState['date_peremption'], $product['id']]);
    if ($scannerAvailable && $scannerAvailable['vendable'] && abs((float)$scannerAvailable['prix_vente'] - (float)$product['prix_vente']) < 0.01 && $scannerUnknown === null && $scannerInactive && !$scannerInactive['vendable'] && $scannerInactive['statut'] === 'INACTIF' && $scannerOutOfStock && !$scannerOutOfStock['vendable'] && $scannerOutOfStock['statut'] === 'STOCK_INSUFFISANT' && $scannerExpired && !$scannerExpired['vendable'] && $scannerExpired['statut'] === 'PERIME') $result['scanner_flow'] = 'PASS';

    $draft = $vente->creerVente(array_merge($base, ['suspendre' => true, 'montant_paye' => 0]));
    $stmt->execute([(int)$product['id']]); $afterDraft = (int)$stmt->fetchColumn();
    $draftState = $db->prepare('SELECT ecriture_id, montant_paye FROM ventes WHERE id = ?'); $draftState->execute([$draft['vente_id']]); $draftState = $draftState->fetch();
    $resumed = $vente->creerVente(array_merge($base, ['vente_id' => $draft['vente_id']]));
    $stmt->execute([(int)$product['id']]); $afterResume = (int)$stmt->fetchColumn();
    if ($afterDraft === $afterCredit && empty($draftState['ecriture_id']) && (float)$draftState['montant_paye'] === 0.0 && $afterResume === $afterCredit - 1) $result['suspension_flow'] = 'PASS';

    $legacy = $db->query("SELECT id, utilisateur_id FROM ventes WHERE statut_vente = 'EN_COURS' AND ecriture_id IS NOT NULL AND deleted_at IS NULL LIMIT 1")->fetch();
    if ($legacy) {
        $beforeLegacyItems = $db->prepare('SELECT COUNT(*) FROM ventes_items WHERE vente_id = ?'); $beforeLegacyItems->execute([$legacy['id']]); $beforeLegacyItems = (int)$beforeLegacyItems->fetchColumn();
        try {
            $vente->creerVente(array_merge($base, ['vente_id' => (int)$legacy['id'], 'utilisateur_id' => (int)$legacy['utilisateur_id']]));
        } catch (Throwable $e) {
            $afterLegacyItems = $db->prepare('SELECT COUNT(*) FROM ventes_items WHERE vente_id = ?'); $afterLegacyItems->execute([$legacy['id']]);
            if ((int)$afterLegacyItems->fetchColumn() === $beforeLegacyItems) $result['legacy_ticket_protection'] = 'PASS';
        }
    }

    $rbac = new RBACService($db);
    // The real schema uses utilisateur_permissions.  Validate an individual
    // temporary grant inside the outer rollback transaction.
    $creditPermissionId = (int)$db->query("SELECT id FROM permissions WHERE nom = 'vente.credit' LIMIT 1")->fetchColumn();
    if ($creditPermissionId <= 0) throw new RuntimeException('Permission vente.credit absente.');
    $db->prepare('INSERT INTO utilisateur_permissions (utilisateur_id, permission_id, statut, date_attribution) VALUES (?, ?, 1, NOW())')->execute([(int)$seller['id'], $creditPermissionId]);
    $individualPermissionPass = $rbac->hasPermission((int)$seller['id'], 'vente.credit');
    $usersByRole = [];
    foreach ($db->query("SELECT LOWER(r.nom) AS role_name, u.id FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE u.deleted_at IS NULL AND LOWER(r.nom) IN ('vendeur', 'assistant', 'charge_commande', 'comptable', 'administrateur') ORDER BY u.id") as $roleUser) {
        $usersByRole[$roleUser['role_name']] ??= (int)$roleUser['id'];
    }
    $rbacChecks = [
        'vendeur' => ['vente.create' => true, 'client.create' => true, 'stock.view' => true, 'caisse.open' => true, 'user.manage' => false, 'stock.update_product' => false],
        'assistant' => ['close_cash_register' => true, 'user.manage' => false],
        'charge_commande' => ['stock.view' => true, 'vente.create' => false, 'client.view' => false],
        'comptable' => ['vente.create' => false, 'stock.update_product' => false, 'caisse.view' => false],
        'administrateur' => ['user.manage' => true, 'vente.create' => true, 'stock.update_product' => true],
    ];
    $rbacResults = [];
    foreach ($rbacChecks as $role => $checks) {
        if (empty($usersByRole[$role])) {
            $rbacResults[$role] = 'NON_TESTE';
            continue;
        }
        $rolePass = true;
        foreach ($checks as $permission => $expected) {
            $actual = $rbac->hasPermission($usersByRole[$role], $permission);
            $rbacResults[$role][$permission] = $actual;
            $rolePass = $rolePass && ($actual === $expected);
        }
        $rbacResults[$role]['status'] = $rolePass ? 'PASS' : 'FAIL';
    }
    if (!in_array('FAIL', array_column($rbacResults, 'status'), true) && !in_array('NON_TESTE', $rbacResults, true) && $individualPermissionPass) $result['rbac_flow'] = 'PASS';
    $result['details'] = compact('before', 'afterPaid', 'afterCredit', 'afterDraft', 'afterResume', 'draftState', 'itemPrice', 'amounts', 'balance', 'accounts', 'rbacResults', 'individualPermissionPass');
} catch (Throwable $e) {
    $result['details']['error'] = $e->getMessage();
} finally {
    if ($db->inTransaction()) $db->rollBack();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
