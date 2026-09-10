<?php
/**
 * Recette finale du dashboard Assistant. Aucun test n'écrit en base.
 * Exécuter : php scripts/test_dashboard_assistant_final.php
 */

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require_once $file;
    }
});
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Services\AssistantDashboardService;
use App\Services\RBACService;

$results = [];
function checkAssistant(array &$results, string $label, bool $ok): void {
    $results[] = $ok;
    echo ($ok ? 'PASS' : 'FAIL') . " [assistant] {$label}" . PHP_EOL;
}

$root = dirname(__DIR__);
foreach ([
    'app/Controllers/AssistantController.php',
    'app/Services/AssistantDashboardService.php',
    'app/Views/assistant/dashboard.php',
] as $file) {
    $output = []; $exit = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($root . '/' . $file) . ' 2>&1', $output, $exit);
    checkAssistant($results, "syntaxe {$file}", $exit === 0);
}

$routes = require $root . '/config/routes.php';
foreach ([
    'GET /assistant/dashboard' => 'AssistantController@dashboard',
    'GET /assistant/api/dashboard' => 'AssistantController@apiDashboard',
    'GET /vente/create' => 'VenteController@create',
    'GET /clients' => 'ClientController@index',
    'GET /caisse/etat' => 'CaisseController@etat',
    'GET /stock/alerts' => 'StockAlertController@index',
] as $key => $handler) {
    [$method, $path] = explode(' ', $key, 2);
    checkAssistant($results, "route {$key}", ($routes[$method][$path] ?? null) === $handler);
}

$db = db();
$schemas = [
    'ventes' => ['id', 'numero_facture', 'date_vente', 'montant_net', 'montant_paye', 'statut_vente', 'deleted_at'],
    'clients' => ['id', 'nom', 'prenom', 'deleted_at'],
    'produits' => ['id', 'stock_alerte', 'stock_securite', 'is_actif', 'deleted_at'],
    'stock' => ['produit_id', 'quantite_disponible'],
    'lots' => ['date_peremption', 'quantite_restante', 'is_actif'],
    'caisse_sessions' => ['caissier_id', 'statut_session', 'date_ouverture'],
];
foreach ($schemas as $table => $columns) {
    $actual = array_column($db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    checkAssistant($results, "schéma {$table}", count(array_diff($columns, $actual)) === 0);
}

$assistantId = (int)$db->query("SELECT u.id FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE LOWER(r.nom) = 'assistant' AND u.deleted_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
$rbac = new RBACService($db);
foreach (['vente.create', 'vente.view', 'client.view', 'client.create', 'client.update', 'caisse.view', 'stock.view', 'cancel_ticket', 'suivi_client.view'] as $permission) {
    checkAssistant($results, "RBAC Assistant {$permission}", $assistantId > 0 && $rbac->hasPermission($assistantId, $permission));
}
checkAssistant($results, 'RBAC Assistant refuse user.manage', $assistantId > 0 && !$rbac->hasPermission($assistantId, 'user.manage'));
checkAssistant($results, 'RBAC Assistant refuse comptabilite_view', $assistantId > 0 && !$rbac->hasPermission($assistantId, 'comptabilite_view'));

$overview = (new AssistantDashboardService($db))->getOverview($assistantId);
checkAssistant($results, 'API service retourne les quatre zones minimales', array_keys($overview) === ['widgets', 'cash_register', 'alerts', 'recent_sales']);
checkAssistant($results, 'KPI utiles présents', count(array_diff(['ca_jour', 'ventes_jour', 'montant_encaisse', 'alertes'], array_keys($overview['widgets'] ?? []))) === 0);
checkAssistant($results, 'caisse sans montant sensible', !array_key_exists('theoretical_amount', $overview['cash_register'] ?? []));
checkAssistant($results, 'ventes annulées absentes de la liste récente', !in_array('ANNULEE', array_column($overview['recent_sales'] ?? [], 'statut_vente'), true));

$view = (string)file_get_contents($root . '/app/Views/assistant/dashboard.php');
checkAssistant($results, 'AJAX traite 401/403', str_contains($view, 'response.status === 401') && str_contains($view, 'response.status === 403'));
checkAssistant($results, 'UI ne rend pas de montant de caisse', !str_contains($view, 'cash.theoretical_amount'));
$router = (string)file_get_contents($root . '/app/Core/Router.php');
checkAssistant($results, 'API Assistant non authentifiée prévue en JSON', str_contains($router, "AssistantController' && \$methodName === 'apiDashboard"));

$passed = count(array_filter($results));
$failed = count($results) - $passed;
echo "=== SYNTHESE: {$passed} PASS / {$failed} FAIL ===" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
