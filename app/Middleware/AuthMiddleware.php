<?php

namespace App\Middleware;

use App\Services\AuditService;
use PDO;

class AuthMiddleware
{
    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->auditService = new AuditService($db);
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function requireAuth(): ?array
    {
        session_start();
        
        if (!$this->isAuthenticated()) {
            $this->redirectLogin();
            return null;
        }
        
        return $this->getCurrentUser();
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function requireRole(string $roleRequis): ?array
    {
        $user = $this->requireAuth();
        
        if (!$user) {
            return null;
        }
        
        if (!$this->hasRole($user, $roleRequis)) {
            $this->denyAccess("Rôle '$roleRequis' requis");
            return null;
        }
        
        return $user;
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique
     */
    public function requirePermission(string $permission): ?array
    {
        $user = $this->requireAuth();
        
        if (!$user) {
            return null;
        }
        
        if (!$this->hasPermission($user, $permission)) {
            $this->denyAccess("Permission '$permission' requise");
            return null;
        }
        
        return $user;
    }

    /**
     * Vérifie si l'utilisateur peut accéder à une ressource spécifique
     */
    public function requireOwnership(int $resourceId, string $resourceType): ?array
    {
        $user = $this->requireAuth();
        
        if (!$user) {
            return null;
        }
        
        // Admin peut tout voir
        if ($user['role'] === 'ADMIN') {
            return $user;
        }
        
        // Vérifier si l'utilisateur est propriétaire de la ressource
        if (!$this->isOwner($user, $resourceId, $resourceType)) {
            $this->denyAccess("Accès non autorisé à cette ressource");
            return null;
        }
        
        return $user;
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    private function isAuthenticated(): bool
    {
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

        return $userId > 0 && $this->validateSession($userId);
    }

    /**
     * Valide la session en base de données
     */
    private function validateSession(int $userId): bool
    {
        $sql = "SELECT id, is_active FROM utilisateurs WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['is_active'];
    }

    /**
     * Récupère l'utilisateur courant
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $sql = "SELECT u.*, r.nom as role_nom 
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)($_SESSION['user']['id'] ?? $_SESSION['user_id'])]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['role'] = strtoupper((string)$user['role_nom']);
            // Ajouter les permissions de l'utilisateur
            $user['permissions'] = $this->getUserPermissions($user['id']);
        }
        
        return $user;
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    private function hasRole(array $user, string $roleRequis): bool
    {
        $hierarchieRoles = [
            'VENDEUR' => 1,
            'ASSISTANT' => 2,
            'CHARGE_COMMANDE' => 3,
            'COMMANDE' => 3,
            'ADMIN' => 4,
            'ADMINISTRATEUR' => 4,
        ];
        
        $roleUser = $hierarchieRoles[strtoupper((string)($user['role'] ?? ''))] ?? 0;
        $roleRequisNiveau = $hierarchieRoles[strtoupper($roleRequis)] ?? 0;
        
        return $roleUser >= $roleRequisNiveau;
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique
     */
    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? []);
    }

    /**
     * Récupère les permissions d'un utilisateur
     */
    private function getUserPermissions(int $userId): array
    {
        $sql = "SELECT p.nom 
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN utilisateurs u ON rp.role_id = u.role_id
                WHERE u.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'une ressource
     */
    private function isOwner(array $user, int $resourceId, string $resourceType): bool
    {
        switch ($resourceType) {
            case 'vente':
                return $this->isVenteOwner($user['id'], $resourceId);
            case 'caisse_session':
                return $this->isCaisseSessionOwner($user['id'], $resourceId);
            case 'mouvement_stock':
                return $this->isMouvementStockOwner($user['id'], $resourceId);
            default:
                return false;
        }
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'une vente
     */
    private function isVenteOwner(int $userId, int $venteId): bool
    {
        $sql = "SELECT utilisateur_id FROM ventes WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $vente && $vente['utilisateur_id'] == $userId;
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'une session de caisse
     */
    private function isCaisseSessionOwner(int $userId, int $sessionId): bool
    {
        $sql = "SELECT caissier_id FROM caisse_sessions WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $session && $session['caissier_id'] == $userId;
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'un mouvement de stock
     */
    private function isMouvementStockOwner(int $userId, int $mouvementId): bool
    {
        $sql = "SELECT utilisateur_id FROM mouvements_stock WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mouvementId]);
        
        $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $mouvement && $mouvement['utilisateur_id'] == $userId;
    }

    /**
     * Redirige vers la page de login
     */
    private function redirectLogin(): void
    {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login');
        exit;
    }

    /**
     * Refuse l'accès avec une erreur 403
     */
    private function denyAccess(string $message = 'Accès non autorisé'): void
    {
        http_response_code(403);
        
        // Logger la tentative d'accès non autorisée
        $this->auditService->logAction(
            $_SESSION['user_id'] ?? null,
            'UNAUTHORIZED_ACCESS',
            'system',
            null,
            null,
            [
                'message' => $message,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'requested_url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]
        );
        
        // Afficher une page d'erreur
        $this->showErrorPage(403, $message);
    }

    /**
     * Affiche une page d'erreur
     */
    private function showErrorPage(int $code, string $message): void
    {
        http_response_code($code);
        
        echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur ' . $code . ' - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="text-red-600 text-6xl mb-4">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Erreur ' . $code . '</h1>
            <p class="text-gray-600 mb-6">' . htmlspecialchars($message) . '</p>
            <div class="space-y-3">
                <a href="/dashboard" class="block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                    <i class="fas fa-home mr-2"></i>Retour au tableau de bord
                </a>
                <a href="/login" class="block bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>
            </div>
        </div>
    </div>
</body>
</html>';
        exit;
    }

    /**
     * Middleware pour les routes API
     */
    public function apiAuth(): ?array
    {
        // Vérifier l'authentification via token ou session
        $token = $this->getApiToken();
        
        if ($token) {
            return $this->validateApiToken($token);
        }
        
        // Fallback sur session
        return $this->requireAuth();
    }

    /**
     * Récupère le token d'API
     */
    private function getApiToken(): ?string
    {
        // Header Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Paramètre GET
        return $_GET['api_token'] ?? null;
    }

    /**
     * Valide un token d'API
     */
    private function validateApiToken(string $token): ?array
    {
        // Implémentation de validation de token API
        // Pour l'instant, retourne null (non implémenté)
        return null;
    }

    /**
     * Vérifie le timeout de session
     */
    public function checkSessionTimeout(): void
    {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return;
        }
        
        $timeout = 30 * 60; // 30 minutes
        
        if (time() - $_SESSION['last_activity'] > $timeout) {
            $this->logout('Session expirée');
            return;
        }
        
        $_SESSION['last_activity'] = time();
    }

    /**
     * Déconnexion
     */
    public function logout(string $reason = 'Déconnexion manuelle'): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        // Logger la déconnexion
        if ($userId) {
            $this->auditService->logAction(
                $userId,
                'LOGOUT',
                'utilisateurs',
                $userId,
                null,
                ['reason' => $reason]
            );
        }
        
        session_destroy();
        
        header('Location: /login');
        exit;
    }
}
