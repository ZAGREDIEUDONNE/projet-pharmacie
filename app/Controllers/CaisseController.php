<?php

namespace App\Controllers;

use App\Services\CaisseService;
use App\Services\AuditService;
use App\Core\BaseController;
use PDO;

class CaisseController extends BaseController
{
    private CaisseService $caisseService;
    private AuditService $auditService;

    private function requireCaisseAccess(): void
    {
        $this->requireAuth();
        $this->denyChargeCommandeRestrictedModules();
        $this->requirePermission('caisse.view');
    }

    private function requireCaisseOpenAccess(): void
    {
        $this->requireAuth();
        $this->denyChargeCommandeRestrictedModules();
        $this->requirePermission('caisse.open');
    }

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService($this->db);
        $this->caisseService = new CaisseService($this->db, $this->auditService);
    }

    /**
     * Affiche le dashboard de caisse
     */
    public function dashboard(): void
    {
        $this->requireCaisseAccess();
        $this->render('caisse/dashboard', [
            'title' => 'Dashboard Caisse'
        ]);
    }

    public function sessionForm(): void
    {
        $this->requireCaisseOpenAccess();
        $userId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
        if ($userId <= 0) {
            $this->redirect('/login');
            return;
        }

        try {
            $activeSession = $this->caisseService->getSessionOuverte($userId);
            if (!$activeSession && $this->canViewCaisseStatus()) {
                $activeSession = $this->getDashboardCaisseData()['activeCaisseSession'];
            }
            $sessionHistory = $this->caisseService->getHistoriqueSessions($userId, 20);

            $this->render('caisse/session', [
                'title' => 'Session Caisse',
                'activeSession' => $activeSession ? $this->mapSessionForView($activeSession) : null,
                'sessionHistory' => array_map([$this, 'mapSessionForView'], $sessionHistory)
            ]);
        } catch (\Exception $e) {
            error_log("CaisseController::sessionForm - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement de la session caisse'];
            $this->render('caisse/session', [
                'title' => 'Session Caisse',
                'activeSession' => null,
                'sessionHistory' => []
            ]);
        }
    }

    public function changeSession(): void
    {
        $this->requireCaisseOpenAccess();
        $userId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
        if ($userId <= 0) {
            $this->redirect('/login');
            return;
        }

        $montantOuverture = (float)($_POST['montant_reel'] ?? $_POST['montant_theorique'] ?? 0);
        if ($montantOuverture < 0) {
            $_SESSION['errors'] = ['Le montant d\'ouverture ne peut pas etre negatif'];
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/caisse/session');
            return;
        }

        try {
            $this->caisseService->ouvrirSession([
                'caissier_id' => $userId,
                'montant_ouverture' => $montantOuverture
            ]);

            $_SESSION['success'] = 'Session caisse ouverte avec succes';
        } catch (\Exception $e) {
            $_SESSION['errors'] = [$e->getMessage()];
            $_SESSION['old_input'] = $_POST;
        }

        $this->redirect('/caisse/session');
    }

    private function mapSessionForView(array $session): array
    {
        $session['statut'] = $session['statut'] ?? ($session['statut_session'] ?? '');
        $session['closed_at'] = $session['closed_at'] ?? ($session['date_fermeture'] ?? null);
        $session['montant_reel'] = $session['montant_reel'] ?? ($session['montant_fermeture'] ?: $session['montant_ouverture'] ?? 0);
        $session['montant_theorique'] = $session['montant_theorique_actuel'] ?? $session['montant_theorique'] ?? ($session['montant_ouverture'] ?? 0);
        $session['created_at'] = $session['created_at'] ?? ($session['date_ouverture'] ?? null);
        $session['observations'] = $session['observations'] ?? ($session['notes_controle'] ?? '');

        return $session;
    }

    /**
     * Page d'ouverture de session caisse
     */
    public function ouverture(): void
    {
        $this->requireCaisseOpenAccess();

        // Vérifier si une session est déjà ouverte
        $sessionExistante = $this->caisseService->getSessionOuverte($this->currentUser['id']);
        
        if ($sessionExistante) {
            $_SESSION['info'] = 'Une session de caisse est déjà ouverte';
            $this->redirectBack('/vente');
            return;
        }

        $this->render('caisse/ouverture');
    }

    /**
     * Traite l'ouverture de session
     */
    public function traiterOuverture(): void
    {
        $this->requireCaisseOpenAccess();
        $montantOuverture = floatval($_POST['montant_ouverture'] ?? 0);
        
        if ($montantOuverture < 0) {
            $_SESSION['error'] = 'Le montant d\'ouverture ne peut être négatif';
            $this->redirect('/caisse/ouverture');
            return;
        }

        try {
            $result = $this->caisseService->ouvrirSession([
                'caissier_id' => $this->currentUser['id'],
                'montant_ouverture' => $montantOuverture
            ]);

            $_SESSION['success'] = $result['message'];
            $this->redirectBack('/vente');

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/ouverture');
        }
    }

    /**
     * Page de fermeture de session caisse
     */
    public function fermeture(): void
    {
        $this->requireCaisseAccess();
        $this->requirePermission('close_cash_register');

        $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
        if (!$session && $this->canViewCaisseStatus()) {
            $session = $this->getDashboardCaisseData()['activeCaisseSession'];
        }
        
        if (!$session) {
            $_SESSION['error'] = 'Aucune session de caisse ouverte';
            $this->redirectBack('/caisse/session');
            return;
        }

        try {
            // Calculer le montant théorique
            $montantTheorique = $this->calculerMontantTheorique($session['id']);
            $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());
            
            $this->render('caisse/fermeture', [
                'session' => $session,
                'montant_theorique' => $montantTheorique,
                'user' => $this->currentUser,
                'returnTo' => $returnTo,
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirectBack('/vente');
        }
    }

    /**
     * Traite la fermeture de session
     */
    public function traiterFermeture(): void
    {
        $this->requireCaisseAccess();
        $this->requirePermission('close_cash_register');
        $sessionId = intval($_POST['session_id'] ?? 0);
        $montantFermeture = floatval($_POST['montant_fermeture'] ?? 0);
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());
        
        if (!$sessionId || $montantFermeture < 0) {
            $_SESSION['error'] = 'Données invalides';
            $this->redirect('/caisse/fermeture?return_to=' . urlencode($returnTo));
            return;
        }

        try {
            $result = $this->caisseService->fermerSession($sessionId, [
                'montant_fermeture' => $montantFermeture,
                'utilisateur_id' => $this->currentUser['id']
            ]);

            $_SESSION['success'] = $result['message'] . ' - Écart: ' . number_format($result['ecart'], 2);
            $this->redirect($returnTo);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/fermeture?return_to=' . urlencode($returnTo));
        }
    }

    /**
     * Historique des sessions de caisse
     */
    public function historique(): void
    {
        $this->requireCaisseAccess();

        try {
            $activeSession = $this->caisseService->getSessionOuverte($this->currentUser['id']);
            $sessions = $this->caisseService->getHistoriqueSessions($this->currentUser['id'], 30);
            
            $this->render('caisse/session', [
                'title' => 'Historique des Sessions Caisse',
                'activeSession' => $activeSession ? $this->mapSessionForView($activeSession) : null,
                'sessionHistory' => array_map([$this, 'mapSessionForView'], $sessions),
                'user' => $this->currentUser,
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirectBack('/vente');
        }
    }

    /**
     * Détails d'une session de caisse
     */
    public function detailsSession(): void
    {
        $sessionId = intval($_GET['id'] ?? 0);
        
        if (!$sessionId) {
            $_SESSION['error'] = 'ID de session non fourni';
            $this->redirect('/caisse/historique');
            return;
        }

        try {
            $session = $this->caisseService->getSessionDetails($sessionId);
            
            // Vérifier que l'utilisateur a le droit de voir cette session
            if ($session['caissier_id'] !== $this->currentUser['id'] && $this->currentUser['role'] !== 'ADMIN') {
                $_SESSION['error'] = 'Accès non autorisé';
                $this->redirect('/caisse/historique');
                return;
            }

            $mouvements = $this->caisseService->getMouvementsSession($sessionId);
            
            $this->render('caisse/details', [
                'session' => $session,
                'mouvements' => $mouvements,
                'user' => $this->currentUser
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/historique');
        }
    }

    /**
     * Génère le rapport Z de caisse
     */
    public function rapportZ(): void
    {
        $sessionId = intval($_GET['id'] ?? 0);
        
        if (!$sessionId) {
            $_SESSION['error'] = 'ID de session non fourni';
            $this->redirect('/caisse/historique');
            return;
        }

        try {
            $rapport = $this->caisseService->genererRapportZ($sessionId);
            
            $this->render('caisse/rapport_z', [
                'rapport' => $rapport,
                'user' => $this->currentUser
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/historique');
        }
    }

    /**
     * API: Récupère le statut de la session caisse actuelle
     */
    public function etat(): void
    {
        $this->requireCaisseAccess();

        try {
            $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
            if (!$session && $this->canViewCaisseStatus()) {
                $session = $this->getDashboardCaisseData()['activeCaisseSession'];
            }
            $montantTheorique = $session ? $this->calculerMontantTheorique((int)$session['id']) : 0;
            $mouvements = $session ? $this->caisseService->getMouvementsSession((int)$session['id']) : [];

            $this->render('caisse/etat', [
                'title' => 'Etat de la Session Caisse',
                'session' => $session,
                'montant_theorique' => $montantTheorique,
                'mouvements' => $mouvements,
                'user' => $this->currentUser,
            ]);
        } catch (\Exception $e) {
            $_SESSION['errors'] = [$e->getMessage()];
            $this->redirect('/caisse/session');
        }
    }

    public function statutSession(): void
    {
        $this->requireCaisseAccess();
        header('Content-Type: application/json');
        
        try {
            $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
            if (!$session && $this->canViewCaisseStatus()) {
                $session = $this->getDashboardCaisseData()['activeCaisseSession'];
            }
            
            if ($session) {
                $montantTheorique = $this->calculerMontantTheorique($session['id']);
                
                echo json_encode([
                    'success' => true,
                    'session' => $session,
                    'montant_theorique' => $montantTheorique,
                    'data' => [
                        'session' => $session,
                        'montant_theorique' => $montantTheorique
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'session' => null,
                    'message' => 'Aucune session ouverte',
                    'data' => [
                        'session' => null,
                        'montant_theorique' => 0
                    ]
                ]);
            }

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * API: Change de session de caisse (admin uniquement)
     */
    public function changerSession(): void
    {
        header('Content-Type: application/json');
        
        // Vérifier les permissions
        if ($this->currentUser['role'] !== 'ADMIN') {
            echo json_encode([
                'success' => false,
                'message' => 'Accès non autorisé'
            ]);
            return;
        }

        $nouveauCaissierId = intval($_POST['caissier_id'] ?? 0);
        
        if (!$nouveauCaissierId) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de caissier non fourni'
            ]);
            return;
        }

        try {
            // Fermer la session actuelle
            $sessionActuelle = $this->caisseService->getSessionOuverte($this->currentUser['id']);
            if ($sessionActuelle) {
                $this->caisseService->fermerSession($sessionActuelle['id'], [
                    'montant_fermeture' => $sessionActuelle['montant_ouverture'],
                    'utilisateur_id' => $this->currentUser['id']
                ]);
            }

            // Ouvrir une nouvelle session pour le nouveau caissier
            $result = $this->caisseService->ouvrirSession([
                'caissier_id' => $nouveauCaissierId,
                'montant_ouverture' => 0
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Session changée avec succès',
                'nouvelle_session' => $result
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * API: Enregistre un mouvement de caisse manuel
     */
    public function enregistrerMouvement(): void
    {
        $this->requireCaisseAccess();
        $this->requirePermission('close_cash_register');
        header('Content-Type: application/json');
        
        $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
        
        if (!$session) {
            echo json_encode([
                'success' => false,
                'message' => 'Aucune session de caisse ouverte'
            ]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            echo json_encode([
                'success' => false,
                'message' => 'Données invalides'
            ]);
            return;
        }

        try {
            $result = $this->caisseService->enregistrerMouvement([
                'caisse_session_id' => $session['id'],
                'type_mouvement' => $data['type_mouvement'],
                'montant' => $data['montant'],
                'moyen_paiement' => $data['moyen_paiement'] ?? 'ESPECE',
                'reference' => $data['reference'] ?? 'MANUEL',
                'description' => $data['description'],
                'utilisateur_id' => $this->currentUser['id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Statistiques de caisse
     */
    public function statistiques(): void
    {
        // Vérifier les permissions (admin/gérant uniquement)
        if (!in_array($this->currentUser['role'], ['ADMIN', 'GERANT'])) {
            $_SESSION['error'] = 'Accès non autorisé';
            $this->redirectBack('/vente');
            return;
        }

        try {
            $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
            $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
            
            $stats = $this->caisseService->getStatistiquesCaisse($dateDebut, $dateFin);
            
            $this->render('caisse/statistiques', [
                'stats' => $stats,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'user' => $this->currentUser
            ]);

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirectBack('/vente');
        }
    }

    /**
     * Calcule le montant théorique d'une session
     */
    private function calculerMontantTheorique(int $sessionId): float
    {
        $sql = "SELECT 
                    montant_ouverture + 
                    COALESCE(SUM(CASE WHEN type_mouvement = 'VENTE' THEN montant ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN type_mouvement = 'REMBOURSEMENT' THEN montant ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN type_mouvement = 'RETRAIT' THEN montant ELSE 0 END), 0)
                FROM caisse_sessions cs
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        return (float) $stmt->fetchColumn();
    }

    /**
     * Affiche la liste des encaissements
     */
    public function encaissements(): void
    {
        $this->requireCaisseAccess();
        
        $search = $_GET['search'] ?? '';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        
        try {
            $sql = "SELECT mc.*, cs.numero_session, u.username as utilisateur_nom 
                    FROM mouvements_caisse mc
                    LEFT JOIN caisse_sessions cs ON mc.caisse_session_id = cs.id
                    LEFT JOIN utilisateurs u ON mc.utilisateur_id = u.id
                    WHERE mc.type_mouvement IN ('VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE')
                    AND mc.supprime = 0
                    AND DATE(mc.date_mouvement) BETWEEN ? AND ?";
            
            $params = [$dateDebut, $dateFin];
            
            if ($search) {
                $sql .= " AND (mc.reference LIKE ? OR mc.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY mc.date_mouvement DESC LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $encaissements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('caisse/encaissements', [
                'title' => 'Encaissements',
                'encaissements' => $encaissements,
                'search' => $search,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse');
        }
    }

    /**
     * Affiche le formulaire de décaissement
     */
    public function decaissements(): void
    {
        $this->requireCaisseAccess();
        $this->requirePermission('close_cash_register');
        
        $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
        
        $this->render('caisse/decaissements', [
            'title' => 'Décaissements',
            'session' => $session,
            'user' => $this->currentUser
        ]);
    }

    /**
     * Enregistre un décaissement
     */
    public function storeDecaissement(): void
    {
        $this->requireCaisseAccess();
        $this->requirePermission('close_cash_register');
        
        $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
        
        if (!$session) {
            $_SESSION['error'] = 'Aucune session de caisse ouverte';
            $this->redirect('/caisse/decaissements');
            return;
        }
        
        $montant = floatval($_POST['montant'] ?? 0);
        $motif = $_POST['motif'] ?? '';
        $observations = $_POST['observations'] ?? '';
        
        if ($montant <= 0) {
            $_SESSION['error'] = 'Le montant doit être supérieur à 0';
            $this->redirect('/caisse/decaissements');
            return;
        }
        
        try {
            $this->caisseService->enregistrerMouvement([
                'caisse_session_id' => $session['id'],
                'type_mouvement' => 'DECAISSEMENT',
                'montant' => $montant,
                'moyen_paiement' => $_POST['moyen_paiement'] ?? 'ESPECE',
                'reference' => 'DECAISSEMENT_MANUEL',
                'description' => $motif . ($observations ? ' - ' . $observations : ''),
                'utilisateur_id' => $this->currentUser['id']
            ]);
            
            $_SESSION['success'] = 'Décaissement enregistré avec succès';
            $this->redirect('/caisse/decaissements');
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/decaissements');
        }
    }

    /**
     * Affiche la liste des annulations
     */
    public function annulations(): void
    {
        $this->requireCaisseAccess();
        
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $search = $_GET['search'] ?? '';
        $typeOperation = $_GET['type_operation'] ?? '';
        
        try {
            $annulations = [];
            
            // Récupérer les annulations depuis trace_annulations
            $sql = "SELECT ta.*, u.username as utilisateur_nom,
                           CASE 
                               WHEN ta.type_document = 'vente' THEN v.numero_facture
                               WHEN ta.type_document = 'paiement' THEN COALESCE(vp.numero_facture, CONCAT('PAIEMENT-', p.id))
                               ELSE CONCAT(UPPER(ta.type_document), '-', ta.document_id)
                           END as reference_operation,
                           CASE 
                               WHEN ta.type_document = 'vente' THEN v.montant_net
                               WHEN ta.type_document = 'paiement' THEN COALESCE(vp.montant_paye, 0)
                               ELSE 0
                           END as montant_operation
                    FROM trace_annulations ta
                    LEFT JOIN utilisateurs u ON ta.utilisateur_id = u.id
                    LEFT JOIN ventes v ON ta.type_document = 'vente' AND ta.document_id = v.id
                    LEFT JOIN paiement_details p ON ta.type_document = 'paiement' AND ta.document_id = p.id
                    LEFT JOIN ventes vp ON p.vente_id = vp.id
                    WHERE DATE(ta.date_annulation) BETWEEN ? AND ?";
            
            $params = [$dateDebut, $dateFin];
            
            if ($typeOperation) {
                $sql .= " AND ta.type_document = ?";
                $params[] = $typeOperation;
            }
            
            if ($search) {
                $sql .= " AND (ta.motif LIKE ? OR v.numero_facture LIKE ? OR vp.numero_facture LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY ta.date_annulation DESC LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $traceAnnulations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les ventes annulées (statut ANNULEE)
            $sqlVentes = "SELECT v.*, u.username as utilisateur_nom,
                                 'VENTE' as type_document,
                                 v.numero_facture as reference_operation,
                                 v.montant_net as montant_operation,
                                 v.updated_at as date_annulation,
                                 'Statut changé en ANNULEE' as motif
                          FROM ventes v
                          LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                          WHERE v.statut_vente = 'ANNULEE'
                          AND DATE(v.updated_at) BETWEEN ? AND ?";
            
            $paramsVentes = [$dateDebut, $dateFin];
            
            if ($typeOperation === '' || $typeOperation === 'vente') {
                if ($search) {
                    $sqlVentes .= " AND (v.numero_facture LIKE ? OR v.motif_annulation LIKE ?)";
                    $paramsVentes[] = "%$search%";
                    $paramsVentes[] = "%$search%";
                }
                
                $sqlVentes .= " ORDER BY v.updated_at DESC LIMIT 100";
                
                $stmtVentes = $this->db->prepare($sqlVentes);
                $stmtVentes->execute($paramsVentes);
                $ventesAnnulees = $stmtVentes->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $ventesAnnulees = [];
            }
            
            // Récupérer les mouvements caisse d'annulation
            $sqlMouvements = "SELECT mc.*, cs.numero_session, u.username as utilisateur_nom,
                                    'MOUVEMENT_CAISSE' as type_document,
                                    mc.reference as reference_operation,
                                    mc.montant as montant_operation,
                                    mc.date_mouvement as date_annulation,
                                    mc.description as motif
                             FROM mouvements_caisse mc
                             LEFT JOIN caisse_sessions cs ON mc.caisse_session_id = cs.id
                             LEFT JOIN utilisateurs u ON mc.utilisateur_id = u.id
                             WHERE mc.type_mouvement IN ('REMBOURSEMENT', 'ANNULATION_VENTE')
                             AND mc.supprime = 0
                             AND DATE(mc.date_mouvement) BETWEEN ? AND ?";
            
            $paramsMouvements = [$dateDebut, $dateFin];
            
            if ($typeOperation === '' || $typeOperation === 'mouvement_caisse') {
                if ($search) {
                    $sqlMouvements .= " AND (mc.reference LIKE ? OR mc.description LIKE ?)";
                    $paramsMouvements[] = "%$search%";
                    $paramsMouvements[] = "%$search%";
                }
                
                $sqlMouvements .= " ORDER BY mc.date_mouvement DESC LIMIT 100";
                
                $stmtMouvements = $this->db->prepare($sqlMouvements);
                $stmtMouvements->execute($paramsMouvements);
                $mouvementsAnnulations = $stmtMouvements->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $mouvementsAnnulations = [];
            }
            
            // Fusionner tous les résultats
            $annulations = array_merge($traceAnnulations, $ventesAnnulees, $mouvementsAnnulations);
            
            // Trier par date d'annulation
            usort($annulations, function($a, $b) {
                $dateA = strtotime($a['date_annulation'] ?? '1970-01-01');
                $dateB = strtotime($b['date_annulation'] ?? '1970-01-01');
                return $dateB <=> $dateA;
            });
            
            $this->render('caisse/annulations', [
                'title' => 'Annulations',
                'annulations' => $annulations,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'search' => $search,
                'type_operation' => $typeOperation,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse');
        }
    }

    /**
     * Affiche les rapports de caisse
     */
    public function rapports(): void
    {
        $this->requireCaisseAccess();
        
        $typeRapport = $_GET['type'] ?? 'journalier';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $utilisateurId = $_GET['utilisateur_id'] ?? '';
        
        try {
            $where = ["DATE(cs.date_ouverture) BETWEEN ? AND ?"];
            $params = [$dateDebut, $dateFin];
            
            if ($utilisateurId) {
                $where[] = "cs.caissier_id = ?";
                $params[] = $utilisateurId;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "SELECT cs.*, u.username as caissier_nom,
                    COUNT(mc.id) as nombre_mouvements,
                    SUM(CASE WHEN mc.type_mouvement = 'VENTE' THEN mc.montant ELSE 0 END) as total_ventes,
                    SUM(CASE WHEN mc.type_mouvement = 'REMBOURSEMENT' THEN mc.montant ELSE 0 END) as total_remboursements,
                    SUM(CASE WHEN mc.type_mouvement = 'DECAISSEMENT' THEN mc.montant ELSE 0 END) as total_decaissements
                    FROM caisse_sessions cs
                    JOIN utilisateurs u ON cs.caissier_id = u.id
                    LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                    WHERE $whereClause
                    GROUP BY cs.id
                    ORDER BY cs.date_ouverture DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer la liste des utilisateurs pour le filtre
            $stmtUsers = $this->db->query("SELECT id, username FROM utilisateurs ORDER BY username");
            $utilisateurs = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('caisse/rapports', [
                'title' => 'Rapports Caisse',
                'sessions' => $sessions,
                'utilisateurs' => $utilisateurs,
                'type_rapport' => $typeRapport,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'utilisateur_id' => $utilisateurId,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse');
        }
    }

    /**
     * Exporte un rapport de caisse
     */
    public function exportRapport(): void
    {
        $this->requireCaisseAccess();
        
        $format = $_GET['format'] ?? 'excel';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        
        try {
            $sql = "SELECT cs.numero_session, u.username as caissier, 
                    cs.date_ouverture, cs.date_fermeture,
                    cs.montant_ouverture, cs.montant_fermeture, 
                    cs.montant_theorique, cs.ecart, cs.statut_session
                    FROM caisse_sessions cs
                    JOIN utilisateurs u ON cs.caissier_id = u.id
                    WHERE DATE(cs.date_ouverture) BETWEEN ? AND ?
                    ORDER BY cs.date_ouverture DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dateDebut, $dateFin]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'excel') {
                header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
                header('Content-Disposition: attachment; filename="rapport-caisse.xls"');
                echo "\xEF\xBB\xBF<table border='1'>";
                echo "<tr><th>Session</th><th>Caissier</th><th>Ouverture</th><th>Fermeture</th><th>Montant Ouverture</th><th>Montant Fermeture</th><th>Montant Théorique</th><th>Écart</th><th>Statut</th></tr>";
                foreach ($sessions as $s) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($s['numero_session']) . "</td>";
                    echo "<td>" . htmlspecialchars($s['caissier']) . "</td>";
                    echo "<td>" . htmlspecialchars($s['date_ouverture']) . "</td>";
                    echo "<td>" . htmlspecialchars($s['date_fermeture'] ?? '-') . "</td>";
                    echo "<td>" . number_format($s['montant_ouverture'], 0, ',', ' ') . "</td>";
                    echo "<td>" . number_format($s['montant_fermeture'] ?? 0, 0, ',', ' ') . "</td>";
                    echo "<td>" . number_format($s['montant_theorique'] ?? 0, 0, ',', ' ') . "</td>";
                    echo "<td>" . number_format($s['ecart'] ?? 0, 0, ',', ' ') . "</td>";
                    echo "<td>" . htmlspecialchars($s['statut_session']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } elseif ($format === 'pdf') {
                $this->render('caisse/rapports', [
                    'title' => 'Rapport Caisse',
                    'sessions' => $sessions,
                    'print' => true,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                    'user' => $this->currentUser
                ]);
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/caisse/rapports');
        }
    }
}
