<?php

declare(strict_types=1);

/**
 * Recette comptable Phase 3. Toutes les données métier sont créées dans
 * medecin_test, qui est refusée si elle préexiste puis supprimée en finally.
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Services\AuditService;
use App\Services\BalanceGeneraleService;
use App\Services\ChargeCommandeService;
use App\Controllers\ComptabiliteController;
use App\Services\EcritureComptableService;
use App\Services\EtatFinancierService;
use App\Services\GrandLivreService;
use App\Services\JournalComptableService;
use App\Services\ReglementTiersService;
use App\Services\TVAService;

$source = Database::getConnection();
$schema = 'medecin_test';
$results = ['engines' => [], 'tests' => [], 'counters' => [], 'errors' => [], 'rollback' => null, 'integrity' => [], 'limits' => [], 'summary' => []];
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$test = static function (string $name, callable $fn) use (&$results): void {
    try { $details = $fn(); $results['tests'][] = ['name' => $name, 'status' => 'PASS', 'details' => $details]; }
    catch (Throwable $e) { $results['tests'][] = ['name' => $name, 'status' => 'FAIL', 'details' => $e->getMessage()]; $results['errors'][] = ['test' => $name, 'error' => $e->getMessage()]; }
};
$tableList = [
    'roles', 'utilisateurs', 'clients', 'fournisseurs', 'produits', 'stock', 'ventes', 'supplier_orders', 'supplier_order_items',
    'receptions', 'reception_items', 'stock_entries', 'mouvements_stock', 'fournisseur_reglements',
    'caisse_sessions', 'mouvements_caisse', 'classes_comptes', 'plan_comptable', 'journaux_comptables',
    'exercices_comptables', 'ecritures_comptables', 'lignes_ecritures', 'tva_taux', 'audit_logs', 'system_errors',
];

if ((int)$source->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'medecin_test'")->fetchColumn() > 0) {
    fwrite(STDERR, "REFUS: medecin_test existe déjà; aucune modification effectuée.\n");
    exit(2);
}

// Témoins de production strictement lus avant et après la recette.
$productionWitness = $source->query("SELECT id, numero_piece, total_debit, total_credit, libelle, reference_type, reference_id FROM ecritures_comptables WHERE id = 109")->fetch();
$results['counters']['production_witness_before'] = $productionWitness ?: null;

try {
    foreach ($tableList as $table) {
        $stmt = $source->prepare("SELECT engine FROM information_schema.tables WHERE table_schema = 'medecin' AND table_name = ?");
        $stmt->execute([$table]);
        $engine = $stmt->fetchColumn();
        if ($engine === false) throw new RuntimeException("Table réelle absente: {$table}");
        $results['engines'][$table] = $engine;
        if (strcasecmp((string)$engine, 'InnoDB') !== 0) throw new RuntimeException("Table non transactionnelle: {$table}");
    }

    $source->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO("mysql:host=localhost;port=3306;dbname={$schema};charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    foreach ($tableList as $table) $db->exec("CREATE TABLE `{$table}` LIKE `medecin`.`{$table}`");

    $results['counters']['before'] = ['ecritures' => 0, 'lignes' => 0, 'ventes' => 0, 'receptions' => 0, 'mouvements_caisse' => 0];

    // Données minimales de recette. Les montants et dates correspondent au schéma réel.
    $role = $source->query("SELECT id FROM roles ORDER BY id LIMIT 1")->fetchColumn();
    $db->prepare('INSERT INTO roles (id, nom) SELECT id, nom FROM medecin.roles WHERE id = ?')->execute([$role]);
    $db->prepare('INSERT INTO utilisateurs (username, password_hash, nom, prenom, role_id, is_active) VALUES (?,?,?,?,?,1)')->execute(['recette_compta', 'x', 'Recette', 'Compta', $role]);
    $userId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO fournisseurs (code, nom, is_actif) VALUES (?,?,1)')->execute(['F-RECETTE', 'Fournisseur recette']);
    $supplierId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO produits (code_cip, nom, prix_achat, prix_vente, is_actif) VALUES (?,?,?,?,1)')->execute(['RECETTE-001', 'Produit recette', 100, 118]);
    $productId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, quantite_reservee, valeur_stock) VALUES (?,0,0,0,0)')->execute([$productId]);
    $db->exec("INSERT INTO exercices_comptables (exercice,date_debut,date_fin,statut) VALUES ('2026','2026-01-01','2026-12-31','ouvert')");
    $db->exec("INSERT INTO classes_comptes (code,libelle,is_actif) VALUES ('3','Stocks',1),('4','Tiers',1),('5','Tresorerie',1),('6','Charges',1),('7','Produits',1)");
    $accounts = [['311','Stock medicaments',3,'ACTIF'],['401','Fournisseurs',4,'PASSIF'],['411','Clients',4,'ACTIF'],['44561','TVA deductible',4,'ACTIF'],['44571','TVA collectee',4,'PASSIF'],['521','Banque',5,'ACTIF'],['571','Caisse',5,'ACTIF'],['581','Virements internes',5,'ACTIF'],['65','Autres charges',6,'CHARGE'],['701','Ventes',7,'PRODUIT'],['75','Autres produits',7,'PRODUIT'],['6031','Variation stock',6,'CHARGE']];
    $accountStmt = $db->prepare('INSERT INTO plan_comptable (numero_compte,code,nom_compte,libelle,classe,classe_id,type_compte,type,is_actif) VALUES (?,?,?,?,?,?,?,?,1)');
    foreach ($accounts as [$code,$label,$class,$type]) $accountStmt->execute([$code,$code,$label,$label,$class,(string)$class,$type,$type]);
    $db->exec("INSERT INTO journaux_comptables (code,libelle,type_journal,is_actif) VALUES ('VT','Ventes','ventes',1),('AC','Achats','achats',1),('CA','Caisse','caisse',1),('BQ','Banque','banque',1),('OD','Divers','operations_diverses',1)");

    $audit = new AuditService($db);
    $journal = new JournalComptableService($db, $audit);
    $ecritures = new EcritureComptableService($db, $audit, $journal);
    $reglements = new ReglementTiersService($db, null, $ecritures);
    $chargeCommande = new ChargeCommandeService($db, $audit, $ecritures);
    $tva = new TVAService($db, $audit);
    $balance = new BalanceGeneraleService($db, $audit);
    $grandLivre = new GrandLivreService($db, $audit);
    $etats = new EtatFinancierService($db, $audit);

    $cashSaleId = 0; $creditSaleId = 0; $receptionId = 0;
    $test('A. vente comptant', static function () use ($db, $ecritures, $userId, &$cashSaleId, $assert): array {
        $db->prepare("INSERT INTO ventes (numero_facture,utilisateur_id,date_vente,montant_total,montant_ht,montant_tva,montant_ttc,montant_net,montant_paye,montant_restant,type_paiement,statut_vente,is_credit,type_vente) VALUES ('V-REC-CASH',?,'2026-06-10 10:00:00',118,100,18,118,118,118,0,'ESPECE','PAYEE',0,'COMPTANT')")->execute([$userId]);
        $cashSaleId = (int)$db->lastInsertId();
        $sale = $db->query("SELECT * FROM ventes WHERE id={$cashSaleId}")->fetch();
        $result = $ecritures->genererEcrituresVente($sale);
        $lines = $db->query("SELECT compte_code,debit,credit FROM lignes_ecritures WHERE ecriture_id=".(int)$result['ecriture_id'])->fetchAll();
        $codes = array_column($lines, 'compte_code');
        $assert(in_array('571',$codes,true) && in_array('701',$codes,true) && in_array('44571',$codes,true), 'Comptes vente comptant incomplets');
        $assert((float)$result['total_debit'] === (float)$result['total_credit'], 'Vente comptant déséquilibrée');
        return ['ecriture_id'=>$result['ecriture_id'], 'lines'=>$lines];
    });
    $test('B. vente à crédit', static function () use ($db, $ecritures, $userId, &$creditSaleId, $assert): array {
        $db->prepare("INSERT INTO ventes (numero_facture,utilisateur_id,date_vente,montant_total,montant_ht,montant_tva,montant_ttc,montant_net,montant_paye,montant_restant,type_paiement,statut_vente,is_credit,type_vente) VALUES ('V-REC-CREDIT',?,'2026-06-11 10:00:00',118,100,18,118,118,0,118,'CREDIT','EN_COURS',1,'CREDIT')")->execute([$userId]);
        $creditSaleId = (int)$db->lastInsertId(); $sale = $db->query("SELECT * FROM ventes WHERE id={$creditSaleId}")->fetch(); $sale['client_nom'] = 'Client recette';
        $result = $ecritures->genererEcrituresVente($sale); $codes = $db->query("SELECT compte_code FROM lignes_ecritures WHERE ecriture_id=".(int)$result['ecriture_id'])->fetchAll(PDO::FETCH_COLUMN);
        $assert(in_array('411',$codes,true) && in_array('701',$codes,true) && in_array('44571',$codes,true), 'Comptes vente crédit incomplets');
        return ['ecriture_id'=>$result['ecriture_id']];
    });
    $test('C-D. TVA et contre-passation', static function () use ($db, $ecritures, $tva, $cashSaleId, $userId, $assert): array {
        $before = $tva->genererDeclarationTVA('2026-06-01','2026-06-30'); $assert((float)$before['totaux']['tva_collectee'] === 36.0, 'TVA initiale incohérente');
        $ecritures->annulerEcritureVente($cashSaleId, $userId);
        $inverse = $db->query("SELECT * FROM ecritures_comptables WHERE reference_type='ANNULATION_VENTE' AND reference_id={$cashSaleId}")->fetch();
        $assert($inverse && (int)$inverse['ecriture_origine_id'] > 0, 'Contre-passation absente');
        $count = (int)$db->query("SELECT COUNT(*) FROM ecritures_comptables WHERE ecriture_origine_id=".(int)$inverse['ecriture_origine_id'])->fetchColumn();
        try { $ecritures->annulerEcritureVente($cashSaleId, $userId); throw new RuntimeException('Double contre-passation acceptée'); } catch (Throwable $e) { $assert($e->getMessage() !== 'Double contre-passation acceptée', 'Double contre-passation acceptée'); }
        $after = $tva->genererDeclarationTVA('2026-06-01','2026-06-30'); $assert((float)$after['totaux']['tva_collectee'] === 18.0, 'TVA contre-passée non neutralisée');
        return ['inverse_id'=>$inverse['id'], 'inverse_count'=>$count, 'tva_before'=>$before['totaux'], 'tva_after'=>$after['totaux']];
    });
    $test('E. réception fournisseur réelle', static function () use ($chargeCommande, $db, $supplierId, $productId, $userId, &$receptionId, $assert): array {
        $order = $chargeCommande->createSupplierOrder(['fournisseur_id'=>$supplierId,'date_commande'=>'2026-06-12','date_livraison_prevue'=>'2026-06-13','tva_globale'=>18,'items'=>[['produit_id'=>$productId,'quantite'=>4,'prix_achat'=>100]]], $userId);
        $itemId = (int)$db->query('SELECT id FROM supplier_order_items WHERE supplier_order_id='.(int)$order['order_id'])->fetchColumn();
        $first = $chargeCommande->receiveOrder(['supplier_order_id'=>$order['order_id'],'date_reception'=>'2026-06-13','montant_facture'=>236,'items'=>[['supplier_order_item_id'=>$itemId,'quantite_recue'=>2]]], $userId);
        $receptionId = (int)$first['reception_id'];
        $second = $chargeCommande->receiveOrder(['supplier_order_id'=>$order['order_id'],'date_reception'=>'2026-06-14','montant_facture'=>236,'items'=>[['supplier_order_item_id'=>$itemId,'quantite_recue'=>2]]], $userId);
        $stock = $db->query("SELECT quantite_disponible FROM stock WHERE produit_id={$productId}")->fetchColumn();
        $status = $db->query('SELECT statut FROM supplier_orders WHERE id='.(int)$order['order_id'])->fetchColumn();
        $assert((int)$stock === 4 && $status === 'RECEPTION_COMPLETE', 'Réception partielle/reliquat incohérente');
        $assert((int)$db->query('SELECT COUNT(*) FROM stock_entries')->fetchColumn() === 2, 'Entrées stock attendues absentes');
        return ['order_id'=>$order['order_id'], 'receptions'=>[$first['reception_id'],$second['reception_id']], 'stock_final'=>$stock];
    });
    $test('E. écriture comptable réception', static function () use ($db, $receptionId, $assert): array {
        $ecritureId = (int)$db->query("SELECT ecriture_id FROM receptions WHERE id={$receptionId}")->fetchColumn();
        $assert($ecritureId > 0, 'La réception réelle n’a pas créé d’écriture comptable');
        $codes = $db->query('SELECT compte_code FROM lignes_ecritures WHERE ecriture_id='.$ecritureId)->fetchAll(PDO::FETCH_COLUMN);
        $assert(in_array('311',$codes,true) && in_array('44561',$codes,true) && in_array('401',$codes,true), 'Comptes réception incomplets');
        return ['ecriture_id'=>$ecritureId, 'codes'=>$codes];
    });
    $test('F. règlement fournisseur comptable', static function () use ($db, $reglements, $supplierId, $receptionId, $userId, $assert): array {
        $result = $reglements->enregistrerReglementFournisseur(['fournisseur_id'=>$supplierId, 'reception_id'=>$receptionId, 'montant'=>50, 'type_mouvement'=>'CREDIT', 'mode_paiement'=>'VIREMENT', 'reference'=>'RF-RECETTE'], $userId);
        $reglementId = (int)$result['reglement_id'];
        $ecriture = $db->query("SELECT id,total_debit,total_credit FROM ecritures_comptables WHERE reference_type='REGLEMENT_FOURNISSEUR' AND reference_id={$reglementId}")->fetch();
        $assert($ecriture && (float)$ecriture['total_debit'] === 50.0 && (float)$ecriture['total_credit'] === 50.0, 'Écriture règlement absente ou déséquilibrée');
        $codes=$db->query('SELECT compte_code FROM lignes_ecritures WHERE ecriture_id='.(int)$ecriture['id'])->fetchAll(PDO::FETCH_COLUMN);
        $assert(in_array('401',$codes,true) && in_array('521',$codes,true), 'Comptes règlement fournisseur incomplets');
        $before = (int)$db->query('SELECT COUNT(*) FROM fournisseur_reglements')->fetchColumn();
        try { $reglements->enregistrerReglementFournisseur(['fournisseur_id'=>$supplierId, 'montant'=>50, 'type_mouvement'=>'CREDIT', 'reference'=>'RF-RECETTE'], $userId); throw new RuntimeException('Doublon règlement accepté'); } catch (Throwable $error) { $assert($error->getMessage() !== 'Doublon règlement accepté', 'Doublon règlement accepté'); }
        $assert($before === (int)$db->query('SELECT COUNT(*) FROM fournisseur_reglements')->fetchColumn(), 'Doublon règlement partiellement persisté');
        return ['reglement_id'=>$reglementId, 'ecriture_id'=>$ecriture['id'], 'codes'=>$codes, 'anti_doublon'=>'PASS'];
    });
    $test('G. caisse et anti-doublon', static function () use ($db, $ecritures, $userId, $assert): array {
        $db->prepare("INSERT INTO caisse_sessions (numero_session,caissier_id,date_ouverture,montant_ouverture,statut_session) VALUES ('CS-REC',?,'2026-06-15 08:00:00',0,'OUVERTE')")->execute([$userId]); $sessionId=(int)$db->lastInsertId();
        $db->prepare("INSERT INTO mouvements_caisse (caisse_session_id,type_mouvement,montant,solde_avant,solde_apres,moyen_paiement,reference,description,utilisateur_id,date_mouvement) VALUES (?,'DECAISSEMENT',50,0,-50,'ESPECE','MC-REC','Décaissement recette',?,'2026-06-15 09:00:00')")->execute([$sessionId,$userId]);
        $move=$db->query('SELECT * FROM mouvements_caisse WHERE id='.(int)$db->lastInsertId())->fetch(); $move['motif']='Décaissement recette';
        $result=$ecritures->genererEcrituresCaisse($move); try {$ecritures->genererEcrituresCaisse($move); throw new RuntimeException('Doublon caisse accepté');} catch(Throwable $e){$assert($e->getMessage() !== 'Doublon caisse accepté','Doublon caisse accepté');}
        return ['ecriture_id'=>$result['ecriture_id']];
    });
    $test('M. données dashboard comptable', static function () use ($db, $ecritures, $userId, $assert): array {
        $today = date('Y-m-d');
        $db->prepare("INSERT INTO ventes (numero_facture,utilisateur_id,date_vente,montant_total,montant_ht,montant_tva,montant_ttc,montant_net,montant_paye,montant_restant,type_paiement,statut_vente,is_credit,type_vente) VALUES ('V-REC-DASH',?,CONCAT(?, ' 12:00:00'),118,100,18,118,118,118,0,'ESPECE','PAYEE',0,'COMPTANT')")->execute([$userId, $today]);
        $saleId = (int)$db->lastInsertId();
        $ecritures->genererEcrituresVente($db->query("SELECT * FROM ventes WHERE id={$saleId}")->fetch());
        $controller = new ComptabiliteController();
        $property = new ReflectionProperty(\App\Core\BaseController::class, 'db');
        $property->setAccessible(true); $property->setValue($controller, $db);
        $reflection = new ReflectionClass($controller);
        $kpisMethod = $reflection->getMethod('getDashboardKpis'); $kpisMethod->setAccessible(true);
        $exerciseMethod = $reflection->getMethod('getStatsExercices'); $exerciseMethod->setAccessible(true);
        $activityMethod = $reflection->getMethod('getActivitesRecentes'); $activityMethod->setAccessible(true);
        $kpis = $kpisMethod->invoke($controller, $today); $exercises = $exerciseMethod->invoke($controller); $activities = $activityMethod->invoke($controller, 5);
        $assert((float)($kpis['chiffre_affaires_ttc'] ?? 0) >= 118.0, 'Dashboard: chiffre d’affaires non issu des écritures');
        $assert((int)($exercises['total_exercices'] ?? 0) === 1 && (int)($exercises['ouverts'] ?? 0) === 1, 'Dashboard: exercices non calculés');
        $assert(count($activities) > 0, 'Dashboard: activités comptables absentes');
        return ['kpis'=>$kpis, 'exercices'=>$exercises, 'activites'=>count($activities)];
    });
    $test('H. rollback volontaire', static function () use ($db, $ecritures, $userId, $assert, &$results): array {
        $before=['ecritures'=>(int)$db->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn(),'lignes'=>(int)$db->query('SELECT COUNT(*) FROM lignes_ecritures')->fetchColumn()];
        try {$db->beginTransaction(); $ecritures->enregistrerEcriture(['journal_code'=>'OD','libelle'=>'Rollback recette','reference_type'=>'TEST','reference_id'=>999,'utilisateur_id'=>$userId,'date_ecriture'=>'2026-06-16','lignes'=>[['compte_code'=>'571','debit'=>10,'credit'=>0],['compte_code'=>'75','debit'=>0,'credit'=>10]]]); throw new RuntimeException('Défaillance volontaire');} catch(Throwable) {if($db->inTransaction())$db->rollBack();}
        $after=['ecritures'=>(int)$db->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn(),'lignes'=>(int)$db->query('SELECT COUNT(*) FROM lignes_ecritures')->fetchColumn()]; $assert($before === $after,'Rollback incomplet'); $results['rollback']=['before'=>$before,'after'=>$after,'status'=>'PASS']; return $after;
    });
    $test('J-K-L. restitutions comptables', static function () use ($grandLivre, $balance, $etats, $assert): array {
        $gl=$grandLivre->getGrandLivreCompte('411','2026-06-01','2026-06-30'); $b=$balance->genererBalance('2026-06-30'); $cr=$etats->genererCompteResultat('2026-06-01','2026-06-30'); $bi=$etats->genererBilan('2026-06-30'); $tr=$etats->genererTableauTresorerie('2026-06-01','2026-06-30');
        $assert($b['totaux_generaux']['equilibree'] === true,'Balance déséquilibrée'); $assert($gl['nombre_ecritures'] > 0,'Grand livre 411 vide'); return ['grand_livre_411'=>$gl['nombre_ecritures'],'balance'=>$b['totaux_generaux'],'resultat'=>$cr['totaux_generaux'],'bilan'=>$bi['totaux_generaux'],'tresorerie'=>$tr['totaux_generaux']];
    });
    $test('I. intégrité comptable', static function () use ($db, &$results, $assert): array {
        $checks=['ecritures_desequilibrees'=>"SELECT COUNT(*) FROM ecritures_comptables WHERE total_debit <> total_credit OR is_equilibree <> 1",'lignes_orphelines'=>"SELECT COUNT(*) FROM lignes_ecritures le LEFT JOIN ecritures_comptables ec ON ec.id=le.ecriture_id WHERE ec.id IS NULL",'contrepassations_orphelines'=>"SELECT COUNT(*) FROM ecritures_comptables ec LEFT JOIN ecritures_comptables eo ON eo.id=ec.ecriture_origine_id WHERE ec.ecriture_origine_id IS NOT NULL AND eo.id IS NULL",'references_dupliquees'=>"SELECT COUNT(*) FROM (SELECT reference_type,reference_id,COUNT(*) n FROM ecritures_comptables WHERE reference_type <> 'ANNULATION_VENTE' GROUP BY reference_type,reference_id HAVING n>1) x"];
        foreach($checks as $name=>$sql){$value=(int)$db->query($sql)->fetchColumn();$results['integrity'][$name]=$value;$assert($value===0,"Anomalie: {$name}");} return $results['integrity'];
    });
    $results['counters']['final']=['ecritures'=>(int)$db->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn(),'lignes'=>(int)$db->query('SELECT COUNT(*) FROM lignes_ecritures')->fetchColumn()];
} catch (Throwable $e) { $results['errors'][] = ['fatal'=>$e->getMessage()]; }
finally { if (isset($source)) $source->exec("DROP DATABASE IF EXISTS `{$schema}`"); }

$results['counters']['production_witness_after'] = $source->query("SELECT id, numero_piece, total_debit, total_credit, libelle, reference_type, reference_id FROM ecritures_comptables WHERE id = 109")->fetch() ?: null;
$results['integrity']['production_witness_unchanged'] = $results['counters']['production_witness_before'] === $results['counters']['production_witness_after'];
if (!$results['integrity']['production_witness_unchanged']) $results['errors'][] = ['production_witness' => 'L’écriture de production TEST/999001 a changé pendant la recette.'];

$pass=count(array_filter($results['tests'],static fn(array $t):bool=>$t['status']==='PASS')); $fail=count($results['tests'])-$pass;
$results['summary']=['PASS'=>$pass,'FAIL'=>$fail,'WARN'=>count($results['limits']),'medecin_test_deleted'=>(int)$source->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'medecin_test'")->fetchColumn()===0];
echo json_encode($results, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($fail===0 && $results['errors']===[] ? 0 : 1);
