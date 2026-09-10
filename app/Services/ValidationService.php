<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Service de validation et protection API
 */
class ValidationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Valide les données d'entrée avec protection contre injection
     */
    public function validateInput(array $data, array $rules): array
    {
        $errors = [];
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // Validation requise
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['message'] ?? "Le champ {$field} est requis";
                continue;
            }

            // Validation de type
            if (isset($rule['type']) && $value !== null) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "L'adresse email n'est pas valide";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Le champ {$field} doit être numérique";
                        }
                        break;
                    case 'integer':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = "Le champ {$field} doit être un entier";
                        }
                        break;
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field] = "Le champ {$field} doit être une chaîne";
                        }
                        break;
                    case 'date':
                        if (!$this->validateDate($value)) {
                            $errors[$field] = "La date n'est pas valide";
                        }
                        break;
                }
            }

            // Validation de longueur
            if (isset($rule['min_length']) && strlen($value ?? '') < $rule['min_length']) {
                $errors[$field] = "Le champ {$field} doit contenir au moins {$rule['min_length']} caractères";
            }

            if (isset($rule['max_length']) && strlen($value ?? '') > $rule['max_length']) {
                $errors[$field] = "Le champ {$field} ne doit pas dépasser {$rule['max_length']} caractères";
            }

            // Validation de plage
            if (isset($rule['min']) && is_numeric($value) && (float)$value < $rule['min']) {
                $errors[$field] = "Le champ {$field} doit être supérieur ou égal à {$rule['min']}";
            }

            if (isset($rule['max']) && is_numeric($value) && (float)$value > $rule['max']) {
                $errors[$field] = "Le champ {$field} doit être inférieur ou égal à {$rule['max']}";
            }

            // Sanitisation
            $sanitized[$field] = $this->sanitizeInput($value);
        }

        return [
            'success' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized
        ];
    }

    /**
     * Valide un token CSRF
     */
    public function validateCSRFToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        // Récupérer le token stocké en session
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        // Vérifier le token
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Génère un token CSRF
     */
    public function generateCSRFToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Protège contre les attaques par force brute
     */
    public function checkRateLimit(string $identifier, string $action, int $maxAttempts = 5, int $timeWindow = 300): bool
    {
        $sql = "SELECT COUNT(*) as attempts 
                FROM rate_limits 
                WHERE identifier = :identifier 
                AND action = :action 
                AND created_at > DATE_SUB(NOW(), INTERVAL :time_window SECOND)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'identifier' => $identifier,
            'action' => $action,
            'time_window' => $timeWindow
        ]);
        
        $attempts = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];
        
        if ($attempts >= $maxAttempts) {
            // Journaliser la tentative de blocage
            $this->logRateLimitViolation($identifier, $action, $attempts);
            return false;
        }

        // Nettoyer les anciennes tentatives
        $this->cleanupRateLimits();
        
        // Enregistrer la tentative actuelle
        $this->recordRateLimitAttempt($identifier, $action);
        
        return true;
    }

    /**
     * Valide une date
     */
    private function validateDate(string $date): bool
    {
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Protège contre les injections SQL
     */
    private function sanitizeInput($input): string
    {
        if ($input === null) {
            return '';
        }

        // Supprimer les caractères dangereux
        $input = preg_replace('/[<>"\']/', '', $input);
        
        // Échapper les caractères spéciaux SQL
        $input = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $input);
        
        return trim($input);
    }

    /**
     * Journalise une violation de rate limit
     */
    private function logRateLimitViolation(string $identifier, string $action, int $attempts): void
    {
        $sql = "INSERT INTO security_logs (type, identifier, action, details, ip_address, date_action)
                    VALUES ('RATE_LIMIT_VIOLATION', :identifier, :action, :details, :ip, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'identifier' => $identifier,
            'action' => $action,
            'details' => "Trop de tentatives: {$attempts}",
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * Enregistre une tentative de rate limit
     */
    private function recordRateLimitAttempt(string $identifier, string $action): void
    {
        $sql = "INSERT INTO rate_limits (identifier, action, created_at)
                    VALUES (:identifier, :action, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'identifier' => $identifier,
            'action' => $action
        ]);
    }

    /**
     * Nettoie les anciennes entrées de rate limit
     */
    private function cleanupRateLimits(): void
    {
        $sql = "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $this->db->exec($sql);
    }

    /**
     * Valide un mot de passe
     */
    public function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une majuscule";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une minuscule";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre";
        }
        
        if (!preg_match('/[!@#$%^&*()_+=\-\[\]{}|;:,.<>?]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Valide un nom d'utilisateur
     */
    public function validateUsername(string $username): array
    {
        $errors = [];
        
        if (strlen($username) < 3) {
            $errors[] = "Le nom d'utilisateur doit contenir au moins 3 caractères";
        }
        
        if (strlen($username) > 50) {
            $errors[] = "Le nom d'utilisateur ne doit pas dépasser 50 caractères";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et underscores";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
