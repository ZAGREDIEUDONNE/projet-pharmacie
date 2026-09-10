<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class DoubleAccesService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Vérifie le code d'accès caisse
     */
    public function verifierCodeAccesCaisse(int $userId, string $codeCaisse): array
    {
        try {
            // Récupérer l'utilisateur
            $sql = "SELECT u.*, r.code as role_code 
                    FROM utilisateurs u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ? AND u.is_actif = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                    'code' => 'USER_NOT_FOUND'
                ];
            }

            // Vérifier si l'utilisateur a la permission d'accès caisse
            if (!in_array($user['role_code'], ['ADMIN', 'VENDEUR', 'ASSISTANT'], true)) {
                return [
                    'success' => false,
                    'message' => 'Accès caisse non autorisé pour ce rôle',
                    'code' => 'CAISSE_ACCESS_DENIED'
                ];
            }

            // Vérifier le code d'accès caisse
            if (!$this->validerCodeCaisse($user, $codeCaisse)) {
                return [
                    'success' => false,
                    'message' => 'Code d\'accès caisse invalide',
                    'code' => 'INVALID_CAISSE_CODE'
                ];
            }

            // Logger l'accès caisse réussi
            $this->auditService->logAction(
                $userId,
                'CAISSE_ACCESS',
                'CAISSE',
                null,
                null,
                ['code_acces' => $codeCaisse]
            );

            // Marquer l'accès caisse comme actif
            $this->marquerAccesCaisse($userId, true);

            return [
                'success' => true,
                'message' => 'Accès caisse autorisé',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nom' => $user['nom'],
                    'role' => $user['role_code']
                ]
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'code' => 'DATABASE_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie le code d'accès fonctions avancées
     */
    public function verifierCodeAccesAvance(int $userId, string $codeAvance): array
    {
        try {
            // Récupérer l'utilisateur
            $sql = "SELECT u.*, r.code as role_code 
                    FROM utilisateurs u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ? AND u.is_actif = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                    'code' => 'USER_NOT_FOUND'
                ];
            }

            // Vérifier si l'utilisateur a la permission d'accès avancé
            if (!in_array($user['role_code'], ['ADMIN', 'VENDEUR', 'ASSISTANT', 'CHARGE_COMMANDE'], true)) {
                return [
                    'success' => false,
                    'message' => 'Accès avancé non autorisé pour ce rôle',
                    'code' => 'AVANCE_ACCESS_DENIED'
                ];
            }

            // Vérifier le code d'accès avancé
            if (!$this->validerCodeAvance($user, $codeAvance)) {
                return [
                    'success' => false,
                    'message' => 'Code d\'accès avancé invalide',
                    'code' => 'INVALID_AVANCE_CODE'
                ];
            }

            // Logger l'accès avancé réussi
            $this->auditService->logAction(
                $userId,
                'AVANCE_ACCESS',
                'SYSTEM',
                null,
                null,
                ['code_acces' => $codeAvance]
            );

            // Marquer l'accès avancé comme actif
            $this->marquerAccesAvance($userId, true);

            return [
                'success' => true,
                'message' => 'Accès avancé autorisé',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nom' => $user['nom'],
                    'role' => $user['role_code']
                ]
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'code' => 'DATABASE_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie les deux codes d'accès
     */
    public function verifierDoubleAcces(int $userId, string $codeCaisse, string $codeAvance): array
    {
        try {
            $resultatCaisse = $this->verifierCodeAccesCaisse($userId, $codeCaisse);
            $resultatAvance = $this->verifierCodeAccesAvance($userId, $codeAvance);

            // Les deux codes doivent être valides
            if (!$resultatCaisse['success'] || !$resultatAvance['success']) {
                return [
                    'success' => false,
                    'message' => 'Échec de la double authentification',
                    'details' => [
                        'caisse' => $resultatCaisse,
                        'avance' => $resultatAvance
                    ],
                    'code' => 'DOUBLE_ACCESS_FAILED'
                ];
            }

            // Logger la double authentification réussie
            $this->auditService->logAction(
                $userId,
                'DOUBLE_ACCESS_SUCCESS',
                'SYSTEM',
                null,
                null,
                [
                    'code_caisse' => $codeCaisse,
                    'code_avance' => $codeAvance,
                    'timestamp' => time()
                ]
            );

            // Marquer les deux accès comme actifs
            $this->marquerDoubleAcces($userId, true);

            return [
                'success' => true,
                'message' => 'Double authentification réussie',
                'user' => $resultatCaisse['user'],
                'acces' => [
                    'caisse' => true,
                    'avance' => true,
                    'timestamp' => time()
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la double authentification',
                'code' => 'SYSTEM_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère un code d'accès caisse pour un utilisateur
     */
    public function genererCodeAccesCaisse(int $userId, int $adminId): array
    {
        try {
            // Vérifier si l'utilisateur a déjà un code caisse
            $sql = "SELECT id FROM acces_caisse WHERE user_id = ? AND is_actif = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'L\'utilisateur a déjà un code d\'accès caisse actif',
                    'code' => 'CODE_EXISTS'
                ];
            }

            // Générer un code aléatoire de 6 chiffres
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Insérer le code
            $sql = "INSERT INTO acces_caisse (
                        user_id, code, created_by, created_at, is_actif
                    ) VALUES (?, ?, ?, NOW(), 1)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $code, $adminId]);

            // Logger la génération
            $this->auditService->logAction(
                $adminId,
                'CAISSE_CODE_GENERATED',
                'CAISSE',
                $userId,
                null,
                ['code' => $code]
            );

            return [
                'success' => true,
                'message' => 'Code d\'accès caisse généré avec succès',
                'code' => $code
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'code' => 'DATABASE_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère un code d'accès avancé pour un utilisateur
     */
    public function genererCodeAccesAvance(int $userId, int $adminId): array
    {
        try {
            // Vérifier si l'utilisateur a déjà un code avancé
            $sql = "SELECT id FROM acces_avance WHERE user_id = ? AND is_actif = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'L\'utilisateur a déjà un code d\'accès avancé actif',
                    'code' => 'CODE_EXISTS'
                ];
            }

            // Générer un code aléatoire de 8 caractères alphanumériques
            $code = strtoupper(substr(md5($userId . time() . random_bytes(16)), 0, 8));

            // Insérer le code
            $sql = "INSERT INTO acces_avance (
                        user_id, code, created_by, created_at, is_actif
                    ) VALUES (?, ?, ?, NOW(), 1)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $code, $adminId]);

            // Logger la génération
            $this->auditService->logAction(
                $adminId,
                'AVANCE_CODE_GENERATED',
                'SYSTEM',
                $userId,
                null,
                ['code' => $code]
            );

            return [
                'success' => true,
                'message' => 'Code d\'accès avancé généré avec succès',
                'code' => $code
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'code' => 'DATABASE_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Désactive un code d'accès caisse
     */
    public function desactiverCodeAccesCaisse(int $userId, int $adminId): array
    {
        try {
            $sql = "UPDATE acces_caisse SET 
                        is_actif = 0,
                        deactivated_at = NOW(),
                        deactivated_by = ?
                    WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$adminId, $userId]);

            if ($result) {
                $this->auditService->logAction(
                    $adminId,
                    'CAISSE_CODE_DEACTIVATED',
                    'CAISSE',
                    $userId,
                    null,
                    null
                );
            }

            return [
                'success' => $result,
                'message' => $result ? 'Code d\'accès caisse désactivé' : 'Code non trouvé'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Désactive un code d'accès avancé
     */
    public function desactiverCodeAccesAvance(int $userId, int $adminId): array
    {
        try {
            $sql = "UPDATE acces_avance SET 
                        is_actif = 0,
                        deactivated_at = NOW(),
                        deactivated_by = ?
                    WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$adminId, $userId]);

            if ($result) {
                $this->auditService->logAction(
                    $adminId,
                    'AVANCE_CODE_DEACTIVATED',
                    'SYSTEM',
                    $userId,
                    null,
                    null
                );
            }

            return [
                'success' => $result,
                'message' => $result ? 'Code d\'accès avancé désactivé' : 'Code non trouvé'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère l'historique des accès caisse
     */
    public function getHistoriqueAccesCaisse(int $userId, int $limit = 50): array
    {
        $sql = "SELECT 
                    ac.*,
                    u.username as created_by_username
                FROM acces_caisse ac
                LEFT JOIN utilisateurs u ON ac.created_by = u.id
                WHERE ac.user_id = ?
                ORDER BY ac.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'historique des accès avancés
     */
    public function getHistoriqueAccesAvance(int $userId, int $limit = 50): array
    {
        $sql = "SELECT 
                    aa.*,
                    u.username as created_by_username
                FROM acces_avance aa
                LEFT JOIN utilisateurs u ON aa.created_by = u.id
                WHERE aa.user_id = ?
                ORDER BY aa.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un utilisateur a un accès caisse actif
     */
    public function hasAccesCaisse(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM acces_caisse 
                WHERE user_id = ? AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si un utilisateur a un accès avancé actif
     */
    public function hasAccesAvance(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM acces_avance 
                WHERE user_id = ? AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Valide le code d'accès caisse
     */
    private function validerCodeCaisse(array $user, string $code): bool
    {
        $sql = "SELECT code, created_at, expires_at 
                FROM acces_caisse 
                WHERE user_id = ? AND is_actif = 1
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user['id']]);
        $codeData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeData) {
            return false;
        }

        // Vérifier l'expiration (24 heures par défaut)
        if ($codeData['expires_at'] && strtotime($codeData['expires_at']) < time()) {
            return false;
        }

        // Vérifier le code
        return hash_equals($codeData['code'], $code);
    }

    /**
     * Valide le code d'accès avancé
     */
    private function validerCodeAvance(array $user, string $code): bool
    {
        $sql = "SELECT code, created_at, expires_at 
                FROM acces_avance 
                WHERE user_id = ? AND is_actif = 1
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user['id']]);
        $codeData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeData) {
            return false;
        }

        // Vérifier l'expiration (7 jours par défaut)
        if ($codeData['expires_at'] && strtotime($codeData['expires_at']) < time()) {
            return false;
        }

        // Vérifier le code
        return hash_equals($codeData['code'], $code);
    }

    /**
     * Marque l'accès caisse comme actif
     */
    private function marquerAccesCaisse(int $userId, bool $actif): void
    {
        $sql = "UPDATE utilisateurs SET 
                    caisse_access_active = ?,
                    last_caisse_access = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actif ? 1 : 0, $actif ? date('Y-m-d H:i:s') : null, $userId]);
    }

    /**
     * Marque l'accès avancé comme actif
     */
    private function marquerAccesAvance(int $userId, bool $actif): void
    {
        $sql = "UPDATE utilisateurs SET 
                    avance_access_active = ?,
                    last_advance_access = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actif ? 1 : 0, $actif ? date('Y-m-d H:i:s') : null, $userId]);
    }

    /**
     * Marque la double authentification
     */
    private function marquerDoubleAcces(int $userId, bool $actif): void
    {
        $sql = "UPDATE utilisateurs SET 
                    double_access_active = ?,
                    last_double_access = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actif ? 1 : 0, $actif ? date('Y-m-d H:i:s') : null, $userId]);
    }

    /**
     * Nettoie les codes expirés
     */
    public function nettoyerCodesExpirés(): array
    {
        $results = [];
        
        try {
            // Nettoyer les codes caisse expirés
            $sql = "UPDATE acces_caisse SET 
                        is_actif = 0,
                        expired_at = NOW()
                    WHERE is_actif = 1 
                    AND expires_at < NOW()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $results['caisse_expired'] = $stmt->rowCount();

            // Nettoyer les codes avancés expirés
            $sql = "UPDATE acces_avance SET 
                        is_actif = 0,
                        expired_at = NOW()
                    WHERE is_actif = 1 
                    AND expires_at < NOW()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $results['avance_expired'] = $stmt->rowCount();

            $results['total_expired'] = $results['caisse_expired'] + $results['avance_expired'];

            return [
                'success' => true,
                'message' => 'Nettoyage des codes expirés terminé',
                'results' => $results
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du nettoyage',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les statistiques des accès
     */
    public function getStatistiquesAcces(): array
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN ac.is_actif = 1 THEN 1 END) as caisse_codes_actifs,
                    COUNT(CASE WHEN aa.is_actif = 1 THEN 1 END) as avance_codes_actifs,
                    COUNT(CASE WHEN u.caisse_access_active = 1 THEN 1 END) as utilisateurs_acces_caisse,
                    COUNT(CASE WHEN u.avance_access_active = 1 THEN 1 END) as utilisateurs_acces_avance,
                    COUNT(CASE WHEN u.double_access_active = 1 THEN 1 END) as utilisateurs_double_acces,
                    COUNT(CASE WHEN ac.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as caisse_codes_24h,
                    COUNT(CASE WHEN aa.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as avance_codes_24h
                FROM utilisateurs u
                LEFT JOIN acces_caisse ac ON u.id = ac.user_id
                LEFT JOIN acces_avance aa ON u.id = aa.user_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
