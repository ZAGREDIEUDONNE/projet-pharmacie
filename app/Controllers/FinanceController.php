<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\ReglementTiersService;
use App\Services\DiscountLimitService;
use App\Services\AuditService;
use App\Services\EcritureComptableService;
use App\Services\JournalComptableService;

/**
 * Suivi financier clients et fournisseurs.
 */
class FinanceController extends BaseController
{
    private ReglementTiersService $reglementService;
    private DiscountLimitService $discountService;

    public function __construct()
    {
        parent::__construct();
        $audit = new AuditService($this->db);
        $this->reglementService = new ReglementTiersService($this->db, null, new EcritureComptableService($this->db, $audit, new JournalComptableService($this->db, $audit)));
        $this->discountService = new DiscountLimitService($this->db);
    }

    public function clients(): void
    {
        $this->requirePermission('finance.clients');

        $this->render('finance/clients', [
            'title' => 'Suivi des créances clients',
            'clients' => $this->reglementService->getSuiviClients(),
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function clientDetail(): void
    {
        $this->requirePermission('finance.clients');
        $clientId = (int)($_GET['id'] ?? 0);
        if ($clientId <= 0) {
            $this->redirect('/finance/clients');
            return;
        }

        $this->render('finance/client_detail', [
            'title' => 'Détail créance client',
            'data' => $this->reglementService->getHistoriqueClient($clientId),
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function storeReglementClient(): void
    {
        $this->requirePermission('finance.clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/finance/clients');
            return;
        }

        try {
            $userId = (int)($this->getCurrentUser()['id'] ?? 0);
            $result = $this->reglementService->enregistrerReglementClient($_POST, $userId);

            (new AuditService($this->db))->logAction(
                $userId,
                'REGLEMENT_CLIENT',
                'client_reglements',
                $result['reglement_id'] ?? null,
                null,
                $_POST
            );

            $_SESSION['success'] = 'Règlement client enregistré.';
        } catch (\Exception $e) {
            $_SESSION['errors'] = [$e->getMessage()];
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $this->redirect($clientId > 0 ? '/finance/clients/detail?id=' . $clientId : '/finance/clients');
    }

    public function fournisseurs(): void
    {
        $this->requirePermission('finance.suppliers');

        $this->render('finance/fournisseurs', [
            'title' => 'Suivi des dettes fournisseurs',
            'fournisseurs' => $this->reglementService->getSuiviFournisseurs(),
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function fournisseurDetail(): void
    {
        $this->requirePermission('finance.suppliers');
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/finance/fournisseurs');
            return;
        }

        $this->render('finance/fournisseur_detail', [
            'title' => 'Détail dette fournisseur',
            'data' => $this->reglementService->getHistoriqueFournisseur($id),
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function storeReglementFournisseur(): void
    {
        $this->requirePermission('finance.suppliers');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/finance/fournisseurs');
            return;
        }

        try {
            $userId = (int)($this->getCurrentUser()['id'] ?? 0);
            $result = $this->reglementService->enregistrerReglementFournisseur($_POST, $userId);

            (new AuditService($this->db))->logAction(
                $userId,
                'REGLEMENT_FOURNISSEUR',
                'fournisseur_reglements',
                $result['reglement_id'] ?? null,
                null,
                $_POST
            );

            $_SESSION['success'] = 'Règlement fournisseur enregistré.';
        } catch (\Exception $e) {
            $_SESSION['errors'] = [$e->getMessage()];
        }

        $id = (int)($_POST['fournisseur_id'] ?? 0);
        $this->redirect($id > 0 ? '/finance/fournisseurs/detail?id=' . $id : '/finance/fournisseurs');
    }

    public function remisesLimites(): void
    {
        $this->requirePermission('discount.manage_limits');

        $this->render('finance/remises_limites', [
            'title' => 'Plafonds de remise par rôle',
            'limits' => $this->discountService->getAllLimits(),
            'roles' => $this->db->query('SELECT id, nom FROM roles ORDER BY nom')->fetchAll(\PDO::FETCH_ASSOC),
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function updateRemiseLimite(): void
    {
        $this->requirePermission('discount.manage_limits');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/finance/remises-limites');
            return;
        }

        try {
            $this->discountService->updateLimit(
                (int)($_POST['role_id'] ?? 0),
                (float)($_POST['max_discount_percent'] ?? 0)
            );
            $_SESSION['success'] = 'Plafond de remise mis à jour.';
        } catch (\Exception $e) {
            $_SESSION['errors'] = [$e->getMessage()];
        }

        $this->redirect('/finance/remises-limites');
    }
}
