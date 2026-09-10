<?php

namespace App\Middleware;

use App\Services\AuditService;
use PDO;

class RoleMiddleware
{
    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->auditService = new AuditService($db);
    }

    /**
     * Vérifie les permissions pour le module Ventes
     */
    public function checkVentesPermission(array $user, string $action): bool
    {
        $permissions = [
            'CAISSIER' => ['create', 'read', 'update_own'],
            'PHARMACIEN' => ['create', 'read', 'update_own', 'read_all'],
            'GERANT' => ['create', 'read', 'update', 'delete', 'read_all'],
            'ADMIN' => ['create', 'read', 'update', 'delete', 'read_all', 'manage_all']
        ];

        $userPermissions = $permissions[$user['role']] ?? [];
        
        if (!in_array($action, $userPermissions)) {
            $this->logUnauthorizedAccess($user, 'VENTES', $action);
            return false;
        }

        return true;
    }

    /**
     * Vérifie les permissions pour le module Caisse
     */
    public function checkCaissePermission(array $user, string $action): bool
    {
        $permissions = [
            'CAISSIER' => ['open_session', 'close_session', 'read_own', 'process_sale'],
            'PHARMACIEN' => ['open_session', 'close_session', 'read_own', 'process_sale'],
            'GERANT' => ['open_session', 'close_session', 'read_all', 'process_sale', 'manage_sessions', 'view_reports'],
            'ADMIN' => ['open_session', 'close_session', 'read_all', 'process_sale', 'manage_sessions', 'view_reports', 'force_close']
        ];

        $userPermissions = $permissions[$user['role']] ?? [];
        
        if (!in_array($action, $userPermissions)) {
            $this->logUnauthorizedAccess($user, 'CAISSE', $action);
            return false;
        }

        return true;
    }

    /**
     * Vérifie les permissions pour le module Stock
     */
    public function checkStockPermission(array $user, string $action): bool
    {
        $permissions = [
            'CAISSIER' => ['read'],
            'PHARMACIEN' => ['read', 'update', 'create_movement', 'adjust_stock'],
            'GERANT' => ['read', 'update', 'create_movement', 'adjust_stock', 'manage_inventory', 'view_reports'],
            'ADMIN' => ['read', 'update', 'create_movement', 'adjust_stock', 'manage_inventory', 'view_reports', 'delete_movement']
        ];

        $userPermissions = $permissions[$user['role']] ?? [];
        
        if (!in_array($action, $userPermissions)) {
            $this->logUnauthorizedAccess($user, 'STOCK', $action);
            return false;
        }

        return true;
    }

    /**
     * Vérifie les permissions pour le module Comptabilité
     */
    public function checkComptabilitePermission(array $user, string $action): bool
    {
        $permissions = [
            'CAISSIER' => ['read_own_reports'],
            'PHARMACIEN' => ['read_own_reports'],
            'GERANT' => ['read', 'validate', 'generate_reports', 'view_ledger'],
            'ADMIN' => ['read', 'write', 'validate', 'generate_reports', 'view_ledger', 'manage_accounts']
        ];

        $userPermissions = $permissions[$user['role']] ?? [];
        
        if (!in_array($action, $userPermissions)) {
            $this->logUnauthorizedAccess($user, 'COMPTABILITE', $action);
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur peut modifier une ressource spécifique
     */
    public function canModifyResource(array $user, string $resourceType, int $resourceId): bool
    {
        // Les admins et gérants peuvent tout modifier
        if (in_array($user['role'], ['ADMIN', 'GERANT'])) {
            return true;
        }

        // Vérifier la propriété pour les autres rôles
        switch ($resourceType) {
            case 'vente':
                return $this->canModifyVente($user['id'], $resourceId);
            case 'caisse_session':
                return $this->canModifyCaisseSession($user['id'], $resourceId);
            case 'mouvement_stock':
                return $this->canModifyMouvementStock($user['id'], $resourceId);
            default:
                return false;
        }
    }

    /**
     * Vérifie si l'utilisateur peut modifier une vente
     */
    private function canModifyVente(int $userId, int $venteId): bool
    {
        $sql = "SELECT utilisateur_id, date_vente, statut_vente 
                FROM ventes 
                WHERE id = ? AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vente) {
            return false;
        }

        // Uniquement le propriétaire peut modifier
        if ($vente['utilisateur_id'] != $userId) {
            return false;
        }

        // Vérifier si la vente peut encore être modifiée
        $dateVente = new \DateTime($vente['date_vente']);
        $now = new \DateTime();
        $interval = $dateVente->diff($now);

        // Les ventes de plus de 24h ne peuvent plus être modifiées
        return $interval->days <= 1 && $vente['statut_vente'] !== 'ANNULEE';
    }

    /**
     * Vérifie si l'utilisateur peut modifier une session de caisse
     */
    private function canModifyCaisseSession(int $userId, int $sessionId): bool
    {
        $sql = "SELECT caissier_id, statut_session 
                FROM caisse_sessions 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return false;
        }

        // Uniquement le propriétaire peut modifier sa session
        return $session['caissier_id'] == $userId && $session['statut_session'] === 'OUVERTE';
    }

    /**
     * Vérifie si l'utilisateur peut modifier un mouvement de stock
     */
    private function canModifyMouvementStock(int $userId, int $mouvementId): bool
    {
        $sql = "SELECT utilisateur_id, date_mouvement 
                FROM mouvements_stock 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mouvementId]);
        $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mouvement) {
            return false;
        }

        // Uniquement le propriétaire peut modifier
        if ($mouvement['utilisateur_id'] != $userId) {
            return false;
        }

        // Les mouvements de plus de 24h ne peuvent plus être modifiés
        $dateMouvement = new \DateTime($mouvement['date_mouvement']);
        $now = new \DateTime();
        $interval = $dateMouvement->diff($now);

        return $interval->hours <= 24;
    }

    /**
     * Vérifie les permissions de consultation
     */
    public function canReadResource(array $user, string $resourceType, ?int $resourceId = null): bool
    {
        // Les admins et gérants peuvent tout lire
        if (in_array($user['role'], ['ADMIN', 'GERANT'])) {
            return true;
        }

        // Les pharmaciens peuvent lire plus de ressources
        if ($user['role'] === 'PHARMACIEN') {
            return true; // Pour l'instant, accès complet en lecture
        }

        // Les caissiers peuvent lire leurs propres ressources
        if ($user['role'] === 'CAISSIER' && $resourceId) {
            return $this->canModifyResource($user, $resourceType, $resourceId);
        }

        return false;
    }

    /**
     * Vérifie les permissions pour les rapports
     */
    public function canViewReports(array $user, string $reportType): bool
    {
        $permissions = [
            'CAISSIER' => ['own_sales', 'own_session'],
            'PHARMACIEN' => ['stock', 'sales_summary'],
            'GERANT' => ['all_reports', 'sales', 'stock', 'caisse', 'comptabilite'],
            'ADMIN' => ['all_reports', 'system', 'audit', 'sales', 'stock', 'caisse', 'comptabilite']
        ];

        return in_array($reportType, $permissions[$user['role']] ?? []);
    }

    /**
     * Vérifie si l'utilisateur peut effectuer une action sensible
     */
    public function canPerformSensitiveAction(array $user, string $action): bool
    {
        $sensitiveActions = [
            'delete_data' => ['ADMIN'],
            'modify_accounts' => ['ADMIN'],
            'change_permissions' => ['ADMIN'],
            'validate_compta' => ['GERANT', 'ADMIN'],
            'force_close_session' => ['GERANT', 'ADMIN'],
            'adjust_stock_value' => ['GERANT', 'ADMIN'],
            'export_sensitive_data' => ['GERANT', 'ADMIN']
        ];

        return in_array($user['role'], $sensitiveActions[$action] ?? []);
    }

    /**
     * Enregistre une tentative d'accès non autorisée
     */
    private function logUnauthorizedAccess(array $user, string $module, string $action): void
    {
        $this->auditService->logAction(
            $user['id'] ?? null,
            'UNAUTHORIZED_ACCESS',
            $module,
            null,
            null,
            [
                'user_role' => $user['role'] ?? 'unknown',
                'requested_action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'requested_url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]
        );
    }

    /**
     * Middleware pour les routes API
     */
    public function apiCheckPermission(array $user, string $module, string $action): array
    {
        $allowed = match($module) {
            'ventes' => $this->checkVentesPermission($user, $action),
            'caisse' => $this->checkCaissePermission($user, $action),
            'stock' => $this->checkStockPermission($user, $action),
            'comptabilite' => $this->checkComptabilitePermission($user, $action),
            default => false
        };

        return [
            'allowed' => $allowed,
            'message' => $allowed ? 'Autorisé' : 'Accès non autorisé'
        ];
    }

    /**
     * Vérifie les permissions basées sur le temps
     */
    public function checkTimeBasedPermissions(array $user, string $action): bool
    {
        // Certaines actions ne sont possibles que pendant les heures de travail
        $timeRestrictedActions = [
            'close_session',
            'validate_compta',
            'force_close_session'
        ];

        if (!in_array($action, $timeRestrictedActions)) {
            return true;
        }

        $heure = (int) date('H');
        $jourSemaine = (int) date('w'); // 0 = Dimanche, 6 = Samedi

        // Hôpital ouvert de 7h à 20h du lundi au samedi
        if ($jourSemaine === 0 || $heure < 7 || $heure > 20) {
            // Les admins peuvent contourner cette restriction
            return $user['role'] === 'ADMIN';
        }

        return true;
    }

    /**
     * Vérifie les permissions basées sur la localisation
     */
    public function checkLocationBasedPermissions(array $user, string $action): bool
    {
        // Vérifier si l'utilisateur est sur une IP autorisée
        $allowedIPs = [
            '127.0.0.1', // Localhost
            '192.168.1.0/24', // Réseau local
            // Ajouter d'autres IP autorisées
        ];

        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Les actions sensibles nécessitent une IP autorisée
        $sensitiveActions = [
            'delete_data',
            'modify_accounts',
            'export_sensitive_data'
        ];

        if (in_array($action, $sensitiveActions)) {
            return $this->isIPAllowed($userIP, $allowedIPs) || $user['role'] === 'ADMIN';
        }

        return true;
    }

    /**
     * Vérifie si une IP est autorisée
     */
    private function isIPAllowed(string $userIP, array $allowedIPs): bool
    {
        foreach ($allowedIPs as $allowedIP) {
            if (strpos($allowedIP, '/') !== false) {
                // CIDR notation
                if ($this->ipInCIDR($userIP, $allowedIP)) {
                    return true;
                }
            } else {
                // IP exacte
                if ($userIP === $allowedIP) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Vérifie si une IP est dans un réseau CIDR
     */
    private function ipInCIDR(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        $subnet = ip2long($subnet);
        $ip = ip2long($ip);
        $mask = -1 << (32 - $mask);
        
        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * Applique toutes les vérifications de permissions
     */
    public function checkAllPermissions(array $user, string $module, string $action, ?int $resourceId = null): array
    {
        $checks = [
            'role_permission' => $this->checkModulePermission($user, $module, $action),
            'resource_ownership' => $resourceId ? $this->canModifyResource($user, $module, $resourceId) : true,
            'time_based' => $this->checkTimeBasedPermissions($user, $action),
            'location_based' => $this->checkLocationBasedPermissions($user, $action),
            'sensitive_action' => $this->canPerformSensitiveAction($user, $action)
        ];

        $allAllowed = array_reduce($checks, fn($carry, $allowed) => $carry && $allowed, true);

        return [
            'allowed' => $allAllowed,
            'checks' => $checks,
            'message' => $allAllowed ? 'Autorisé' : 'Accès non autorisé'
        ];
    }

    /**
     * Vérifie la permission pour un module spécifique
     */
    private function checkModulePermission(array $user, string $module, string $action): bool
    {
        return match($module) {
            'ventes' => $this->checkVentesPermission($user, $action),
            'caisse' => $this->checkCaissePermission($user, $action),
            'stock' => $this->checkStockPermission($user, $action),
            'comptabilite' => $this->checkComptabilitePermission($user, $action),
            default => false
        };
    }
}
