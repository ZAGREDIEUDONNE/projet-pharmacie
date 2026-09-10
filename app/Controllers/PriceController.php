<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\AuditService;
use App\Services\PriceService;
use Exception;

class PriceController extends BaseController
{
    private PriceService $priceService;

    public function __construct()
    {
        parent::__construct();
        $this->priceService = new PriceService($this->db, new AuditService($this->db));
    }

    /**
     * Formulaire de modification de prix
     */
    public function modifyForm(): void
    {
        $this->requirePermission('produit.modifier_prix');

        $productId = (int)($_GET['produit_id'] ?? 0);

        try {
            $produit = $this->priceService->getProductForPriceModification($productId);
            $motifs = $this->priceService->getPriceModificationMotifs();

            $this->render('produits/modify_price', [
                'title' => 'Modifier le prix - ' . $produit['nom'],
                'produit' => $produit,
                'motifs' => $motifs,
                'user' => $this->getCurrentUser(),
                'return_to' => $_GET['return_to'] ?? '/produits',
            ]);

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect($_GET['return_to'] ?? '/produits');
        }
    }

    /**
     * Traitement de la modification de prix
     */
    public function modify(): void
    {
        $this->requirePermission('produit.modifier_prix');

        try {
            $data = [
                'produit_id' => (int)($_POST['produit_id'] ?? 0),
                'nouveau_prix_achat' => !empty($_POST['nouveau_prix_achat']) ? (float)$_POST['nouveau_prix_achat'] : null,
                'nouveau_prix_vente' => (float)($_POST['nouveau_prix_vente'] ?? 0),
                'nouveau_prix_vente_assure' => !empty($_POST['nouveau_prix_vente_assure']) ? (float)$_POST['nouveau_prix_vente_assure'] : null,
                'date_application' => $_POST['date_application'] ?? date('Y-m-d'),
                'motif' => trim($_POST['motif'] ?? ''),
                'observation' => trim($_POST['observation'] ?? ''),
            ];

            $result = $this->priceService->modifyPrice($data, (int)$this->getCurrentUser()['id']);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect($_POST['return_to'] ?? '/produits');
    }

    /**
     * Historique des modifications de prix
     */
    public function history(): void
    {
        $this->requirePermission('produit.modifier_prix');

        // Récupérer les filtres
        $filters = [
            'search' => $_GET['search'] ?? null,
            'produit_id' => $_GET['produit_id'] ?? null,
            'utilisateur_id' => $_GET['utilisateur_id'] ?? null,
            'motif' => $_GET['motif'] ?? null,
            'date_debut' => $_GET['date_debut'] ?? null,
            'date_fin' => $_GET['date_fin'] ?? null,
            'periode' => $_GET['periode'] ?? null,
        ];

        $history = $this->priceService->getPriceHistory(100, $filters);
        $stats = $this->priceService->getPriceModificationStats($filters);
        $motifs = $this->priceService->getPriceModificationMotifs();

        // Récupérer les utilisateurs pour le filtre
        $stmt = $this->db->query("SELECT id, username FROM utilisateurs WHERE is_active = 1 ORDER BY username");
        $utilisateurs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('produits/price_history', [
            'title' => 'Historique des modifications de prix',
            'history' => $history,
            'stats' => $stats,
            'motifs' => $motifs,
            'utilisateurs' => $utilisateurs,
            'filters' => $filters,
            'user' => $this->getCurrentUser(),
        ]);
    }

    /**
     * Export CSV de l'historique des prix
     */
    public function exportCSV(): void
    {
        $this->requirePermission('produit.modifier_prix');

        try {
            $filters = [
                'search' => $_GET['search'] ?? null,
                'produit_id' => $_GET['produit_id'] ?? null,
                'utilisateur_id' => $_GET['utilisateur_id'] ?? null,
                'motif' => $_GET['motif'] ?? null,
                'date_debut' => $_GET['date_debut'] ?? null,
                'date_fin' => $_GET['date_fin'] ?? null,
                'periode' => $_GET['periode'] ?? null,
            ];

            $filepath = $this->priceService->exportPriceHistoryCSV($filters);
            $filename = basename($filepath);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));

            readfile($filepath);
            unlink($filepath);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits/price-history');
        }
    }

    /**
     * API pour vérifier si l'utilisateur peut modifier les prix
     */
    public function canModifyPrice(): void
    {
        $canModify = $this->hasPermission('produit.modifier_prix');
        $this->json(['success' => true, 'can_modify' => $canModify]);
    }
}
