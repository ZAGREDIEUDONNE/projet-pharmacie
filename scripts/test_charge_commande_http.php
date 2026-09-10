<?php

declare(strict_types=1);

/**
 * Recette HTTP réelle du rôle CHARGE_COMMANDE.
 *
 * Usage :
 *   $env:CHARGE_COMMANDE_PASSWORD = '…'; php scripts/test_charge_commande_http.php
 *   php scripts/test_charge_commande_http.php --unauth-only
 *
 * Le mot de passe n'est jamais enregistré ni affiché. Toutes les requêtes métier
 * sont des GET : ce script ne crée, ne modifie ni ne réceptionne aucune commande.
 */

$baseUrl = rtrim(getenv('CHARGE_COMMANDE_BASE_URL') ?: 'http://localhost', '/');
$login = getenv('CHARGE_COMMANDE_LOGIN') ?: 'KAMBOU';
$password = getenv('CHARGE_COMMANDE_PASSWORD') ?: '';
$unauthOnly = in_array('--unauth-only', $argv, true);
$cookieFile = tempnam(sys_get_temp_dir(), 'charge_commande_http_');

if ($cookieFile === false) {
    throw new RuntimeException('Impossible de créer le fichier cookie temporaire.');
}

/** @return array{status:int,headers:array<string,string>,body:string,url:string} */
function request(string $baseUrl, string $path, string $cookieFile, string $method = 'GET', array $data = [], array $headers = []): array
{
    $url = $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Impossible d’initialiser cURL.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $raw = curl_exec($curl);
    if ($raw === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException("Erreur HTTP cURL : {$error}");
    }

    $headerLength = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);

    $headerLines = preg_split('/\r?\n/', trim(substr($raw, 0, $headerLength))) ?: [];
    $responseHeaders = [];
    foreach ($headerLines as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);
        }
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => substr($raw, $headerLength),
        'url' => $effectiveUrl,
    ];
}

/** @return array{status:int,headers:array<string,string>,body:string,url:string,redirects:array<int,string>} */
function requestFollowingRedirects(string $baseUrl, string $path, string $cookieFile, string $method = 'GET', array $data = [], array $headers = []): array
{
    $redirects = [];
    $response = request($baseUrl, $path, $cookieFile, $method, $data, $headers);
    for ($i = 0; $i < 5 && $response['status'] >= 300 && $response['status'] < 400; $i++) {
        $location = $response['headers']['location'] ?? '';
        if ($location === '') {
            break;
        }
        $redirects[] = $location;
        $response = request($baseUrl, $location, $cookieFile);
    }
    $response['redirects'] = $redirects;
    return $response;
}

/** @return array<string,mixed> */
function routeResult(string $name, array $response, string $expectedContent = ''): array
{
    $body = $response['body'];
    $isServerError = $response['status'] >= 500
        || str_contains($body, 'Erreur SQL')
        || str_contains($body, 'Fatal error')
        || str_contains($body, 'Route non trouvée');
    $contentFound = $expectedContent === '' || stripos($body, $expectedContent) !== false;

    return [
        'route' => $name,
        'status' => $response['status'],
        'url' => $response['url'],
        'redirects' => $response['redirects'] ?? [],
        'content_type' => $response['headers']['content-type'] ?? '',
        'expected_content_found' => $contentFound,
        'server_error' => $isServerError,
        'pass' => $response['status'] >= 200 && $response['status'] < 300 && !$isServerError && $contentFound,
    ];
}

try {
    $results = ['base_url' => $baseUrl, 'login' => $login, 'authenticated' => false, 'routes' => [], 'apis' => [], 'rbac' => [], 'unauthenticated' => []];

    // Contrôle sans session : les HTML redirigent vers login, l'API XHR retourne un JSON 401.
    $htmlWithoutSession = request($baseUrl, '/commande/dashboard', $cookieFile);
    $apiWithoutSession = request($baseUrl, '/commande/api/dashboard', $cookieFile, 'GET', [], ['X-Requested-With: XMLHttpRequest']);
    $results['unauthenticated'] = [
        'dashboard' => [
            'status' => $htmlWithoutSession['status'],
            'location' => $htmlWithoutSession['headers']['location'] ?? null,
            'pass' => $htmlWithoutSession['status'] >= 300 && $htmlWithoutSession['status'] < 400,
        ],
        'api_dashboard' => [
            'status' => $apiWithoutSession['status'],
            'content_type' => $apiWithoutSession['headers']['content-type'] ?? '',
            'body' => json_decode($apiWithoutSession['body'], true),
            'pass' => $apiWithoutSession['status'] === 401
                && (json_decode($apiWithoutSession['body'], true)['error'] ?? null) === 'AUTHENTICATION_REQUIRED',
        ],
    ];

    if ($unauthOnly) {
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(array_reduce($results['unauthenticated'], static fn (bool $ok, array $test): bool => $ok && $test['pass'], true) ? 0 : 1);
    }

    if ($password === '') {
        throw new RuntimeException('CHARGE_COMMANDE_PASSWORD est requis pour la recette authentifiée.');
    }

    // Authentification réelle : récupération du formulaire, puis POST /login/auth avec le cookie PHPSESSID.
    $loginForm = request($baseUrl, '/login', $cookieFile);
    if ($loginForm['status'] !== 200) {
        throw new RuntimeException("Le formulaire de connexion retourne HTTP {$loginForm['status']}." );
    }
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginForm['body'], $csrfMatch);
    $loginResponse = requestFollowingRedirects($baseUrl, '/login/auth', $cookieFile, 'POST', [
        'login' => $login,
        'password' => $password,
        'csrf_token' => $csrfMatch[1] ?? '',
    ], ['Content-Type: application/x-www-form-urlencoded']);
    if ($loginResponse['status'] !== 200 || str_contains($loginResponse['body'], 'Identifiants invalides')) {
        throw new RuntimeException('Authentification de KAMBOU refusée.');
    }
    $results['authenticated'] = true;

    $routes = [
        '/commande/dashboard' => 'Dashboard',
        '/produits/catalogue-vendeur' => 'Catalogue',
        '/stock/historique-prix' => 'Historique',
        '/stock/fournisseurs' => 'Fournisseur',
        '/commande/saisie' => 'Commande',
        '/commande/historique' => 'Historique',
        '/commande/reception' => 'Reception',
        '/commande/mouvements' => 'Mouvements',
        '/stock/index' => 'Stock',
        '/produits/sortie-stock' => 'Sortie',
        '/stock/ajustement' => 'Ajustement',
        '/inventaire' => 'Inventaire',
        '/stock/alerts' => 'Alerte',
        '/stock/peremptions' => 'Peremption',
    ];
    foreach ($routes as $route => $expectedContent) {
        $results['routes'][] = routeResult($route, requestFollowingRedirects($baseUrl, $route, $cookieFile), $expectedContent);
    }

    $api = requestFollowingRedirects($baseUrl, '/commande/api/dashboard', $cookieFile, 'GET', [], ['X-Requested-With: XMLHttpRequest']);
    $payload = json_decode($api['body'], true);
    $dashboardData = is_array($payload) ? ($payload['data'] ?? []) : [];
    $requiredDashboardSections = ['widgets', 'produits_a_commander', 'commandes_attente', 'receptions_recentes', 'mouvements_recents', 'alertes', 'charts', 'stock_by_forme', 'top_used_products'];
    $results['apis'][] = [
        'route' => '/commande/api/dashboard',
        'status' => $api['status'],
        'content_type' => $api['headers']['content-type'] ?? '',
        'success' => $payload['success'] ?? false,
        'sections' => array_map(static fn (string $key): bool => array_key_exists($key, $dashboardData), $requiredDashboardSections),
        'pass' => $api['status'] === 200
            && str_contains($api['headers']['content-type'] ?? '', 'application/json')
            && ($payload['success'] ?? false) === true
            && !in_array(false, array_map(static fn (string $key): bool => array_key_exists($key, $dashboardData), $requiredDashboardSections), true),
    ];

    foreach (['/admin', '/comptabilite', '/caisse', '/vente'] as $route) {
        $response = requestFollowingRedirects($baseUrl, $route, $cookieFile);
        $results['rbac'][] = [
            'route' => $route,
            'status' => $response['status'],
            'url' => $response['url'],
            'redirects' => $response['redirects'],
            'access_refused' => $response['status'] === 403 || str_contains($response['body'], 'Acces refuse'),
            'server_error' => $response['status'] >= 500 || str_contains($response['body'], 'Route non trouvée'),
        ];
    }

    $allRoutePass = !in_array(false, array_column($results['routes'], 'pass'), true);
    $allApiPass = !in_array(false, array_column($results['apis'], 'pass'), true);
    $allUnauthPass = !in_array(false, array_column($results['unauthenticated'], 'pass'), true);
    $noRbacServerError = !in_array(true, array_column($results['rbac'], 'server_error'), true);
    $results['summary'] = ['routes_pass' => $allRoutePass, 'apis_pass' => $allApiPass, 'unauthenticated_pass' => $allUnauthPass, 'rbac_no_server_error' => $noRbacServerError];
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($allRoutePass && $allApiPass && $allUnauthPass && $noRbacServerError ? 0 : 1);
} finally {
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}
