<?php
/**
 * Audit RBAC — accès Dashboard Administrateur.
 * Compare les permissions demandées par chaque option du menu admin
 * aux permissions réellement attribuées au rôle ADMINISTRATEUR (role_id=1).
 */
require_once __DIR__ . '/../config/database.php';

$db = db();

// --- Options du dashboard admin (sidebar + cartes + modules) ---
$options = [
    ['label' => 'Dashboard', 'route' => 'GET /admin/dashboard', 'url' => '/admin/dashboard', 'permission' => 'reports.view', 'role' => 1, 'controller' => 'AdminController@dashboard'],
    ['label' => 'Vente (sidebar)', 'route' => 'GET /vente/create', 'url' => '/vente/create', 'permission' => 'vente.create', 'role' => null, 'controller' => 'VenteController@create'],
    ['label' => 'Utilisateurs', 'route' => 'GET /admin/users', 'url' => '/admin/users', 'permission' => 'user.manage', 'role' => 1, 'controller' => 'AdminController@users'],
    ['label' => 'Roles', 'route' => 'GET /admin/roles', 'url' => '/admin/roles', 'permission' => 'user.manage', 'role' => 1, 'controller' => 'AdminController@roles'],
    ['label' => 'Statistiques', 'route' => 'GET /admin/statistiques', 'url' => '/admin/statistiques', 'permission' => 'reports.view', 'role' => 1, 'controller' => 'AdminController@statistiques'],
    ['label' => 'Stock (sidebar)', 'route' => 'GET /stock', 'url' => '/stock', 'permission' => '(auth only / role bypass admin)', 'role' => null, 'controller' => 'StockController@index'],
    ['label' => 'Caisse (sidebar)', 'route' => 'GET /caisse/etat', 'url' => '/caisse/etat', 'permission' => 'caisse.view', 'role' => null, 'controller' => 'CaisseController@etat'],
    ['label' => 'Suivi Client (sidebar)', 'route' => 'GET /suivi-client', 'url' => '/suivi-client', 'permission' => 'suivi_client.view', 'role' => null, 'controller' => 'SuiviClientController@index'],
    ['label' => 'SYSCOHADA (sidebar)', 'route' => 'GET /comptabilite', 'url' => '/comptabilite', 'permission' => '(auth only)', 'role' => null, 'controller' => 'ComptabiliteController@index'],
    ['label' => 'Traçabilité', 'route' => 'GET /admin/audit', 'url' => '/admin/audit', 'permission' => 'audit.view', 'role' => 1, 'controller' => 'AdminController@audit'],
    ['label' => 'Systeme', 'route' => 'GET /admin/system', 'url' => '/admin/system', 'permission' => 'settings.manage', 'role' => 1, 'controller' => 'AdminController@system'],
    ['label' => 'Faire une vente', 'route' => 'GET /vente/create', 'url' => '/vente/create', 'permission' => 'vente.create', 'role' => null, 'controller' => 'VenteController@create'],
    ['label' => 'Permissions', 'route' => 'GET /admin/roles#permissions', 'url' => '/admin/roles#permissions', 'permission' => 'user.manage', 'role' => 1, 'controller' => 'AdminController@roles'],
    ['label' => 'Fournisseurs', 'route' => 'GET /fournisseurs', 'url' => '/fournisseurs', 'permission' => '(auth / stock view)', 'role' => null, 'controller' => 'StockController@listFournisseurs'],
    ['label' => 'Flux de stock', 'route' => 'GET /stock/flux', 'url' => '/stock/flux', 'permission' => 'stock.flux', 'role' => null, 'controller' => 'StockController@flux'],
    ['label' => 'Inventaire', 'route' => 'GET /inventaire', 'url' => '/inventaire', 'permission' => 'stock.inventory', 'role' => null, 'controller' => 'InventaireController@index'],
    ['label' => 'Créances clients', 'route' => 'GET /finance/clients', 'url' => '/finance/clients', 'permission' => 'finance.clients', 'role' => null, 'controller' => 'FinanceController@clients'],
    ['label' => 'Dettes fournisseurs', 'route' => 'GET /finance/fournisseurs', 'url' => '/finance/fournisseurs', 'permission' => 'finance.suppliers', 'role' => null, 'controller' => 'FinanceController@fournisseurs'],
    ['label' => 'Plafonds remise', 'route' => 'GET /finance/remises-limites', 'url' => '/finance/remises-limites', 'permission' => 'discount.manage_limits', 'role' => null, 'controller' => 'FinanceController@remisesLimites'],
    ['label' => 'Clients', 'route' => 'GET /clients', 'url' => '/clients', 'permission' => 'client.view', 'role' => null, 'controller' => 'ClientController@index'],
    ['label' => 'Créer un client', 'route' => 'GET /clients/creer', 'url' => '/clients/creer', 'permission' => 'client.create', 'role' => null, 'controller' => 'ClientController@create'],
    ['label' => 'Correction stock', 'route' => 'GET /stock/ajustement', 'url' => '/stock/ajustement', 'permission' => 'stock.adjust', 'role' => null, 'controller' => 'StockController@ajustement'],
    ['label' => 'Commandes fournisseurs', 'route' => 'GET /commande/historique', 'url' => '/commande/historique', 'permission' => 'view_supplier_orders', 'role' => null, 'controller' => 'ChargeCommandeController@orderHistory'],
    ['label' => 'Reception produits', 'route' => 'GET /commande/reception', 'url' => '/commande/reception', 'permission' => 'receive_products', 'role' => null, 'controller' => 'ChargeCommandeController@receptionForm'],
    ['label' => 'Annuler vente', 'route' => 'GET /admin/annulation-tickets', 'url' => '/admin/annulation-tickets', 'permission' => 'vente.cancel', 'role' => 1, 'controller' => 'AdminController@annulationTickets'],
    ['label' => 'Rapports stock', 'route' => 'GET /stock/rapports', 'url' => '/stock/rapports', 'permission' => '(auth only / blocks assistant)', 'role' => null, 'controller' => 'StockController@rapports'],
    ['label' => 'Produits', 'route' => 'GET /produits', 'url' => '/produits', 'permission' => '(auth / role bypass admin)', 'role' => null, 'controller' => 'ProduitController@index'],
    ['label' => 'Caisse dashboard', 'route' => 'GET /caisse', 'url' => '/caisse', 'permission' => 'caisse.view', 'role' => null, 'controller' => 'CaisseController@dashboard'],
    ['label' => 'Administration', 'route' => 'GET /admin', 'url' => '/admin', 'permission' => 'reports.view', 'role' => 1, 'controller' => 'AdminController@adminDashboard'],
];

// Permissions admin en base
$adminPerms = $db->query(
    "SELECT p.nom FROM role_permissions rp
     JOIN permissions p ON p.id = rp.permission_id
     WHERE rp.role_id = 1 ORDER BY p.nom"
)->fetchAll(PDO::FETCH_COLUMN);
$adminPermSet = array_flip($adminPerms);

// Toutes les permissions existantes
$allPerms = $db->query("SELECT nom FROM permissions ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
$allPermSet = array_flip($allPerms);

// Compte admin
$adminUser = $db->query(
    "SELECT u.id, u.username, u.email, u.role_id, r.nom AS role_nom
     FROM utilisateurs u JOIN roles r ON r.id = u.role_id
     WHERE u.role_id = 1 AND u.is_active = 1 AND u.deleted_at IS NULL
     ORDER BY u.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

echo "=== AUDIT RBAC DASHBOARD ADMIN ===\n\n";
echo "Compte admin: " . json_encode($adminUser, JSON_UNESCAPED_UNICODE) . "\n";
echo "Permissions admin (role_id=1): " . count($adminPerms) . "\n";
echo implode(', ', $adminPerms) . "\n\n";

echo "| Option | Route | Permission demandée | Permission Admin | Résultat |\n";
echo "|---|---|---|---|---|\n";

$blocked = [];
$missingInDb = [];

foreach ($options as $opt) {
    $perm = $opt['permission'];
    if (str_starts_with($perm, '(')) {
        $hasPerm = 'N/A';
        $result = 'OK (pas de permission RBAC)';
    } elseif ($perm === 'vente.cancel') {
        // AdminController checks vente.cancel; cancel_ticket also used elsewhere
        $hasPerm = isset($adminPermSet['vente.cancel']) ? 'OUI' : (isset($adminPermSet['cancel_ticket']) ? 'cancel_ticket only' : 'NON');
        $result = isset($adminPermSet['vente.cancel']) ? 'OK' : 'REFUSÉ';
        if ($result === 'REFUSÉ') {
            $blocked[] = $opt;
        }
    } else {
        $existsInPermissions = isset($allPermSet[$perm]);
        $hasPerm = isset($adminPermSet[$perm]) ? 'OUI' : 'NON';
        if (!$existsInPermissions) {
            $hasPerm = 'permission absente table permissions';
            $result = 'REFUSÉ (permission inexistante)';
            $missingInDb[] = $perm;
        } elseif (isset($adminPermSet[$perm])) {
            $result = 'OK';
        } else {
            $result = 'REFUSÉ';
            $blocked[] = $opt;
        }
    }
    echo "| {$opt['label']} | {$opt['url']} | {$perm} | {$hasPerm} | {$result} |\n";
}

echo "\n=== OPTIONS BLOQUÉES ===\n";
if (empty($blocked)) {
    echo "Aucune option bloquée par permission manquante.\n";
} else {
    foreach ($blocked as $b) {
        echo "- {$b['label']} ({$b['url']}) → {$b['permission']}\n";
    }
}

echo "\n=== PERMISSIONS MANQUANTES DANS TABLE permissions ===\n";
echo empty($missingInDb) ? "Aucune.\n" : implode("\n", array_unique($missingInDb)) . "\n";

// Permissions en base non attribuées à admin mais potentiellement utiles pour admin dashboard
$adminLegitimate = [
    'reports.view', 'user.manage', 'audit.view', 'settings.manage', 'vente.cancel', 'cancel_ticket',
    'vente.create', 'vente.view', 'client.view', 'client.create', 'client.manage',
    'caisse.view', 'caisse.manage', 'caisse.open',
    'stock.view', 'stock.manage', 'stock.adjust', 'stock.flux', 'stock.inventory',
    'finance.clients', 'finance.suppliers', 'discount.manage_limits',
    'suivi_client.view', 'suivi_client.create', 'suivi_client.update',
    'view_supplier_orders', 'receive_products', 'create_supplier_orders', 'edit_supplier_orders',
    'produit.create', 'produit.edit', 'produit.manage',
    'date.change', 'session.change',
    'comptabilite_view', 'plan_comptable_view', 'ecritures_view',
];

echo "\n=== PERMISSIONS LÉGITIMES ADMIN NON ATTRIBUÉES ===\n";
$missingLegit = [];
foreach ($adminLegitimate as $p) {
    if (isset($allPermSet[$p]) && !isset($adminPermSet[$p])) {
        $missingLegit[] = $p;
        echo "MANQUE: {$p}\n";
    } elseif (!isset($allPermSet[$p])) {
        echo "ABSENTE EN BASE: {$p}\n";
    }
}
if (empty($missingLegit)) {
    echo "Toutes les permissions légitimes identifiées sont attribuées.\n";
}

// Test RBACService
require_once __DIR__ . '/../app/Services/RBACService.php';
$rbac = new App\Services\RBACService($db);
$adminId = (int)($adminUser['id'] ?? 0);
echo "\n=== TEST RBACService hasPermission (admin id={$adminId}) ===\n";
$testPerms = array_unique(array_filter(array_column($options, 'permission'), fn($p) => !str_starts_with($p, '(')));
foreach ($testPerms as $p) {
    if (!isset($allPermSet[$p])) {
        echo "FAIL {$p}: permission inexistante en base\n";
        continue;
    }
    $ok = $rbac->hasPermission($adminId, $p);
    echo ($ok ? 'OK' : 'FAIL') . " {$p}\n";
}
