<?php

namespace App\Middleware;

use App\Services\SecurityService;
use App\Services\LoggingService;
use Exception;

class SecurityMiddleware
{
    private SecurityService $securityService;
    private LoggingService $loggingService;

    public function __construct(SecurityService $securityService, LoggingService $loggingService)
    {
        $this->securityService = $securityService;
        $this->loggingService = $loggingService;
    }

    /**
     * Middleware principal de sécurité
     */
    public function handle(): callable
    {
        return function ($request, $response, $next) {
            // Configurer les en-têtes de sécurité
            $this->securityService->setSecurityHeaders();

            // Valider l'origine de la requête
            if (!$this->validateOrigin($request)) {
                $this->logSecurityIssue('INVALID_ORIGIN', 'Origine non autorisée', $request);
                return $this->securityErrorResponse($response, 'Origine non autorisée');
            }

            // Détecter les attaques potentielles
            $this->detectAndLogAttacks($request);

            // Valider les entrées
            $this->validateInputs($request);

            // Limiter le taux de requêtes
            if (!$this->checkRateLimit($request)) {
                $this->logSecurityIssue('RATE_LIMIT_EXCEEDED', 'Limite de requêtes dépassée', $request);
                return $this->securityErrorResponse($response, 'Trop de requêtes', 429);
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware pour la validation des entrées
     */
    public function validateInput(): callable
    {
        return function ($request, $response, $next) {
            // Nettoyer toutes les entrées
            $this->sanitizeRequest($request);

            // Valider les paramètres GET
            $this->validateGetParams($request);

            // Valider les paramètres POST
            $this->validatePostParams($request);

            // Valider les fichiers uploadés
            $this->validateUploadedFiles($request);

            return $next($request, $response);
        };
    }

    /**
     * Middleware pour la protection contre les injections SQL
     */
    public function preventSQLInjection(): callable
    {
        return function ($request, $response, $next) {
            $sqlPatterns = [
                '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
                '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
                '/(\b(OR|AND)\s+["\']?\w+["\']?\s*=\s*["\']?\w+["\']?)/i',
                '/(\b(OR|AND)\s+["\']?\w+["\']?\s*LIKE\s*["\'][^"\']*["\']?)/i',
                '/(--|\/\*|\*\/|;|\'|")/',
                '/(\b(LOAD_FILE|INTO\s+OUTFILE|DUMPFILE)\b)/i'
            ];

            $params = array_merge(
                $request->get ?? [],
                $request->post ?? [],
                $request->files ?? []
            );

            foreach ($params as $key => $value) {
                if (is_string($value)) {
                    foreach ($sqlPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $this->logSecurityIssue('SQL_INJECTION_ATTEMPT', 'Tentative d\'injection SQL détectée', [
                                'parameter' => $key,
                                'value' => $value,
                                'pattern' => $pattern
                            ]);
                            return $this->securityErrorResponse($response, 'Entrée invalide', 400);
                        }
                    }
                }
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware pour la protection XSS
     */
    public function preventXSS(): callable
    {
        return function ($request, $response, $next) {
            $xssPatterns = [
                '/<script[^>]*>.*?<\/script>/si',
                '/<iframe[^>]*>.*?<\/iframe>/si',
                '/<object[^>]*>.*?<\/object>/si',
                '/<embed[^>]*>.*?<\/embed>/si',
                '/javascript:/i',
                '/vbscript:/i',
                '/onload\s*=/i',
                '/onerror\s*=/i',
                '/onclick\s*=/i',
                '/onmouseover\s*=/i',
                '/<img[^>]*src[^>]*javascript:/i',
                '/<[^>]*on\w+\s*=[^>]*>/i'
            ];

            $params = array_merge(
                $request->get ?? [],
                $request->post ?? []
            );

            foreach ($params as $key => $value) {
                if (is_string($value)) {
                    foreach ($xssPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $this->logSecurityIssue('XSS_ATTEMPT', 'Tentative XSS détectée', [
                                'parameter' => $key,
                                'value' => $value,
                                'pattern' => $pattern
                            ]);
                            return $this->securityErrorResponse($response, 'Entrée invalide', 400);
                        }
                    }
                }
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware pour la validation des fichiers
     */
    public function validateFiles(): callable
    {
        return function ($request, $response, $next) {
            if (empty($request->files)) {
                return $next($request, $response);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB

            foreach ($request->files as $key => $file) {
                if (is_array($file)) {
                    foreach ($file as $f) {
                        $validation = $this->validateSingleFile($f, $allowedExtensions, $maxFileSize);
                        if (!$validation['valid']) {
                            $this->logSecurityIssue('INVALID_FILE', 'Fichier invalide détecté', [
                                'filename' => $f['name'] ?? 'unknown',
                                'error' => $validation['error']
                            ]);
                            return $this->securityErrorResponse($response, $validation['error'], 400);
                        }
                    }
                } else {
                    $validation = $this->validateSingleFile($file, $allowedExtensions, $maxFileSize);
                    if (!$validation['valid']) {
                        $this->logSecurityIssue('INVALID_FILE', 'Fichier invalide détecté', [
                            'filename' => $file['name'] ?? 'unknown',
                            'error' => $validation['error']
                        ]);
                        return $this->securityErrorResponse($response, $validation['error'], 400);
                    }
                }
            }

            return $next($request, $response);
        };
    }

    /**
     * Valide un fichier unique
     */
    private function validateSingleFile(array $file, array $allowedExtensions, int $maxFileSize): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Fichier non valide'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            return ['valid' => false, 'error' => 'Extension de fichier non autorisée'];
        }

        if ($file['size'] > $maxFileSize) {
            return ['valid' => false, 'error' => 'Fichier trop volumineux'];
        }

        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return ['valid' => false, 'error' => 'Type de fichier non autorisé'];
        }

        return ['valid' => true];
    }

    /**
     * Valide l'origine de la requête
     */
    private function validateOrigin($request): bool
    {
        $allowedOrigins = [
            $_ENV['APP_URL'] ?? 'http://localhost',
            'http://localhost:3000',
            'https://localhost:3000'
        ];

        $origin = $request->server['HTTP_ORIGIN'] ?? null;
        
        if (!$origin) {
            return true; // Requêtes same-origin
        }

        return in_array($origin, $allowedOrigins);
    }

    /**
     * Détecte et journalise les attaques
     */
    private function detectAndLogAttacks($request): void
    {
        $userAgent = $request->server['HTTP_USER_AGENT'] ?? '';
        $ip = $request->server['REMOTE_ADDR'] ?? '';

        // Détecter les user agents suspects
        $suspiciousUserAgents = [
            'sqlmap', 'nikto', 'nmap', 'burp', 'metasploit',
            'curl', 'wget', 'python-requests', 'scrapy'
        ];

        foreach ($suspiciousUserAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                $this->logSecurityIssue('SUSPICIOUS_USER_AGENT', 'User agent suspect détecté', [
                    'user_agent' => $userAgent,
                    'ip' => $ip
                ]);
                break;
            }
        }

        // Détecter les requêtes anormales
        $uri = $request->server['REQUEST_URI'] ?? '';
        if (strpos($uri, '..') !== false || strpos($uri, '%2e%2e') !== false) {
            $this->logSecurityIssue('PATH_TRAVERSAL_ATTEMPT', 'Tentative de path traversal détectée', [
                'uri' => $uri,
                'ip' => $ip
            ]);
        }

        // Détecter les requêtes très longues
        if (strlen($uri) > 1000) {
            $this->logSecurityIssue('LONG_URI', 'URI anormalement longue détectée', [
                'uri' => $uri,
                'ip' => $ip,
                'length' => strlen($uri)
            ]);
        }
    }

    /**
     * Valide les entrées de la requête
     */
    private function validateInputs($request): void
    {
        // Limiter la taille des entrées
        $maxInputSize = 10000; // 10KB
        
        foreach (['get', 'post'] as $method) {
            if (isset($request->$method)) {
                foreach ($request->$method as $key => $value) {
                    if (is_string($value) && strlen($value) > $maxInputSize) {
                        $this->logSecurityIssue('LARGE_INPUT', 'Entrée trop volumineuse détectée', [
                            'method' => strtoupper($method),
                            'parameter' => $key,
                            'size' => strlen($value)
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Nettoie la requête
     */
    private function sanitizeRequest($request): void
    {
        // Nettoyer les paramètres GET
        if (isset($request->get)) {
            foreach ($request->get as $key => $value) {
                if (is_string($value)) {
                    $request->get[$key] = $this->securityService->sanitizeInput($value);
                }
            }
        }

        // Nettoyer les paramètres POST
        if (isset($request->post)) {
            foreach ($request->post as $key => $value) {
                if (is_string($value)) {
                    $request->post[$key] = $this->securityService->sanitizeInput($value);
                }
            }
        }
    }

    /**
     * Valide les paramètres GET
     */
    private function validateGetParams($request): void
    {
        if (!isset($request->get)) {
            return;
        }

        foreach ($request->get as $key => $value) {
            // Vérifier les caractères dangereux
            if (is_string($value) && preg_match('/[<>"\']/', $value)) {
                $this->logSecurityIssue('DANGEROUS_GET_PARAM', 'Paramètre GET dangereux détecté', [
                    'parameter' => $key,
                    'value' => $value
                ]);
            }
        }
    }

    /**
     * Valide les paramètres POST
     */
    private function validatePostParams($request): void
    {
        if (!isset($request->post)) {
            return;
        }

        foreach ($request->post as $key => $value) {
            // Vérifier les caractères dangereux
            if (is_string($value) && preg_match('/[<>"\']/', $value)) {
                $this->logSecurityIssue('DANGEROUS_POST_PARAM', 'Paramètre POST dangereux détecté', [
                    'parameter' => $key,
                    'value' => $value
                ]);
            }
        }
    }

    /**
     * Valide les fichiers uploadés
     */
    private function validateUploadedFiles($request): void
    {
        if (!isset($request->files)) {
            return;
        }

        foreach ($request->files as $key => $file) {
            if (is_array($file)) {
                foreach ($file as $f) {
                    $this->validateSingleFileSecurity($f, $key);
                }
            } else {
                $this->validateSingleFileSecurity($file, $key);
            }
        }
    }

    /**
     * Valide la sécurité d'un fichier unique
     */
    private function validateSingleFileSecurity(array $file, string $key): void
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->logSecurityIssue('INVALID_UPLOAD', 'Upload de fichier invalide détecté', [
                'parameter' => $key,
                'filename' => $file['name'] ?? 'unknown'
            ]);
        }

        // Vérifier les extensions dangereuses
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'cmd', 'sh'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, $dangerousExtensions)) {
            $this->logSecurityIssue('DANGEROUS_FILE_EXTENSION', 'Extension de fichier dangereuse détectée', [
                'parameter' => $key,
                'filename' => $file['name'],
                'extension' => $extension
            ]);
        }
    }

    /**
     * Vérifie la limite de taux
     */
    private function checkRateLimit($request): bool
    {
        $ip = $request->server['REMOTE_ADDR'] ?? 'unknown';
        $key = 'rate_limit_' . md5($ip);
        
        $currentCount = $_SESSION[$key] ?? 0;
        $lastRequest = $_SESSION[$key . '_time'] ?? 0;
        
        $now = time();
        $window = 60; // 1 minute
        $maxRequests = 100; // 100 requêtes par minute
        
        // Réinitialiser si la fenêtre est expirée
        if ($now - $lastRequest > $window) {
            $currentCount = 0;
        }
        
        if ($currentCount >= $maxRequests) {
            return false;
        }
        
        $_SESSION[$key] = $currentCount + 1;
        $_SESSION[$key . '_time'] = $now;
        
        return true;
    }

    /**
     * Journalise un problème de sécurité
     */
    private function logSecurityIssue(string $type, string $message, $context = null): void
    {
        $this->loggingService->security($message, array_merge([
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ], $context ?? []));
    }

    /**
     * Retourne une réponse d'erreur de sécurité
     */
    private function securityErrorResponse($response, string $message, int $code = 403)
    {
        $response->header("HTTP/1.1 {$code} Forbidden");
        $response->header('Content-Type: application/json');
        $response->header('Access-Control-Allow-Origin: *');
        $response->header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'SECURITY_ERROR'
        ]);
        exit;
    }
}
