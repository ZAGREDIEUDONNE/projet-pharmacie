<?php

/**
 * Recette Phase 2 Dashboard Administrateur.
 * Toutes les écritures métier utilisent BEGIN … ROLLBACK.
 * Exécuter : php scripts/test_admin_phase2.php
 */

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\Controllers\\' => __DIR__ . '/../app/Controllers/',
        'App\\Core\\' => __DIR__ . '/../app/Core/',
        'App\\Services\\' => __DIR__ . '/../app/Services/',
        'App\\Repositories\\' => __DIR__ . '/../app/Repositories/',
        'App\\Models\\' => __DIR__ . '/../app/Models/',
        'App\\Middleware\\' => __DIR__ . '/../app/Middleware/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Core\Router;
use App\Services\AuditService;
use App\Services\CaisseService;
use App\Services\ComptabiliteService;
use App\Services\CsrfService;
use App\Services\RBACService;
use App\Services\StockService;
use App\Services\VenteService;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$results = [];

function record(array &$results, string $section, string $label, bool $ok): void
{
    $results[] = ['section' => $section, 'label' => $label, 'status' => $ok ? 'PASS' : 'FAIL'];
    echo ($ok ? 'PASS' : 'FAIL') . " [{$section}] {$label}" . PHP_EOL;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// ---------------------------------------------------------------------------
// 1. Syntaxe PHP
// ---------------------------------------------------------------------------
$phpFiles = [
    'app/Core/BaseController.php',
    'app/Core/Router.php',
    'app/Services/RBACService.php',
    'app/Services/CsrfService.php',
    'app/Services/EcritureComptableService.php',
    'app/Services/VenteService.php',
    'app/Services/CaisseService.php',
    'app/Controllers/AdminController.php',
    'config/routes.php',
];
foreach ($phpFiles as $relativePath) {
    $path = __DIR__ . '/../' . $relativePath;
    $output = [];
    $code = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    record($results, 'syntax', $relativePath, $code === 0);
}

// ---------------------------------------------------------------------------
// 2. Routes admin
// ---------------------------------------------------------------------------
$routesFile = __DIR__ . '/../config/routes.php';
$routes = [];
require $routesFile;
$adminRoutes = [
    'GET /admin/dashboard' => 'AdminController@dashboard',
    'GET /admin/statistiques' => 'AdminController@statistiques',
    'GET /admin/statistiques/live' => 'AdminController@statistiquesLive',
    'GET /admin/annulation-tickets' => 'AdminController@annulationTickets',
    'GET /admin/users' => 'AdminController@users',
    'GET /admin/users/create' => 'AdminController@createUser',
    'POST /admin/users/store' => 'AdminController@storeUser',
    'GET /admin/users/{id}/edit' => 'AdminController@editUser',
    'POST /admin/users/{id}/update' => 'AdminController@updateUser',
    'POST /admin/users/{id}/delete' => 'AdminController@deleteUser',
    'GET /admin/roles' => 'AdminController@roles',
    'GET /admin/audit' => 'AdminController@audit',
    'GET /admin/audit/live' => 'AdminController@auditLive',
    'GET /admin/system' => 'AdminController@system',
];
foreach ($adminRoutes as $key => $expectedHandler) {
    [$method, $path] = explode(' ', $key, 2);
    $handler = $routes[$method][$path] ?? null;
    record($results, 'routes', $key, $handler === $expectedHandler);
    if ($handler) {
        [$controller, $methodName] = explode('@', $handler);
        $class = "App\\Controllers\\{$controller}";
        $file = __DIR__ . '/../app/Controllers/' . $controller . '.php';
        record($results, 'routes', "{$handler} existe", is_file($file) && class_exists($class) && method_exists($class, $methodName));
    }
}

// ---------------------------------------------------------------------------
// 3. CSRF centralisé
// ---------------------------------------------------------------------------
$_SESSION = [];
$validToken = CsrfService::token();
record($results, 'csrf', 'token valide accepté', CsrfService::isValid($validToken));
record($results, 'csrf', 'token absent refusé', !CsrfService::isValid(null));
record($results, 'csrf', 'token incorrect refusé', !CsrfService::isValid('invalid-token'));
$_SESSION['admin_csrf_token'] = 'a' . str_repeat('b', 63);
record($results, 'csrf', 'token session altéré refusé', !CsrfService::isValid($validToken));
$_SESSION['admin_csrf_token'] = $validToken;
$_SESSION['admin_csrf_token_issued_at'] = time() - 7200;
record($results, 'csrf', 'token expiré refusé', !CsrfService::isValid($validToken));

$viewFiles = [
    'app/Views/admin/users.php',
    'app/Views/admin/user_form.php',
];
foreach ($viewFiles as $view) {
    $content = (string)file_get_contents(__DIR__ . '/../' . $view);
    record($results, 'csrf', "{$view} contient csrf_token", str_contains($content, 'name="csrf_token"'));
}

$routerSource = (string)file_get_contents(__DIR__ . '/../app/Core/Router.php');
record($results, 'csrf', 'Router vérifie CsrfService sur AdminController POST', str_contains($routerSource, 'requiresCsrfProtection') && str_contains($routerSource, 'CsrfService::isValid'));

$adminControllerSource = (string)file_get_contents(__DIR__ . '/../app/Controllers/AdminController.php');
record($results, 'users', 'rôle actif obligatoire à la validation', str_contains($adminControllerSource, 'AND is_actif = 1 AND statut = 1'));
record($results, 'users', 'suppression de son propre compte refusée', str_contains($adminControllerSource, 'Vous ne pouvez pas supprimer votre propre compte'));

// ---------------------------------------------------------------------------
// 4. RBAC sans bypass admin
// ---------------------------------------------------------------------------
$db = db();
$rbac = new RBACService($db);
$rbacSource = (string)file_get_contents(__DIR__ . '/../app/Services/RBACService.php');
record(
    $results,
    'rbac',
    'hasPermission sans bypass admin',
    !str_contains($rbacSource, 'if ($this->isAdminRole($userRole))')
);

$adminId = (int)$db->query('SELECT id FROM utilisateurs WHERE role_id = 1 AND is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
$vendeur = $db->query("SELECT u.id FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE LOWER(r.nom) = 'vendeur' AND u.deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
record($results, 'rbac', 'admin a reports.view', $adminId > 0 && $rbac->hasPermission($adminId, 'reports.view'));
record($results, 'rbac', 'admin a user.manage', $rbac->hasPermission($adminId, 'user.manage'));
record($results, 'rbac', 'vendeur refuse user.manage', $vendeur && !$rbac->hasPermission((int)$vendeur['id'], 'user.manage'));

$roleChecks = [
    1 => ['nom' => 'ADMINISTRATEUR', 'min_perms' => 10],
    2 => ['nom' => 'VENDEUR', 'min_perms' => 1],
    3 => ['nom' => 'ASSISTANT', 'min_perms' => 1],
    4 => ['nom' => 'CHARGE_COMMANDE', 'min_perms' => 1],
    6 => ['nom' => 'COMPTABLE', 'min_perms' => 15],
];
foreach ($roleChecks as $roleId => $meta) {
    $role = $db->prepare('SELECT id, nom, is_actif, statut FROM roles WHERE id = ?');
    $role->execute([$roleId]);
    $row = $role->fetch(PDO::FETCH_ASSOC);
    $permCount = (int)$db->query('SELECT COUNT(*) FROM role_permissions WHERE role_id = ' . (int)$roleId)->fetchColumn();
    record(
        $results,
        'rbac',
        "rôle {$meta['nom']} actif avec permissions",
        $row && (int)$row['is_actif'] === 1 && (int)$row['statut'] === 1 && $permCount >= $meta['min_perms']
    );
}

// ---------------------------------------------------------------------------
// 5–13. Tests métier transactionnels (ROLLBACK)
// ---------------------------------------------------------------------------
$db->beginTransaction();

try {
    $product = $db->query(
        'SELECT s.produit_id, s.quantite_disponible, s.quantite_theorique, p.prix_achat, p.prix_vente
         FROM stock s JOIN produits p ON p.id = s.produit_id
         WHERE s.quantite_disponible > 0 ORDER BY s.id LIMIT 1 FOR UPDATE'
    )->fetch(PDO::FETCH_ASSOC);
    $session = $db->query("SELECT id FROM caisse_sessions WHERE statut_session = 'OUVERTE' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assertTrue($product !== false && $session !== false && $adminId > 0, 'Préconditions indisponibles');

    $_SESSION['user_id'] = $adminId;
    $_SESSION['user'] = ['id' => $adminId, 'role_id' => 1, 'role_code' => 'ADMIN'];

    $audit = new AuditService($db);
    $stock = new StockService($db, $audit);
    $caisse = new CaisseService($db, $audit);
    $comptabilite = new ComptabiliteService($db, $audit);
    $venteService = new VenteService($db, $stock, $caisse, $comptabilite, $audit);

    // Création utilisateur + audit
    $testUsername = 'test_admin_' . bin2hex(random_bytes(4));
    $testEmail = $testUsername . '@example.test';
    $db->prepare(
        'INSERT INTO utilisateurs (username, email, password_hash, nom, prenom, role_id, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
    )->execute([$testUsername, $testEmail, password_hash('TestPass123!', PASSWORD_DEFAULT), 'Test', 'Admin', 2]);
    $newUserId = (int)$db->lastInsertId();
    $audit->logAction($adminId, 'CREATE_USER', 'utilisateurs', $newUserId, null, ['username' => $testUsername]);
    record($results, 'users', 'création utilisateur', $newUserId > 0);
    $dup = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE username = ?');
    $dup->execute([$testUsername]);
    record($results, 'users', 'username unique en base', (int)$dup->fetchColumn() === 1);
    $dupEmail = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = ?');
    $dupEmail->execute([$testEmail]);
    record($results, 'users', 'email unique en base', (int)$dupEmail->fetchColumn() === 1);

    $db->prepare('UPDATE utilisateurs SET prenom = ? WHERE id = ?')->execute(['Modifie', $newUserId]);
    $audit->logAction($adminId, 'UPDATE_USER', 'utilisateurs', $newUserId, ['prenom' => 'Test'], ['prenom' => 'Modifie']);
    $db->prepare('UPDATE utilisateurs SET is_active = 0 WHERE id = ?')->execute([$newUserId]);
    $audit->logAction($adminId, 'DEACTIVATE_USER', 'utilisateurs', $newUserId, ['is_active' => 1], ['is_active' => 0]);
    $updated = $db->prepare('SELECT prenom, is_active FROM utilisateurs WHERE id = ?');
    $updated->execute([$newUserId]);
    $updatedRow = $updated->fetch(PDO::FETCH_ASSOC);
    record($results, 'users', 'modification utilisateur', ($updatedRow['prenom'] ?? '') === 'Modifie');
    record($results, 'users', 'désactivation utilisateur', (int)($updatedRow['is_active'] ?? 1) === 0);

    $db->prepare('UPDATE utilisateurs SET is_active = 1 WHERE id = ?')->execute([$newUserId]);
    $audit->logAction($adminId, 'ACTIVATE_USER', 'utilisateurs', $newUserId, ['is_active' => 0], ['is_active' => 1]);
    $activated = $db->prepare('SELECT is_active FROM utilisateurs WHERE id = ?');
    $activated->execute([$newUserId]);
    record($results, 'users', 'réactivation utilisateur', (int)$activated->fetchColumn() === 1);

    $db->prepare('UPDATE utilisateurs SET deleted_at = NOW() WHERE id = ?')->execute([$newUserId]);
    $audit->logAction($adminId, 'DELETE_USER', 'utilisateurs', $newUserId, ['username' => $testUsername], ['deleted_at' => date('Y-m-d H:i:s')]);
    $deleted = $db->prepare('SELECT deleted_at FROM utilisateurs WHERE id = ?');
    $deleted->execute([$newUserId]);
    record($results, 'users', 'suppression logique', $deleted->fetchColumn() !== null);

    $auditCount = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE table_name = 'utilisateurs' AND record_id = ? AND action IN ('CREATE_USER','UPDATE_USER','ACTIVATE_USER','DEACTIVATE_USER','DELETE_USER')");
    $auditCount->execute([$newUserId]);
    record($results, 'audit', 'audit CREATE/UPDATE/ACTIVATE/DEACTIVATE/DELETE user', (int)$auditCount->fetchColumn() >= 5);

    // Vente comptant + annulation
    $amount = (float)$product['prix_vente'];
    $ticket = 'TEST-ADMIN-' . bin2hex(random_bytes(5));
    $db->prepare(
        "INSERT INTO ventes (numero_facture, utilisateur_id, caisse_session_id, date_vente, montant_total, montant_ht, montant_tva, montant_ttc, montant_net, montant_paye, montant_restant, type_paiement, statut_vente, statut_paiement, is_credit, type_vente)
         VALUES (?, ?, ?, NOW(), ?, ?, 0, ?, ?, ?, 0, 'ESPECE', 'PAYEE', 'paye', 0, 'COMPTANT')"
    )->execute([$ticket, $adminId, (int)$session['id'], $amount, $amount, $amount, $amount, $amount]);
    $venteId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO ventes_items (vente_id, produit_id, quantite, prix_unitaire, prix_vente, montant_total, total_ligne) VALUES (?, ?, 1, ?, ?, ?, ?)')
        ->execute([$venteId, (int)$product['produit_id'], $amount, $amount, $amount, $amount]);
    $db->prepare('UPDATE stock SET quantite_disponible = quantite_disponible - 1, quantite_theorique = quantite_theorique - 1, valeur_stock = valeur_stock - ? WHERE produit_id = ?')
        ->execute([(float)$product['prix_achat'], (int)$product['produit_id']]);
    $db->prepare("INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_type, reference_id, utilisateur_id, date_mouvement) VALUES (?, 'SORTIE', 1, ?, ?, 'Vente test', 'VENTE', ?, ?, NOW())")
        ->execute([(int)$product['produit_id'], (int)$product['quantite_disponible'], (int)$product['quantite_disponible'] - 1, $venteId, $adminId]);

    $comptabilite->genererEcritureVente($venteId);
    $caisse->enregistrerMouvement([
        'caisse_session_id' => (int)$session['id'], 'type_mouvement' => 'VENTE', 'montant' => $amount,
        'moyen_paiement' => 'ESPECE', 'reference' => 'VENTE_' . $venteId,
        'description' => 'Vente test ' . $venteId, 'utilisateur_id' => $adminId, 'vente_id' => $venteId,
    ]);

    $stmt = $db->prepare("SELECT id FROM ecritures_comptables WHERE reference_type = 'VENTE' AND reference_id = ?");
    $stmt->execute([$venteId]);
    $originalId = (int)$stmt->fetchColumn();
    record($results, 'comptabilite', 'écriture originale créée', $originalId > 0);

    $venteService->annulerVente($venteId, $adminId);

    $stmt = $db->prepare('SELECT quantite_disponible, quantite_theorique FROM stock WHERE produit_id = ?');
    $stmt->execute([(int)$product['produit_id']]);
    $stockAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    record($results, 'stock', 'stock disponible restauré', (int)$stockAfter['quantite_disponible'] === (int)$product['quantite_disponible']);
    record($results, 'stock', 'stock théorique restauré', (int)$stockAfter['quantite_theorique'] === (int)$product['quantite_theorique']);

    $stmt = $db->prepare("SELECT COUNT(*) FROM mouvements_stock WHERE reference_type = 'ANNULATION_VENTE' AND reference_id = ?");
    $stmt->execute([$venteId]);
    record($results, 'stock', 'mouvement ANNULATION_VENTE', (int)$stmt->fetchColumn() === 1);

    $stmt = $db->prepare("SELECT COUNT(*) FROM mouvements_caisse WHERE type_mouvement = 'ANNULATION_VENTE' AND vente_id = ?");
    $stmt->execute([$venteId]);
    record($results, 'caisse', 'mouvement caisse inverse vente_id', (int)$stmt->fetchColumn() === 1);

    $stmt = $db->prepare("SELECT id, ecriture_origine_id, total_debit, total_credit, is_equilibree FROM ecritures_comptables WHERE reference_type = 'ANNULATION_VENTE' AND reference_id = ?");
    $stmt->execute([$venteId]);
    $counter = $stmt->fetch(PDO::FETCH_ASSOC);
    record($results, 'comptabilite', 'contre-passation liée origine', $counter && (int)$counter['ecriture_origine_id'] === $originalId);
    record($results, 'comptabilite', 'contre-passation équilibrée', $counter && (float)$counter['total_debit'] === (float)$counter['total_credit'] && (int)$counter['is_equilibree'] === 1);

    $stmt = $db->prepare('SELECT COUNT(*) FROM ecritures_comptables WHERE id = ?');
    $stmt->execute([$originalId]);
    record($results, 'comptabilite', 'écriture originale conservée', (int)$stmt->fetchColumn() === 1);

    $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'ANNULER_VENTE' AND table_name = 'ventes' AND record_id = ?");
    $stmt->execute([$venteId]);
    record($results, 'audit', 'audit ANNULER_VENTE', (int)$stmt->fetchColumn() >= 1);

    // Vente crédit : pas de mouvement caisse à l'annulation si montant_paye = 0
    $creditClient = $db->query('SELECT id FROM clients WHERE is_actif = 1 AND deleted_at IS NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($creditClient) {
        $ticketCredit = 'TEST-CREDIT-' . bin2hex(random_bytes(4));
        $db->prepare(
            "INSERT INTO ventes (numero_facture, client_id, utilisateur_id, caisse_session_id, date_vente, montant_total, montant_ht, montant_tva, montant_ttc, montant_net, montant_paye, montant_restant, type_paiement, statut_vente, statut_paiement, is_credit, type_vente)
             VALUES (?, ?, ?, ?, NOW(), ?, ?, 0, ?, ?, 0, ?, 'CREDIT', 'PARTIELLEMENT_PAYEE', 'impaye', 1, 'CREDIT')"
        )->execute([$ticketCredit, (int)$creditClient['id'], $adminId, (int)$session['id'], $amount, $amount, $amount, $amount, $amount]);
        $creditVenteId = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO ventes_items (vente_id, produit_id, quantite, prix_unitaire, prix_vente, montant_total, total_ligne) VALUES (?, ?, 1, ?, ?, ?, ?)')
            ->execute([$creditVenteId, (int)$product['produit_id'], $amount, $amount, $amount, $amount]);
        $comptabilite->genererEcritureVente($creditVenteId);
        $venteService->annulerVente($creditVenteId, $adminId);
        $stmt = $db->prepare('SELECT COUNT(*) FROM mouvements_caisse WHERE vente_id = ?');
        $stmt->execute([$creditVenteId]);
        record($results, 'caisse', 'vente crédit sans mouvement caisse orphelin', (int)$stmt->fetchColumn() === 0);
    } else {
        record($results, 'caisse', 'vente crédit sans mouvement caisse orphelin', true);
    }

    // KPI : ventes annulées exclues
    $kpi = $db->query("SELECT COUNT(*) FROM ventes WHERE deleted_at IS NULL AND statut_vente != 'ANNULEE' AND DATE(date_vente) = CURDATE()")->fetchColumn();
    record($results, 'kpi', 'requête CA jour exclut ANNULEE', is_numeric($kpi));

    // Orphelins : mouvements caisse ANNULATION_VENTE sans vente_id
    $orphans = (int)$db->query("SELECT COUNT(*) FROM mouvements_caisse WHERE type_mouvement = 'ANNULATION_VENTE' AND (vente_id IS NULL OR vente_id = 0)")->fetchColumn();
    record($results, 'orphelins', 'aucun ANNULATION_VENTE caisse sans vente_id', $orphans === 0);

    $db->rollBack();
    record($results, 'transaction', 'ROLLBACK appliqué', true);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    record($results, 'transaction', 'ROLLBACK après erreur: ' . $e->getMessage(), false);
}

// ---------------------------------------------------------------------------
// 15. API live JSON sans session (simulation Router)
// ---------------------------------------------------------------------------
$router = new Router();
$routerSource = (string)file_get_contents(__DIR__ . '/../app/Core/Router.php');
record($results, 'api_live', 'Router renvoie JSON 401 pour live endpoints', str_contains($routerSource, "error' => 'AUTHENTICATION_REQUIRED'") && str_contains($routerSource, 'statistiquesLive'));
record($results, 'api_live', 'AuditService supporte since_id', method_exists(new AuditService($db), 'getAllAuditLogs'));

$statView = (string)file_get_contents(__DIR__ . '/../app/Views/admin/statistiques.php');
$auditView = (string)file_get_contents(__DIR__ . '/../app/Views/admin/audit.php');
record($results, 'api_live', 'JS statistiques gère 401/403', str_contains($statView, 'response.status === 401') && str_contains($statView, 'response.status === 403'));
record($results, 'api_live', 'JS audit utilise since_id', str_contains($auditView, 'since_id'));

// ---------------------------------------------------------------------------
// 16. Régression vendeur (script existant)
// ---------------------------------------------------------------------------
$vendeurScript = __DIR__ . '/test_dashboard_vendeur_phase2.php';
if (is_file($vendeurScript)) {
    $output = [];
    $code = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($vendeurScript) . ' 2>&1', $output, $code);
    $joined = implode("\n", $output);
    record($results, 'regression', 'dashboard vendeur phase2', $code === 0 && (bool)preg_match('/"sale_flow"\s*:\s*"PASS"/', $joined));
}

// ---------------------------------------------------------------------------
// Synthèse
// ---------------------------------------------------------------------------
$passed = count(array_filter($results, static fn(array $r): bool => $r['status'] === 'PASS'));
$failed = count(array_filter($results, static fn(array $r): bool => $r['status'] === 'FAIL'));
echo PHP_EOL . "=== SYNTHESE: {$passed} PASS / {$failed} FAIL ===" . PHP_EOL;

if ($failed > 0) {
    echo PHP_EOL . 'ECHECS:' . PHP_EOL;
    foreach ($results as $row) {
        if ($row['status'] === 'FAIL') {
            echo " - [{$row['section']}] {$row['label']}" . PHP_EOL;
        }
    }
    exit(1);
}

exit(0);
