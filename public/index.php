<?php

/**
 * Point d'entrée unique de l'application MVC
 */

// Démarrer la session
session_start();

// Charger la configuration de l'application
require_once __DIR__ . '/../config/app.php';

// AUTOLOADER PSR-4 MANUEL
spl_autoload_register(function ($class) {
    // Préfixes des namespaces
    $prefixes = [
        'App\\Controllers\\' => __DIR__ . '/../app/Controllers/',
        'App\\Core\\' => __DIR__ . '/../app/Core/',
        'App\\Services\\' => __DIR__ . '/../app/Services/',
        'App\\Repositories\\' => __DIR__ . '/../app/Repositories/',
        'App\\Models\\' => __DIR__ . '/../app/Models/',
        'App\\Middleware\\' => __DIR__ . '/../app/Middleware/',
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        // Vérifier si la classe utilise le namespace
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            
            // DEBUG - Log de l'autoloader
            error_log("Autoload: Loading class {$class} from file {$file}");
            
            if (file_exists($file)) {
                require_once $file;
                return;
            } else {
                error_log("Autoload: File not found: {$file}");
            }
        }
    }
});

// Charger la configuration
require_once __DIR__ . '/../config/database.php';

// Charger les routes
$routes = require_once __DIR__ . '/../config/routes.php';

// Créer et utiliser le Router
$router = new App\Core\Router();

// DEBUG - Log des routes chargées
error_log("Routes loaded: " . json_encode($routes));

// Enregistrer les routes
foreach ($routes as $httpMethod => $routesByMethod) {
    foreach ($routesByMethod as $pattern => $handler) {
        $router->register($httpMethod, $pattern, $handler);
        error_log("Route registered: {$httpMethod} {$pattern} -> {$handler}");
    }
}


// Dispatcher la requête
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (\Throwable $e) {
    http_response_code(404);
    $payload = [
        'error' => 'URL introuvable',
        'message' => $e->getMessage(),
        'uri' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD'],
    ];

    if (defined('APP_DEBUG') && APP_DEBUG && $e->getPrevious()) {
        $payload['detail'] = $e->getPrevious()->getMessage();
        $payload['file'] = $e->getPrevious()->getFile();
        $payload['line'] = $e->getPrevious()->getLine();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}
