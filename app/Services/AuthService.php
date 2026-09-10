<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;

class AuthService
{
    private PDO $db;
    private string $secretKey;
    private int $tokenExpiration = 3600; // 1 heure

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->secretKey = $_ENV['JWT_SECRET'] ?? 'votre_secret_key_tres_securisee';
    }

    /**
     * Authentifie un utilisateur
     */
    public function authenticate(string $username, string $password): array
    {
        try {
            // Récupérer l'utilisateur avec son rôle
            $sql = "SELECT u.*, r.code as role_code, r.libelle as role_libelle, r.permissions as role_permissions
                    FROM utilisateurs u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.username = ? AND u.is_actif = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Identifiant ou mot de passe incorrect',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }

            // Vérifier le mot de passe
            if (!password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Identifiant ou mot de passe incorrect',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }

            // Vérifier si le compte est bloqué
            if ($user['is_locked']) {
                return [
                    'success' => false,
                    'message' => 'Compte bloqué. Veuillez contacter l\'administrateur.',
                    'code' => 'ACCOUNT_LOCKED'
                ];
            }

            // Générer le token JWT
            $token = $this->generateToken($user);

            // Mettre à jour la dernière connexion
            $this->updateLastLogin($user['id']);

            return [
                'success' => true,
                'message' => 'Authentification réussie',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nom' => $user['nom'],
                    'email' => $user['email'],
                    'role' => [
                        'code' => $user['role_code'],
                        'libelle' => $user['role_libelle'],
                        'permissions' => json_decode($user['role_permissions'], true)
                    ],
                    'last_login' => $user['last_login']
                ],
                'expires_in' => $this->tokenExpiration
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données',
                'code' => 'DATABASE_ERROR',
                'error' => $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur système',
                'code' => 'SYSTEM_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Valide un token JWT
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            
            // Vérifier si le token n'est pas expiré
            if ($decoded->exp < time()) {
                return [
                    'valid' => false,
                    'message' => 'Token expiré',
                    'code' => 'TOKEN_EXPIRED'
                ];
            }

            // Vérifier si l'utilisateur est toujours actif
            $sql = "SELECT is_actif, is_locked FROM utilisateurs WHERE id = ? AND is_actif = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$decoded->sub]);
            $userStatus = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userStatus || $userStatus['is_locked']) {
                return [
                    'valid' => false,
                    'message' => 'Compte désactivé ou bloqué',
                    'code' => 'ACCOUNT_INACTIVE'
                ];
            }

            return [
                'valid' => true,
                'user_id' => $decoded->sub,
                'username' => $decoded->username,
                'role' => $decoded->role,
                'permissions' => $decoded->permissions,
                'expires_at' => $decoded->exp
            ];

        } catch (ExpiredException $e) {
            return [
                'valid' => false,
                'message' => 'Token expiré',
                'code' => 'TOKEN_EXPIRED'
            ];
        } catch (BeforeValidException $e) {
            return [
                'valid' => false,
                'message' => 'Token invalide',
                'code' => 'TOKEN_INVALID'
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Erreur de validation du token',
                'code' => 'VALIDATION_ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère un token JWT
     */
    private function generateToken(array $user): string
    {
        $payload = [
            'iss' => 'pharmacie_erp', // Émetteur
            'aud' => 'pharmacie_users', // Audience
            'iat' => time(), // Heure d'émission
            'exp' => time() + $this->tokenExpiration, // Expiration
            'sub' => $user['id'], // Subject (ID utilisateur)
            'username' => $user['username'],
            'role' => $user['role_code'],
            'permissions' => json_decode($user['role_permissions'], true)
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    /**
     * Rafraîchit un token
     */
    public function refreshToken(string $token): array
    {
        $validation = $this->validateToken($token);
        
        if (!$validation['valid']) {
            return $validation;
        }

        // Récupérer les informations utilisateur à jour
        $sql = "SELECT u.*, r.code as role_code, r.libelle as role_libelle, r.permissions as role_permissions
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$validation['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Utilisateur non trouvé',
                'code' => 'USER_NOT_FOUND'
            ];
        }

        // Générer un nouveau token
        $newToken = $this->generateToken($user);

        return [
            'success' => true,
            'message' => 'Token rafraîchi avec succès',
            'token' => $newToken,
            'expires_in' => $this->tokenExpiration
        ];
    }

    /**
     * Déconnecte un utilisateur
     */
    public function logout(string $token): array
    {
        try {
            $validation = $this->validateToken($token);
            
            if ($validation['valid']) {
                // Logger la déconnexion
                $this->logActivity($validation['user_id'], 'LOGOUT', 'Déconnexion utilisateur');
                
                // Ajouter le token à la liste noire (optionnel)
                $this->blacklistToken($token);
            }

            return [
                'success' => true,
                'message' => 'Déconnexion réussie'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie les permissions d'un utilisateur
     */
    public function checkPermission(int $userId, string $permission): bool
    {
        $sql = "SELECT r.permissions 
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchColumn();

        if (!$permissions) {
            return false;
        }

        $userPermissions = json_decode($permissions, true);
        return in_array($permission, $userPermissions);
    }

    /**
     * Vérifie si un utilisateur a un rôle spécifique
     */
    public function hasRole(int $userId, string $roleCode): bool
    {
        $sql = "SELECT r.code 
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $userRole = $stmt->fetchColumn();

        return $userRole === $roleCode;
    }

    /**
     * Met à jour la dernière connexion
     */
    private function updateLastLogin(int $userId): void
    {
        $sql = "UPDATE utilisateurs SET 
                    last_login = NOW(),
                    login_count = login_count + 1
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    /**
     * Ajoute un token à la liste noire
     */
    private function blacklistToken(string $token): void
    {
        $sql = "INSERT INTO token_blacklist (token, created_at) VALUES (?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
    }

    /**
     * Vérifie si un token est dans la liste noire
     */
    public function isTokenBlacklisted(string $token): bool
    {
        $sql = "SELECT COUNT(*) as count FROM token_blacklist WHERE token = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Change le mot de passe
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        try {
            // Vérifier le mot de passe actuel
            $sql = "SELECT password_hash FROM utilisateurs WHERE id = ? AND is_actif = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect',
                    'code' => 'INVALID_CURRENT_PASSWORD'
                ];
            }

            // Valider le nouveau mot de passe
            $passwordValidation = $this->validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                return $passwordValidation;
            }

            // Hasher et mettre à jour le nouveau mot de passe
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE utilisateurs SET 
                        password_hash = ?,
                        password_changed_at = NOW(),
                        must_change_password = 0
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$newPasswordHash, $userId]);

            // Logger le changement
            $this->logActivity($userId, 'PASSWORD_CHANGE', 'Changement mot de passe');

            return [
                'success' => true,
                'message' => 'Mot de passe changé avec succès'
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
     * Valide un mot de passe
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        // Longueur minimale
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        }

        // Contient au moins une majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule';
        }

        // Contient au moins une minuscule
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule';
        }

        // Contient au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre';
        }

        // Contient au moins un caractère spécial
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"|,.<>?]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Bloque un compte utilisateur
     */
    public function lockAccount(int $userId, string $reason, int $adminId): array
    {
        try {
            $sql = "UPDATE utilisateurs SET 
                        is_locked = 1,
                        lock_reason = ?,
                        locked_at = NOW(),
                        locked_by = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$reason, $adminId, $userId]);

            // Logger le blocage
            $this->logActivity($userId, 'ACCOUNT_LOCKED', "Compte bloqué: $reason");

            return [
                'success' => true,
                'message' => 'Compte bloqué avec succès'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du blocage du compte',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Débloque un compte utilisateur
     */
    public function unlockAccount(int $userId, int $adminId): array
    {
        try {
            $sql = "UPDATE utilisateurs SET 
                        is_locked = 0,
                        lock_reason = NULL,
                        locked_at = NULL,
                        locked_by = NULL,
                        unlocked_at = NOW(),
                        unlocked_by = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $userId]);

            // Logger le déblocage
            $this->logActivity($userId, 'ACCOUNT_UNLOCKED', 'Compte débloqué');

            return [
                'success' => true,
                'message' => 'Compte débloqué avec succès'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du déblocage du compte',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enregistre une activité d'authentification
     */
    private function logActivity(int $userId, string $action, string $description): void
    {
        $sql = "INSERT INTO auth_logs (user_id, action, description, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Récupère l'historique des connexions
     */
    public function getLoginHistory(int $userId, int $limit = 50): array
    {
        $sql = "SELECT al.*, u.username
                FROM auth_logs al
                JOIN utilisateurs u ON al.user_id = u.id
                WHERE al.user_id = ?
                ORDER BY al.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Nettoie les tokens expirés
     */
    public function cleanupExpiredTokens(): int
    {
        $sql = "DELETE FROM token_blacklist WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}
