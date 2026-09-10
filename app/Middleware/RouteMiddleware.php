<?php

namespace App\Middleware;

use App\Services\AuthService;
use App\Services\PermissionService;
use App\Services\ValidationService;
use App\Services\DoubleAccesService;
use Exception;

/**
 * Middleware de protection des routes avec authentification et permissions
 */
class RouteMiddleware
{
    private AuthService $authService;
    private PermissionService $permissionService;
    private ValidationService $validationService;
    private DoubleAccesService $doubleAccesService;

    public function __construct(
        AuthService $authService,
        PermissionService $permissionService,
        ValidationService $validationService,
        DoubleAccesService $doubleAccesService
    ) {
        $this->authService = $authService;
        $this->permissionService = $permissionService;
        $this->validationService = $validationService;
        $this->doubleAccesService = $doubleAccesService;
    }

    /**
     * Middleware d'authentification requise
     */
    public function requireAuth(): callable
    {
        return function ($request, $response, $next) {
            // Vérifier le token JWT
            $token = $this->extractTokenFromRequest($request);
            
            if (!$token) {
                $this->sendUnauthorizedResponse('Token manquant');
                return;
            }

            $payload = $this->authService->verifyToken($token);
            
            if (!$payload) {
                $this->sendUnauthorizedResponse('Token invalide');
                return;
            }

            // Ajouter les informations utilisateur à la requête
            $request['user'] = $payload;
            
            return $next($request, $response);
        };
    }

    /**
     * Middleware de permission requise
     */
    public function requirePermission(string $permission): callable
    {
        return function ($request, $response, $next) use ($permission) {
            if (!isset($request['user'])) {
                $this->sendUnauthorizedResponse('Authentification requise');
                return;
            }

            $userId = $request['user']['user_id'];
            
            if (!$this->permissionService->hasPermission($userId, $permission)) {
                $this->sendForbiddenResponse('Permission non accordée: ' . $permission);
                return;
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware de rôle requis
     */
    public function requireRole(string $role): callable
    {
        return function ($request, $response, $next) use ($role) {
            if (!isset($request['user'])) {
                $this->sendUnauthorizedResponse('Authentification requise');
                return;
            }

            if ($request['user']['role'] !== $role && $request['user']['role'] !== 'ADMIN') {
                $this->sendForbiddenResponse('Rôle non autorisé: ' . $role);
                return;
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware de validation CSRF
     */
    public function requireCSRF(): callable
    {
        return function ($request, $response, $next) {
            $method = $request['method'] ?? 'GET';
            
            // Les méthodes GET, HEAD, OPTIONS ne nécessitent pas CSRF
            if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
                return $next($request, $response);
            }

            $token = $request['csrf_token'] ?? '';
            
            if (!$this->validationService->validateCSRFToken($token)) {
                $this->sendForbiddenResponse('Token CSRF invalide');
                return;
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware de double authentification pour actions sensibles
     */
    public function requireDoubleAuth(string $action, string $module): callable
    {
        return function ($request, $response, $next) use ($action, $module) {
            if (!isset($request['user'])) {
                $this->sendUnauthorizedResponse('Authentification requise');
                return;
            }

            $userId = $request['user']['user_id'];
            
            if ($this->doubleAccesService->requiresDoubleConfirmation($action)) {
                if (!$this->doubleAccesService->hasPendingConfirmation($userId, $action)) {
                    // Demander la double authentification
                    $code = $this->doubleAccesService->generateConfirmationCode($userId, $action, $module);
                    $this->sendDoubleAuthRequiredResponse($code);
                    return;
                }
                
                // Vérifier le code de confirmation
                $confirmCode = $request['confirmation_code'] ?? '';
                
                if (!$this->doubleAccesService->verifyConfirmationCode($userId, $action, $confirmCode)) {
                    $this->sendForbiddenResponse('Code de confirmation invalide');
                    return;
                }
            }

            return $next($request, $response);
        };
    }

    /**
     * Middleware de validation des entrées
     */
    public function validateInput(array $rules): callable
    {
        return function ($request, $response, $next) use ($rules) {
            $validation = $this->validationService->validateInput($request, $rules);
            
            if (!$validation['success']) {
                $this->sendValidationErrorResponse($validation['errors']);
                return;
            }

            // Remplacer les données par les données validées
            $request = array_merge($request, $validation['data']);
            
            return $next($request, $response);
        };
    }

    /**
     * Middleware de protection contre les attaques par force brute
     */
    public function rateLimit(string $identifier, int $maxAttempts = 5, int $timeWindow = 300): callable
    {
        return function ($request, $response, $next) use ($identifier, $maxAttempts, $timeWindow) {
            $ip = $request['REMOTE_ADDR'] ?? 'unknown';
            
            if (!$this->validationService->checkRateLimit($identifier, 'login', $maxAttempts, $timeWindow)) {
                $this->sendTooManyRequestsResponse();
                return;
            }

            return $next($request, $response);
        };
    }

    /**
     * Extrait le token JWT de la requête
     */
    private function extractTokenFromRequest(array $request): ?string
    {
        // Priorité: Header Authorization > Cookie > Paramètre GET/POST
        $token = null;

        // Header Authorization
        if (isset($request['HTTP_AUTHORIZATION'])) {
            $authHeader = $request['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = trim($matches[1]);
            }
        }

        // Cookie
        if (!$token && isset($request['jwt_token'])) {
            $token = $request['jwt_token'];
        }

        // Paramètre
        if (!$token && isset($request['token'])) {
            $token = $request['token'];
        }

        return $token;
    }

    /**
     * Envoie une réponse 401
     */
    private function sendUnauthorizedResponse(string $message): void
    {
        header('HTTP/1.0 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }

    /**
     * Envoie une réponse 403
     */
    private function sendForbiddenResponse(string $message): void
    {
        header('HTTP/1.0 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => 'FORBIDDEN'
        ]);
        exit;
    }

    /**
     * Envoie une réponse 429
     */
    private function sendTooManyRequestsResponse(): void
    {
        header('HTTP/1.0 429 Too Many Requests');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Trop de tentatives. Veuillez réessayer plus tard.',
            'code' => 'TOO_MANY_REQUESTS'
        ]);
        exit;
    }

    /**
     * Envoie une réponse demandant double authentification
     */
    private function sendDoubleAuthRequiredResponse(string $code): void
    {
        header('HTTP/1.0 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Double authentification requise',
            'code' => 'DOUBLE_AUTH_REQUIRED',
            'confirmation_code' => $code,
            'expires_in' => 300 // 5 minutes
        ]);
        exit;
    }

    /**
     * Envoie une réponse d'erreur de validation
     */
    private function sendValidationErrorResponse(array $errors): void
    {
        header('HTTP/1.0 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreurs de validation',
            'errors' => $errors,
            'code' => 'VALIDATION_ERROR'
        ]);
        exit;
    }
}
