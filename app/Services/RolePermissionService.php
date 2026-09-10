<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class RolePermissionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Vérifie si un utilisateur a accès au module vente
     */
    public function hasVenteAccess(int $userId): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count 
                     FROM utilisateurs u 
                     JOIN role_permissions rp ON u.role_id = rp.role_id 
                     JOIN permissions p ON rp.permission_id = p.id 
                     WHERE u.id = :user_id AND p.code IN ('vente.access', 'vente.create', 'vente.view')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (\Exception $e) {
            error_log("RolePermissionService::hasVenteAccess - " . $e->getMessage());
            return false;
        }
    }
    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * Vérifie si un utilisateur a une permission spécifique
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                JOIN utilisateurs u ON rp.role_id = u.role_id
                WHERE u.id = ? AND p.nom = ? AND u.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $permission]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    /**
     * Vérifie si un utilisateur a un rôle spécifique
     */
    public function hasRole(int $userId, string $role): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND r.nom = ? AND u.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $role]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    /**
     * Récupère toutes les permissions d'un utilisateur
     */
    public function getUserPermissions(int $userId): array
    {
        $sql = "SELECT p.nom, p.description, p.module
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN utilisateurs u ON rp.role_id = u.role_id
                WHERE u.id = ? AND u.is_active = 1
                ORDER BY p.module, p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le rôle d'un utilisateur
     */
    public function getUserRole(int $userId): ?array
    {
        $sql = "SELECT r.id, r.nom, r.description
                FROM roles r
                JOIN utilisateurs u ON r.id = u.role_id
                WHERE u.id = ? AND u.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Vérifie les permissions par action spécifique
     */
    public function checkActionPermission(int $userId, string $action): array
    {
        $permissionMap = [
            'vente' => 'vente_create',
            'session_change' => 'session_change',
            'date_change' => 'date_change',
            'commande_manage' => 'commande_manage',
            'view_stock' => 'view_stock',
            'add_stock' => 'add_stock',
            'receive_products' => 'receive_products',
            'create_supplier_orders' => 'create_supplier_orders',
            'view_stock_movements' => 'view_stock_movements',
            'remise_apply' => 'remise_apply',
            'ticket_annuler' => 'ticket_annuler',
            'vente_corriger' => 'vente_corriger',
            'caisse_arret' => 'caisse_arret',
            'facture_imprimer' => 'facture_imprimer',
            'commande_preparer' => 'commande_preparer',
            'statistiques_view' => 'statistiques_view',
            'stock_consulter' => 'stock_consulter',
            'assistant_acces_avance' => 'assistant_acces_avance',
            'users_manage' => 'users_manage',
            'products_manage' => 'products_manage',
            'stock_manage' => 'stock_manage',
            'caisse_manage' => 'caisse_manage',
            'audit_view' => 'audit_view',
            'system_config' => 'system_config'
        ];

        $permission = $permissionMap[$action] ?? null;
        if (!$permission) {
            return [
                'allowed' => false,
                'message' => 'Action non reconnue',
                'permission' => null
            ];
        }

        $hasPermission = $this->hasPermission($userId, $permission);
        
        return [
            'allowed' => $hasPermission,
            'message' => $hasPermission ? 'Autorisé' : 'Permission refusée',
            'permission' => $permission
        ];
    }

    /**
     * Vérifie les permissions multiples
     */
    public function checkMultiplePermissions(int $userId, array $actions): array
    {
        $results = [];
        foreach ($actions as $action) {
            $results[$action] = $this->checkActionPermission($userId, $action);
        }
        
        return $results;
    }

    /**
     * Récupère la liste des rôles disponibles
     */
    public function getAllRoles(): array
    {
        $sql = "SELECT id, nom, description FROM roles ORDER BY nom";
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les permissions par rôle
     */
    public function getRolePermissions(int $roleId): array
    {
        $sql = "SELECT p.id, p.nom, p.description, p.module
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.module, p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une permission à un rôle
     */
    public function addPermissionToRole(int $roleId, int $permissionId): bool
    {
        try {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$roleId, $permissionId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retire une permission d'un rôle
     */
    public function removePermissionFromRole(int $roleId, int $permissionId): bool
    {
        try {
            $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$roleId, $permissionId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Vérifie si un utilisateur est administrateur
     */
    public function isAdmin(int $userId): bool
    {
        return $this->hasRole($userId, 'administrateur');
    }

    /**
     * Vérifie si un utilisateur est assistant
     */
    public function isAssistant(int $userId): bool
    {
        return $this->hasRole($userId, 'assistant');
    }

    /**
     * Vérifie si un utilisateur est vendeur
     */
    public function isVendeur(int $userId): bool
    {
        return $this->hasRole($userId, 'vendeur');
    }

    /**
     * Vérifie si un utilisateur est chargé de commande
     */
    public function isChargeCommande(int $userId): bool
    {
        return $this->hasRole($userId, 'charge_commande');
    }

    /**
     * Récupère les permissions par module
     */
    public function getPermissionsByModule(int $userId): array
    {
        $permissions = $this->getUserPermissions($userId);
        $byModule = [];

        foreach ($permissions as $permission) {
            $byModule[$permission['module']][] = $permission;
        }

        return $byModule;
    }

    /**
     * Assigne les permissions du module Suivi Client aux rôles par défaut
     */
    public function assignSuiviClientPermissions(): void
    {
        $suiviClientPermissions = [
            'suivi_client.view',
            'suivi_client.create',
            'suivi_client.update',
            'suivi_client.delete',
            'suivi_client.reglement',
            'suivi_client.solde',
            'suivi_client.releve'
        ];

        // Récupérer les IDs des permissions
        $permissionIds = [];
        foreach ($suiviClientPermissions as $code) {
            $sql = "SELECT id FROM permissions WHERE nom = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $permissionIds[$code] = $result['id'];
            }
        }

        // Récupérer les IDs des rôles
        $roleIds = [];
        $sql = "SELECT id, nom FROM roles";
        $stmt = $this->db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $roleIds[strtolower($row['nom'])] = $row['id'];
        }

        // Assigner les permissions selon les rôles
        $assignments = [];

        // Administrateur : toutes les permissions
        if (isset($roleIds['administrateur'])) {
            foreach ($permissionIds as $permId) {
                $assignments[] = ['role_id' => $roleIds['administrateur'], 'permission_id' => $permId];
            }
        }

        // Vendeur : permissions de consultation et saisie
        $vendeurPerms = [
            'suivi_client.view',
            'suivi_client.reglement',
            'suivi_client.solde',
            'suivi_client.releve'
        ];
        if (isset($roleIds['vendeur'])) {
            foreach ($vendeurPerms as $code) {
                if (isset($permissionIds[$code])) {
                    $assignments[] = ['role_id' => $roleIds['vendeur'], 'permission_id' => $permissionIds[$code]];
                }
            }
        }

        // Assistant : mêmes permissions que vendeur
        if (isset($roleIds['assistant'])) {
            foreach ($vendeurPerms as $code) {
                if (isset($permissionIds[$code])) {
                    $assignments[] = ['role_id' => $roleIds['assistant'], 'permission_id' => $permissionIds[$code]];
                }
            }
        }

        // Chargé de commande : permissions de consultation seulement
        $commandePerms = [
            'suivi_client.view',
            'suivi_client.solde',
            'suivi_client.releve'
        ];
        if (isset($roleIds['charge_commande'])) {
            foreach ($commandePerms as $code) {
                if (isset($permissionIds[$code])) {
                    $assignments[] = ['role_id' => $roleIds['charge_commande'], 'permission_id' => $permissionIds[$code]];
                }
            }
        }

        // Insérer les assignments
        foreach ($assignments as $assignment) {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$assignment['role_id'], $assignment['permission_id']]);
        }
    }
}
