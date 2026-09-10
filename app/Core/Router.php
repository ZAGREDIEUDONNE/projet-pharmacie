<?php

namespace App\Core;

use Exception;

/**
 * Router MVC central pour l'application
 */
class Router
{
    private array $routes = [];

    /**
     * Enregistre une route pour une méthode HTTP
     */
    public function register(string $method, string $pattern, string $handler): void
    {
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }
        
        $this->routes[$method][$pattern] = $handler;
    }

    /**
     * Dispatch la requête vers le controller approprié avec middleware
     */
    public function dispatch(string $method, string $uri): void
    {
        // DEBUG - Log de l'URI reçue
        error_log("Router Dispatch: Method={$method}, URI={$uri}");
        
        // Nettoyer l'URI
        $uri = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $uri = str_replace('/index.php', '', $uri);

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir)) ?: '/';
        }
        
        // Normaliser : toujours commencer par / et ne pas finir par / (sauf pour /)
        if ($uri !== '/') {
            $uri = '/' . ltrim($uri, '/');
            $uri = rtrim($uri, '/');
        }
        
        // DEBUG - Log de l'URI nettoyée
        error_log("Router URI Normalized: {$uri}");
        
        // Chercher la route correspondante
        $route = $this->findRoute($method, $uri);
        
        if (!$route) {
            // DEBUG - Log des routes disponibles
            error_log("Routes disponibles pour {$method}: " . json_encode(array_keys($this->routes[$method] ?? [])));
            throw new Exception("Route non trouvée: {$method} {$uri}");
        }

        // Parser le handler
        [$controllerName, $methodName] = explode('@', $route['handler']);
        
        // Construire le nom complet de la classe du controller
        $controllerClass = "App\\Controllers\\{$controllerName}";
        
        // DEBUG - Log de la classe du controller
        error_log("Router: Looking for controller class: {$controllerClass}");
        
        // Vérifier si la classe existe (après autoload)
        if (!class_exists($controllerClass)) {
            error_log("Router: Class {$controllerClass} not found after autoload");
            
            // Vérifier si le fichier existe physiquement
            $controllerFile = __DIR__ . '/../Controllers/' . $controllerName . '.php';
            if (file_exists($controllerFile)) {
                error_log("Router: File exists but class not loaded: {$controllerFile}");
                // Forcer le chargement du fichier
                require_once $controllerFile;
                if (!class_exists($controllerClass)) {
                    throw new Exception("Contrôleur non trouvé même après chargement: {$controllerClass}");
                }
            } else {
                throw new Exception("Contrôleur non trouvé: {$controllerClass} (fichier: {$controllerFile})");
            }
        }

        // DEBUG - Log succès
        error_log("Router: Controller class found: {$controllerClass}");

        // Charger les dépendances du controller
        $this->loadControllerDependencies($controllerClass);
        
        // Créer l'instance du controller
        try {
            $controller = new $controllerClass();
            error_log("Router: Controller instantiated successfully: {$controllerClass}");
        } catch (Exception $e) {
            error_log("Router: Failed to instantiate {$controllerClass}: " . $e->getMessage());
            throw new Exception("Erreur lors de l'instanciation du contrôleur: {$controllerClass}");
        }
        
        // Vérifier la méthode
        if (!method_exists($controller, $methodName)) {
            throw new Exception("Méthode non trouvée: {$methodName} dans {$controllerClass}");
        }

        // Middleware de protection pour routes sensibles
        if ($this->requiresAuthentication($controllerName, $methodName)) {
            if (!isset($_SESSION['user'])) {
                // Redirection vers login pour les requêtes web
                if ($this->expectsJson($controllerName, $methodName, $uri)) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'AUTHENTICATION_REQUIRED']);
                } else {
                    header('Location: /login');
                }
                exit;
            }
        }

        if ($this->requiresCsrfProtection($controllerName, $methodName)) {
            $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!\App\Services\CsrfService::isValid(is_string($token) ? $token : null)) {
                $this->sendCsrfFailure($controllerName, $methodName, $uri);
            }
        }

        // Appeler la méthode avec les paramètres
        try {
            call_user_func_array([$controller, $methodName], $route['params']);
        } catch (\Throwable $e) {
            error_log("Erreur dans le contrôleur {$controllerClass}::{$methodName}: " . $e->getMessage());
            throw new Exception(
                defined('APP_DEBUG') && APP_DEBUG
                    ? 'Erreur lors de l\'execution du controleur: ' . $e->getMessage()
                    : 'Erreur lors de l\'execution du controleur',
                0,
                $e
            );
        }
    }

    /**
     * Trouve une route correspondante
     */
    private function findRoute(string $method, string $uri): ?array
    {
        if (!isset($this->routes[$method])) {
            error_log("Router: Aucune route pour la méthode {$method}");
            return null;
        }

        $routes = $this->routes[$method];
        $exactRoutes = [];
        $dynamicRoutes = [];

        foreach ($routes as $pattern => $handler) {
            if (str_contains($pattern, '{')) {
                $dynamicRoutes[$pattern] = $handler;
            } else {
                $exactRoutes[$pattern] = $handler;
            }
        }

        foreach ([$exactRoutes, $dynamicRoutes] as $routeSet) {
            foreach ($routeSet as $pattern => $handler) {
                if ($pattern === $uri) {
                    return [
                        'handler' => $handler,
                        'params' => []
                    ];
                }

                $regexPattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern);
                $regexPattern = '#^' . $regexPattern . '$#';

                if (preg_match($regexPattern, $uri, $matches)) {
                    array_shift($matches);
                    return [
                        'handler' => $handler,
                        'params' => $matches
                    ];
                }
            }
        }

        error_log("Router: Aucune route trouvée pour {$method} {$uri}");
        return null;
    }

    /**
     * Charge les dépendances nécessaires pour un controller
     */
    private function loadControllerDependencies(string $controllerClass): void
    {
        // Charger automatiquement les services et dépendances
        $dependencies = [
            'App\\Controllers\\AuthController' => ['App\\Services\\RBACService', 'App\\Services\\AuditService'],
            'App\\Controllers\\ClientController' => ['App\\Services\\RBACService', 'App\\Services\\AuditService'],
            'App\\Controllers\\VenteController' => ['App\\Services\\VenteService', 'App\\Services\\AuditService'],
            'App\\Controllers\\CaisseController' => ['App\\Services\\RBACService', 'App\\Services\\AuditService'],
            'App\\Controllers\\SystemController' => ['App\\Services\\AuditService'],
            'App\\Controllers\\VenteAuthController' => ['App\\Services\\RBACService', 'App\\Services\\AuditService'],
            'App\\Controllers\\AdminController' => ['App\\Services\\RoleService', 'App\\Services\\RBACService', 'App\\Services\\AuditService']
        ];

        if (isset($dependencies[$controllerClass])) {
            foreach ($dependencies[$controllerClass] as $service) {
                $serviceFile = __DIR__ . '/../Services/' . basename($service) . '.php';
                if (file_exists($serviceFile)) {
                    require_once $serviceFile;
                }
            }
        }
        
        // Toujours charger BaseController si ce n'est pas déjà fait
        if (!class_exists('App\\Core\\BaseController')) {
            require_once __DIR__ . '/BaseController.php';
        }
        
        // Charger systématiquement toutes les classes de base nécessaires
        $baseClasses = [
            'BaseController' => __DIR__ . '/BaseController.php',
            'RBACService' => __DIR__ . '/../Services/RBACService.php',
            'AuditService' => __DIR__ . '/../Services/AuditService.php',
            'RoleService' => __DIR__ . '/../Services/RoleService.php',
            'VenteService' => __DIR__ . '/../Services/VenteService.php'
        ];

        foreach ($baseClasses as $className => $filePath) {
            if (!class_exists('App\\Core\\' . $className) && !class_exists('App\\Services\\' . $className)) {
                if (file_exists($filePath)) {
                    require_once $filePath;
                }
            }
        }
    }

    /**
     * Vérifie si une route existe
     */
    public function hasRoute(string $method, string $uri): bool
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = str_replace('/index.php', '', $uri);
        $uri = rtrim($uri, '/');
        
        return $this->findRoute($method, $uri) !== null;
    }

    /**
     * Vérifie si une route nécessite une authentification
     */
    private function requiresAuthentication(string $controllerName, string $methodName): bool
    {
        // Routes publiques qui ne nécessitent pas d'authentification
        $publicRoutes = [
            'AuthController@login',
            'AuthController@authenticate',
            'VenteAuthController@login',
            'VenteAuthController@authenticate'
        ];

        $handler = $controllerName . '@' . $methodName;
        return !in_array($handler, $publicRoutes);
    }

    private function requiresCsrfProtection(string $controllerName, string $methodName): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return false;
        }

        return $controllerName === 'AdminController'
            || ($controllerName === 'ComptabiliteController' && in_array($methodName, ['api', 'integration'], true))
            || ($controllerName === 'VenteController' && in_array($methodName, ['cancelTicket', 'annulerTicketEnAttente'], true));
    }

    private function expectsJson(string $controllerName, string $methodName, string $uri): bool
    {
        return str_starts_with($uri, '/api/')
            || ($controllerName === 'AdminController' && in_array($methodName, ['statistiquesLive', 'auditLive'], true))
            || ($controllerName === 'AssistantController' && $methodName === 'apiDashboard')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    private function sendCsrfFailure(string $controllerName, string $methodName, string $uri): void
    {
        http_response_code(403);
        if ($this->expectsJson($controllerName, $methodName, $uri)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'CSRF_INVALID']);
        } else {
            echo 'Requete refusee : jeton CSRF invalide ou manquant.';
        }
        exit;
    }

    /**
     * Obtient toutes les routes enregistrées
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
