<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\RoleService;
use App\Services\DoubleAccesService;
use App\Services\SecurityService;
use App\Services\AuditGlobalService;
use Exception;

class SecurityController
{
    private AuthService $authService;
    private RoleService $roleService;
    private DoubleAccesService $doubleAccesService;
    private SecurityService $securityService;
    private AuditGlobalService $auditGlobalService;

    public function __construct(
        AuthService $authService,
        RoleService $roleService,
        DoubleAccesService $doubleAccesService,
        SecurityService $securityService,
        AuditGlobalService $auditGlobalService
    ) {
        $this->authService = $authService;
        $this->roleService = $roleService;
        $this->doubleAccesService = $doubleAccesService;
        $this->securityService = $securityService;
        $this->auditGlobalService = $auditGlobalService;
    }

    /**
     * Page de connexion
     */
    public function login(): void
    {
        // Si déjà connecté, rediriger vers le dashboard
        if (isset($_SESSION['user'])) {
            header('Location: /dashboard');
            exit;
        }

        require_once __DIR__ . '/../Views/security/login.php';
    }

    /**
     * Traitement de la connexion
     */
    public function authenticate(): void
    {
        header('Content-Type: application/json');

        try {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $codeCaisse = $_POST['code_caisse'] ?? '';
            $codeAvance = $_POST['code_avance'] ?? '';

            // Validation des entrées
            if (empty($username) || empty($password)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Identifiant et mot de passe requis',
                    'code' => 'MISSING_CREDENTIALS'
                ]);
                exit;
            }

            // Vérification des tentatives de connexion
            $loginAttempts = $this->securityService->checkLoginAttempts($username);
            if ($loginAttempts['blocked']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Compte temporairement bloqué. Veuillez réessayer plus tard.',
                    'code' => 'ACCOUNT_BLOCKED',
                    'block_time' => $loginAttempts['block_time']
                ]);
                exit;
            }

            // Authentification principale
            $authResult = $this->authService->authenticate($username, $password);

            if (!$authResult['success']) {
                // Enregistrer la tentative échouée
                $this->securityService->recordLoginAttempt($username, false, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                
                echo json_encode($authResult);
                exit;
            }

            // Vérification du double accès si requis
            $userId = $authResult['user']['id'];
            $doubleAuthRequired = in_array($authResult['user']['role']['code'], ['ADMIN', 'VENDEUR']);

            if ($doubleAuthRequired) {
                $doubleAuthResult = $this->doubleAccesService->verifierDoubleAcces(
                    $userId,
                    $codeCaisse,
                    $codeAvance
                );

                if (!$doubleAuthResult['success']) {
                    echo json_encode($doubleAuthResult);
                    exit;
                }
            }

            // Enregistrer la connexion réussie
            $this->securityService->recordLoginAttempt($username, true, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            // Créer la session utilisateur
            $_SESSION['user'] = $authResult['user'];
            $_SESSION['token'] = $authResult['token'];
            $_SESSION['expires_at'] = time() + $authResult['expires_in'];
            $_SESSION['double_auth'] = $doubleAuthRequired ? [
                'verified' => true,
                'timestamp' => time()
            ] : null;

            echo json_encode([
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => $authResult['user'],
                'redirect' => '/dashboard'
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'authentification',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Déconnexion
     */
    public function logout(): void
    {
        try {
            $token = $_SESSION['token'] ?? null;
            $userId = $_SESSION['user']['id'] ?? null;

            if ($token && $userId) {
                $this->authService->logout($token);
            }

            // Détruire la session
            session_destroy();

            header('Location: /login');
            exit;

        } catch (Exception $e) {
            // En cas d'erreur, quand même détruire la session
            session_destroy();
            header('Location: /login');
            exit;
        }
    }

    /**
     * Vérification du token pour l'AJAX
     */
    public function verifyToken(): void
    {
        header('Content-Type: application/json');

        try {
            $token = $_POST['token'] ?? '';
            
            if (empty($token)) {
                echo json_encode([
                    'valid' => false,
                    'message' => 'Token manquant'
                ]);
                exit;
            }

            $validation = $this->authService->validateToken($token);

            echo json_encode($validation);

        } catch (Exception $e) {
            echo json_encode([
                'valid' => false,
                'message' => 'Erreur lors de la validation du token',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Rafraîchissement du token
     */
    public function refreshToken(): void
    {
        header('Content-Type: application/json');

        try {
            $token = $_POST['token'] ?? '';
            
            if (empty($token)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Token manquant'
                ]);
                exit;
            }

            $result = $this->authService->refreshToken($token);

            echo json_encode($result);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement du token',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Changement de mot de passe
     */
    public function changePassword(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!$userId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Utilisateur non connecté'
                ]);
                exit;
            }

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tous les champs sont requis'
                ]);
                exit;
            }

            if ($newPassword !== $confirmPassword) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Les mots de passe ne correspondent pas'
                ]);
                exit;
            }

            $result = $this->authService->changePassword($userId, $currentPassword, $newPassword);

            echo json_encode($result);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Vérification du code d'accès caisse
     */
    public function verifyCaisseCode(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $codeCaisse = $_POST['code_caisse'] ?? '';

            if (!$userId || empty($codeCaisse)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Code d\'accès caisse requis'
                ]);
                exit;
            }

            $result = $this->doubleAccesService->verifierCodeAccesCaisse($userId, $codeCaisse);

            echo json_encode($result);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la vérification du code caisse',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Vérification du code d'accès avancé
     */
    public function verifyAvanceCode(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $codeAvance = $_POST['code_avance'] ?? '';

            if (!$userId || empty($codeAvance)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Code d\'accès avancé requis'
                ]);
                exit;
            }

            $result = $this->doubleAccesService->verifierCodeAccesAvance($userId, $codeAvance);

            echo json_encode($result);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la vérification du code avancé',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Génération des codes d'accès
     */
    public function generateAccessCodes(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $codeType = $_POST['code_type'] ?? ''; // 'caisse' ou 'avance'

            if (!$userId || empty($codeType)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Type de code et utilisateur requis'
                ]);
                exit;
            }

            $adminId = $_SESSION['user']['id']; // L'utilisateur génère ses propres codes

            $result = [];
            if ($codeType === 'caisse') {
                $result = $this->doubleAccesService->genererCodeAccesCaisse($userId, $adminId);
            } elseif ($codeType === 'avance') {
                $result = $this->doubleAccesService->genererCodeAccesAvance($userId, $adminId);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Type de code invalide'
                ]);
                exit;
            }

            echo json_encode($result);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la génération des codes',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tableau de bord de sécurité
     */
    public function dashboard(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        // Récupérer les statistiques de sécurité
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $auditDashboard = $this->auditGlobalService->getAuditDashboard($dateDebut, $dateFin);
        $securityStats = $this->doubleAccesService->getStatistiquesAcces();

        require_once __DIR__ . '/../Views/security/dashboard.php';
    }

    /**
     * Rapport d'audit complet
     */
    public function auditReport(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $report = $this->auditGlobalService->generateComprehensiveAuditReport($dateDebut, $dateFin);

        if (isset($_GET['export']) && $_GET['export'] === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="audit_report_' . date('YmdHis') . '.json"');
            echo json_encode($report, JSON_PRETTY_PRINT);
            exit;
        }

        require_once __DIR__ . '/../Views/security/audit_report.php';
    }

    /**
     * Gestion des rôles
     */
    public function roles(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'list':
                $roles = $this->roleService->getAllRoles();
                require_once __DIR__ . '/../Views/security/roles_list.php';
                break;

            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = $_POST;
                    $result = $this->roleService->createRole($data);
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                }
                require_once __DIR__ . '/../Views/security/roles_form.php';
                break;

            case 'edit':
                $roleId = $_GET['id'] ?? 0;
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = $_POST;
                    $result = $this->roleService->updateRole($roleId, $data);
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                }
                $role = $this->roleService->getRoleById($roleId);
                require_once __DIR__ . '/../Views/security/roles_form.php';
                break;

            case 'delete':
                $roleId = $_GET['id'] ?? 0;
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $result = $this->roleService->deleteRole($roleId);
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                }
                break;

            case 'permissions':
                $permissions = $this->roleService->getAvailablePermissions();
                header('Content-Type: application/json');
                echo json_encode($permissions);
                exit;
                break;

            default:
                header('Location: /security/roles');
                exit;
        }
    }

    /**
     * Configuration de la sécurité
     */
    public function config(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config = $_POST;
            
            // Valider et sauvegarder la configuration
            // À implémenter selon les besoins
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Configuration sauvegardée'
            ]);
            exit;
        }

        require_once __DIR__ . '/../Views/security/config.php';
    }

    /**
     * API pour les statistiques de sécurité
     */
    public function securityStats(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Accès non autorisé'
            ]);
            exit;
        }

        header('Content-Type: application/json');

        try {
            $stats = $this->doubleAccesService->getStatistiquesAcces();
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Nettoyage des codes expirés
     */
    public function cleanup(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Accès non autorisé'
            ]);
            exit;
        }

        header('Content-Type: application/json');

        try {
            $result = $this->doubleAccesService->nettoyerCodesExpirés();
            
            echo json_encode($result);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du nettoyage',
                'error' => $e->getMessage()
            ]);
        }
    }
}
