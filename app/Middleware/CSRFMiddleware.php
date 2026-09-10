<?php

namespace App\Middleware;

use Exception;

class CSRFMiddleware
{
    private string $secretKey;
    private int $tokenExpiration = 3600; // 1 heure

    public function __construct()
    {
        $this->secretKey = $_ENV['CSRF_SECRET'] ?? 'votre_secret_csrf_tres_securise';
    }

    /**
     * Génère un token CSRF
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        $payload = [
            'token' => $token,
            'timestamp' => $timestamp
        ];
        
        $signature = hash_hmac('sha256', json_encode($payload), $this->secretKey);
        
        $csrfData = [
            'token' => $token,
            'signature' => $signature,
            'timestamp' => $timestamp
        ];
        
        // Stocker en session
        $_SESSION['csrf_token'] = $csrfData;
        
        return $token;
    }

    /**
     * Vérifie un token CSRF
     */
    public function validateToken(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        $storedCSRF = $_SESSION['csrf_token'];
        
        // Vérifier la signature
        $expectedSignature = hash_hmac('sha256', json_encode([
            'token' => $storedCSRF['token'],
            'timestamp' => $storedCSRF['timestamp']
        ]), $this->secretKey);
        
        if (!hash_equals($storedCSRF['signature'], $expectedSignature)) {
            return false;
        }

        // Vérifier le token
        if (!hash_equals($storedCSRF['token'], $token)) {
            return false;
        }

        // Vérifier l'expiration
        if (time() - $storedCSRF['timestamp'] > $this->tokenExpiration) {
            return false;
        }

        return true;
    }

    /**
     * Middleware pour vérifier le token CSRF
     */
    public function requireCSRF(): callable
    {
        return function ($request, $response, $next) {
            $method = $request->server['REQUEST_METHOD'] ?? 'GET';
            
            // Les requêtes GET, HEAD, OPTIONS ne nécessitent pas de validation CSRF
            if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
                return $next($request, $response);
            }

            // Récupérer le token depuis la requête
            $token = $this->getTokenFromRequest($request);
            
            if (!$this->validateToken($token)) {
                return $this->csrfErrorResponse($response, 'Token CSRF invalide ou manquant');
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware pour ajouter le token CSRF aux formulaires
     */
    public function addCSRFToken(): callable
    {
        return function ($request, $response, $next) {
            // Générer un nouveau token si nécessaire
            if (!isset($_SESSION['csrf_token']) || 
                time() - $_SESSION['csrf_token']['timestamp'] > $this->tokenExpiration) {
                $this->generateToken();
            }

            // Ajouter le token à la réponse pour les formulaires
            $response->csrf_token = $_SESSION['csrf_token']['token'];
            $response->csrf_field_name = 'csrf_token';

            return $next($request, $response);
        };
    }

    /**
     * Extrait le token de la requête
     */
    private function getTokenFromRequest($request): ?string
    {
        // Essayer depuis l'en-tête
        $headerToken = $request->server['HTTP_X_CSRF_TOKEN'] ?? 
                     $request->server['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if ($headerToken) {
            return $headerToken;
        }

        // Essayer depuis les données POST
        $postToken = $request->post['csrf_token'] ?? 
                    $request->post['_csrf_token'] ?? null;
        
        if ($postToken) {
            return $postToken;
        }

        // Essayer depuis les paramètres GET
        $getToken = $request->get['csrf_token'] ?? 
                   $request->get['_csrf_token'] ?? null;
        
        return $getToken;
    }

    /**
     * Retourne une réponse d'erreur CSRF
     */
    private function csrfErrorResponse($response, string $message)
    {
        $response->header('HTTP/1.1 403 Forbidden');
        $response->header('Content-Type: application/json');
        $response->header('Access-Control-Allow-Origin: *');
        $response->header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        $response->header('Access-Control-Expose-Headers: X-CSRF-Token');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'CSRF_INVALID',
            'csrf_required' => true
        ]);
        exit;
    }

    /**
     * Génère le champ HTML pour le token CSRF
     */
    public function generateCSRFField(): string
    {
        $token = $this->generateToken();
        
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars('csrf_token'),
            htmlspecialchars($token)
        );
    }

    /**
     * Génère le champ HTML pour l'AJAX
     */
    public function generateCSRFMeta(): string
    {
        $token = $this->generateToken();
        
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token)
        );
    }

    /**
     * Génère le JavaScript pour l'AJAX
     */
    public function generateCSRFJavaScript(): string
    {
        return "
        <script>
        // Configuration CSRF pour les requêtes AJAX
        window.CSRF = {
            getToken: function() {
                return document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || 
                       document.querySelector('input[name=\"csrf_token\"]')?.value;
            },
            
            setupAjax: function() {
                const token = this.getToken();
                if (token) {
                    // Configuration pour fetch
                    const originalFetch = window.fetch;
                    window.fetch = function(url, options = {}) {
                        options.headers = options.headers || {};
                        options.headers['X-CSRF-Token'] = token;
                        return originalFetch(url, options);
                    };
                    
                    // Configuration pour XMLHttpRequest
                    const originalXHROpen = XMLHttpRequest.prototype.open;
                    XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
                        this.setRequestHeader('X-CSRF-Token', token);
                        return originalXHROpen.call(this, method, url, async, user, pass);
                    };
                }
            }
        };
        
        // Initialiser au chargement du DOM
        document.addEventListener('DOMContentLoaded', function() {
            CSRF.setupAjax();
        });
        </script>";
    }

    /**
     * Vérifie si la requête est AJAX
     */
    public function isAjaxRequest($request): bool
    {
        return (
            !empty($request->server['HTTP_X_REQUESTED_WITH']) &&
            strtolower($request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            !empty($request->server['HTTP_CONTENT_TYPE']) &&
            strpos($request->server['HTTP_CONTENT_TYPE'], 'application/json') !== false
        );
    }

    /**
     * Nettoie les anciens tokens CSRF
     */
    public function cleanupOldTokens(): void
    {
        if (isset($_SESSION['csrf_token']) && 
            time() - $_SESSION['csrf_token']['timestamp'] > $this->tokenExpiration) {
            unset($_SESSION['csrf_token']);
        }
    }

    /**
     * Configure les en-têtes de sécurité
     */
    public function setSecurityHeaders($response): void
    {
        $response->header('X-Content-Type-Options: nosniff');
        $response->header('X-Frame-Options: DENY');
        $response->header('X-XSS-Protection: 1; mode=block');
        $response->header('Referrer-Policy: strict-origin-when-cross-origin');
        $response->header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\'; frame-ancestors \'none\';');
    }

    /**
     * Valide une origine de requête CORS
     */
    public function validateOrigin($request): bool
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
     * Middleware CORS
     */
    public function handleCORS(): callable
    {
        return function ($request, $response, $next) {
            // Configurer les en-têtes CORS
            $response->header('Access-Control-Allow-Origin: *');
            $response->header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            $response->header('Access-Control-Allow-Credentials: true');
            $response->header('Access-Control-Max-Age: 86400'); // 24 heures

            // Gérer les requêtes preflight OPTIONS
            if ($request->server['REQUEST_METHOD'] === 'OPTIONS') {
                $response->header('HTTP/1.1 200 OK');
                exit;
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware de sécurité complet
     */
    public function securityMiddleware(): callable
    {
        return function ($request, $response, $next) {
            // Configurer les en-têtes de sécurité
            $this->setSecurityHeaders($response);

            // Valider l'origine
            if (!$this->validateOrigin($request)) {
                return $this->corsErrorResponse($response, 'Origine non autorisée');
            }

            // Protection contre les attaques de type clickjacking
            $response->header('X-Frame-Options: DENY');

            // Protection contre le MIME-sniffing
            $response->header('X-Content-Type-Options: nosniff');

            return $next($request, $response);
        };
    }

    /**
     * Retourne une réponse d'erreur CORS
     */
    private function corsErrorResponse($response, string $message)
    {
        $response->header('HTTP/1.1 403 Forbidden');
        $response->header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'CORS_ERROR'
        ]);
        exit;
    }

    /**
     * Génère un nonce pour CSP
     */
    public function generateNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['csp_nonce'] = $nonce;
        
        return $nonce;
    }

    /**
     * Récupère le nonce CSP actuel
     */
    public function getCurrentNonce(): ?string
    {
        return $_SESSION['csp_nonce'] ?? null;
    }

    /**
     * Configure le Content Security Policy
     */
    public function setCSP($response, string $nonce = null): void
    {
        $nonce = $nonce ?? $this->generateNonce();
        
        $csp = "default-src 'self'; " .
                "script-src 'self' 'nonce-{$nonce}'; " .
                "style-src 'self' 'nonce-{$nonce}'; " .
                "img-src 'self' data: https:; " .
                "font-src 'self'; " .
                "connect-src 'self'; " .
                "frame-ancestors 'none'; " .
                "base-uri 'self'; " .
                "form-action 'self';";

        $response->header("Content-Security-Policy: $csp");
    }
}
