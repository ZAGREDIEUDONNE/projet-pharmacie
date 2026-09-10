<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT FONCTIONNEL COMPLET - DASHBOARD ASSISTANT ===\n\n";

    // 1. Analyse des permissions actuelles du rôle Assistant
    echo "=== 1. PERMISSIONS ACTUELLES DU RÔLE ASSISTANT ===\n";
    $stmt = $pdo->prepare("SELECT id, nom FROM roles WHERE nom = 'assistant'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo "ERREUR: Le rôle 'assistant' n'existe pas.\n";
        exit;
    }

    echo "Rôle: {$role['nom']} (ID: {$role['id']})\n\n";

    $stmt = $pdo->prepare("
        SELECT p.nom, p.description, p.module
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.module, p.nom
    ");
    $stmt->execute([$role['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Permissions actuelles (" . count($permissions) . "):\n";
    foreach ($permissions as $perm) {
        echo "- {$perm['nom']} ({$perm['module']})\n";
    }
    echo "\n";

    // 2. Analyse des actions du dashboard
    echo "=== 2. ACTIONS DU DASHBOARD ===\n";
    $actions = [
        ['Créer Client', 'create_client', '/clients/create', 'ClientController@create'],
        ['Modifier Client', 'edit_client', '/clients', 'ClientController@index'],
        ['Vente', 'make_sale', '/vente/create', 'VenteController@create'],
        ['Annuler Ticket', 'cancel_ticket', '/assistant/annulation-ticket', 'AssistantController@annulationTickets'],
        ['Remise', 'apply_discount', '/assistant/remise', 'AssistantController@remise'],
        ['Arrêt Caisse', 'close_cash_register', '/assistant/arret-caisse', 'AssistantController@arretCaisse'],
        ['Voir Statistiques', 'view_statistics', '/assistant/statistiques', 'AssistantController@statistiques'],
        ['Mouvements Stock', 'view_stock_movements', '/assistant/mouvements-produits', 'AssistantController@mouvementsProduits'],
        ['Préparation Commande', 'prepare_orders', '/assistant/preparation-commandes', 'AssistantController@preparationCommandes']
    ];

    foreach ($actions as $action) {
        $nom = $action[0];
        $permission = $action[1];
        $route = $action[2];
        $controller = $action[3];

        // Vérifier si la permission existe
        $hasPermission = false;
        foreach ($permissions as $perm) {
            if ($perm['nom'] === $permission) {
                $hasPermission = true;
                break;
            }
        }

        echo "$nom:\n";
        echo "  Permission: $permission - " . ($hasPermission ? 'OK' : 'MANQUANTE') . "\n";
        echo "  Route: $route\n";
        echo "  Contrôleur: $controller\n\n";
    }

    // 3. Analyse des KPIs
    echo "=== 3. ANALYSE DES KPIS ===\n";
    $kpis = [
        'ventes_jour' => 'Nombre de ventes du jour',
        'ca_jour' => 'Chiffre d\'affaires du jour',
        'montant_encaisse' => 'Montant encaissé du jour',
        'tickets_annules' => 'Tickets annulés du jour',
        'clients_actifs' => 'Nombre de clients actifs',
        'produits_alerte' => 'Produits en alerte stock',
        'commandes_attente' => 'Commandes en attente',
        'caisse_ouverte' => 'Caisse ouverte (0/1)'
    ];

    foreach ($kpis as $kpi => $description) {
        echo "$kpi: $description\n";
    }
    echo "\n";

    // 4. Vérification des données sensibles potentielles
    echo "=== 4. DONNÉES SENSIBLES POTENTIELLES ===\n";
    $sensitiveData = [
        'solde_caisse' => 'Solde global de caisse',
        'benefice' => 'Bénéfice',
        'marge' => 'Marge',
        'cout_achat' => 'Coût d\'achat',
        'resultat_financier' => 'Résultat financier',
        'salaires' => 'Salaires',
        'donnees_comptables' => 'Données comptables'
    ];

    echo "Vérification dans AssistantDashboardService:\n";
    $serviceFile = 'app/Services/AssistantDashboardService.php';
    if (file_exists($serviceFile)) {
        $content = file_get_contents($serviceFile);
        foreach ($sensitiveData as $key => $description) {
            if (strpos($content, $key) !== false) {
                echo "- $description ($key): PRÉSENT\n";
            } else {
                echo "- $description ($key): ABSENT\n";
            }
        }
    }
    echo "\n";

    // 5. Analyse des routes de vente
    echo "=== 5. ANALYSE DES ROUTES DE VENTE ===\n";
    $venteRoutes = [
        '/vente/create' => 'VenteController@create',
        '/vente/store' => 'VenteController@store',
        '/vente/cancel-ticket' => 'VenteController@cancelTicket'
    ];

    foreach ($venteRoutes as $route => $controller) {
        echo "$route → $controller\n";
    }
    echo "\n";

    // 6. Analyse des routes de caisse
    echo "=== 6. ANALYSE DES ROUTES DE CAISSE ===\n";
    $caisseRoutes = [
        '/caisse/etat' => 'CaisseController@etat',
        '/caisse/fermeture' => 'CaisseController@fermeture',
        '/caisse/traiter-fermeture' => 'CaisseController@traiterFermeture'
    ];

    foreach ($caisseRoutes as $route => $controller) {
        echo "$route → $controller\n";
    }
    echo "\n";

    // 7. Analyse des routes de stock
    echo "=== 7. ANALYSE DES ROUTES DE STOCK ===\n";
    $stockRoutes = [
        '/stock' => 'StockController@dashboard',
        '/stock/mouvements' => 'StockController@mouvements',
        '/stock/commandes-automatiques' => 'StockController@commandesAutomatiques'
    ];

    foreach ($stockRoutes as $route => $controller) {
        echo "$route → $controller\n";
    }
    echo "\n";

    // 8. Analyse des routes de clients
    echo "=== 8. ANALYSE DES ROUTES DE CLIENTS ===\n";
    $clientRoutes = [
        '/clients' => 'ClientController@index',
        '/clients/create' => 'ClientController@create',
        '/clients/statistiques' => 'ClientController@statistiques'
    ];

    foreach ($clientRoutes as $route => $controller) {
        echo "$route → $controller\n";
    }
    echo "\n";

    // 9. Vérification de la cohérence des tables
    echo "=== 9. VÉRIFICATION DES TABLES UTILISÉES ===\n";
    $tables = ['ventes', 'clients', 'produits', 'stock', 'supplier_orders', 'supplier_order_items', 'commandes', 'commande_items', 'fournisseurs', 'caisse_sessions', 'mouvements_caisse', 'utilisateurs', 'mouvements_stock', 'audit_logs'];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "$table: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 10. Analyse des permissions par module
    echo "=== 10. PERMISSIONS PAR MODULE ===\n";
    $modules = [];
    foreach ($permissions as $perm) {
        $module = $perm['module'];
        if (!isset($modules[$module])) {
            $modules[$module] = [];
        }
        $modules[$module][] = $perm['nom'];
    }

    foreach ($modules as $module => $perms) {
        echo "$module (" . count($perms) . "):\n";
        foreach ($perms as $perm) {
            echo "  - $perm\n";
        }
    }
    echo "\n";

    echo "=== FIN DE L'AUDIT ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
