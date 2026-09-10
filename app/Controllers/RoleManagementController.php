<?php

namespace App\Controllers;

use App\Services\RolePermissionService;
use App\Services\AssistantAuthService;
use App\Services\CaisseSessionService;

class RoleManagementController
{
    private RolePermissionService $rolePermissionService;
    private AssistantAuthService $assistantAuthService;
    private CaisseSessionService $caisseSessionService;

    public function __construct(
        RolePermissionService $rolePermissionService,
        AssistantAuthService $assistantAuthService,
        CaisseSessionService $caisseSessionService
    ) {
        $this->rolePermissionService = $rolePermissionService;
        $this->assistantAuthService = $assistantAuthService;
        $this->caisseSessionService = $caisseSessionService;
    }

    /**
     * Vérifie une permission via API
     */
    public function apiCheckPermission(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $action = $_POST['action'] ?? null;
        $assistantCode = $_POST['assistant_code'] ?? null;

        if (!$userId || !$action) {
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        $check = $this->rolePermissionService->checkActionPermission($userId, $action);
        
        echo json_encode($check);
    }

    /**
     * Vérifie un code d'accès assistant via API
     */
    public function apiVerifyCode(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $code = $_POST['code'] ?? null;
        $type = $_POST['type'] ?? null;

        if (!$userId || !$code || !$type) {
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        $verification = $this->assistantAuthService->verifyAssistantCode($userId, $code, $type);
        
        echo json_encode($verification);
    }

    /**
     * Génère les codes d'accès assistant
     */
    public function generateAssistantCodes(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $result = $this->assistantAuthService->generateAssistantCodes($userId);
        
        echo json_encode($result);
    }

    /**
     * Ouvre une session de caisse
     */
    public function ouvrirSessionCaisse(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $sessionNumber = $_POST['session_number'] ?? null;
        $montantOuverture = floatval($_POST['montant_ouverture'] ?? 0);

        if (!$userId || !$sessionNumber) {
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        $result = $this->caisseSessionService->ouvrirSession($userId, $sessionNumber, $montantOuverture);
        
        echo json_encode($result);
    }

    /**
     * Ferme une session de caisse
     */
    public function fermerSessionCaisse(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $montantFermeture = floatval($_POST['montant_fermeture'] ?? 0);
        $notes = $_POST['notes'] ?? '';

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $result = $this->caisseSessionService->fermerSession($userId, $montantFermeture, $notes);
        
        echo json_encode($result);
    }

    /**
     * Change la session de caisse
     */
    public function changerSessionCaisse(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $nouvelleSession = $_POST['nouvelle_session'] ?? null;
        $montantOuverture = floatval($_POST['montant_ouverture'] ?? 0);

        if (!$userId || !$nouvelleSession) {
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        $result = $this->caisseSessionService->changerSession($userId, $nouvelleSession, $montantOuverture);
        
        echo json_encode($result);
    }

    /**
     * Récupère les informations de session actuelle
     */
    public function getSessionInfo(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $sessionActive = $this->caisseSessionService->getSessionActive($userId);
        $userInfo = $this->rolePermissionService->getUserRole($userId);
        $permissions = $this->rolePermissionService->getUserPermissions($userId);

        echo json_encode([
            'success' => true,
            'session' => $sessionActive,
            'user_role' => $userInfo,
            'permissions' => $permissions
        ]);
    }

    /**
     * Récupère toutes les sessions actives
     */
    public function getAllSessions(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        // Vérifier si l'utilisateur a la permission de voir toutes les sessions
        if (!$this->rolePermissionService->hasPermission($userId, 'caisse_manage')) {
            echo json_encode([
                'success' => false,
                'message' => 'Permission refusée'
            ]);
            exit;
        }

        $sessions = $this->caisseSessionService->getAllSessionsActives();

        echo json_encode([
            'success' => true,
            'sessions' => $sessions
        ]);
    }

    /**
     * Récupère l'historique des sessions
     */
    public function getHistoriqueSessions(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $historique = $this->caisseSessionService->getHistoriqueSessions($userId, $dateDebut, $dateFin);

        echo json_encode([
            'success' => true,
            'historique' => $historique
        ]);
    }

    /**
     * Récupère les statistiques des sessions
     */
    public function getSessionsStatistics(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $stats = $this->caisseSessionService->getSessionsStatistics($dateDebut, $dateFin);

        echo json_encode($stats);
    }

    /**
     * Récupère les informations d'authentification assistant
     */
    public function getAssistantAuthInfo(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        // Vérifier si l'utilisateur est un assistant
        if (!$this->rolePermissionService->isAssistant($userId)) {
            echo json_encode([
                'success' => false,
                'message' => 'L\'utilisateur n\'est pas un assistant'
            ]);
            exit;
        }

        $authInfo = $this->assistantAuthService->getAssistantAuthInfo($userId);

        echo json_encode($authInfo);
    }

    /**
     * Réinitialise les codes d'un assistant
     */
    public function resetAssistantCodes(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $result = $this->assistantAuthService->resetAssistantCodes($userId);

        echo json_encode($result);
    }

    /**
     * Récupère l'historique des utilisations de codes
     */
    public function getCodeUsageHistory(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;
        $type = $_GET['type'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $history = $this->assistantAuthService->getCodeUsageHistory($userId, $type);

        echo json_encode($history);
    }

    /**
     * Récupère les statistiques d'utilisation des codes
     */
    public function getCodeStatistics(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non connecté'
            ]);
            exit;
        }

        $stats = $this->assistantAuthService->getCodeStatistics($userId);

        echo json_encode($stats);
    }

    /**
     * Page de gestion des rôles et permissions
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            header('Location: /login');
            exit;
        }

        // Vérifier si l'utilisateur est administrateur
        if (!$this->rolePermissionService->isAdmin($userId)) {
            header('Location: /dashboard');
            exit;
        }

        $roles = $this->rolePermissionService->getAllRoles();
        $userRole = $this->rolePermissionService->getUserRole($userId);

        require_once __DIR__ . '/../Views/admin/roles_permissions.php';
    }

    /**
     * Page de gestion des sessions de caisse
     */
    public function sessions(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            header('Location: /login');
            exit;
        }

        $sessionActive = $this->caisseSessionService->getSessionActive($userId);
        $allSessions = $this->caisseSessionService->getAllSessionsActives();
        $userRole = $this->rolePermissionService->getUserRole($userId);

        require_once __DIR__ . '/../Views/admin/caisse_sessions.php';
    }

    /**
     * Page de configuration des codes assistants
     */
    public function assistantCodes(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            header('Location: /login');
            exit;
        }

        // Vérifier si l'utilisateur est assistant ou admin
        if (!$this->rolePermissionService->isAssistant($userId) && !$this->rolePermissionService->isAdmin($userId)) {
            header('Location: /dashboard');
            exit;
        }

        $authInfo = $this->assistantAuthService->getAssistantAuthInfo($userId);
        $userRole = $this->rolePermissionService->getUserRole($userId);

        require_once __DIR__ . '/../Views/admin/assistant_codes.php';
    }
}
