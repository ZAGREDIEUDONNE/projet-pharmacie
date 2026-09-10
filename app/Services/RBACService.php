<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Service RBAC (Role Based Access Control) centralisé
 */
class RBACService
{
    private PDO $db;
    private array $userPermissions = [];
    private array $rolePermissions = [];
    private array $permissions = [];
    private ?bool $userPermissionsTableAvailable = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadPermissions();
    }

    /**
     * Charge toutes les permissions en cache depuis la base réelle
     */
    private function loadPermissions(): void
    {
        $sql = "SELECT * FROM permissions";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $permission) {
            $this->permissions[$permission['nom']] = $permission;
        }
    }

    /**
     * Vérifie si un utilisateur a une permission spécifique
     * Fonction centrale du système RBAC connectée à la base réelle
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $userRole = $this->getUserRole($userId);

        // Vérifier si la permission existe dans la base
        if (!isset($this->permissions[$permission])) {
            return false;
        }
        
        // Vérifier les permissions de rôle
        if ($this->hasRolePermission($userRole, $permission)) {
            return true;
        }

        // Vérifier les permissions spécifiques à l'utilisateur
        if ($this->hasUserPermission($userId, $permission)) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si un utilisateur a une permission spécifique avec audit
     */
    public function hasPermissionWithAudit(int $userId, string $permission, string $route = ''): bool
    {
        $hasPermission = $this->hasPermission($userId, $permission);

        if (!$hasPermission) {
            $this->logAccessDenied($userId, $permission, $route);
        }

        return $hasPermission;
    }

    /**
     * Récupère le rôle d'un utilisateur
     */
    public function getUserRole(int $userId): string
    {
        $sql = "SELECT UPPER(r.nom) as code FROM utilisateurs u JOIN roles r ON u.role_id = r.id WHERE u.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['code'] ?? 'GUEST';
    }

    /**
     * Vérifie si un rôle a une permission
     */
    private function hasRolePermission(string $roleCode, string $permission): bool
    {
        if (!isset($this->rolePermissions[$roleCode])) {
            $this->loadRolePermissions($roleCode);
        }

        return in_array($permission, $this->rolePermissions[$roleCode]);
    }

    /**
     * Charge les permissions d'un rôle depuis la base de données réelle
     */
    private function loadRolePermissions(string $roleCode): void
    {
        $sql = "SELECT p.nom as code 
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                JOIN roles r ON rp.role_id = r.id
                WHERE UPPER(r.nom) = :role_code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role_code' => $roleCode]);
        
        $this->rolePermissions[$roleCode] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');
    }

    /**
     * Vérifie si un utilisateur a une permission spécifique
     */
    private function hasUserPermission(int $userId, string $permission): bool
    {
        if (!isset($this->userPermissions[$userId])) {
            $this->loadUserPermissions($userId);
        }

        return in_array($permission, $this->userPermissions[$userId]);
    }

    /**
     * Charge les permissions spécifiques d'un utilisateur depuis la base réelle
     */
    private function loadUserPermissions(int $userId): void
    {
        if (!$this->hasUserPermissionsTable()) {
            $this->userPermissions[$userId] = [];
            return;
        }

        try {
            $sql = "SELECT p.nom as code 
                    FROM utilisateur_permissions up
                    JOIN permissions p ON up.permission_id = p.id
                    WHERE up.utilisateur_id = :user_id
                    AND up.statut = 1
                    AND up.date_suppression IS NULL";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            $this->userPermissions[$userId] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');
        } catch (Exception $e) {
            error_log("RBACService::loadUserPermissions - " . $e->getMessage());
            $this->userPermissions[$userId] = [];
        }
    }

    /** The real schema may rely exclusively on role_permissions. */
    private function hasUserPermissionsTable(): bool
    {
        if ($this->userPermissionsTableAvailable !== null) {
            return $this->userPermissionsTableAvailable;
        }

        $stmt = $this->db->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'utilisateur_permissions' LIMIT 1"
        );
        return $this->userPermissionsTableAvailable = (bool)$stmt->fetchColumn();
    }

    /**
     * Récupère toutes les permissions d'un utilisateur
     */
    public function getUserPermissions(int $userId): array
    {
        $userRole = $this->getUserRole($userId);
        
        $permissions = [];

        // Permissions de rôle
        if (!isset($this->rolePermissions[$userRole])) {
            $this->loadRolePermissions($userRole);
        }
        $permissions = array_merge($permissions, $this->rolePermissions[$userRole]);

        // Permissions spécifiques à l'utilisateur
        if (!isset($this->userPermissions[$userId])) {
            $this->loadUserPermissions($userId);
        }
        $permissions = array_merge($permissions, $this->userPermissions[$userId]);

        return array_unique($permissions);
    }

    /**
     * Ajoute une permission à un utilisateur
     */
    public function addUserPermission(int $userId, string $permission): bool
    {
        try {
            if (!isset($this->permissions[$permission])) {
                return false;
            }

            $sql = "INSERT IGNORE INTO utilisateur_permissions (utilisateur_id, permission_id, statut, date_attribution)
                    VALUES (:user_id, :permission_id, 1, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'permission_id' => $this->permissions[$permission]['id']
            ]);

            // Vider le cache
            unset($this->userPermissions[$userId]);
            
            return true;

        } catch (Exception $e) {
            error_log("RBACService::addUserPermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retire une permission à un utilisateur
     */
    public function removeUserPermission(int $userId, string $permission): bool
    {
        try {
            if (!isset($this->permissions[$permission])) {
                return false;
            }

            $sql = "UPDATE utilisateur_permissions
                    SET statut = 0, date_suppression = NOW()
                    WHERE utilisateur_id = :user_id AND permission_id = :permission_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'permission_id' => $this->permissions[$permission]['id']
            ]);

            // Vider le cache
            unset($this->userPermissions[$userId]);
            
            return true;

        } catch (Exception $e) {
            error_log("RBACService::removeUserPermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ajoute une permission à un rôle
     */
    public function addRolePermission(string $roleCode, string $permission): bool
    {
        try {
            if (!isset($this->permissions[$permission])) {
                return false;
            }

            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id, statut)
                    SELECT r.id, :permission_id, 'ACTIF'
                    FROM roles r 
                    WHERE r.code = :role_code";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'role_code' => $roleCode,
                'permission_id' => $this->permissions[$permission]['id']
            ]);

            // Vider le cache
            unset($this->rolePermissions[$roleCode]);
            
            return true;

        } catch (Exception $e) {
            error_log("RBACService::addRolePermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retire une permission à un rôle
     */
    public function removeRolePermission(string $roleCode, string $permission): bool
    {
        try {
            if (!isset($this->permissions[$permission])) {
                return false;
            }

            $sql = "UPDATE role_permissions rp
                    JOIN roles r ON rp.role_id = r.id
                    SET rp.statut = 'INACTIF'
                    WHERE r.code = :role_code AND rp.permission_id = :permission_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'role_code' => $roleCode,
                'permission_id' => $this->permissions[$permission]['id']
            ]);

            // Vider le cache
            unset($this->rolePermissions[$roleCode]);
            
            return true;

        } catch (Exception $e) {
            error_log("RBACService::removeRolePermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les permissions disponibles
     */
    public function getAllPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Récupère les permissions par module
     */
    public function getPermissionsByModule(string $module): array
    {
        return array_filter($this->permissions, function($permission) use ($module) {
            return $permission['module'] === $module;
        });
    }

    /**
     * Récupère les modules disponibles
     */
    public function getModules(): array
    {
        $modules = array_unique(array_column($this->permissions, 'module'));
        sort($modules);
        return $modules;
    }

    /**
     * Journalise un accès refusé
     */
    private function logAccessDenied(int $userId, string $permission, string $route): void
    {
        try {
            $sql = "INSERT INTO access_denied_audit (user_id, permission_code, route, ip_address, user_agent)
                    VALUES (:user_id, :permission_code, :route, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId ?: null,
                'permission_code' => $permission,
                'route' => $route,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

        } catch (Exception $e) {
            error_log("RBACService::logAccessDenied - " . $e->getMessage());
        }
    }

    /**
     * Récupère les logs d'accès refusés
     */
    public function getAccessDeniedLogs(int $limit = 100): array
    {
        $sql = "SELECT ada.*, u.username 
                FROM access_denied_audit ada
                LEFT JOIN utilisateurs u ON ada.user_id = u.id
                ORDER BY ada.date_refus DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vide les caches
     */
    private function isAdminRole(string $roleCode): bool
    {
        return in_array(strtoupper($roleCode), ['ADMIN', 'ADMINISTRATEUR'], true);
    }

    public function clearCache(): void
    {
        $this->userPermissions = [];
        $this->rolePermissions = [];
        $this->loadPermissions();
    }
}
