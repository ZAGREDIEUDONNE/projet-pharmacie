<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class RoleService
{
    private PDO $db;
    private array $roles = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadRoles();
    }

    /**
     * Charge tous les rôles en cache
     */
    private function loadRoles(): void
    {
        $sql = "SELECT * FROM roles ORDER BY nom";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $this->roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les rôles
     */
    public function getAllRoles(): array
    {
        return $this->roles;
    }

    /**
     * Récupère un rôle par son code
     */
    public function getRoleByCode(string $code): ?array
    {
        $sql = "SELECT * FROM roles WHERE code = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$code]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère un rôle par son ID
     */
    public function getRoleById(int $id): ?array
    {
        $sql = "SELECT * FROM roles WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crée un nouveau rôle
     */
    public function createRole(array $data): int
    {
        $this->db->beginTransaction();
        
        try {
            // Validation des données
            $this->validateRoleData($data);
            
            // Vérifier si le code existe déjà
            if ($this->roleExists($data['code'])) {
                throw new Exception("Le code de rôle '{$data['code']}' existe déjà");
            }
            
            $sql = "INSERT INTO roles (code, libelle, description, permissions, is_actif, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['code'],
                $data['libelle'],
                $data['description'] ?? null,
                json_encode($data['permissions'] ?? []),
                $data['is_actif'] ?? true
            ]);
            
            $roleId = $this->db->lastInsertId();
            $this->db->commit();
            
            return $roleId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la création du rôle: " . $e->getMessage());
        }
    }

    /**
     * Met à jour un rôle
     */
    public function updateRole(int $id, array $data): bool
    {
        $this->db->beginTransaction();
        
        try {
            // Vérifier si le rôle existe
            $role = $this->getRoleById($id);
            if (!$role) {
                throw new Exception("Rôle non trouvé");
            }
            
            // Validation des données
            $this->validateRoleData($data, $id);
            
            // Vérifier si le nouveau code n'est pas déjà utilisé
            if (isset($data['code']) && $data['code'] !== $role['code']) {
                if ($this->roleExists($data['code'], $id)) {
                    throw new Exception("Le code de rôle '{$data['code']}' existe déjà");
                }
            }
            
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['id', 'created_at'])) {
                    continue;
                }
                
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $id;
            $sql = "UPDATE roles SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            $this->db->commit();
            
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la mise à jour du rôle: " . $e->getMessage());
        }
    }

    /**
     * Désactive un rôle
     */
    public function deactivateRole(int $id): bool
    {
        $sql = "UPDATE roles SET is_actif = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Active un rôle
     */
    public function activateRole(int $id): bool
    {
        $sql = "UPDATE roles SET is_actif = 1, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Supprime un rôle
     */
    public function deleteRole(int $id): bool
    {
        $this->db->beginTransaction();
        
        try {
            // Vérifier si des utilisateurs sont liés à ce rôle
            $sql = "SELECT COUNT(*) as count FROM utilisateurs WHERE role_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $count = (int) $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("Impossible de supprimer ce rôle: $count utilisateur(s) y sont lié(s)");
            }
            
            // Supprimer le rôle
            $sql = "DELETE FROM roles WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            $this->db->commit();
            
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la suppression du rôle: " . $e->getMessage());
        }
    }

    /**
     * Récupère les permissions d'un rôle
     */
    public function getRolePermissions(int $roleId): array
    {
        $sql = "SELECT permissions FROM roles WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        
        $permissions = $stmt->fetchColumn();
        
        return $permissions ? json_decode($permissions, true) : [];
    }

    /**
     * Ajoute une permission à un rôle
     */
    public function addPermissionToRole(int $roleId, string $permission): bool
    {
        $this->db->beginTransaction();
        
        try {
            $role = $this->getRoleById($roleId);
            if (!$role) {
                throw new Exception("Rôle non trouvé");
            }
            
            $permissions = $this->getRolePermissions($roleId);
            
            if (!in_array($permission, $permissions)) {
                $permissions[] = $permission;
                $newPermissions = json_encode($permissions);
                
                $sql = "UPDATE roles SET permissions = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$newPermissions, $roleId]);
                
                $this->db->commit();
                return $result;
            }
            
            return true; // La permission existe déjà
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de l'ajout de la permission: " . $e->getMessage());
        }
    }

    /**
     * Retire une permission d'un rôle
     */
    public function removePermissionFromRole(int $roleId, string $permission): bool
    {
        $this->db->beginTransaction();
        
        try {
            $role = $this->getRoleById($roleId);
            if (!$role) {
                throw new Exception("Rôle non trouvé");
            }
            
            $permissions = $this->getRolePermissions($roleId);
            
            $key = array_search($permission, $permissions);
            if ($key !== false) {
                unset($permissions[$key]);
                $newPermissions = json_encode(array_values($permissions));
                
                $sql = "UPDATE roles SET permissions = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$newPermissions, $roleId]);
                
                $this->db->commit();
                return $result;
            }
            
            return true; // La permission n'existe pas
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors du retrait de la permission: " . $e->getMessage());
        }
    }

    /**
     * Récupère les rôles actifs
     */
    public function getActiveRoles(): array
    {
        $sql = "SELECT * FROM roles WHERE is_actif = 1 ORDER BY code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les permissions disponibles
     */
    public function getAvailablePermissions(): array
    {
        return [
            // Permissions de base
            'DASHBOARD_ACCESS' => 'Accès au tableau de bord',
            'VENTE_GESTION' => 'Gestion des ventes',
            'VENTE_ANNULATION' => 'Annulation des ventes',
            'VENTE_CORRECTION' => 'Correction des ventes',
            'STOCK_GESTION' => 'Gestion du stock',
            'STOCK_AJUSTEMENT' => 'Ajustement du stock',
            'CLIENT_GESTION' => 'Gestion des clients',
            'CLIENT_CREATION' => 'Création des clients',
            'CLIENT_MODIFICATION' => 'Modification des clients',
            'FOURNISSEUR_GESTION' => 'Gestion des fournisseurs',
            'COMMANDE_GESTION' => 'Gestion des commandes',
            'COMMANDE_CREATION' => 'Création des commandes',
            'COMMANDE_VALIDATION' => 'Validation des commandes',
            'CAISSE_GESTION' => 'Gestion de la caisse',
            'CAISSE_OUVERTURE' => 'Ouverture de la caisse',
            'CAISSE_FERMETURE' => 'Fermeture de la caisse',
            'CAISSE_MOUVEMENT' => 'Mouvements de caisse',
            'COMPTABILITE_GESTION' => 'Gestion de la comptabilité',
            'COMPTABILITE_ECRITURE' => 'Écriture comptable',
            'COMPTABILITE_VALIDATION' => 'Validation comptable',
            'COMPTABILITE_RAPPORT' => 'Rapports comptables',
            'UTILISATEUR_GESTION' => 'Gestion des utilisateurs',
            'UTILISATEUR_CREATION' => 'Création des utilisateurs',
            'UTILISATEUR_MODIFICATION' => 'Modification des utilisateurs',
            'UTILISATEUR_SUPPRESSION' => 'Suppression des utilisateurs',
            'ROLE_GESTION' => 'Gestion des rôles',
            'ROLE_CREATION' => 'Création des rôles',
            'ROLE_MODIFICATION' => 'Modification des rôles',
            'ROLE_SUPPRESSION' => 'Suppression des rôles',
            'PARAMETRE_GESTION' => 'Gestion des paramètres',
            'EXPORT_DONNEES' => 'Export des données',
            'AUDIT_ACCESS' => 'Accès aux logs d\'audit',
            'JOURNAL_SYSTEME' => 'Accès au journal système',
            'CAISSE_ACCESS' => 'Accès caisse',
            'RAPPORT_CONSULTATION' => 'Consultation des rapports'
        ];
    }

    /**
     * Initialise les rôles par défaut
     */
    public function initializeDefaultRoles(): array
    {
        $this->db->beginTransaction();
        
        try {
            $defaultRoles = [
                [
                    'code' => 'ADMIN',
                    'libelle' => 'Administrateur',
                    'description' => 'Accès complet au système',
                    'permissions' => [
                        'DASHBOARD_ACCESS',
                        'VENTE_GESTION',
                        'VENTE_ANNULATION',
                        'VENTE_CORRECTION',
                        'STOCK_GESTION',
                        'STOCK_AJUSTEMENT',
                        'CLIENT_GESTION',
                        'CLIENT_CREATION',
                        'CLIENT_MODIFICATION',
                        'FOURNISSEUR_GESTION',
                        'COMMANDE_GESTION',
                        'COMMANDE_CREATION',
                        'COMMANDE_VALIDATION',
                        'CAISSE_GESTION',
                        'CAISSE_OUVERTURE',
                        'CAISSE_FERMETURE',
                        'CAISSE_MOUVEMENT',
                        'COMPTABILITE_GESTION',
                        'COMPTABILITE_ECRITURE',
                        'COMPTABILITE_VALIDATION',
                        'COMPTABILITE_RAPPORT',
                        'UTILISATEUR_GESTION',
                        'UTILISATEUR_CREATION',
                        'UTILISATEUR_MODIFICATION',
                        'UTILISATEUR_SUPPRESSION',
                        'ROLE_GESTION',
                        'ROLE_CREATION',
                        'ROLE_MODIFICATION',
                        'ROLE_SUPPRESSION',
                        'PARAMETRE_GESTION',
                        'EXPORT_DONNEES',
                        'AUDIT_ACCESS',
                        'JOURNAL_SYSTEME',
                        'CAISSE_ACCESS',
                        'RAPPORT_CONSULTATION'
                    ]
                ],
                [
                    'code' => 'VENDEUR',
                    'libelle' => 'Vendeur',
                    'description' => 'Accès aux ventes et caisse',
                    'permissions' => [
                        'DASHBOARD_ACCESS',
                        'VENTE_GESTION',
                        'VENTE_ANNULATION',
                        'VENTE_CORRECTION',
                        'STOCK_GESTION',
                        'CLIENT_GESTION',
                        'CLIENT_CREATION',
                        'CLIENT_MODIFICATION',
                        'COMMANDE_GESTION',
                        'COMMANDE_CREATION',
                        'CAISSE_GESTION',
                        'CAISSE_OUVERTURE',
                        'CAISSE_FERMETURE',
                        'CAISSE_MOUVEMENT',
                        'COMPTABILITE_GESTION',
                        'COMPTABILITE_ECRITURE',
                        'RAPPORT_CONSULTATION',
                        'CAISSE_ACCESS'
                    ]
                ],
                [
                    'code' => 'CHARGE_COMMANDE',
                    'libelle' => 'Chargé de commande',
                    'description' => 'Gestion des commandes fournisseurs',
                    'permissions' => [
                        'DASHBOARD_ACCESS',
                        'STOCK_GESTION',
                        'FOURNISSEUR_GESTION',
                        'COMMANDE_GESTION',
                        'COMMANDE_CREATION',
                        'COMMANDE_VALIDATION',
                        'COMPTABILITE_GESTION',
                        'COMPTABILITE_ECRITURE',
                        'RAPPORT_CONSULTATION'
                    ]
                ],
                [
                    'code' => 'ASSISTANT',
                    'libelle' => 'Assistant',
                    'description' => 'Vente avancee, stock et actions metier',
                    'permissions' => [
                        'DASHBOARD_ACCESS',
                        'VENTE_GESTION',
                        'VENTE_ANNULATION',
                        'STOCK_GESTION',
                        'STOCK_AJUSTEMENT',
                        'CLIENT_GESTION',
                        'CLIENT_CREATION',
                        'CLIENT_MODIFICATION',
                        'CAISSE_GESTION',
                        'CAISSE_OUVERTURE',
                        'CAISSE_FERMETURE',
                        'CAISSE_MOUVEMENT',
                        'COMPTABILITE_GESTION',
                        'COMPTABILITE_ECRITURE',
                        'RAPPORT_CONSULTATION',
                        'CAISSE_ACCESS'
                    ]
                ],
            ];

            $createdRoles = [];
            foreach ($defaultRoles as $role) {
                if (!$this->roleExists($role['code'])) {
                    $sql = "INSERT INTO roles (code, libelle, description, permissions, is_actif, created_at) 
                                VALUES (?, ?, ?, ?, ?, NOW())";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $role['code'],
                        $role['libelle'],
                        $role['description'],
                        json_encode($role['permissions']),
                        true
                    ]);
                    $createdRoles[] = $role['code'];
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Rôles par défaut initialisés',
                'created_roles' => $createdRoles,
                'total_created' => count($createdRoles)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de l'initialisation des rôles: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si un rôle existe
     */
    private function roleExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM roles WHERE code = ?";
        $params = [$code];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Valide les données d'un rôle
     */
    private function validateRoleData(array $data, ?int $excludeId = null): void
    {
        $requiredFields = ['code', 'libelle'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Le champ '$field' est obligatoire");
            }
        }
        
        // Validation du format du code
        if (!preg_match('/^[A-Z0-9_]+$/', $data['code'])) {
            throw new Exception("Le code du rôle doit contenir uniquement des lettres majuscules, chiffres et underscores");
        }
        
        // Validation des permissions
        if (isset($data['permissions'])) {
            if (!is_array($data['permissions'])) {
                throw new Exception("Les permissions doivent être un tableau");
            }
            
            $availablePermissions = $this->getAvailablePermissions();
            foreach ($data['permissions'] as $permission) {
                if (!in_array($permission, array_keys($availablePermissions))) {
                    throw new Exception("Permission non valide: $permission");
                }
            }
        }
    }

    /**
     * Récupère les statistiques des rôles
     */
    public function getRoleStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_roles,
                    COUNT(CASE WHEN is_actif = 1 THEN 1 END) as active_roles,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as roles_created_last_30_days,
                    COUNT(CASE WHEN updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as roles_updated_last_30_days
                FROM roles";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les utilisateurs par rôle
     */
    public function getUsersByRole(int $roleId): array
    {
        $sql = "SELECT u.id, u.username, u.nom, u.email, u.is_actif, u.last_login
                FROM utilisateurs u
                WHERE u.role_id = ?
                ORDER BY u.username";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte les rôles
     */
    public function exportRoles(): array
    {
        $roles = $this->getAllRoles();
        
        $exportData = [];
        
        // En-tête
        $exportData[] = [
            'ID' => 'ID',
            'Code' => 'Code',
            'Libellé' => 'Libellé',
            'Description' => 'Description',
            'Permissions' => 'Permissions',
            'Actif' => 'Actif',
            'Date Création' => 'Date Création',
            'Date Mise à jour' => 'Date Mise à jour'
        ];
        
        // Données
        foreach ($roles as $role) {
            $permissions = json_decode($role['permissions'], true);
            $permissionsList = implode(', ', $permissions);
            
            $exportData[] = [
                'ID' => $role['id'],
                'Code' => $role['code'],
                'Libellé' => $role['libelle'],
                'Description' => $role['description'],
                'Permissions' => $permissionsList,
                'Actif' => $role['is_actif'] ? 'Oui' : 'Non',
                'Date Création' => $role['created_at'],
                'Date Mise à jour' => $role['updated_at']
            ];
        }
        
        return $exportData;
    }
}
