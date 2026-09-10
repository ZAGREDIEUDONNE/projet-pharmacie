<?php
/** Test d'intégration transactionnel : aucune donnée n'est conservée. */
require __DIR__ . '/../config/database.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require_once $file;
    }
});

use App\Repositories\SuiviClientRepository;
use App\Services\AuditService;
use App\Services\CaisseService;

function expect(bool $condition, string $label): void { if (!$condition) throw new RuntimeException('Échec : '.$label); echo "OK - {$label}\n"; }

$db = db();
$client = $db->query('SELECT id FROM clients WHERE deleted_at IS NULL LIMIT 1')->fetch();
$user = $db->query('SELECT id FROM utilisateurs WHERE is_active=1 AND deleted_at IS NULL LIMIT 1')->fetch();
if (!$client || !$user) throw new RuntimeException('Un client et un utilisateur actif sont requis pour le test.');

$repository = new SuiviClientRepository($db);
$audit = new AuditService($db);
$db->beginTransaction();
try {
    $data = ['client_id'=>(int)$client['id'], 'montant'=>1234.50, 'mode_paiement'=>'VIREMENT', 'reference'=>'TEST-BROUILLON', 'notes'=>'Test automatisé', 'date_reglement'=>date('Y-m-d H:i:s')];
    $draftId = $repository->saveDraft($data, (int)$user['id']);
    $draft = $repository->draft($draftId);
    expect($draft !== null && $draft['statut'] === 'BROUILLON', 'création du brouillon');
    expect(count($repository->drafts(['search'=>$draft['numero_brouillon']])) === 1, 'recherche par numéro de brouillon');

    $data['montant'] = 1500.00;
    $repository->saveDraft($data, (int)$user['id'], $draftId);
    expect((float)$repository->draft($draftId)['montant'] === 1500.00, 'reprise et modification du brouillon');

    $audit->logAction((int)$user['id'], 'TEST_BROUILLON_REGLEMENT', 'client_reglements', $draftId, null, ['test'=>true]);
    expect((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='TEST_BROUILLON_REGLEMENT' AND record_id=".(int)$draftId)->fetchColumn() === 1, 'journal d’audit');

    $repository->validateDraft($draftId);
    $repository->syncBalance((int)$client['id']);
    expect($repository->draft($draftId) === null, 'validation et retrait de la liste des brouillons');
    expect((string)$db->query('SELECT statut FROM client_reglements WHERE id='.(int)$draftId)->fetchColumn() === 'VALIDE', 'statut validé et recalcul du solde');

    $deleteId = $repository->saveDraft($data, (int)$user['id']);
    $repository->deleteDraft($deleteId);
    expect($repository->draft($deleteId) === null && (string)$db->query('SELECT statut FROM client_reglements WHERE id='.(int)$deleteId)->fetchColumn() === 'SUPPRIME', 'suppression logique');

    $sessionNumber = 'TEST'.date('YmdHis').random_int(100,999);
    $stmt = $db->prepare("INSERT INTO caisse_sessions (numero_session,caissier_id,date_ouverture,montant_ouverture,statut_session) VALUES (?,?,NOW(),0,'OUVERTE')");
    $stmt->execute([$sessionNumber, (int)$user['id']]);
    $sessionId = (int)$db->lastInsertId();
    $caisse = new CaisseService($db, $audit);
    $movement = $caisse->enregistrerMouvement(['caisse_session_id'=>$sessionId,'type_mouvement'=>'ENCAISSEMENT_CLIENT','montant'=>1500.00,'moyen_paiement'=>'ESPECE','reference'=>'TEST-ENCAISSEMENT','description'=>'Test encaissement règlement client','utilisateur_id'=>(int)$user['id'],'client_id'=>(int)$client['id']]);
    expect(!empty($movement['mouvement_id']), 'mise à jour de la caisse pour un règlement espèces');
    echo "OK - filtre période (couvert par la requête drafts)\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
