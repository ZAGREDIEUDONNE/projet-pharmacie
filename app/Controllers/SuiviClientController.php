<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Repositories\SuiviClientRepository;
use App\Services\AuditService;
use App\Services\CaisseService;
use App\Services\SuiviClientService;

final class SuiviClientController extends BaseController
{
    private SuiviClientRepository $repository;
    private SuiviClientService $service;

    public function __construct()
    {
        parent::__construct();
        $audit = new AuditService($this->db);
        $this->repository = new SuiviClientRepository($this->db);
        $this->service = new SuiviClientService($this->db, $this->repository, $audit, new CaisseService($this->db, $audit));
    }

    private function screen(string $view, string $permission, array $data = []): void
    {
        $this->requirePermission($permission);
        $titles = ['saisie-reglement'=>'Saisie de règlement','ouvrir-saisie'=>'Ouvrir une saisie','consulter-brouillon'=>'Consulter un brouillon','recu-reglement'=>'Reçu de règlement','releve-reglements'=>'Relevé des règlements','liste-clients'=>'Liste des clients','solde-courant'=>'Solde client courant','releve-courant'=>'Relevé client courant','solde-arrete'=>'Solde client arrêté','releve-arrete'=>'Relevé client arrêté'];
        $this->render('suivi-client/'.$view, array_merge($data, ['title'=>$titles[$view] ?? 'Suivi Client']));
    }

    public function index(): void { $this->requirePermission('suivi_client.view'); $this->render('suivi-client/index',['title'=>'Suivi Client']); }

    public function saisieReglement(): void
    {
        $draftId = (int)($_GET['brouillon_id'] ?? 0);
        $draft = null;
        if ($draftId > 0) { $this->requirePermission('suivi_client.update'); $draft = $this->repository->draft($draftId); if (!$draft) { $_SESSION['errors']=['Brouillon introuvable ou déjà traité.']; $this->redirect('/suivi-client/ouvrir-saisie'); return; } }
        $this->screen('saisie-reglement', 'suivi_client.reglement', ['clients'=>$this->repository->clients('', '', 1, 500)['rows'], 'draft'=>$draft]);
    }

    public function storeReglement(): void
    {
        $this->requirePermission('suivi_client.reglement');
        try { $id=$this->service->validatePayment($_POST, (int)($this->getCurrentUser()['id'] ?? 0)); $_SESSION['success']='Règlement validé.'; $this->redirect('/suivi-client/recu-reglement?id='.$id); }
        catch (\Throwable $e) { $_SESSION['errors']=[$e->getMessage()]; $_SESSION['old_data']=$_POST; $url='/suivi-client/saisie-reglement'; if (!empty($_POST['draft_id'])) $url.='?brouillon_id='.(int)$_POST['draft_id']; $this->redirect($url); }
    }

    public function saveDraft(?string $id = null): void
    {
        $this->requirePermission($id === null ? 'suivi_client.create' : 'suivi_client.update');
        try {
            $draftId=$this->service->saveDraft($_POST, (int)($this->getCurrentUser()['id'] ?? 0), $id !== null ? (int)$id : null);
            if ($this->isAjax()) { $this->json(['success'=>true,'id'=>$draftId]); return; }
            $_SESSION['success']='Brouillon enregistré.'; $this->redirect('/suivi-client/saisie-reglement?brouillon_id='.$draftId);
        } catch (\Throwable $e) {
            if ($this->isAjax()) { http_response_code(422); $this->json(['success'=>false,'message'=>$e->getMessage()]); return; }
            $_SESSION['errors']=[$e->getMessage()]; $_SESSION['old_data']=$_POST; $this->redirect('/suivi-client/saisie-reglement'.($id ? '?brouillon_id='.(int)$id : ''));
        }
    }

    public function ouvrirSaisie(): void { $this->screen('ouvrir-saisie', 'suivi_client.reglement', ['drafts'=>$this->repository->drafts($_GET),'clients'=>$this->repository->clients('', '',1,500)['rows'],'users'=>$this->repository->users()]); }
    public function consulterBrouillon(string $id): void { $this->screen('consulter-brouillon','suivi_client.view',['draft'=>$this->repository->draft((int)$id)]); }
    public function deleteDraft(string $id): void { $this->requirePermission('suivi_client.delete'); try { $this->service->deleteDraft((int)$id,(int)($this->getCurrentUser()['id']??0)); $_SESSION['success']='Brouillon supprimé.'; } catch (\Throwable $e) { $_SESSION['errors']=[$e->getMessage()]; } $this->redirect('/suivi-client/ouvrir-saisie'); }

    public function releveReglements(): void { $this->screen('releve-reglements','suivi_client.releve',$this->paymentData()); }
    public function recuReglement(): void { $this->screen('recu-reglement','suivi_client.releve',['payment'=>$this->repository->payment((int)($_GET['id']??0))]); }
    public function listeClients(): void { $this->screen('liste-clients','suivi_client.view',['listing'=>$this->repository->clients(trim($_GET['q']??''),$_GET['statut']??'',max(1,(int)($_GET['page']??1)),25,$_GET['sort']??'nom',$_GET['direction']??'asc',$_GET['type_client']??'',$_GET['ville']??'')]); }
    public function soldeCourant(): void { $id=(int)($_GET['client_id']??0);$this->screen('solde-courant','suivi_client.solde',['clients'=>$this->repository->clients('', '',1,500)['rows'],'balances'=>$this->repository->balances(null,$id?:null)]); }
    public function soldeArrete(): void { $id=(int)($_GET['client_id']??0);$date=$this->date($_GET['date_fin']??date('Y-m-d'));$this->screen('solde-arrete','suivi_client.solde',['clients'=>$this->repository->clients('', '',1,500)['rows'],'date_fin'=>$date,'balances'=>$this->repository->balances($date,$id?:null)]); }
    public function releveCourant(): void { $this->statementScreen('releve-courant'); }
    public function releveArrete(): void { $this->statementScreen('releve-arrete'); }

    public function export(): void { $this->requirePermission('suivi_client.releve');$type=$_GET['type']??'reglements';$format=$_GET['format']??'pdf';if($type==='releve'){$data=$this->statementData((int)($_GET['client_id']??0),$_GET['date_debut']??null,$_GET['date_fin']??null);if($format==='excel'){$this->excelStatement($data);return;}$this->render('suivi-client/releve-arrete',array_merge($data,['title'=>'Relevé client','print'=>true]));return;}$data=$this->paymentData();if($format==='excel'){$this->excelPayments($data['payments']);return;}$this->render('suivi-client/releve-reglements',array_merge($data,['title'=>'Relevé des règlements','print'=>true])); }
    private function paymentData(): array { return ['payments'=>$this->repository->payments($_GET),'clients'=>$this->repository->clients('', '',1,500)['rows'],'users'=>$this->repository->users()]; }
    private function date(?string $date): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$date)?$date:date('Y-m-d'); }
    private function statementData(int $id,?string $from,?string $to): array { $from=$from?$this->date($from):null;$to=$to?$this->date($to):null;$opening=0.0;if($id&&$from){$prior=date('Y-m-d',strtotime($from.' -1 day'));$opening=(float)($this->repository->balances($prior,$id)[0]['solde_calcule']??0);}return ['clients'=>$this->repository->clients('', '',1,500)['rows'],'client'=>$id?$this->repository->client($id):null,'movements'=>$id?$this->repository->statement($id,$from,$to):[],'opening_balance'=>$opening]; }
    private function statementScreen(string $view): void { $this->screen($view,'suivi_client.releve',$this->statementData((int)($_GET['client_id']??0),$_GET['date_debut']??null,$_GET['date_fin']??null)); }
    private function excelPayments(array $rows): void { header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="releve-reglements.xls"');echo "\xEF\xBB\xBF<table><tr><th>Date</th><th>N°</th><th>Client</th><th>Référence</th><th>Montant</th><th>Mode</th><th>Utilisateur</th></tr>";foreach($rows as $r)echo '<tr><td>'.htmlspecialchars($r['date_mouvement']).'</td><td>RC-'.(int)$r['id'].'</td><td>'.htmlspecialchars($r['nom'].' '.$r['prenom']).'</td><td>'.htmlspecialchars((string)$r['reference']).'</td><td>'.$r['montant'].'</td><td>'.htmlspecialchars($r['mode_paiement']).'</td><td>'.htmlspecialchars((string)$r['username']).'</td></tr>';echo '</table>'; }
    private function excelStatement(array $data): void { header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="releve-client.xls"');echo "\xEF\xBB\xBF<table><tr><th>Date</th><th>Type</th><th>Référence</th><th>Débit</th><th>Crédit</th></tr>";foreach($data['movements'] as $r)echo '<tr><td>'.htmlspecialchars($r['operation_date']).'</td><td>'.htmlspecialchars($r['type_operation']).'</td><td>'.htmlspecialchars($r['reference']).'</td><td>'.$r['debit'].'</td><td>'.$r['credit'].'</td></tr>';echo '</table>'; }
    private function isAjax(): bool { return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'; }
    private function json(array $payload): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload); }

    public function exportClients(): void { $this->requirePermission('suivi_client.view');$format=$_GET['format']??'excel';$data=$this->repository->clients(trim($_GET['q']??''),$_GET['statut']??'',1,5000,$_GET['sort']??'nom',$_GET['direction']??'asc',$_GET['type_client']??'',$_GET['ville']??'');if($format==='excel'){header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="liste-clients.xls"');echo "\xEF\xBB\xBF<table><tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Date Naissance</th><th>Téléphone</th><th>Adresse</th><th>Type Client</th><th>Plafond</th><th>Solde</th><th>Date Création</th></tr>";foreach($data['rows'] as $r)echo '<tr><td>'.$r['id'].'</td><td>'.htmlspecialchars($r['matricule']).'</td><td>'.htmlspecialchars($r['nom']).'</td><td>'.htmlspecialchars($r['date_naissance']).'</td><td>'.htmlspecialchars($r['telephone']).'</td><td>'.htmlspecialchars($r['adresse']).'</td><td>'.htmlspecialchars($r['type_client']).'</td><td>'.$r['plafond'].'</td><td>'.$r['solde'].'</td><td>'.htmlspecialchars($r['created_at']).'</td></tr>';echo '</table>';}elseif($format==='pdf'){$this->render('suivi-client/liste-clients',['listing'=>$data,'print'=>true,'title'=>'Liste des clients']);} }
    public function create(): void { $this->saisieReglement(); } public function store(): void { $this->storeReglement(); } public function edit(): void { $this->listeClients(); } public function update(): void { $this->listeClients(); } public function destroy(): void { $this->listeClients(); } public function soldeClient(): void { $this->soldeCourant(); } public function releveClient(): void { $this->releveCourant(); }
}
