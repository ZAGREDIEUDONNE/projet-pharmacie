<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\AuditService;
use App\Services\StockService;
use App\Services\CommandeService;

class CommandeController extends BaseController
{
    private AuditService $auditService;
    private StockService $stockService;
    private CommandeService $commandeService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService($this->db);
        $this->stockService = new StockService($this->db, $this->auditService);
        $this->commandeService = new CommandeService($this->db);
    }

    public function index(): void
    {
        $this->dashboard();
    }

    /**
     * Dashboard des commandes
     */
    public function dashboard(): void
    {
        $this->requireAuth();

        try {
            // Statistiques des commandes
            $stats = $this->commandeService->getCommandeStats();
            
            // Commandes récentes
            $recentesCommandes = $this->commandeService->getRecentesCommandes(10);

            $this->render('commande/dashboard', [
                'title' => 'Dashboard Commandes',
                'user' => $this->currentUser,
                'stats' => $stats,
                'recentesCommandes' => $recentesCommandes
            ]);

        } catch (\Exception $e) {
            error_log("CommandeController::dashboard - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors du chargement du dashboard';
            $this->redirect('/admin/dashboard');
        }
    }

    /**
     * Page de réception des produits
     */
    public function reception(): void
    {
        $this->requireAuth();

        try {
            // Récupérer les fournisseurs pour le formulaire
            $fournisseurs = $this->commandeService->getFournisseursActifs();
            
            // Récupérer les produits pour la sélection
            $produits = $this->stockService->getAllProduits();

            $this->render('commande/reception', [
                'title' => 'Réception de Produits',
                'user' => $this->currentUser,
                'fournisseurs' => $fournisseurs,
                'produits' => $produits
            ]);

        } catch (\Exception $e) {
            error_log("CommandeController::reception - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors du chargement de la page de réception';
            $this->redirect('/commande/dashboard');
        }
    }

    /**
     * Enregistre une réception de produits
     */
    public function storeReception(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/commande/reception');
            return;
        }

        try {
            $data = [
                'fournisseur_id' => intval($_POST['fournisseur_id'] ?? 0),
                'date_reception' => $_POST['date_reception'] ?? date('Y-m-d'),
                'reference' => trim($_POST['reference'] ?? ''),
                'produits' => json_decode($_POST['produits'] ?? '[]', true) ?: []
            ];

            $result = $this->commandeService->creerReception($data, $this->getCurrentUser()['id']);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'] ?? 'Réception enregistrée avec succès';
                $this->redirect('/commande/dashboard');
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Erreur lors de la réception';
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/commande/reception');
            }

        } catch (\Exception $e) {
            error_log("CommandeController::storeReception - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors de la réception';
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/commande/reception');
        }
    }

    /**
     * Page de saisie des commandes
     */
    public function saisie(): void
    {
        $this->requireAuth();

        try {
            // Récupérer les fournisseurs
            $fournisseurs = $this->commandeService->getFournisseursActifs();
            
            // Récupérer les produits
            $produits = $this->stockService->getAllProduits();

            $this->render('commande/saisie', [
                'title' => 'Saisie de Commande',
                'user' => $this->currentUser,
                'fournisseurs' => $fournisseurs,
                'produits' => $produits
            ]);

        } catch (\Exception $e) {
            error_log("CommandeController::saisie - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors du chargement de la page de saisie';
            $this->redirect('/commande/dashboard');
        }
    }

    /**
     * Enregistre une commande fournisseur
     */
    public function storeSaisie(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/commande/saisie');
            return;
        }

        try {
            $data = [
                'fournisseur_id' => intval($_POST['fournisseur_id'] ?? 0),
                'date_commande' => $_POST['date_commande'] ?? date('Y-m-d'),
                'date_livraison' => $_POST['date_livraison'] ?? null,
                'reference' => trim($_POST['reference'] ?? ''),
                'remise_globale' => floatval($_POST['remise_globale'] ?? 0),
                'produits' => json_decode($_POST['produits'] ?? '[]', true) ?: []
            ];

            $result = $this->commandeService->creerCommande($data, $this->getCurrentUser()['id']);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'] ?? 'Commande créée avec succès';
                $this->redirect('/commande/dashboard');
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Erreur lors de la création de la commande';
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/commande/saisie');
            }

        } catch (\Exception $e) {
            error_log("CommandeController::storeSaisie - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors de la création de la commande';
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/commande/saisie');
        }
    }

    /**
     * Page de gestion des remises
     */
    public function remise(): void
    {
        $this->requireAuth();
        $this->requireRole('CHARGE_COMMANDE');

        $this->redirect('/vente/create?return_to=' . urlencode('/commande/dashboard'));
    }

    /**
     * Changement de session/date
     */
    public function session(): void
    {
        $this->requireRole('CHARGE_COMMANDE');

        $this->redirect('/date/change?return_to=' . urlencode('/commande/dashboard'));
    }
}
