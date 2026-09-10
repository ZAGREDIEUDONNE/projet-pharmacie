<?php

namespace App\Services;

use PDO;

/**
 * Service de gestion des permissions fines
 */
class PermissionService
{
    private PDO $db;
    private array $permissions = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadPermissions();
    }

    /**
     * Charge toutes les permissions en cache
     */
    private function loadPermissions(): void
    {
        $sql = "SELECT p.code, p.description, p.module, p.action 
                FROM permissions p 
                WHERE p.statut = 'ACTIF'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $this->permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un utilisateur a une permission spécifique
     */
    public function hasPermission(int $userId, string $permissionCode): bool
    {
        try {
            // Vérifier si l'utilisateur est admin (accès total)
            if ($this->isAdmin($userId)) {
                return true;
            }

            $sql = "SELECT COUNT(*) as count 
                    FROM utilisateur_permissions up
                    JOIN permissions p ON up.permission_id = p.id
                    WHERE up.utilisateur_id = :user_id 
                    AND p.code = :permission_code 
                    AND up.statut = 'ACTIF'
                    AND p.statut = 'ACTIF'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'permission_code' => $permissionCode
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
        } catch (\Exception $e) {
            error_log("PermissionService::hasPermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un utilisateur a une permission sur une action spécifique
     */
    public function canPerformAction(int $userId, string $module, string $action): bool
    {
        $permissionCode = $module . '.' . $action;
        return $this->hasPermission($userId, $permissionCode);
    }

    /**
     * Vérifie si un utilisateur est administrateur
     */
    public function isAdmin(int $userId): bool
    {
        $sql = "SELECT role FROM utilisateurs WHERE id = :id AND statut = 'ACTIF'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user && $user['role'] === 'ADMIN';
    }

    /**
     * Ajoute une permission à un utilisateur
     */
    public function addPermission(int $userId, string $permissionCode): bool
    {
        try {
            $this->db->beginTransaction();

            // Récupérer l'ID de la permission
            $sql = "SELECT id FROM permissions WHERE code = :code AND statut = 'ACTIF'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['code' => $permissionCode]);
            $permission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$permission) {
                $this->db->rollBack();
                return false;
            }

            // Vérifier si la permission existe déjà
            $sql = "SELECT COUNT(*) as count FROM utilisateur_permissions 
                    WHERE utilisateur_id = :user_id AND permission_id = :permission_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'permission_id' => $permission['id']
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $this->db->rollBack();
                return false;
            }

            // Ajouter la permission
            $sql = "INSERT INTO utilisateur_permissions (utilisateur_id, permission_id, statut, date_attribution)
                    VALUES (:user_id, :permission_id, 'ACTIF', NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'permission_id' => $permission['id']
            ]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("PermissionService::addPermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retire une permission à un utilisateur
     */
    public function removePermission(int $userId, string $permissionCode): bool
    {
        try {
            $sql = "UPDATE utilisateur_permissions up
                    JOIN permissions p ON up.permission_id = p.id
                    SET up.statut = 'INACTIF', date_revocation = NOW()
                    WHERE up.utilisateur_id = :user_id 
                    AND p.code = :permission_code";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'permission_code' => $permissionCode
            ]);

        } catch (\Exception $e) {
            error_log("PermissionService::removePermission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les permissions d'un utilisateur
     */
    public function getUserPermissions(int $userId): array
    {
        try {
            if ($this->isAdmin($userId)) {
                // Admin a toutes les permissions
                return array_column($this->permissions, 'code');
            }

            $sql = "SELECT p.code 
                    FROM utilisateur_permissions up
                    JOIN permissions p ON up.permission_id = p.id
                    WHERE up.utilisateur_id = :user_id 
                    AND up.statut = 'ACTIF'
                    AND p.statut = 'ACTIF'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');

        } catch (\Exception $e) {
            error_log("PermissionService::getUserPermissions - " . $e->getMessage());
            return [];
        }
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
     * Crée les permissions par défaut du système
     */
    public function createDefaultPermissions(): void
    {
        $defaultPermissions = [
            // Module Ventes
            ['code' => 'ventes.lister', 'description' => 'Lister les ventes', 'module' => 'ventes', 'action' => 'lister'],
            ['code' => 'ventes.creer', 'description' => 'Créer une vente', 'module' => 'ventes', 'action' => 'creer'],
            ['code' => 'ventes.modifier', 'description' => 'Modifier une vente', 'module' => 'ventes', 'action' => 'modifier'],
            ['code' => 'ventes.supprimer', 'description' => 'Supprimer une vente', 'module' => 'ventes', 'action' => 'supprimer'],
            ['code' => 'ventes.annuler', 'description' => 'Annuler une vente', 'module' => 'ventes', 'action' => 'annuler'],
            
            // Module Caisse
            ['code' => 'caisse.ouvrir', 'description' => 'Ouvrir la caisse', 'module' => 'caisse', 'action' => 'ouvrir'],
            ['code' => 'caisse.fermer', 'description' => 'Fermer la caisse', 'module' => 'caisse', 'action' => 'fermer'],
            ['code' => 'caisse.rapport', 'description' => 'Voir les rapports de caisse', 'module' => 'caisse', 'action' => 'rapport'],
            ['code' => 'caisse.mouvement', 'description' => 'Gérer les mouvements de caisse', 'module' => 'caisse', 'action' => 'mouvement'],
            
            // Module Stock
            ['code' => 'stock.lister', 'description' => 'Lister le stock', 'module' => 'stock', 'action' => 'lister'],
            ['code' => 'stock.ajouter', 'description' => 'Ajouter au stock', 'module' => 'stock', 'action' => 'ajouter'],
            ['code' => 'stock.modifier', 'description' => 'Modifier le stock', 'module' => 'stock', 'action' => 'modifier'],
            ['code' => 'stock.supprimer', 'description' => 'Supprimer du stock', 'module' => 'stock', 'action' => 'supprimer'],
            ['code' => 'stock.inventaire', 'description' => 'Faire un inventaire', 'module' => 'stock', 'action' => 'inventaire'],
            
            // Module Clients
            ['code' => 'clients.lister', 'description' => 'Lister les clients', 'module' => 'clients', 'action' => 'lister'],
            ['code' => 'clients.creer', 'description' => 'Créer un client', 'module' => 'clients', 'action' => 'creer'],
            ['code' => 'clients.modifier', 'description' => 'Modifier un client', 'module' => 'clients', 'action' => 'modifier'],
            ['code' => 'clients.supprimer', 'description' => 'Supprimer un client', 'module' => 'clients', 'action' => 'supprimer'],
            
            // Module Comptabilité
            ['code' => 'compta.lister', 'description' => 'Lister les écritures', 'module' => 'compta', 'action' => 'lister'],
            ['code' => 'compta.creer', 'description' => 'Créer une écriture', 'module' => 'compta', 'action' => 'creer'],
            ['code' => 'compta.modifier', 'description' => 'Modifier une écriture', 'module' => 'compta', 'action' => 'modifier'],
            ['code' => 'compta.supprimer', 'description' => 'Supprimer une écriture', 'module' => 'compta', 'action' => 'supprimer'],
            ['code' => 'compta.rapport', 'description' => 'Voir les rapports', 'module' => 'compta', 'action' => 'rapport'],
            ['code' => 'compta.balance', 'description' => 'Vérifier la balance', 'module' => 'compta', 'action' => 'balance'],
            
            // Module Utilisateurs
            ['code' => 'utilisateurs.lister', 'description' => 'Lister les utilisateurs', 'module' => 'utilisateurs', 'action' => 'lister'],
            ['code' => 'utilisateurs.creer', 'description' => 'Créer un utilisateur', 'module' => 'utilisateurs', 'action' => 'creer'],
            ['code' => 'utilisateurs.modifier', 'description' => 'Modifier un utilisateur', 'module' => 'utilisateurs', 'action' => 'modifier'],
            ['code' => 'utilisateurs.supprimer', 'description' => 'Supprimer un utilisateur', 'module' => 'utilisateurs', 'action' => 'supprimer'],
            ['code' => 'utilisateurs.permissions', 'description' => 'Gérer les permissions', 'module' => 'utilisateurs', 'action' => 'permissions'],
            
            // Module Système
            ['code' => 'systeme.backup', 'description' => 'Faire des sauvegardes', 'module' => 'systeme', 'action' => 'backup'],
            ['code' => 'systeme.restore', 'description' => 'Restaurer les données', 'module' => 'systeme', 'action' => 'restore'],
            ['code' => 'systeme.config', 'description' => 'Configurer le système', 'module' => 'systeme', 'action' => 'config'],
            ['code' => 'systeme.audit', 'description' => 'Voir les audits', 'module' => 'systeme', 'action' => 'audit'],

            // Module Suivi Client
            ['code' => 'suivi_client.view', 'description' => 'Voir le suivi client', 'module' => 'suivi_client', 'action' => 'view'],
            ['code' => 'suivi_client.create', 'description' => 'Creer un element de suivi client', 'module' => 'suivi_client', 'action' => 'create'],
            ['code' => 'suivi_client.update', 'description' => 'Modifier un element de suivi client', 'module' => 'suivi_client', 'action' => 'update'],
            ['code' => 'suivi_client.delete', 'description' => 'Supprimer un element de suivi client', 'module' => 'suivi_client', 'action' => 'delete'],
            ['code' => 'suivi_client.reglement', 'description' => 'Saisir un reglement client', 'module' => 'suivi_client', 'action' => 'reglement'],
            ['code' => 'suivi_client.solde', 'description' => 'Consulter les soldes clients', 'module' => 'suivi_client', 'action' => 'solde'],
            ['code' => 'suivi_client.releve', 'description' => 'Consulter les releves clients', 'module' => 'suivi_client', 'action' => 'releve']
        ];

        foreach ($defaultPermissions as $permission) {
            $sql = "INSERT IGNORE INTO permissions (code, description, module, action, statut, date_creation)
                    VALUES (:code, :description, :module, :action, 'ACTIF', NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($permission);
        }

        // Recharger les permissions
        $this->loadPermissions();
    }
}
