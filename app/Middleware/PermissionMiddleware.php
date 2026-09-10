<?php

namespace App\Middleware;

use App\Services\RBACService;
use App\Services\AuthService;
use Exception;

/**
 * Middleware RBAC pour la vérification des permissions
 */
class PermissionMiddleware
{
    private RBACService $rbacService;
    private AuthService $authService;

    public function __construct(RBACService $rbacService, AuthService $authService)
    {
        $this->rbacService = $rbacService;
        $this->authService = $authService;
    }

    /**
     * Vérifie si l'utilisateur a la permission requise
     * Middleware principal du système RBAC connecté à la base réelle
     */
    public function checkPermission(string $permission): callable
    {
        return function ($request, $response, $next) use ($permission) {
            // Vérifier l'authentification
            $userId = $this->getCurrentUserId();
            
            if (!$userId) {
                return $this->unauthorizedResponse($response, 'Authentification requise');
            }

            // Vérifier la permission avec RBACService
            if (!$this->rbacService->hasPermission($userId, $permission)) {
                // Enregistrer l'accès refusé dans audit_logs
                $this->logAccessDenied($userId, $permission);
                return $this->forbiddenResponse($response, "Permission non accordée: {$permission}");
            }

            return $next($request, $response);
        };
    }

    /**
     * Vérifie plusieurs permissions (ET logique)
     */
    public function checkPermissions(array $permissions): callable
    {
        return function ($request, $response, $next) use ($permissions) {
            $userId = $this->getCurrentUserId();
            
            if (!$userId) {
                return $this->unauthorizedResponse($response, 'Authentification requise');
            }

            foreach ($permissions as $permission) {
                if (!$this->rbacService->hasPermissionWithAudit($userId, $permission, $this->getCurrentRoute())) {
                    return $this->forbiddenResponse($response, "Permission non accordée: {$permission}");
                }
            }

            return $next($request, $response);
        };
    }

    /**
     * Vérifie au moins une permission (OU logique)
     */
    public function checkAnyPermission(array $permissions): callable
    {
        return function ($request, $response, $next) use ($permissions) {
            $userId = $this->getCurrentUserId();
            
            if (!$userId) {
                return $this->unauthorizedResponse($response, 'Authentification requise');
            }

            $hasAnyPermission = false;
            foreach ($permissions as $permission) {
                if ($this->rbacService->hasPermission($userId, $permission)) {
                    $hasAnyPermission = true;
                    break;
                }
            }

            if (!$hasAnyPermission) {
                return $this->forbiddenResponse($response, 'Aucune permission accordée pour cette action');
            }

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function checkRole(string $role): callable
    {
        return function ($request, $response, $next) use ($role) {
            $userId = $this->getCurrentUserId();
            
            if (!$userId) {
                return $this->unauthorizedResponse($response, 'Authentification requise');
            }

            $userRole = $this->rbacService->getUserRole($userId);
            
            if ($userRole !== $role && $userRole !== 'ADMIN') {
                return $this->forbiddenResponse($response, "Rôle non autorisé: {$role}");
            }

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public function checkAdmin(): callable
    {
        return $this->checkRole('ADMIN');
    }

    /**
     * Middleware pour les routes API
     */
    public function apiCheckPermission(string $permission): callable
    {
        return function ($request, $response, $next) use ($permission) {
            // Pour les API, vérifier le token JWT
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return $this->apiResponse(401, false, 'Token manquant');
            }

            $payload = $this->authService->verifyToken($token);
            
            if (!$payload) {
                return $this->apiResponse(401, false, 'Token invalide');
            }

            // Vérifier la permission
            if (!$this->rbacService->hasPermissionWithAudit($payload['user_id'], $permission, $this->getCurrentRoute())) {
                return $this->apiResponse(403, false, "Permission non accordée: {$permission}");
            }

            // Ajouter les infos utilisateur à la requête
            $request['user'] = $payload;

            return $next($request, $response);
        };
    }

    /**
     * Récupère l'ID de l'utilisateur actuel
     */
    private function getCurrentUserId(): ?int
    {
        // Vérifier la session
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        // Vérifier le token JWT
        $token = $this->getTokenFromRequest($_SERVER);
        if ($token) {
            $payload = $this->authService->verifyToken($token);
            return $payload ? (int)$payload['user_id'] : null;
        }

        return null;
    }

    /**
     * Récupère la route actuelle
     */
    private function getCurrentRoute(): string
    {
        return $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'] ?? 'unknown';
    }

    /**
     * Extrait le token de la requête
     */
    private function getTokenFromRequest($request): ?string
    {
        // Header Authorization
        if (isset($request['HTTP_AUTHORIZATION'])) {
            $authHeader = $request['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return trim($matches[1]);
            }
        }

        // Cookie
        if (isset($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }

        // Paramètre
        if (isset($request['token'])) {
            return $request['token'];
        }

        return null;
    }

    /**
     * Réponse 401 Unauthorized
     */
    private function unauthorizedResponse($response, string $message)
    {
        if (is_array($response)) {
            // Mode API
            return $this->apiResponse(401, false, $message);
        } else {
            // Mode Web
            header('HTTP/1.0 401 Unauthorized');
            if (strpos($this->getCurrentRoute(), '/api/') === 0) {
                return $this->apiResponse(401, false, $message);
            } else {
                $_SESSION['redirect_after_login'] = $this->getCurrentRoute();
                header('Location: /login');
                exit;
            }
        }
    }

    /**
     * Réponse 403 Forbidden
     */
    private function forbiddenResponse($response, string $message)
    {
        if (is_array($response)) {
            // Mode API
            return $this->apiResponse(403, false, $message);
        } else {
            // Mode Web
            header('HTTP/1.0 403 Forbidden');
            if (strpos($this->getCurrentRoute(), '/api/') === 0) {
                return $this->apiResponse(403, false, $message);
            } else {
                // Afficher page d'erreur 403
                $this->renderErrorPage(403, $message);
            }
        }
    }

    /**
     * Réponse API standardisée
     */
    private function apiResponse(int $status, bool $success, string $message, array $data = []): array
    {
        header('Content-Type: application/json');
        header('HTTP/1.0 ' . $status);
        
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Journalise un accès refusé dans audit_logs
     */
    private function logAccessDenied(?int $userId, string $permission): void
    {
        try {
            $sql = "INSERT INTO audit_logs (utilisateur_id, action, table_name, record_id, ip_address, user_agent, date_action)
                    VALUES (:user_id, :action, :table_name, :record_id, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'action' => 'ACCESS_DENIED',
                'table_name' => 'permissions',
                'record_id' => null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            error_log("PermissionMiddleware::logAccessDenied - " . $e->getMessage());
        }
    }

    /**
     * Affiche une page d'erreur
     */
    private function renderErrorPage(int $code, string $message): void
    {
        $title = $code === 403 ? 'Accès Refusé' : 'Erreur';
        
        // Si c'est une requête AJAX, retourner JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode([
                'success' => false,
                'message' => $message,
                'code' => $code
            ]);
            return;
        }

        // Sinon afficher la page d'erreur
        echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error-box { max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #ddd; border-radius: 8px; }
        .error-code { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        .error-message { font-size: 18px; color: #333; margin-bottom: 30px; }
        .back-link { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class='error-box'>
        <div class='error-code'>{$code}</div>
        <div class='error-message'>{$message}</div>
        <a href='/dashboard' class='back-link'>← Retour au dashboard</a>
    </div>
</body>
</html>";
        exit;
    }
}
            $tokenValidation = $this->authService->validateToken($token);
            
            if (!$tokenValidation['valid']) {
                return $this->unauthorizedResponse($response, $tokenValidation['message']);
            }

            // Vérifier la permission
            if (!$this->authService->checkPermission($tokenValidation['user_id'], $permission)) {
                return $this->unauthorizedResponse($response, 'Permission non accordée');
            }

            // Ajouter les informations utilisateur à la requête
            $request->user_id = $tokenValidation['user_id'];
            $request->user_role = $tokenValidation['role'];
            $request->user_permissions = $tokenValidation['permissions'];
            $request->username = $tokenValidation['username'];

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur a le rôle requis
     */
    public function checkRole(string $role): callable
    {
        return function ($request, $response, $next) use ($role) {
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return $this->unauthorizedResponse($response, 'Token manquant');
            }

            $tokenValidation = $this->authService->validateToken($token);
            
            if (!$tokenValidation['valid']) {
                return $this->unauthorizedResponse($response, $tokenValidation['message']);
            }

            if (!$this->authService->hasRole($tokenValidation['user_id'], $role)) {
                return $this->unauthorizedResponse($response, 'Rôle non accordé');
            }

            // Ajouter les informations utilisateur à la requête
            $request->user_id = $tokenValidation['user_id'];
            $request->user_role = $tokenValidation['role'];
            $request->user_permissions = $tokenValidation['permissions'];
            $request->username = $tokenValidation['username'];

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function requireAuth(): callable
    {
        return function ($request, $response, $next) {
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return $this->unauthorizedResponse($response, 'Token manquant');
            }

            $tokenValidation = $this->authService->validateToken($token);
            
            if (!$tokenValidation['valid']) {
                return $this->unauthorizedResponse($response, $tokenValidation['message']);
            }

            // Ajouter les informations utilisateur à la requête
            $request->user_id = $tokenValidation['user_id'];
            $request->user_role = $tokenValidation['role'];
            $request->user_permissions = $tokenValidation['permissions'];
            $request->username = $tokenValidation['username'];

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public function requireAdmin(): callable
    {
        return $this->checkRole('ADMIN');
    }

    /**
     * Vérifie si l'utilisateur est vendeur
     */
    public function requireVendeur(): callable
    {
        return $this->checkRole('VENDEUR');
    }

    /**
     * Vérifie si l'utilisateur est chargé de commande
     */
    public function requireChargeCommande(): callable
    {
        return $this->checkRole('CHARGE_COMMANDE');
    }

    /**
     * Vérifie si l'utilisateur a accès à la caisse
     */
    public function requireCaisse(): callable
    {
        return $this->checkPermission('CAISSE_ACCESS');
    }

    /**
     * Vérifie si l'utilisateur peut gérer les ventes
     */
    public function requireVenteGestion(): callable
    {
        return $this->checkPermission('VENTE_GESTION');
    }

    /**
     * Vérifie si l'utilisateur peut annuler des ventes
     */
    public function requireVenteAnnulation(): callable
    {
        return $this->checkPermission('VENTE_ANNULATION');
    }

    /**
     * Vérifie si l'utilisateur peut gérer le stock
     */
    public function requireStockGestion(): callable
    {
        return $this->checkPermission('STOCK_GESTION');
    }

    /**
     * Vérifie si l'utilisateur peut gérer les clients
     */
    public function requireClientGestion(): callable
    {
        return $this->checkPermission('CLIENT_GESTION');
    }

    /**
     * Vérifie si l'utilisateur peut gérer la comptabilité
     */
    public function requireComptabiliteGestion(): callable
    {
        return $this->checkPermission('COMPTABILITE_GESTION');
    }

    /**
     * Vérifie si l'utilisateur peut consulter les rapports
     */
    public function requireRapportConsultation(): callable
    {
        return $this->checkPermission('RAPPORT_CONSULTATION');
    }

    /**
     * Vérifie si l'utilisateur peut exporter des données
     */
    public function requireExport(): callable
    {
        return $this->checkPermission('EXPORT_DONNEES');
    }

    /**
     * Vérifie si l'utilisateur peut gérer les utilisateurs
     */
    public function requireUtilisateurGestion(): callable
    {
        return $this->checkPermission('UTILISATEUR_GESTION');
    }

    /**
     * Vérifie si l'utilisateur peut gérer les rôles
     */
    public function requireRoleGestion(): callable
    {
        return $this->checkPermission('ROLE_GESTION');
    }

    /**
     * Vérifie si l'utilisateur peut accéder à l'audit
     */
    public function requireAuditAccess(): callable
    {
        return $this->checkPermission('AUDIT_ACCESS');
    }

    /**
     * Vérifie si l'utilisateur peut accéder au journal système
     */
    public function requireJournalSysteme(): callable
    {
        return $this->checkPermission('JOURNAL_SYSTEME');
    }

    /**
     * Vérifie si l'utilisateur peut gérer les paramètres
     */
    public function requireParametresGestion(): callable
    {
        return $this->checkPermission('PARAMETRES_GESTION');
    }

    /**
     * Extrait le token de la requête
     */
    private function getTokenFromRequest($request): ?string
    {
        // Essayer de récupérer depuis l'en-tête Authorization
        $authHeader = $request->server['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }

        // Essayer de récupérer depuis les cookies
        return $_COOKIE['auth_token'] ?? null;
    }

    /**
     * Retourne une réponse non autorisée
     */
    private function unauthorizedResponse($response, string $message)
    {
        $response->header('HTTP/1.1 401 Unauthorized');
        $response->header('Content-Type: application/json');
        $response->header('Access-Control-Allow-Origin: *');
        $response->header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers: Authorization, Content-Type');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }

    /**
     * Vérifie si l'utilisateur a l'une des permissions requises (OU logique)
     */
    public function checkAnyPermission(array $permissions): callable
    {
        return function ($request, $response, $next) use ($permissions) {
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return $this->unauthorizedResponse($response, 'Token manquant');
            }

            $tokenValidation = $this->authService->validateToken($token);
            
            if (!$tokenValidation['valid']) {
                return $this->unauthorizedResponse($response, $tokenValidation['message']);
            }

            // Vérifier si l'utilisateur a au moins une des permissions
            $hasPermission = false;
            foreach ($permissions as $permission) {
                if ($this->authService->checkPermission($tokenValidation['user_id'], $permission)) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                return $this->unauthorizedResponse($response, 'Permission non accordée');
            }

            // Ajouter les informations utilisateur à la requête
            $request->user_id = $tokenValidation['user_id'];
            $request->user_role = $tokenValidation['role'];
            $request->user_permissions = $tokenValidation['permissions'];
            $request->username = $tokenValidation['username'];

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur a toutes les permissions requises (ET logique)
     */
    public function checkAllPermissions(array $permissions): callable
    {
        return function ($request, $response, $next) use ($permissions) {
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return $this->unauthorizedResponse($response, 'Token manquant');
            }

            $tokenValidation = $this->authService->validateToken($token);
            
            if (!$tokenValidation['valid']) {
                return $this->unauthorizedResponse($response, $tokenValidation['message']);
            }

            // Vérifier si l'utilisateur a toutes les permissions
            $hasAllPermissions = true;
            foreach ($permissions as $permission) {
                if (!$this->authService->checkPermission($tokenValidation['user_id'], $permission)) {
                    $hasAllPermissions = false;
                    break;
                }
            }

            if (!$hasAllPermissions) {
                return $this->unauthorizedResponse($response, 'Permissions insuffisantes');
            }

            // Ajouter les informations utilisateur à la requête
            $request->user_id = $tokenValidation['user_id'];
            $request->user_role = $tokenValidation['role'];
            $request->user_permissions = $tokenValidation['permissions'];
            $request->username = $tokenValidation['username'];

            return $next($request, $response);
        };
    }

    /**
     * Vérifie si l'utilisateur est propriétaire de la ressource ou a un rôle autorisé
     */
    public function checkOwnershipOrRole(int $resourceUserId, array $allowedRoles): callable
    {
        return function ($request, $response, $next) use ($resourceUserId, $allowedRoles) {
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return $this->unauthorizedResponse($response, 'Token manquant');
            }

            $tokenValidation = $this->authService->validateToken($token);
            
            if (!$tokenValidation['valid']) {
                return $this->unauthorizedResponse($response, $tokenValidation['message']);
            }

            // Vérifier si l'utilisateur est le propriétaire ou a un rôle autorisé
            $isOwner = $tokenValidation['user_id'] == $resourceUserId;
            $hasAllowedRole = in_array($tokenValidation['role'], $allowedRoles);

            if (!$isOwner && !$hasAllowedRole) {
                return $this->unauthorizedResponse($response, 'Accès non autorisé');
            }

            // Ajouter les informations utilisateur à la requête
            $request->user_id = $tokenValidation['user_id'];
            $request->user_role = $tokenValidation['role'];
            $request->user_permissions = $tokenValidation['permissions'];
            $request->username = $tokenValidation['username'];

            return $next($request, $response);
        };
    }
}
