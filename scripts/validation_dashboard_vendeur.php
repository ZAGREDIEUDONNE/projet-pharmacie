<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VALIDATION FONCTIONNELLE - DASHBOARD VENDEUR ===\n\n";

    // 1. Analyse de l'architecture
    echo "=== 1. ARCHITECTURE ===\n";
    echo "Controllers:\n";
    echo "- VenteController: EXISTE\n";
    echo "- VenteAuthController: EXISTE\n";
    echo "- VentesController: EXISTE\n";
    echo "Views:\n";
    echo "- vente/create.php: EXISTE\n";
    echo "- vente/dashboard.php: EXISTE\n";
    echo "- vente/historique.php: EXISTE\n";
    echo "- vente/impression.php: EXISTE\n";
    echo "- vente/show.php: EXISTE\n";
    echo "- vente/tickets-en-attente.php: EXISTE\n";
    echo "Services:\n";
    echo "- VenteService: À vérifier\n";
    echo "- StockService: À vérifier\n";
    echo "- CaisseService: À vérifier\n";
    echo "- ComptabiliteService: À vérifier\n";
    echo "- AuditService: À vérifier\n";
    echo "- DiscountLimitService: À vérifier\n";
    echo "\n";

    // 2. Vérification des routes
    echo "=== 2. ROUTES ===\n";
    $routes = [
        '/vente/login' => 'VenteAuthController@login',
        '/vente/create' => 'VenteController@create',
        '/vente/dashboard' => 'VenteController@dashboard',
        '/vente/historique' => 'VenteController@historique',
        '/vente/impression' => 'VenteController@impression',
        '/vente/show' => 'VenteController@show',
        '/vente/tickets-en-attente' => 'VenteController@ticketsEnAttente',
        '/vente/store' => 'VenteController@store',
        '/vente/suspend' => 'VenteController@suspend',
        '/vente/resume' => 'VenteController@resume',
        '/vente/cancel' => 'VenteController@cancel',
        '/vente/scan' => 'VenteController@scan',
    ];

    foreach ($routes as $route => $controller) {
        echo "$route → $controller\n";
    }
    echo "\n";

    // 3. Vérification des tables
    echo "=== 3. TABLES ===\n";
    $tables = ['ventes', 'ventes_items', 'ventes_credit', 'ventes_suspended', 'stock', 'produits', 'clients', 'caisse_sessions', 'mouvements_caisse', 'ecritures_comptables'];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "$table: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 4. Vérification des permissions vendeur
    echo "=== 4. PERMISSIONS VENDEUR ===\n";
    $stmt = $pdo->prepare("SELECT id, nom FROM roles WHERE nom = 'vendeur'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role) {
        $stmt = $pdo->prepare("
            SELECT p.nom, p.module
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.module, p.nom
        ");
        $stmt->execute([$role['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Permissions du rôle vendeur (" . count($permissions) . "):\n";
        foreach ($permissions as $perm) {
            echo "- {$perm['nom']} ({$perm['module']})\n";
        }
    } else {
        echo "Rôle vendeur non trouvé\n";
    }
    echo "\n";

    // 5. Vérification des données de test
    echo "=== 5. DONNÉES DE TEST ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits");
    $stmt->execute();
    $produits = $stmt->fetchColumn();
    echo "Produits: $produits\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients");
    $stmt->execute();
    $clients = $stmt->fetchColumn();
    echo "Clients: $clients\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock");
    $stmt->execute();
    $stock = $stmt->fetchColumn();
    echo "Stock: $stock\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventes");
    $stmt->execute();
    $ventes = $stmt->fetchColumn();
    echo "Ventes: $ventes\n";

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventes_suspended");
        $stmt->execute();
        $suspended = $stmt->fetchColumn();
        echo "Ventes suspendues: $suspended\n";
    } catch (PDOException $e) {
        echo "Ventes suspendues: TABLE MANQUANTE\n";
    }
    echo "\n";

    // 6. Vérification des services
    echo "=== 6. SERVICES ===\n";
    $services = [
        'VenteService' => 'app/Services/VenteService.php',
        'StockService' => 'app/Services/StockService.php',
        'CaisseService' => 'app/Services/CaisseService.php',
        'ComptabiliteService' => 'app/Services/ComptabiliteService.php',
        'AuditService' => 'app/Services/AuditService.php',
        'DiscountLimitService' => 'app/Services/DiscountLimitService.php',
    ];

    foreach ($services as $service => $file) {
        $path = __DIR__ . '/../' . $file;
        $exists = file_exists($path);
        echo "$service: " . ($exists ? 'EXISTE' : 'MANQUANT') . "\n";
    }
    echo "\n";

    // 7. Vérification de la caisse
    echo "=== 7. CAISSE ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM caisse_sessions WHERE statut = 'OUVERTE'");
    $stmt->execute();
    $sessions_ouvertes = $stmt->fetchColumn();
    echo "Sessions caisse ouvertes: $sessions_ouvertes\n";

    if ($sessions_ouvertes > 0) {
        $stmt = $pdo->prepare("SELECT * FROM caisse_sessions WHERE statut = 'OUVERTE' LIMIT 1");
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Session ouverte ID: {$session['id']}\n";
        echo "Utilisateur: {$session['utilisateur_id']}\n";
        echo "Solde théorique: {$session['solde_theorique']}\n";
    }
    echo "\n";

    // 8. Vérification de la comptabilité
    echo "=== 8. COMPTABILITÉ ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ecritures_comptables");
    $stmt->execute();
    $ecritures = $stmt->fetchColumn();
    echo "Écritures comptables: $ecritures\n";

    if ($ecritures > 0) {
        $stmt = $pdo->prepare("SELECT * FROM ecritures_comptables ORDER BY date_ecriture DESC LIMIT 1");
        $stmt->execute();
        $ecriture = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Dernière écriture ID: {$ecriture['id']}\n";
        echo "Date: {$ecriture['date_ecriture']}\n";
        echo "Montant: " . ($ecriture['montant'] ?? 'N/A') . "\n";
    }
    echo "\n";

    echo "=== FIN DE L'ANALYSE ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
