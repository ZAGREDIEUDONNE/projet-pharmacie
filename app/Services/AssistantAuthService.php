<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class AssistantAuthService
{
    private PDO $db;
    private RolePermissionService $rolePermissionService;

    public function __construct(PDO $db, RolePermissionService $rolePermissionService)
    {
        $this->db = $db;
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Génère les codes d'accès pour un assistant
     */
    public function generateAssistantCodes(int $userId): array
    {
        if (!$this->rolePermissionService->isAssistant($userId)) {
            return [
                'success' => false,
                'message' => 'L\'utilisateur n\'est pas un assistant'
            ];
        }

        try {
            // Code 1 : Accès caisse
            $codeCaisse = $this->generateRandomCode();
            
            // Code 2 : Accès avancé
            $codeAvance = $this->generateRandomCode();

            $this->db->beginTransaction();

            // Supprimer les anciens codes
            $sql = "DELETE FROM assistant_auth_codes WHERE utilisateur_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            // Insérer le code caisse (type 1)
            $sql = "INSERT INTO assistant_auth_codes 
                    (utilisateur_id, code_type, code_secret, expires_at, max_usage) 
                    VALUES (?, 'CAISSE', ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 1000)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $codeCaisse]);

            // Insérer le code avancé (type 2)
            $sql = "INSERT INTO assistant_auth_codes 
                    (utilisateur_id, code_type, code_secret, expires_at, max_usage) 
                    VALUES (?, 'AVANCE', ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 500)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $codeAvance]);

            // Mettre à jour l'utilisateur
            $sql = "UPDATE utilisateurs SET assistant_code = ?, last_auth_code = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$codeCaisse, $userId]);

            $this->db->commit();

            // Logger l'opération
            $this->logAuthOperation($userId, 'CODES_GENERES', [
                'code_caisse' => $codeCaisse,
                'code_avance' => $codeAvance
            ]);

            return [
                'success' => true,
                'code_caisse' => $codeCaisse,
                'code_avance' => $codeAvance,
                'message' => 'Codes générés avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération des codes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie un code d'accès assistant
     */
    public function verifyAssistantCode(int $userId, string $code, string $type): array
    {
        try {
            $codeType = $this->resolveCodeType($type);

            $sql = "SELECT * FROM assistant_auth_codes 
                    WHERE utilisateur_id = ? AND code_type = ? AND code_secret = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $code, $codeType]);
            $authCode = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$authCode) {
                $this->logAuthOperation($userId, 'CODE_ECHEC', [
                    'code' => $code,
                    'type' => $type,
                    'raison' => 'Code invalide ou inactif'
                ]);

                return [
                    'success' => false,
                    'message' => 'Code d\'accès invalide'
                ];
            }

            // Vérifier l'expiration
            if ($authCode['expires_at'] && new \DateTime($authCode['expires_at']) < new \DateTime()) {
                $this->logAuthOperation($userId, 'CODE_ECHEC', [
                    'code' => $code,
                    'type' => $type,
                    'raison' => 'Code expiré'
                ]);

                return [
                    'success' => false,
                    'message' => 'Code d\'accès expiré'
                ];
            }

            // Vérifier l'utilisation maximale
            if ($authCode['usage_count'] >= $authCode['max_usage']) {
                $this->logAuthOperation($userId, 'CODE_ECHEC', [
                    'code' => $code,
                    'type' => $type,
                    'raison' => 'Utilisation maximale atteinte'
                ]);

                return [
                    'success' => false,
                    'message' => 'Code d\'accès épuisé'
                ];
            }

            // Mettre à jour l'utilisation
            $sql = "UPDATE assistant_auth_codes 
                    SET usage_count = usage_count + 1, last_used = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$authCode['id']]);

            // Mettre à jour la dernière auth de l'utilisateur
            $sql = "UPDATE utilisateurs SET last_auth_code = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            // Logger l'opération
            $this->logAuthOperation($userId, 'CODE_VALIDE', [
                'code' => $code,
                'type' => $type,
                'usage_count' => $authCode['usage_count'] + 1
            ]);

            return [
                'success' => true,
                'message' => 'Code validé avec succès',
                'access_level' => $type,
                'usage_count' => $authCode['usage_count'] + 1,
                'max_usage' => $authCode['max_usage']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification du code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les informations d'accès d'un assistant
     */
    public function getAssistantAuthInfo(int $userId): array
    {
        try {
            $sql = "SELECT 
                        u.assistant_code,
                        u.last_auth_code,
                        ac.code_type,
                        ac.code_secret,
                        ac.is_active,
                        ac.expires_at,
                        ac.usage_count,
                        ac.max_usage,
                        ac.last_used
                    FROM utilisateurs u
                    LEFT JOIN assistant_auth_codes ac ON u.id = ac.utilisateur_id
                    WHERE u.id = ? AND u.is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $authInfo = [];
            foreach ($results as $result) {
                $authInfo[$result['code_type']] = $result;
            }

            return [
                'success' => true,
                'data' => $authInfo
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des infos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Désactive un code d'accès
     */
    public function deactivateCode(int $userId, string $type): array
    {
        try {
            $codeType = $this->resolveCodeType($type);

            $sql = "UPDATE assistant_auth_codes 
                    SET is_active = 0 
                    WHERE utilisateur_id = ? AND code_type = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$userId, $codeType]);

            if ($result) {
                $this->logAuthOperation($userId, 'CODE_DESACTIVE', [
                    'type' => $type
                ]);

                return [
                    'success' => true,
                    'message' => 'Code désactivé avec succès'
                ];
            }

            return [
                'success' => false,
                'message' => 'Code non trouvé ou déjà désactivé'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la désactivation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Réinitialise les codes d'un assistant
     */
    public function resetAssistantCodes(int $userId): array
    {
        if (!$this->rolePermissionService->isAssistant($userId)) {
            return [
                'success' => false,
                'message' => 'L\'utilisateur n\'est pas un assistant'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Désactiver tous les codes
            $sql = "UPDATE assistant_auth_codes SET is_active = 0 WHERE utilisateur_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            // Réinitialiser le code utilisateur
            $sql = "UPDATE utilisateurs SET assistant_code = NULL, last_auth_code = NULL WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            $this->db->commit();

            $this->logAuthOperation($userId, 'CODES_RESET', []);

            return [
                'success' => true,
                'message' => 'Codes réinitialisés avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Génère un code aléatoire sécurisé
     */
    private function generateRandomCode(int $length = 6): string
    {
        $chars = '0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $code;
    }

    private function resolveCodeType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return in_array($normalized, ['caisse', 'vente', 'vente_comptoir', 'comptoir'], true)
            ? 'CAISSE'
            : 'AVANCE';
    }

    /**
     * Logger les opérations d'authentification
     */
    private function logAuthOperation(int $userId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs 
                    (utilisateur_id, action, table_name, new_values, ip_address, user_agent) 
                    VALUES (?, 'ASSISTANT_AUTH', 'assistant_auth_codes', ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            // Silencieux pour éviter les boucles d'erreur
            error_log("Erreur log auth operation: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si un assistant peut accéder à une fonction avancée
     */
    public function canAccessAdvanced(int $userId, string $code): bool
    {
        $verification = $this->verifyAssistantCode($userId, $code, 'avance');
        
        if ($verification['success']) {
            // Vérifier la permission spécifique
            return $this->rolePermissionService->hasPermission($userId, 'assistant_acces_avance');
        }
        
        return false;
    }

    /**
     * Récupère l'historique des utilisations de codes
     */
    public function getCodeUsageHistory(int $userId, ?string $type = null): array
    {
        try {
            $sql = "SELECT 
                        al.action,
                        al.new_values,
                        al.date_action,
                        al.ip_address
                    FROM audit_logs al
                    WHERE al.utilisateur_id = ? 
                    AND al.action LIKE 'ASSISTANT_AUTH_%'";
            
            $params = [$userId];
            
            if ($type) {
                $sql .= " AND al.new_values LIKE ?";
                $params[] = "%\"type\":\"$type\"%";
            }
            
            $sql .= " ORDER BY al.date_action DESC LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Statistiques d'utilisation des codes
     */
    public function getCodeStatistics(int $userId): array
    {
        try {
            $sql = "SELECT 
                        code_type,
                        usage_count,
                        max_usage,
                        last_used,
                        expires_at,
                        is_active
                    FROM assistant_auth_codes
                    WHERE utilisateur_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            
            $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'codes_actifs' => 0,
                'codes_expires' => 0,
                'utilisation_totale' => 0,
                'par_type' => []
            ];
            
            foreach ($codes as $code) {
                if ($code['is_active']) {
                    $stats['codes_actifs']++;
                }
                
                if ($code['expires_at'] && new \DateTime($code['expires_at']) < new \DateTime()) {
                    $stats['codes_expires']++;
                }
                
                $stats['utilisation_totale'] += $code['usage_count'];
                $stats['par_type'][$code['code_type']] = $code;
            }
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
}
