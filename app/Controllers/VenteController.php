<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\VenteService;
use App\Services\AuditService;
use App\Services\StockService;
use App\Services\CaisseService;
use App\Services\ComptabiliteService;
use App\Services\DiscountLimitService;
use App\Models\PaiementDetails;
use PDO;

/**
 * Contrôleur pour la gestion des ventes
 */
class VenteController extends BaseController
{
    private VenteService $venteService;
    private AuditService $auditService;
    private CaisseService $caisseService;
    private DiscountLimitService $discountLimitService;
    private PaiementDetails $paiementDetailsModel;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService($this->db);
        $this->discountLimitService = new DiscountLimitService($this->db);
        $this->paiementDetailsModel = new PaiementDetails($this->db);

        $stockService = new StockService($this->db, $this->auditService);
        $this->caisseService = new CaisseService($this->db, $this->auditService);
        $comptabiliteService = new ComptabiliteService($this->db, $this->auditService);

        $this->venteService = new VenteService(
            $this->db,
            $stockService,
            $this->caisseService,
            $comptabiliteService,
            $this->auditService
        );
    }

    /**
     * Vérifie si l'utilisateur est connecté au module vente
     */
    private function isVenteUserLoggedIn(): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        // The business permission check performed by each action is the source
        // of truth.  Do not reject a user merely because their role label is not
        // one of the historical POS labels: explicit RBAC grants must work.
        $user = $_SESSION['user'] ?? [];

        // Vérifier l'expiration de la session
        if (time() - ($_SESSION['last_activity'] ?? 0) > 3600) {
            session_destroy();
            return false;
        }

        // Mettre à jour la dernière activité
        $_SESSION['last_activity'] = time();
        $_SESSION['user_id'] = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
        $_SESSION['username'] = $user['username'] ?? ($_SESSION['username'] ?? '');
        $_SESSION['role'] = strtoupper((string)($user['role_code'] ?? $_SESSION['role'] ?? $user['role_name'] ?? ''));
        return true;
    }

    /**
     * Affiche le dashboard des ventes
     */
    public function index(): void
    {
        // Vérifier si l'utilisateur est connecté au module vente
        if (!$this->isVenteUserLoggedIn()) {
            $_SESSION['errors'] = ['Veuillez vous connecter pour accéder au module vente'];
            $this->redirect('/login');
            return;
        }

        // Vérifier la permission d'accès au module vente
        $this->requirePermission('vente.view');

        $user = $this->getCurrentUser();
        $userId = (int)($user['id'] ?? 0);
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());

        // Récupérer les statistiques des ventes
        $stats = $this->venteService->getVenteStats();
        
        // Ajouter le nombre de clients servis aujourd'hui
        $stats['clients_servis'] = $this->venteService->getClientsServisAujourdhui();
        
        // Récupérer les dernières ventes de l'utilisateur
        $dernieresVentes = $this->venteService->getDernieresVentes($userId, 10);
        
        // Récupérer les notifications
        $notifications = $this->venteService->getNotifications();
        
        // Récupérer l'état de la session de caisse
        $caisseSession = $this->caisseService->getSessionOuverte($userId);
        
        // Calculer les totaux de caisse du jour
        $caisseStats = $this->getCaisseStats($userId);

        $this->render('vente/dashboard', [
            'title' => 'Point de Vente',
            'stats' => $stats,
            'user' => $user,
            'returnTo' => $returnTo,
            'dernieresVentes' => $dernieresVentes,
            'notifications' => $notifications,
            'caisseSession' => $caisseSession,
            'caisseStats' => $caisseStats,
        ]);
    }

    /**
     * Récupère les statistiques de caisse du jour pour un utilisateur
     */
    private function getCaisseStats(int $userId): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN type_mouvement IN ('VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE') THEN montant ELSE 0 END), 0) as encaissements,
                    COALESCE(SUM(CASE WHEN type_mouvement IN ('REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE') THEN montant ELSE 0 END), 0) as decaissements
                FROM mouvements_caisse mc
                JOIN caisse_sessions cs ON mc.caisse_session_id = cs.id
                WHERE cs.caissier_id = ?
                AND DATE(mc.date_mouvement) = CURDATE()
                AND mc.supprime = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'encaissements' => 0,
            'decaissements' => 0
        ];
    }

    /**
     * Affiche le formulaire de création de vente
     */
    public function create(): void
    {
        $this->requirePermission('vente.create');

        $user = $this->getCurrentUser();
        $maxDiscountPercent = $this->discountLimitService->getMaxDiscountForUser($user);
        $ordonnancePrefill = null;
        $ordonnanceId = (int)($_GET['ordonnance_id'] ?? 0);
        if ($ordonnanceId > 0) {
            $this->requirePermission('ordonnance.process');
            $stmt = $this->db->prepare('SELECT * FROM ordonnances WHERE id = ?');
            $stmt->execute([$ordonnanceId]);
            $ordonnancePrefill = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$ordonnancePrefill) {
                $_SESSION['errors'] = ['Ordonnance introuvable'];
                $this->redirect('/ordonnances');
                return;
            }
        }

        $this->render('vente/create', [
            'title' => 'Nouvelle Vente',
            'maxDiscountPercent' => $maxDiscountPercent,
            'canCreditSale' => $this->can('vente.credit'),
            'user' => $user,
            'ordonnancePrefill' => $ordonnancePrefill,
        ]);
    }

    /**
     * Crée une nouvelle vente
     */
    public function store(): void
    {
        $this->requirePermission('vente.create');
        $returnTo = $this->getSafeReturnUrl('/vente');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/vente/create?return_to=' . urlencode($returnTo));
            return;
        }

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        if ($isAjax) {
            ob_start();
        }

        try {
            $userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $this->getCurrentUser()['id'] ?? 0);
            
            // Récupérer la session de caisse ouverte
            $caisseSessionId = 0;
            try {
                $activeSession = $this->caisseService->getSessionOuverte($userId);
                if ($activeSession) {
                    $caisseSessionId = (int)$activeSession['id'];
                }
            } catch (\Exception $e) {
                // Ignorer l'erreur si pas de session ouverte
            }
            
            // Récupérer les données du formulaire
            $articles = json_decode($_POST['articles'] ?? '[]', true) ?: [];
            $ordonnance = json_decode($_POST['ordonnance'] ?? '[]', true) ?: [];
            $ordonnanceId = (int)($_POST['ordonnance_id'] ?? 0);
            $typePaiement = $this->paiementDetailsModel->normalizePaymentMode($_POST['mode_paiement'] ?? 'ESPECE');
            if ($typePaiement === 'CREDIT') {
                $this->requirePermission('vente.credit');
            }
            $remise = $this->normalizeDiscount((float)($_POST['remise'] ?? 0));
            
            // Récupérer les détails de paiement
            $paiementDetails = $this->extractPaiementDetails($_POST, $typePaiement);
            
            // Valider les détails de paiement si requis
            $paymentErrors = $this->paiementDetailsModel->validatePaymentData($typePaiement, $paiementDetails);
            if (!empty($paymentErrors)) {
                if ($isAjax) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => implode(', ', $paymentErrors)
                    ], 400);
                }
                $_SESSION['errors'] = $paymentErrors;
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/vente/create?return_to=' . urlencode($returnTo));
                return;
            }
            
            $data = [
                'utilisateur_id' => $userId,
                'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null,
                'caisse_session_id' => $caisseSessionId,
                'articles' => $this->applyGlobalDiscountToArticles($articles, $remise),
                'montant_total' => floatval($_POST['montant_total'] ?? 0),
                'montant_paye' => floatval($_POST['montant_paye'] ?? 0),
                'type_paiement' => $typePaiement,
                'is_credit' => $typePaiement === 'CREDIT',
                'remise' => $remise,
                'ordonnance' => $ordonnance,
                'ordonnance_id' => $ordonnanceId,
                'paiement_details' => $paiementDetails,
                'suspendre' => !empty($_POST['suspendre']),
                'vente_id' => (int)($_POST['vente_id'] ?? 0),
            ];

            // Valider les données
            $errors = $this->validateVenteData($data);
            if (!empty($errors)) {
                if ($isAjax) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => implode(', ', $errors)
                    ], 400);
                }

                $_SESSION['errors'] = $errors;
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/vente/create?return_to=' . urlencode($returnTo));
                return;
            }

            // Créer la vente
            $result = $this->venteService->creerVente($data);

            // Vérifier si c'est une requête AJAX
            if ($result['success']) {
                // Journaliser la création
                $this->auditService->logAction(
                    $userId,
                    'CREATE_VENTE',
                    'ventes',
                    $result['vente_id'],
                    null,
                    $data,
                    null,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                );
                
                if ($isAjax) {
                    // Réponse JSON pour AJAX
                    $this->jsonResponse([
                        'success' => true,
                        'message' => $result['message'],
                        'vente_id' => $result['vente_id'],
                        'ticket_url' => '/vente/impression?id=' . $result['vente_id'] . '&return_to=' . urlencode($returnTo),
                        'redirect_url' => !empty($result['suspended']) ? '/vente/tickets-en-attente?return_to=' . urlencode($returnTo) : '/vente/impression?id=' . $result['vente_id'] . '&return_to=' . urlencode($returnTo)
                    ]);
                } else {
                    $_SESSION['success'] = $result['message'];
                    $this->redirect(!empty($result['suspended']) ? '/vente/tickets-en-attente?return_to=' . urlencode($returnTo) : '/vente/impression?id=' . $result['vente_id'] . '&return_to=' . urlencode($returnTo));
                }
            } else {
                if ($isAjax) {
                    // Réponse JSON pour AJAX
                    $this->jsonResponse([
                        'success' => false,
                        'message' => $result['message'] ?? 'Erreur lors de la création de la vente'
                    ], 400);
                } else {
                    $_SESSION['errors'] = [$result['message']];
                    $_SESSION['old_input'] = $_POST;
                    $this->redirect('/vente/create?return_to=' . urlencode($returnTo));
                }
            }

        } catch (\Exception $e) {
            error_log("VenteController::store - " . $e->getMessage());
            
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                     $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
            
            if ($isAjax) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la création de la vente: ' . $e->getMessage()
                ], 500);
            } else {
                $_SESSION['errors'] = ['Erreur lors de la création de la vente'];
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/vente/create?return_to=' . urlencode($returnTo));
            }
        }
    }

    /** Detail route shared by dashboard, history and global search. */
    public function show(string $id): void
    {
        $this->requirePermission('vente.view');
        $vente = $this->venteService->getVenteComplete((int)$id);
        if (!$vente) {
            http_response_code(404);
            $this->render('vente/show', ['title' => 'Vente introuvable', 'vente' => null, 'returnTo' => $this->getSafeReturnUrl('/vente')]);
            return;
        }
        $user = $this->getCurrentUser();
        if (!$this->isAdminUser() && (int)$vente['utilisateur_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo 'Acces refuse';
            return;
        }
        $this->render('vente/show', ['title' => 'Détail vente', 'vente' => $vente, 'returnTo' => $this->getSafeReturnUrl('/vente')]);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Changement de session
     */
    public function changeSession(): void
    {
        $this->requirePermission('session.change');

        $this->render('vente/session', [
            'title' => 'Changement de Session'
        ]);
    }

    /**
     * Changement de date
     */
    public function changeDate(): void
    {
        $this->requirePermission('date.change');

        $this->render('vente/date', [
            'title' => 'Changement de Date'
        ]);
    }

    /**
     * Valide les données de vente
     */
    private function validateVenteData(array $data): array
    {
        $errors = [];

        if (empty($data['articles']) || !is_array($data['articles'])) {
            $errors[] = 'Au moins un article est requis';
        }

        $totalArticles = 0;
        foreach ($data['articles'] as $article) {
            if (!isset($article['produit_id']) || !isset($article['quantite']) || $article['quantite'] <= 0) {
                $errors[] = 'Tous les articles doivent avoir une quantité valide';
            }
            $totalArticles += $article['quantite'] ?? 0;
        }

        if ($totalArticles <= 0) {
            $errors[] = 'La quantité totale doit être supérieure à 0';
        }

        if ($data['montant_total'] <= 0) {
            $errors[] = 'Le montant total doit être supérieur à 0';
        }

        return $errors;
    }

    /**
     * Extrait les détails de paiement selon le mode choisi
     */
    private function extractPaiementDetails(array $postData, string $modePaiement): array
    {
        $details = [
            'mode_paiement' => $modePaiement
        ];

        switch ($modePaiement) {
            case 'DEPOT':
                $details['depot_nom_etablissement'] = trim($postData['depot_nom_etablissement'] ?? '');
                $details['depot_adresse'] = trim($postData['depot_adresse'] ?? '');
                $details['depot_telephone'] = trim($postData['depot_telephone'] ?? '');
                $details['depot_numero_arrete'] = trim($postData['depot_numero_arrete'] ?? '');
                break;

            case 'MOBILE_MONEY':
                $details['mobile_operateur'] = trim($postData['mobile_operateur'] ?? '');
                $details['mobile_nom_titulaire'] = trim($postData['mobile_nom_titulaire'] ?? '');
                $details['mobile_telephone'] = trim($postData['mobile_telephone'] ?? '');
                break;

            case 'CHEQUE':
                $details['cheque_numero'] = trim($postData['cheque_numero'] ?? '');
                $details['cheque_nom_banque'] = trim($postData['cheque_nom_banque'] ?? '');
                break;

            case 'BON':
                $details['bon_nom_beneficiaire'] = trim($postData['bon_nom_beneficiaire'] ?? '');
                $details['bon_telephone'] = trim($postData['bon_telephone'] ?? '');
                $details['bon_matricule'] = trim($postData['bon_matricule'] ?? '');
                $details['bon_numero_bon'] = trim($postData['bon_numero_bon'] ?? '');
                break;

            case 'ESPECE':
            case 'CARNET':
            case 'CARTE_VISA':
                // Aucun détail supplémentaire requis
                break;
        }

        return $details;
    }

    public function cancelTicket(): void
    {
        $this->requirePermission('cancel_ticket');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/assistant/annulation-ticket');
            return;
        }

        $venteId = (int)($_POST['vente_id'] ?? $_POST['ticket_id'] ?? 0);
        $motif = trim((string)($_POST['motif'] ?? ''));
        $returnTo = $this->getSafeReturnUrl('/assistant/annulation-ticket');
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

        if ($venteId <= 0 || $motif === '') {
            $message = 'Ticket et motif obligatoire pour annuler une vente';
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $_SESSION['errors'] = [$message];
            $this->redirect($returnTo);
            return;
        }

        try {
            $userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $this->getCurrentUser()['id'] ?? 0);
            $result = $this->venteService->annulerVente($venteId, $userId);
            $this->auditService->logAction(
                $userId,
                'CANCEL_TICKET_WITH_REASON',
                'ventes',
                $venteId,
                null,
                ['motif' => $motif],
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );

            if ($isAjax) {
                $this->jsonResponse($result);
            }

            $_SESSION['success'] = $result['message'] ?? 'Ticket annule avec succes';
            $this->redirect($returnTo);
        } catch (\Exception $e) {
            error_log('VenteController::cancelTicket - ' . $e->getMessage());
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $_SESSION['errors'] = [$e->getMessage()];
            $this->redirect($returnTo);
        }
    }

    private function normalizeDiscount(float $discount): float
    {
        return $this->discountLimitService->validateDiscount($discount, $this->getCurrentUser());
    }

    private function applyGlobalDiscountToArticles(array $articles, float $discount): array
    {
        if ($discount <= 0) {
            return $articles;
        }

        foreach ($articles as &$article) {
            $article['remise'] = max((float)($article['remise'] ?? 0), $discount);
        }

        return $articles;
    }

    /**
     * API pour récupérer les produits
     */
    public function apiProduits(): void
    {
        $this->requirePermission('vente.create');

        try {
            $produits = $this->venteService->getProduitsDisponibles();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $produits
            ]);

        } catch (\Exception $e) {
            error_log("VenteController::apiProduits - " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du chargement des produits'
            ]);
        }
    }

    /**
     * API pour récupérer les clients
     */
    public function apiClients(): void
    {
        $this->requirePermission('vente.create');

        try {
            $clients = $this->venteService->getClientsDisponibles();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $clients
            ]);

        } catch (\Exception $e) {
            error_log("VenteController::apiClients - " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du chargement des clients'
            ]);
        }
    }

    /**
     * Affiche la liste des tickets en attente
     */
    public function ticketsEnAttente(): void
    {
        if (!$this->isVenteUserLoggedIn()) {
            $_SESSION['errors'] = ['Veuillez vous connecter pour accéder au module vente'];
            $this->redirect('/login');
            return;
        }

        $this->requirePermission('vente.view');

        $user = $this->getCurrentUser();
        $userId = (int)($user['id'] ?? 0);
        $returnTo = $this->getSafeReturnUrl('/vente');

        // Récupérer les filtres
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;
        $clientId = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null;

        // Récupérer les tickets en attente
        $tickets = $this->venteService->getTicketsEnAttente($userId, $dateDebut, $dateFin, $clientId);

        // Récupérer les clients pour le filtre
        $clients = $this->venteService->getClientsDisponibles();

        $this->render('vente/tickets-en-attente', [
            'title' => 'Tickets en Attente',
            'tickets' => $tickets,
            'clients' => $clients,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'clientId' => $clientId,
            'returnTo' => $returnTo,
        ]);
    }

    /**
     * Annule un ticket en attente
     */
    public function annulerTicketEnAttente(): void
    {
        if (!$this->isVenteUserLoggedIn()) {
            $_SESSION['errors'] = ['Veuillez vous connecter pour accéder au module vente'];
            $this->redirect('/login');
            return;
        }

        $this->requirePermission('vente.delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/vente/tickets-en-attente');
            return;
        }

        $venteId = (int)($_POST['vente_id'] ?? 0);
        $motif = trim((string)($_POST['motif'] ?? ''));
        $returnTo = $this->getSafeReturnUrl('/vente/tickets-en-attente');
        $userId = (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0);

        if ($venteId <= 0 || $motif === '') {
            $_SESSION['errors'] = ['Ticket et motif obligatoires'];
            $this->redirect($returnTo);
            return;
        }

        try {
            $result = $this->venteService->annulerTicketEnAttente($venteId, $userId, $motif);
            
            $this->auditService->logAction(
                $userId,
                'ANNULER_TICKET_EN_ATTENTE',
                'ventes',
                $venteId,
                null,
                ['motif' => $motif],
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );

            $_SESSION['success'] = $result['message'] ?? 'Ticket annulé avec succès';
            $this->redirect($returnTo);

        } catch (\Exception $e) {
            error_log('VenteController::annulerTicketEnAttente - ' . $e->getMessage());
            $_SESSION['errors'] = [$e->getMessage()];
            $this->redirect($returnTo);
        }
    }

    /**
     * Reprend une vente en attente
     */
    public function reprendreVente(): void
    {
        if (!$this->isVenteUserLoggedIn()) {
            $_SESSION['errors'] = ['Veuillez vous connecter pour accéder au module vente'];
            $this->redirect('/login');
            return;
        }

        $this->requirePermission('vente.create');

        $venteId = (int)($_GET['id'] ?? 0);
        $userId = (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0);
        $returnTo = $this->getSafeReturnUrl('/vente/tickets-en-attente');

        if ($venteId <= 0) {
            $_SESSION['errors'] = ['Ticket invalide'];
            $this->redirect($returnTo);
            return;
        }

        try {
            $vente = $this->venteService->getVentePourReprise($venteId);

            if (!$vente) {
                $_SESSION['errors'] = ['Ticket non trouvé ou déjà traité'];
                $this->redirect($returnTo);
                return;
            }

            // Vérifier que l'utilisateur a le droit de reprendre cette vente
            if ((int)($vente['utilisateur_id'] ?? 0) !== $userId) {
                $_SESSION['errors'] = ['Vous n\'êtes pas autorisé à reprendre ce ticket'];
                $this->redirect($returnTo);
                return;
            }

            if (!empty($vente['ecriture_id'])) {
                $_SESSION['errors'] = ['Ce ticket historique déjà comptabilisé est conservé en lecture seule et ne peut pas être repris.'];
                $this->redirect($returnTo);
                return;
            }

            $maxDiscountPercent = $this->discountLimitService->getMaxDiscountForUser($this->getCurrentUser());

            $this->render('vente/create', [
                'title' => 'Reprendre la Vente',
                'venteEnCours' => $vente,
                'maxDiscountPercent' => $maxDiscountPercent,
                'user' => $this->getCurrentUser(),
                'returnTo' => $returnTo,
            ]);

        } catch (\Exception $e) {
            error_log('VenteController::reprendreVente - ' . $e->getMessage());
            $_SESSION['errors'] = [$e->getMessage()];
            $this->redirect($returnTo);
        }
    }

    /**
     * Affiche l'historique des ventes
     */
    public function historique(): void
    {
        if (!$this->isVenteUserLoggedIn()) {
            $_SESSION['errors'] = ['Veuillez vous connecter pour accéder au module vente'];
            $this->redirect('/login');
            return;
        }

        $this->requirePermission('vente.view');

        $user = $this->getCurrentUser();
        $userId = (int)($user['id'] ?? 0);
        $isAdmin = $this->isAdminUser();
        $returnTo = $this->getSafeReturnUrl('/vente');

        // Récupérer les filtres
        $periode = $_GET['periode'] ?? null;
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;
        $recherche = trim($_GET['recherche'] ?? '');
        $filtreUserId = !empty($_GET['utilisateur_id']) ? (int)$_GET['utilisateur_id'] : null;

        // Le vendeur ne voit que ses ventes, l'admin peut filtrer par utilisateur
        $filterUserId = $isAdmin ? $filtreUserId : $userId;

        // Récupérer l'historique des ventes
        $ventes = $this->venteService->getHistoriqueVentes($filterUserId, $periode, $dateDebut, $dateFin, $recherche ?: null, $isAdmin ? $filtreUserId : null);

        // Récupérer les utilisateurs pour le filtre (admin uniquement)
        $utilisateurs = [];
        if ($isAdmin) {
            $sql = "SELECT id, nom FROM utilisateurs WHERE deleted_at IS NULL ORDER BY nom";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('vente/historique', [
            'title' => 'Historique des Ventes',
            'ventes' => $ventes,
            'utilisateurs' => $utilisateurs,
            'periode' => $periode,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'recherche' => $recherche,
            'filtreUserId' => $filtreUserId,
            'isAdmin' => $isAdmin,
            'returnTo' => $returnTo,
        ]);
    }

    /**
     * Recherche globale (produits, clients, factures)
     */
    public function rechercheGlobale(): void
    {
        if (!$this->isVenteUserLoggedIn()) {
            $this->jsonResponse(['success' => false, 'message' => 'Non connecté'], 401);
            return;
        }

        $query = trim((string)($_GET['q'] ?? ''));
        if (strlen($query) < 2) {
            $this->jsonResponse(['success' => true, 'results' => []]);
            return;
        }

        $results = [];
        $returnTo = $this->getSafeReturnUrl('/vente');

        // Recherche produits
        $sqlProduits = "SELECT id, nom, code_cip, code_barre, prix_vente
                        FROM produits
                        WHERE is_actif = 1
                        AND (nom LIKE ? OR code_cip LIKE ? OR code_barre LIKE ? OR dci LIKE ?)
                        LIMIT 5";
        $stmtProduits = $this->db->prepare($sqlProduits);
        $stmtProduits->execute(["%$query%", "%$query%", "%$query%", "%$query%"]);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produits as $produit) {
            $results[] = [
                'type' => 'produit',
                'title' => $produit['nom'],
                'subtitle' => $produit['code_cip'] . ' - ' . number_format($produit['prix_vente'], 0, ',', ' ') . ' FCFA',
                'icon' => 'fa-box',
                'url' => '/produits/catalogue-vendeur?search=' . urlencode($produit['nom']) . '&return_to=' . urlencode($returnTo)
            ];
        }

        // Recherche clients
        $sqlClients = "SELECT id, nom, prenom, telephone
                       FROM clients
                       WHERE is_actif = 1
                       AND (nom LIKE ? OR prenom LIKE ? OR telephone LIKE ?)
                       LIMIT 5";
        $stmtClients = $this->db->prepare($sqlClients);
        $stmtClients->execute(["%$query%", "%$query%", "%$query%"]);
        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clients as $client) {
            $results[] = [
                'type' => 'client',
                'title' => $client['nom'] . ' ' . $client['prenom'],
                'subtitle' => $client['telephone'] ?? '',
                'icon' => 'fa-user',
                'url' => '/clients?search=' . urlencode($client['nom']) . '&return_to=' . urlencode($returnTo)
            ];
        }

        // Recherche factures
        $sqlFactures = "SELECT id, numero_facture, montant_net, date_vente, statut_vente
                        FROM ventes
                        WHERE (numero_facture LIKE ?)
                        AND deleted_at IS NULL
                        LIMIT 5";
        $stmtFactures = $this->db->prepare($sqlFactures);
        $stmtFactures->execute(["%$query%"]);
        $factures = $stmtFactures->fetchAll(PDO::FETCH_ASSOC);

        foreach ($factures as $facture) {
            $results[] = [
                'type' => 'facture',
                'title' => $facture['numero_facture'],
                'subtitle' => number_format($facture['montant_net'], 0, ',', ' ') . ' FCFA - ' . $facture['statut_vente'],
                'icon' => 'fa-receipt',
                'url' => '/vente/show/' . $facture['id'] . '?return_to=' . urlencode($returnTo)
            ];
        }

        $this->jsonResponse(['success' => true, 'results' => $results]);
    }

    /**
     * Page d'impression des tickets
     */
    public function impression(): void
    {
        if (!$this->isVenteUserLoggedIn()) {
            $_SESSION['errors'] = ['Veuillez vous connecter pour accéder au module vente'];
            $this->redirect('/login');
            return;
        }

        $this->requirePermission('vente.view');

        $venteId = (int)($_GET['id'] ?? 0);
        $returnTo = $this->getSafeReturnUrl('/vente');
        $userId = (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0);
        $onlyUser = !$this->isAdminUser();

        try {
            $vente = null;
            $tickets = [];

            if ($venteId > 0) {
                $vente = $this->venteService->getVenteComplete($venteId);

                if (!$vente) {
                    $_SESSION['error'] = 'Vente non trouvee';
                    $this->redirect('/vente');
                    return;
                }

                if ($onlyUser && (int)($vente['utilisateur_id'] ?? 0) !== $userId) {
                    $_SESSION['error'] = 'Acces refuse a cette vente';
                    $this->redirect('/vente/impression?return_to=' . urlencode($returnTo));
                    return;
                }
            } else {
                $tickets = $this->venteService->getTicketsPourImpression($userId, $onlyUser);
            }

            $this->render('vente/impression', [
                'title' => 'Impression Ticket - Vente',
                'user' => $this->currentUser,
                'vente' => $vente,
                'tickets' => $tickets,
                'returnTo' => $returnTo,
            ]);
        } catch (\Exception $e) {
            error_log("VenteController::impression - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors du chargement de la page d\'impression';
            $this->redirect('/vente');
        }
    }
}
