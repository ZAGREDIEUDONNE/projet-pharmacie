<?php

declare(strict_types=1);

/**
 * Vérifie que les options du dashboard sont accessibles avec la même structure
 * de session que celle créée par AuthController pour le rôle CHARGE_COMMANDE.
 */
session_save_path(sys_get_temp_dir());
session_id('test-mouvements-charge-commande');
session_start();

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$user = $db->query(
    "SELECT u.id, u.username, u.email, u.role_id, r.nom AS role_name, r.code AS role_code
     FROM utilisateurs u
     JOIN roles r ON r.id = u.role_id
     WHERE u.role_id = 4 AND u.is_active = 1
     ORDER BY u.id
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    fwrite(STDERR, "FAIL: aucun utilisateur CHARGE_COMMANDE actif n'est disponible.\n");
    exit(1);
}

$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'username' => (string)$user['username'],
    'email' => (string)$user['email'],
    'role_id' => (int)$user['role_id'],
    'role_code' => (string)$user['role_code'],
    'role_name' => (string)$user['role_name'],
];
$_SESSION['user_id'] = (int)$user['id'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/commande/mouvements';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$completed = false;
ob_start();
register_shutdown_function(function () use (&$completed): void {
    if ($completed) {
        return;
    }

    $output = ob_get_contents();
    if (str_contains($output, 'Acces refuse')) {
        fwrite(STDERR, "FAIL: requirePermission() a refusé l'accès.\n");
        return;
    }

    if (!empty($_SESSION['error'])) {
        fwrite(STDERR, "FAIL: le contrôleur a redirigé après erreur: {$_SESSION['error']}\n");
        return;
    }

    fwrite(STDERR, "FAIL: l'exécution s'est arrêtée avant le rendu de la page.\n");
});

(new App\Controllers\ChargeCommandeController())->mouvements();
$page = ob_get_clean();
$completed = true;

if (!str_contains($page, 'Mouvements de stock')) {
    fwrite(STDERR, "FAIL: la page des mouvements n'a pas été rendue.\n");
    exit(1);
}

$_SERVER['REQUEST_URI'] = '/inventaire';
$completed = false;
ob_start();
(new App\Controllers\InventaireController())->index();
$inventairePage = ob_get_clean();
$completed = true;

if (!str_contains($inventairePage, 'Inventaires')) {
    fwrite(STDERR, "FAIL: la page des inventaires n'a pas été rendue.\n");
    exit(1);
}

$_SERVER['REQUEST_URI'] = '/produits/catalogue-vendeur';
$completed = false;
ob_start();
(new App\Controllers\ProduitController())->catalogueVendeur();
$cataloguePage = ob_get_clean();
$completed = true;

if (!str_contains($cataloguePage, 'Catalogue des Produits')) {
    fwrite(STDERR, "FAIL: le catalogue des produits n'a pas été rendu.\n");
    exit(1);
}

$_SERVER['REQUEST_URI'] = '/stock/historique-prix';
$completed = false;
ob_start();
(new App\Controllers\StockController($db))->historiquePrix();
$prixPage = ob_get_clean();
$completed = true;

if (!str_contains($prixPage, 'Historique')) {
    fwrite(STDERR, "FAIL: l'historique des prix n'a pas été rendu.\n");
    exit(1);
}

$_SERVER['REQUEST_URI'] = '/produits/sortie-stock?return_to=/commande/dashboard';
$_GET['return_to'] = '/commande/dashboard';
$completed = false;
ob_start();
(new App\Controllers\ProduitController())->sortieStock();
$sortiePage = ob_get_clean();
$completed = true;

if (!str_contains($sortiePage, 'Sortie de Stock') || !str_contains($sortiePage, '/commande/dashboard')) {
    fwrite(STDERR, "FAIL: la page des sorties de stock n'a pas été rendue avec son retour.\n");
    exit(1);
}

echo "PASS: CHARGE_COMMANDE ({$user['username']}) -> mouvements, inventaire, catalogue, prix et sorties\n";
