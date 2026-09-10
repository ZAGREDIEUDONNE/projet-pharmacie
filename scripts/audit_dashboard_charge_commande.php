<?php
/**
 * Script d'audit complet du Dashboard Charge de Commande
 * Vérifie l'architecture, les routes, les permissions, les services et les données
 */

// Configuration de la base de données
$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

echo "=== AUDIT DASHBOARD CHARGE DE COMMANDE ===\n\n";

// 1. Vérification des fichiers
echo "1. FICHIERS\n";
$files = [
    'Controller' => __DIR__ . '/../app/Controllers/ChargeCommandeController.php',
    'Vue Dashboard' => __DIR__ . '/../app/Views/commande/dashboard.php',
    'Service' => __DIR__ . '/../app/Services/ChargeCommandeService.php',
    'Middleware' => __DIR__ . '/../app/Middleware/ChargeCommandeMiddleware.php',
];

foreach ($files as $name => $path) {
    $exists = file_exists($path) ? '✓' : '✗';
    echo "   {$name}: {$exists}\n";
}

// 2. Vérification des routes
echo "\n2. ROUTES COMMANDE\n";
$routes = [
    '/commande/dashboard' => 'GET',
    '/commande/api/dashboard' => 'GET',
    '/commande/saisie' => ['GET', 'POST'],
    '/commande/edit' => 'GET',
    '/commande/update' => 'POST',
    '/commande/show' => 'GET',
    '/commande/print' => 'GET',
    '/commande/export-pdf' => 'GET',
    '/commande/export-excel' => 'GET',
    '/commande/cancel' => 'POST',
    '/commande/duplicate' => 'POST',
    '/commande/reception' => ['GET', 'POST'],
    '/commande/order-items' => 'GET',
    '/commande/mouvements' => 'GET',
    '/commande/historique' => 'GET',
    '/commande/update-status' => 'POST',
    '/commande/send' => 'POST',
];

foreach ($routes as $route => $methods) {
    $methods = (array)$methods;
    echo "   {$route}: " . implode(', ', $methods) . "\n";
}
echo "   Total: " . count($routes) . " routes\n";

// 3. Vérification des permissions
echo "\n3. PERMISSIONS CHARGE COMMANDE\n";
$permissions = [
    'view_stock',
    'add_stock',
    'receive_products',
    'create_supplier_orders',
    'view_supplier_orders',
    'edit_supplier_orders',
    'send_supplier_orders',
    'export_supplier_orders',
    'view_stock_movements',
    'stock.create_product',
    'product.price.update',
    'product.price.history',
    'stock.update_product',
    'stock.adjust',
    'stock.view_expiry',
];

$stmt = $db->query("SELECT nom FROM permissions WHERE nom IN ('" . implode("','", $permissions) . "')");
$existingPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($permissions as $perm) {
    $exists = in_array($perm, $existingPerms) ? '✓' : '✗';
    echo "   {$perm}: {$exists}\n";
}

// 4. Vérification du rôle charge_commande
echo "\n4. RÔLE CHARGE COMMANDE\n";
$stmt = $db->query("SELECT id, nom, description FROM roles WHERE LOWER(nom) IN ('commande', 'charge_commande', 'charge de commande')");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($roles as $role) {
    echo "   ID: {$role['id']}, Nom: {$role['nom']}, Description: {$role['description']}\n";
}

// 5. Vérification des tables
echo "\n5. TABLES UTILISÉES\n";
$tables = [
    'produits',
    'stock',
    'stock_entries',
    'supplier_orders',
    'supplier_order_items',
    'receptions',
    'reception_items',
    'mouvements_stock',
    'fournisseurs',
    'categories',
    'lots',
    'inventaires',
    'inventaire_articles',
];

foreach ($tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
    $exists = $stmt->rowCount() > 0 ? '✓' : '✗';
    echo "   {$table}: {$exists}\n";
}

// 6. Données actuelles
echo "\n6. DONNÉES ACTUELLES\n";

// Nombre de produits
$stmt = $db->query("SELECT COUNT(*) FROM produits WHERE is_actif = 1 AND deleted_at IS NULL");
$produits = $stmt->fetchColumn();
echo "   Produits actifs: {$produits}\n";

// Nombre de fournisseurs
$stmt = $db->query("SELECT COUNT(*) FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL");
$fournisseurs = $stmt->fetchColumn();
echo "   Fournisseurs actifs: {$fournisseurs}\n";

// Commandes en cours
$stmt = $db->query("SELECT COUNT(*) FROM supplier_orders WHERE statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')");
$commandesEnCours = $stmt->fetchColumn();
echo "   Commandes en cours: {$commandesEnCours}\n";

// Réceptions récentes
$stmt = $db->query("SELECT COUNT(*) FROM receptions WHERE date_reception >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$receptionsRecentes = $stmt->fetchColumn();
echo "   Réceptions récentes (7j): {$receptionsRecentes}\n";

// Ruptures de stock
$stmt = $db->query("SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND COALESCE(s.quantite_disponible, 0) <= 0");
$ruptures = $stmt->fetchColumn();
echo "   Ruptures de stock: {$ruptures}\n";

// Stock sous seuil
$stmt = $db->query("SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND COALESCE(s.quantite_disponible, 0) <= p.stock_alerte AND COALESCE(s.quantite_disponible, 0) > 0");
$stockSousSeuil = $stmt->fetchColumn();
echo "   Stock sous seuil: {$stockSousSeuil}\n";

// 7. Méthodes du controller
echo "\n7. MÉTHODES CONTROLLER\n";
$controllerContent = file_get_contents(__DIR__ . '/../app/Controllers/ChargeCommandeController.php');
preg_match_all('/public function (\w+)/', $controllerContent, $matches);
$methods = $matches[1];

foreach ($methods as $method) {
    echo "   {$method}()\n";
}
echo "   Total: " . count($methods) . " méthodes publiques\n";

// 8. Méthodes du service
echo "\n8. MÉTHODES SERVICE\n";
$serviceContent = file_get_contents(__DIR__ . '/../app/Services/ChargeCommandeService.php');
preg_match_all('/public function (\w+)/', $serviceContent, $matches);
$methods = $matches[1];

foreach ($methods as $method) {
    echo "   {$method}()\n";
}
echo "   Total: " . count($methods) . " méthodes publiques\n";

echo "\n=== FIN DE L'AUDIT ===\n";
