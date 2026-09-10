<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\AuditService;
use App\Services\ChargeCommandeService;
use Exception;

class ChargeCommandeController extends BaseController
{
    private ChargeCommandeService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ChargeCommandeService($this->db, new AuditService($this->db));
    }

    public function dashboard(): void
    {
        $this->requirePermission('view_stock');
        $this->render('commande/dashboard', [
            'title' => 'Dashboard Charge de commande',
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function apiDashboard(): void
    {
        $this->requirePermission('view_stock');
        $this->json(['success' => true, 'data' => $this->service->getDashboardData()]);
    }

    public function orderForm(): void
    {
        $this->requirePermission('create_supplier_orders');
        $this->render('commande/saisie', [
            'title' => 'Commande fournisseur',
            'user' => $this->getCurrentUser(),
            'fournisseurs' => $this->service->getSuppliers(),
            'produits' => $this->service->getProducts(),
            'produitPreselectionneId' => (int)($_GET['produit_id'] ?? 0),
        ]);
    }

    public function editOrder(): void
    {
        $this->requirePermission('edit_supplier_orders');
        $orderId = (int)($_GET['id'] ?? 0);
        
        if ($orderId <= 0) {
            $_SESSION['error'] = 'Commande invalide';
            $this->redirect('/commande/historique');
            return;
        }

        $order = $this->service->getSupplierOrder($orderId);
        if (!$order) {
            $_SESSION['error'] = 'Commande introuvable';
            $this->redirect('/commande/historique');
            return;
        }

        if (!in_array($order['statut'], ['BROUILLON', 'EN_ATTENTE'], true)) {
            $_SESSION['error'] = 'Cette commande ne peut plus etre modifiee';
            $this->redirect('/commande/historique');
            return;
        }

        $items = $this->service->getOrderItems($orderId);

        $this->render('commande/edit', [
            'title' => 'Modifier commande fournisseur',
            'user' => $this->getCurrentUser(),
            'order' => $order,
            'items' => $items,
            'fournisseurs' => $this->service->getSuppliers(),
            'produits' => $this->service->getProducts(),
        ]);
    }

    public function updateOrder(): void
    {
        $this->requirePermission('edit_supplier_orders');

        try {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $items = json_decode((string)($_POST['items_json'] ?? '[]'), true) ?: [];
            
            $result = $this->service->updateSupplierOrder($orderId, [
                'reference_commande' => $_POST['reference_commande'] ?? '',
                'date_livraison_prevue' => $_POST['date_livraison_prevue'] ?? null,
                'remise_globale' => $_POST['remise_globale'] ?? 0,
                'tva_globale' => $_POST['tva_globale'] ?? 0,
                'observations' => $_POST['observations'] ?? '',
                'items' => $items,
            ], (int)$this->getCurrentUser()['id']);

            $_SESSION['success'] = $result['message'];
            $this->redirect('/commande/historique');
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/commande/edit?id=' . ($_POST['order_id'] ?? 0));
        }
    }

    public function showOrder(): void
    {
        $this->requirePermission('view_supplier_orders');
        $orderId = (int)($_GET['id'] ?? 0);
        
        if ($orderId <= 0) {
            $_SESSION['error'] = 'Commande invalide';
            $this->redirect('/commande/historique');
            return;
        }

        $order = $this->service->getSupplierOrder($orderId);
        if (!$order) {
            $_SESSION['error'] = 'Commande introuvable';
            $this->redirect('/commande/historique');
            return;
        }

        $items = $this->service->getOrderItems($orderId);

        $this->render('commande/show', [
            'title' => 'Détails commande fournisseur',
            'user' => $this->getCurrentUser(),
            'order' => $order,
            'items' => $items,
        ]);
    }

    public function cancelOrder(): void
    {
        $this->requirePermission('edit_supplier_orders');

        try {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $motif = trim((string)($_POST['motif'] ?? ''));
            
            if ($orderId <= 0 || empty($motif)) {
                throw new Exception('Commande et motif sont obligatoires.');
            }

            $result = $this->service->cancelSupplierOrder($orderId, $motif, (int)$this->getCurrentUser()['id']);

            $_SESSION['success'] = $result['message'];
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/commande/historique');
    }

    public function duplicateOrder(): void
    {
        $this->requirePermission('create_supplier_orders');

        try {
            $orderId = (int)($_POST['order_id'] ?? 0);
            
            if ($orderId <= 0) {
                throw new Exception('Commande invalide.');
            }

            $result = $this->service->duplicateSupplierOrder($orderId, (int)$this->getCurrentUser()['id']);

            $_SESSION['success'] = $result['message'];
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/commande/historique');
    }

    public function printOrder(): void
    {
        $this->requirePermission('view_supplier_orders');
        $orderId = (int)($_GET['id'] ?? 0);
        
        $order = $this->service->getSupplierOrder($orderId);
        if (!$order) {
            $_SESSION['error'] = 'Commande introuvable';
            $this->redirect('/commande/historique');
            return;
        }

        $items = $this->service->getOrderItems($orderId);

        $this->render('commande/print', [
            'title' => 'Imprimer commande',
            'order' => $order,
            'items' => $items,
        ]);
    }

    public function exportPdf(): void
    {
        $this->requirePermission('export_supplier_orders');
        $_SESSION['error'] = 'Export PDF non implemente - utiliser la fonction d\'impression du navigateur';
        $this->redirect('/commande/historique');
    }

    public function exportExcel(): void
    {
        $this->requirePermission('export_supplier_orders');
        
        $orders = $this->service->getOrderHistory(1000);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="commandes_fournisseurs_' . date('YmdHis') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Numero', 'Reference', 'Fournisseur', 'Date commande', 'Date livraison prevue', 'Statut', 'Montant HT', 'Remise %', 'TVA %', 'Montant TTC', 'Utilisateur'], ';');
        
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['numero_commande'] ?? '',
                $order['reference_commande'] ?? '',
                $order['fournisseur_nom'] ?? '',
                $order['date_commande'] ?? '',
                $order['date_livraison_prevue'] ?? '',
                $order['statut'] ?? '',
                number_format((float)($order['montant_ht'] ?? 0), 2, ',', ''),
                number_format((float)($order['remise_globale'] ?? 0), 2, ',', ''),
                number_format((float)($order['tva_globale'] ?? 0), 2, ',', ''),
                number_format((float)($order['montant_total'] ?? 0), 2, ',', ''),
                $order['utilisateur_nom'] ?? '',
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    public function storeOrder(): void
    {
        $this->requirePermission('create_supplier_orders');

        try {
            $items = json_decode((string)($_POST['items_json'] ?? '[]'), true) ?: [];
            $result = $this->service->createSupplierOrder([
                'fournisseur_id' => $_POST['fournisseur_id'] ?? 0,
                'reference_commande' => $_POST['reference_commande'] ?? '',
                'date_commande' => $_POST['date_commande'] ?? date('Y-m-d'),
                'date_livraison_prevue' => $_POST['date_livraison_prevue'] ?? null,
                'statut' => $_POST['statut'] ?? 'BROUILLON',
                'remise_globale' => $_POST['remise_globale'] ?? 0,
                'tva_globale' => $_POST['tva_globale'] ?? 0,
                'observations' => $_POST['observations'] ?? '',
                'items' => $items,
            ], (int)$this->getCurrentUser()['id']);

            $_SESSION['success'] = $result['message'];
            $this->redirect('/commande/dashboard');
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/commande/saisie');
        }
    }

    public function receptionForm(): void
    {
        $this->requirePermission('receive_products');
        $this->render('commande/reception', [
            'title' => 'Reception produits',
            'user' => $this->getCurrentUser(),
            'orders' => $this->service->getReceivableOrders(),
        ]);
    }

    public function orderItems(): void
    {
        $this->requirePermission('receive_products');
        $orderId = (int)($_GET['order_id'] ?? 0);
        $this->json(['success' => true, 'items' => $orderId > 0 ? $this->service->getOrderItems($orderId) : []]);
    }

    public function storeReception(): void
    {
        $this->requirePermission('receive_products');

        try {
            $items = json_decode((string)($_POST['items_json'] ?? '[]'), true) ?: [];
            $result = $this->service->receiveOrder([
                'supplier_order_id' => $_POST['supplier_order_id'] ?? 0,
                'date_reception' => $_POST['date_reception'] ?? date('Y-m-d'),
                'numero_facture' => $_POST['numero_facture'] ?? '',
                'date_facture' => $_POST['date_facture'] ?? ($_POST['date_reception'] ?? date('Y-m-d')),
                'reference_facture' => $_POST['reference_facture'] ?? ($_POST['numero_facture'] ?? ''),
                'montant_facture' => $_POST['montant_facture'] ?? 0,
                'observations' => $_POST['observations'] ?? '',
                'items' => $items,
            ], (int)$this->getCurrentUser()['id']);

            $_SESSION['success'] = $result['message'];
            $this->redirect('/commande/dashboard');
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/commande/reception');
        }
    }

    public function mouvements(): void
    {
        $this->requirePermission('view_stock_movements');
        $this->render('stock/mouvements', [
            'title' => 'Mouvements de stock',
            'user' => $this->getCurrentUser(),
            'mouvements' => $this->service->getStockMovements($_GET, 150),
            'produits' => $this->service->getProducts(),
            'utilisateurs' => $this->getUsers(),
            'returnTo' => '/commande/dashboard',
        ]);
    }

    public function orderHistory(): void
    {
        $this->requirePermission('view_supplier_orders');
        
        // Get filters
        $filters = [
            'search' => $_GET['search'] ?? null,
            'statut' => $_GET['statut'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
            'retard' => $_GET['retard'] ?? null,
        ];
        
        $this->render('commande/historique', [
            'title' => 'Historique commandes fournisseurs',
            'user' => $this->getCurrentUser(),
            'orders' => $this->service->getOrderHistory(150, $filters),
            'filters' => $filters,
            'returnTo' => $this->getSafeReturnUrl('/commande/dashboard'),
        ]);
    }

    public function updateOrderStatus(): void
    {
        $this->requirePermission('edit_supplier_orders');

        try {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $status = (string)($_POST['statut'] ?? '');
            $result = $this->service->updateSupplierOrderStatus(
                $orderId,
                $status,
                (int)$this->getCurrentUser()['id']
            );

            $_SESSION['success'] = $result['message'];
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/commande/historique');
    }

    public function sendOrder(): void
    {
        $this->requirePermission('send_supplier_orders');

        try {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $result = $this->service->updateSupplierOrderStatus(
                $orderId,
                'ENVOYEE',
                (int)$this->getCurrentUser()['id']
            );

            $_SESSION['success'] = $result['message'];
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/commande/historique');
    }

    public function commandesAutomatiques(): void
    {
        $this->requirePermission('create_supplier_orders');
        $returnTo = '/commande/dashboard';
        $userId = (int)($this->getCurrentUser()['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $service = new \App\Services\CommandeAutomatiqueService($this->db, new AuditService($this->db));
                $result = $service->genererCommandesAutomatiques($userId);

                $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
                $xhr = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
                if (str_contains($accept, 'application/json') || strcasecmp($xhr, 'XMLHttpRequest') === 0) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($result);
                    exit;
                }

                $_SESSION['success'] = $result['message'];
                $_SESSION['commandes_auto_resume'] = $result['data'] ?? [];
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }

            $this->redirect('/stock/commandes-automatiques?return_to=' . urlencode($returnTo));
            return;
        }

        $this->redirect('/stock/commandes-automatiques?return_to=' . urlencode($returnTo));
    }

    private function getUsers(): array
    {
        $stmt = $this->db->query("SELECT id, username FROM utilisateurs WHERE is_active = 1 ORDER BY username");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}
