<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\AuditService;
use App\Services\OrdonnanceService;
use App\Services\StockService;

class OrdonnanceController extends BaseController
{
    private OrdonnanceService $ordonnanceService;

    public function __construct()
    {
        parent::__construct();
        $audit = new AuditService($this->db);
        $this->ordonnanceService = new OrdonnanceService($this->db, new StockService($this->db, $audit), $audit);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('ordonnance.view');
        $returnTo = $this->getSafeReturnUrl('/vente');

        $this->render('ordonnances/index', [
            'title' => 'Ordonnances',
            'returnTo' => $returnTo,
            'ordonnances' => $this->rechercheData(),
            'filtres' => $this->filtres(),
        ]);
    }

    public function liste(): void
    {
        $this->index();
    }

    public function voir(string $id): void
    {
        $this->requireAuth();
        $this->requirePermission('ordonnance.view');
        $ordonnance = $this->ordonnanceService->consulter((int)$id);
        if (!$ordonnance) {
            http_response_code(404);
        } else {
            $this->ordonnanceService->tracerConsultation((int)$this->getCurrentUser()['id'], (int)$id);
        }
        $this->render('ordonnances/detail', ['title' => $ordonnance ? 'Ordonnance ' . $ordonnance['numero_ordonnance'] : 'Ordonnance introuvable', 'ordonnance' => $ordonnance, 'returnTo' => $this->getSafeReturnUrl('/ordonnances')]);
    }

    public function traiter(string $id): void
    {
        $this->requireAuth();
        $this->requirePermission('ordonnance.process');
        if (!$this->ordonnanceService->consulter((int)$id)) {
            http_response_code(404);
            echo 'Ordonnance introuvable';
            return;
        }
        $this->ordonnanceService->tracerConsultation((int)$this->getCurrentUser()['id'], (int)$id);
        $this->redirect('/vente/create?ordonnance_id=' . (int)$id . '&return_to=' . urlencode('/ordonnances/voir/' . (int)$id));
    }

    private function filtres(): array
    {
        return ['numero' => trim((string)($_GET['numero'] ?? '')), 'patient' => trim((string)($_GET['patient'] ?? '')), 'prescripteur' => trim((string)($_GET['prescripteur'] ?? '')), 'date' => trim((string)($_GET['date'] ?? ''))];
    }

    private function rechercheData(): array
    {
        return $this->ordonnanceService->lister($this->filtres());
    }
}
