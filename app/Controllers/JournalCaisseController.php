<?php

namespace App\Controllers;

use App\Services\JournalCaisseService;
use App\Services\AuditService;
use App\Core\BaseController;
use PDO;

class JournalCaisseController extends BaseController
{
    private JournalCaisseService $journalService;
    private AuditService $auditService;

    /**
     * Restricts the journal to authenticated users allowed to access caisse.
     */
    private function requireCaisseAccess(): void
    {
        $this->requireAuth();
        $this->denyChargeCommandeRestrictedModules();
    }

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService($this->db);
        $this->journalService = new JournalCaisseService($this->db);
    }

    /**
     * Affiche le journal de caisse avec filtres
     */
    public function index(): void
    {
        $this->requireCaisseAccess();

        $filters = [
            'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
            'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
            'utilisateur_id' => $_GET['utilisateur_id'] ?? '',
            'session_id' => $_GET['session_id'] ?? '',
            'type_operation' => $_GET['type_operation'] ?? '',
            'reference' => $_GET['reference'] ?? '',
            'montant_min' => $_GET['montant_min'] ?? '',
            'montant_max' => $_GET['montant_max'] ?? '',
            'limit' => $_GET['limit'] ?? 100
        ];

        try {
            $operations = $this->journalService->getJournal($filters);
            $statistiques = $this->journalService->getStatistiques($filters);
            $utilisateurs = $this->journalService->getUtilisateurs();
            $sessions = $this->journalService->getSessions();

            $this->render('caisse/journal', [
                'title' => 'Journal de Caisse',
                'operations' => $operations,
                'statistiques' => $statistiques,
                'utilisateurs' => $utilisateurs,
                'sessions' => $sessions,
                'filters' => $filters,
                'user' => $this->currentUser
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse');
        }
    }

    /**
     * Affiche le détail d'une opération
     */
    public function detail(): void
    {
        $this->requireCaisseAccess();

        $id = intval($_GET['id'] ?? 0);

        if (!$id) {
            $_SESSION['error'] = 'ID d\'opération non fourni';
            $this->redirect('/caisse/journal');
            return;
        }

        try {
            $operation = $this->journalService->getOperationDetail($id);

            $this->render('caisse/journal_detail', [
                'title' => 'Détail Opération',
                'operation' => $operation,
                'user' => $this->currentUser
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/journal');
        }
    }

    /**
     * Exporte le journal en Excel
     */
    public function exportExcel(): void
    {
        $this->requireCaisseAccess();

        $filters = [
            'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
            'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
            'utilisateur_id' => $_GET['utilisateur_id'] ?? '',
            'session_id' => $_GET['session_id'] ?? '',
            'type_operation' => $_GET['type_operation'] ?? '',
            'reference' => $_GET['reference'] ?? '',
            'montant_min' => $_GET['montant_min'] ?? '',
            'montant_max' => $_GET['montant_max'] ?? ''
        ];

        try {
            $excel = $this->journalService->exportExcel($filters);

            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="journal-caisse.xls"');
            echo $excel;
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/journal');
        }
    }

    /**
     * Exporte le journal en PDF (vue d'impression)
     */
    public function exportPdf(): void
    {
        $this->requireCaisseAccess();

        $filters = [
            'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
            'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
            'utilisateur_id' => $_GET['utilisateur_id'] ?? '',
            'session_id' => $_GET['session_id'] ?? '',
            'type_operation' => $_GET['type_operation'] ?? '',
            'reference' => $_GET['reference'] ?? '',
            'montant_min' => $_GET['montant_min'] ?? '',
            'montant_max' => $_GET['montant_max'] ?? ''
        ];

        try {
            $operations = $this->journalService->getJournal($filters);
            $statistiques = $this->journalService->getStatistiques($filters);

            $this->render('caisse/journal', [
                'title' => 'Journal de Caisse',
                'operations' => $operations,
                'statistiques' => $statistiques,
                'filters' => $filters,
                'print' => true,
                'user' => $this->currentUser
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/journal');
        }
    }

    /**
     * API: Recherche instantanée
     */
    public function search(): void
    {
        $this->requireCaisseAccess();
        header('Content-Type: application/json');

        $query = $_GET['q'] ?? '';

        try {
            $filters = [
                'reference' => $query,
                'limit' => 20
            ];

            $operations = $this->journalService->getJournal($filters);

            echo json_encode([
                'success' => true,
                'data' => $operations
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
