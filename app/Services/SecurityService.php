<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class SecurityService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Protège contre les injections SQL
     */
    public function sanitizeInput(string $input): string
    {
        // Supprimer les caractères dangereux
        $input = preg_replace('/[<>"\']/', '', $input);
        
        // Échapper les caractères spéciaux SQL
        $input = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $input);
        
        return trim($input);
    }

    /**
     * Valide une adresse email
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valide un numéro de téléphone
     */
    public function validatePhone(string $phone): bool
    {
        // Format: +225 XX XX XX XX XX ou 0X XX XX XX XX
        $pattern = '/^(?:\+225|0)[\s-]?([0-9]{2})[\s-]?([0-9]{2})[\s-]?([0-9]{2})[\s-]?([0-9]{2})$/';
        return preg_match($pattern, $phone);
    }

    /**
     * Génère un token sécurisé aléatoire
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Vérifie la force d'un mot de passe
     */
    public function checkPasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];

        // Longueur
        if (strlen($password) >= 8) {
            $score += 1;
        } else {
            $feedback[] = 'Le mot de passe doit contenir au moins 8 caractères';
        }

        if (strlen($password) >= 12) {
            $score += 2;
        }

        // Complexité
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Le mot de passe doit contenir des lettres minuscules';
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Le mot de passe doit contenir des lettres majuscules';
        }

        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Le mot de passe doit contenir des chiffres';
        }

        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};:"|,.<>?]/', $password)) {
            $score += 2;
        } else {
            $feedback[] = 'Le mot de passe doit contenir des caractères spéciaux';
        }

        // Bonus pour la complexité
        if (preg_match('/[a-z].*[A-Z].*[0-9].*[!@#$%^&*()_+\-=\[\]{};:"|,.<>?]/', $password)) {
            $score += 1;
        }

        // Pénalités pour les mots de passe faibles
        $weakPatterns = [
            '/123/i', '/password/i', '/admin/i', '/qwerty/i',
            '/abc/i', '/111/i', '/000/i', '/login/i'
        ];

        foreach ($weakPatterns as $pattern) {
            if (preg_match($pattern, $password)) {
                $score -= 2;
                $feedback[] = 'Le mot de passe contient des motifs trop communs';
                break;
            }
        }

        $strength = 'FAIBLE';
        if ($score >= 6) $strength = 'MOYEN';
        if ($score >= 8) $strength = 'FORT';
        if ($score >= 10) $strength = 'TRÈS FORT';

        return [
            'score' => max(0, $score),
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }

    /**
     * Détecte les tentatives d'attaque
     */
    public function detectAttackPatterns(string $input): array
    {
        $patterns = [
            'sql_injection' => [
                'pattern' => '/(union|select|insert|update|delete|drop|create|alter|exec|script|javascript|vbscript|onload|onerror)/i',
                'risk' => 'HIGH'
            ],
            'xss' => [
                'pattern' => '/(<script|<iframe|<object|<embed|javascript:|vbscript:|onload=|onerror=|onclick=)/i',
                'risk' => 'HIGH'
            ],
            'path_traversal' => [
                'pattern' => '/(\.\.\/|\.\.\\|\/etc\/|\/proc\/|\/usr\/|\/var\/)/i',
                'risk' => 'MEDIUM'
            ],
            'command_injection' => [
                'pattern' => '/(;|\||&|`|\$|\$\(|\$\{)/i',
                'risk' => 'HIGH'
            ],
            'ldap_injection' => [
                'pattern' => '/(\*\)|\(\()|\(=|\)=\*|\*=\()/i',
                'risk' => 'MEDIUM'
            ]
        ];

        $detected = [];
        foreach ($patterns as $type => $data) {
            if (preg_match($data['pattern'], $input)) {
                $detected[] = [
                    'type' => $type,
                    'risk' => $data['risk'],
                    'pattern' => $data['pattern']
                ];
            }
        }

        return $detected;
    }

    /**
     * Valide une adresse IP
     */
    public function validateIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Vérifie si une adresse IP est dans la liste noire
     */
    public function isIPBlacklisted(string $ip): bool
    {
        $sql = "SELECT COUNT(*) as count FROM ip_blacklist WHERE ip = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ip]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Ajoute une adresse IP à la liste noire
     */
    public function blacklistIP(string $ip, string $reason, int $adminId): bool
    {
        $sql = "INSERT INTO ip_blacklist (ip, reason, created_by, created_at, is_active) 
                VALUES (?, ?, ?, NOW(), 1)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$ip, $reason, $adminId]);
    }

    /**
     * Retire une adresse IP de la liste noire
     */
    public function unblacklistIP(string $ip, int $adminId): bool
    {
        $sql = "UPDATE ip_blacklist SET 
                    is_active = 0, 
                    removed_by = ?, 
                    removed_at = NOW() 
                WHERE ip = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$adminId, $ip]);
    }

    /**
     * Détecte les activités suspectes
     */
    public function detectSuspiciousActivity(int $userId): array
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN action = 'LOGIN_FAILED' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as failed_logins_1h,
                    COUNT(CASE WHEN action = 'LOGIN_FAILED' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as failed_logins_24h,
                    COUNT(CASE WHEN action = 'PERMISSION_DENIED' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as permission_denied_1h,
                    COUNT(CASE WHEN action = 'UNAUTHORIZED_ACCESS' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as unauthorized_1h,
                    COUNT(DISTINCT ip_address) as unique_ips_24h
                FROM audit_logs 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $suspicious = [];
        $riskScore = 0;

        // Analyse des échecs de connexion
        if ($activity['failed_logins_1h'] >= 5) {
            $suspicious[] = 'Tentatives de connexion multiples échouées (1h)';
            $riskScore += 3;
        }

        if ($activity['failed_logins_24h'] >= 10) {
            $suspicious[] = 'Tentatives de connexion multiples échouées (24h)';
            $riskScore += 2;
        }

        // Analyse des accès refusés
        if ($activity['permission_denied_1h'] >= 3) {
            $suspicious[] = 'Accès refusés multiples (1h)';
            $riskScore += 2;
        }

        if ($activity['unauthorized_1h'] >= 2) {
            $suspicious[] = 'Accès non autorisés multiples (1h)';
            $riskScore += 3;
        }

        // Analyse des adresses IP multiples
        if ($activity['unique_ips_24h'] >= 5) {
            $suspicious[] = 'Connexions depuis adresses IP multiples (24h)';
            $riskScore += 1;
        }

        $riskLevel = 'FAIBLE';
        if ($riskScore >= 4) $riskLevel = 'MOYEN';
        if ($riskScore >= 6) $riskLevel = 'ÉLEVÉ';
        if ($riskScore >= 8) $riskLevel = 'CRITIQUE';

        return [
            'suspicious' => !empty($suspicious),
            'activities' => $suspicious,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'recommendations' => $this->generateSecurityRecommendations($riskScore)
        ];
    }

    /**
     * Génère des recommandations de sécurité
     */
    private function generateSecurityRecommendations(int $riskScore): array
    {
        $recommendations = [];

        if ($riskScore >= 3) {
            $recommendations[] = 'Surveiller les tentatives de connexion';
        }

        if ($riskScore >= 5) {
            $recommendations[] = 'Considérer le blocage temporaire du compte';
        }

        if ($riskScore >= 7) {
            $recommendations[] = 'Exiger une double authentification';
            $recommendations[] = 'Notifier l\'administrateur immédiatement';
        }

        return $recommendations;
    }

    /**
     * Configure les en-têtes de sécurité
     */
    public function setSecurityHeaders(): void
    {
        // Protection contre le clickjacking
        header('X-Frame-Options: DENY');

        // Protection contre le MIME-sniffing
        header('X-Content-Type-Options: nosniff');

        // Protection XSS
        header('X-XSS-Protection: 1; mode=block');

        // Politique de référence
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HSTS (uniquement en HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // CSP de base
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
    }

    /**
     * Valide un nom d'utilisateur
     */
    public function validateUsername(string $username): array
    {
        $errors = [];

        // Longueur
        if (strlen($username) < 3) {
            $errors[] = 'Le nom d\'utilisateur doit contenir au moins 3 caractères';
        }

        if (strlen($username) > 50) {
            $errors[] = 'Le nom d\'utilisateur ne doit pas dépasser 50 caractères';
        }

        // Caractères autorisés
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores';
        }

        // Pas de caractères spéciaux au début ou à la fin
        if (preg_match('/^[-_]|[-_]$/', $username)) {
            $errors[] = 'Le nom d\'utilisateur ne peut pas commencer ou finir par un tiret ou underscore';
        }

        // Mots réservés
        $reservedNames = ['admin', 'root', 'system', 'administrator', 'test', 'demo', 'guest', 'user'];
        if (in_array(strtolower($username), $reservedNames)) {
            $errors[] = 'Ce nom d\'utilisateur est réservé';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Nettoie les données pour éviter les injections
     */
    public function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Génère un hash sécurisé
     */
    public function generateHash(string $data, string $salt = ''): string
    {
        return hash('sha256', $data . $salt);
    }

    /**
     * Vérifie un hash
     */
    public function verifyHash(string $data, string $hash, string $salt = ''): bool
    {
        return hash_equals($hash, $this->generateHash($data, $salt));
    }

    /**
     * Génère un sel aléatoire
     */
    public function generateSalt(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Configure la session de manière sécurisée
     */
    public function configureSecureSession(): void
    {
        // Configuration des cookies de session
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_lifetime', 3600); // 1 heure

        // Configuration de la session
        ini_set('session.gc_maxlifetime', 3600);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);

        // Désactiver la transmission de l'ID de session dans l'URL
        ini_set('session.use_trans_sid', 0);

        // Régénération de l'ID de session
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }

    /**
     * Détruit la session de manière sécurisée
     */
    public function destroySecureSession(): void
    {
        // Vider les données de session
        $_SESSION = [];

        // Détruire le cookie de session
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        // Détruire la session
        session_destroy();
    }

    /**
     * Limite les tentatives de connexion
     */
    public function checkLoginAttempts(string $username): array
    {
        $sql = "SELECT 
                    COUNT(*) as attempts,
                    MAX(created_at) as last_attempt,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 END) as attempts_15min,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as attempts_1h
                FROM login_attempts 
                WHERE username = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username]);
        
        $attempts = $stmt->fetch(PDO::FETCH_ASSOC);

        $blocked = false;
        $blockTime = 0;

        // Bloquer après 5 tentatives en 15 minutes
        if ($attempts['attempts_15min'] >= 5) {
            $blocked = true;
            $blockTime = 900; // 15 minutes
        }

        // Bloquer après 10 tentatives en 1 heure
        if ($attempts['attempts_1h'] >= 10) {
            $blocked = true;
            $blockTime = 3600; // 1 heure
        }

        return [
            'blocked' => $blocked,
            'block_time' => $blockTime,
            'attempts' => $attempts['attempts'],
            'attempts_15min' => $attempts['attempts_15min'],
            'attempts_1h' => $attempts['attempts_1h'],
            'last_attempt' => $attempts['last_attempt']
        ];
    }

    /**
     * Enregistre une tentative de connexion
     */
    public function recordLoginAttempt(string $username, bool $success, string $ip): void
    {
        $sql = "INSERT INTO login_attempts (username, success, ip_address, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username, $success ? 1 : 0, $ip]);

        // Nettoyer les anciennes tentatives
        $this->cleanupOldLoginAttempts();
    }

    /**
     * Nettoie les anciennes tentatives de connexion
     */
    private function cleanupOldLoginAttempts(): void
    {
        $sql = "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
