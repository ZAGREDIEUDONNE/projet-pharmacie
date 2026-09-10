<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\AssistantDashboardService;
use App\Services\VenteService;
use App\Services\AuditService;
use App\Services\StockService;
use App\Services\CaisseService;
use App\Services\ComptabiliteService;

class AssistantController extends BaseController
{
    private ?VenteService $venteService = null;

    private function getVenteService(): VenteService
    {
        if ($this->venteService === null) {
            $audit = new AuditService($this->db);
            $this->venteService = new VenteService(
                $this->db,
                new StockService($this->db, $audit),
                new CaisseService($this->db, $audit),
                new ComptabiliteService($this->db, $audit),
                $audit
            );
        }

        return $this->venteService;
    }

    private const ACTIONS = [
        ['title' => 'Nouvelle vente', 'url' => '/vente/create?return_to=/assistant/dashboard', 'permission' => 'vente.create'],
        ['title' => 'Clients', 'url' => '/clients?return_to=/assistant/dashboard', 'permission' => 'client.view'],
        ['title' => 'Suivi client', 'url' => '/suivi-client?return_to=/assistant/dashboard', 'permission' => 'suivi_client.view'],
        ['title' => 'Caisse', 'url' => '/caisse/etat?return_to=/assistant/dashboard', 'permission' => 'caisse.view'],
    ];

    public function index(): void
    {
        $this->dashboard();
    }

    public function dashboard(): void
    {
        $this->requireAuth();
        $this->requireRole(3);

        $this->render('assistant/dashboard', [
            'title' => 'Dashboard Assistant',
            'user' => $_SESSION['user'] ?? null,
            'actionCards' => $this->getAuthorizedActions(),
            'canCancelTicket' => $this->can('cancel_ticket'),
        ]);
    }

    public function apiDashboard(): void
    {
        $this->requireAuth();
        $this->requireRole(3);

        try {
            $service = new AssistantDashboardService($this->db);
            $this->jsonResponse([
                'success' => true,
                'data' => $service->getOverview((int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0)),
                'actions' => $this->getAuthorizedActions(),
                'can_cancel_ticket' => $this->can('cancel_ticket'),
            ]);
        } catch (\Exception $e) {
            error_log('AssistantController::apiDashboard - ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard assistant',
            ], 500);
        }
    }

    public function commandes(): void
    {
        $this->requireRole(3);
        $this->preparationCommandes();
    }

    public function venteSession(): void
    {
        $this->requireRole(3);
        $this->requirePermission('make_sale');

        $this->redirect('/vente/create?return_to=' . urlencode('/assistant/dashboard'));
    }

    public function remise(): void
    {
        $this->requireRole(3);
        $this->requirePermission('apply_discount');

        $this->renderAssistantPage('Remise', [
            ['label' => 'Appliquer une remise', 'url' => '/vente/create?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-percentage', 'color' => 'text-yellow-600'],
            ['label' => 'Point de vente', 'url' => '/vente?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-shopping-cart', 'color' => 'text-green-600'],
        ], 'Action autorisee : appliquer une remise pendant une vente.');
    }

    public function preparationCommandes(): void
    {
        $this->requireRole(3);
        $this->requirePermission('prepare_orders');

        $this->renderAssistantPage('Preparation commande', [
            ['label' => 'Voir commandes a preparer', 'url' => '/stock/commandes-automatiques', 'icon' => 'fa-clipboard-list', 'color' => 'text-purple-600'],
            ['label' => 'Verifier stock', 'url' => '/stock', 'icon' => 'fa-boxes', 'color' => 'text-blue-600'],
            ['label' => 'Mouvements stock', 'url' => '/stock/mouvements', 'icon' => 'fa-exchange-alt', 'color' => 'text-indigo-600'],
        ], 'Action autorisee : consulter et preparer les commandes, sans acces aux parametres systeme ni aux utilisateurs.');
    }

    public function mouvementsProduits(): void
    {
        $this->requireRole(3);
        $this->requirePermission('view_stock_movements');

        $this->renderAssistantPage('Entrees et sorties produits', [
            ['label' => 'Mouvements produits', 'url' => '/stock/mouvements?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-exchange-alt', 'color' => 'text-indigo-600'],
            ['label' => 'Voir disponibilite stock', 'url' => '/stock?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-boxes', 'color' => 'text-blue-600'],
        ], 'Acces autres fonctions');
    }

    public function codesAcces(): void
    {
        $this->requireRole(3);

        $this->renderAssistantPage('Codes d acces assistant', [
            ['label' => 'Acces vente au comptoir', 'url' => '/vente?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-cash-register', 'color' => 'text-green-600'],
            ['label' => 'Code autres acces', 'url' => '/assistant/dashboard', 'icon' => 'fa-key', 'color' => 'text-orange-600'],
            ['label' => 'Tableau de bord assistant', 'url' => '/assistant/dashboard', 'icon' => 'fa-tachometer-alt', 'color' => 'text-blue-600'],
        ], 'Deux codes distincts : un code pour vente au comptoir, un code pour commandes, caisse, factures, statistiques et stock.');
    }

    public function annulationTickets(): void
    {
        $this->requireRole(3);
        $this->requirePermission('cancel_ticket');

        $this->renderAssistantPage('Annuler ticket', [
            ['label' => 'Rechercher un ticket', 'url' => '/vente/impression?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-receipt', 'color' => 'text-orange-600'],
            ['label' => 'Point de vente', 'url' => '/vente?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-shopping-cart', 'color' => 'text-green-600'],
        ], 'Selectionnez un ticket enregistre, indiquez le motif puis confirmez l annulation.', [
            'tickets' => $this->getVenteService()->getTicketsAnnulables(),
            'cancelReturnTo' => '/assistant/annulation-ticket',
        ]);
    }

    public function arretCaisse(): void
    {
        $this->requireRole(3);
        $this->requirePermission('close_cash_register');

        $this->renderAssistantPage('Arret de Caisse', [
            ['label' => 'Etat de caisse', 'url' => '/caisse/etat?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-cash-register', 'color' => 'text-purple-600'],
            ['label' => 'Fermer la caisse', 'url' => '/caisse/fermeture?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-lock', 'color' => 'text-red-600'],
            ['label' => 'Session caisse', 'url' => '/caisse/session?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-calendar-alt', 'color' => 'text-blue-600'],
        ], 'Acces autres fonctions');
    }

    public function facturation(): void
    {
        $this->requireRole(3);

        $this->renderAssistantPage('Facturation', [
            ['label' => 'Imprimer ticket', 'url' => '/vente/impression?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-print', 'color' => 'text-purple-600'],
            ['label' => 'Point de vente', 'url' => '/vente?return_to=' . urlencode('/assistant/dashboard'), 'icon' => 'fa-shopping-cart', 'color' => 'text-green-600'],
        ], 'Acces autres fonctions');
    }

    public function statistiques(): void
    {
        $this->requireRole(3);
        $this->requirePermission('view_statistics');

        $this->renderAssistantPage('Statistiques', [
            ['label' => 'Statistiques clients', 'url' => '/clients/statistiques', 'icon' => 'fa-users', 'color' => 'text-blue-600'],
            ['label' => 'Etat de caisse', 'url' => '/caisse/etat', 'icon' => 'fa-cash-register', 'color' => 'text-purple-600'],
            ['label' => 'Rapports stock', 'url' => '/stock/rapports', 'icon' => 'fa-chart-bar', 'color' => 'text-indigo-600'],
        ], 'Action autorisee : consulter les statistiques operationnelles.');
    }

    public function stock(): void
    {
        $this->requireRole(3);
        $this->redirect('/stock');
    }

    private function renderAssistantPage(string $title, array $actions, string $accessNote = '', array $extra = []): void
    {
        $this->render('assistant/action-page', array_merge([
            'title' => $title,
            'actions' => $actions,
            'accessNote' => $accessNote,
            'user' => $_SESSION['user'] ?? null,
        ], $extra));
    }

    private function forbiddenAssistantAction(): void
    {
        http_response_code(403);
        $this->renderAssistantPage('Acces interdit', [
            ['label' => 'Retour dashboard', 'url' => '/assistant/dashboard', 'icon' => 'fa-arrow-left', 'color' => 'text-orange-600'],
        ], 'Cette action n est pas autorisee pour le role assistant.');
    }

    private function getAuthorizedActions(): array
    {
        return array_values(array_filter(self::ACTIONS, function (array $action): bool {
            if (!isset($action['permission'])) {
                return true;
            }
            return $this->canUseAssistantPermission((string)$action['permission']);
        }));
    }

    private function canUseAssistantPermission(string $permission): bool
    {
        // Utiliser la méthode can() du BaseController qui vérifie réellement les permissions
        return $this->can($permission);
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($payload);
        exit;
    }
}
