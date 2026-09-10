<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT COMPLET - DASHBOARD ASSISTANT ===\n\n";

    // 1. Architecture actuelle
    echo "=== 1. ARCHITECTURE ACTUELLE ===\n";
    echo "View: app/Views/assistant/dashboard.php\n";
    echo "Route: /assistant/dashboard (GET) → AssistantController@dashboard\n";
    echo "API: /assistant/api/dashboard (GET) → AssistantController@apiDashboard\n";
    echo "Controller: app/Controllers/AssistantController.php\n";
    echo "Service: app/Services/AssistantDashboardService.php\n";
    echo "BaseController: app/Core/BaseController.php\n\n";

    // 2. Fonctionnalités existantes
    echo "=== 2. FONCTIONNALITÉS EXISTANTES ===\n";
    $actions = [
        'Créer Client' => ['permission' => 'create_client', 'route' => '/clients/create', 'controller' => 'ClientController@create'],
        'Modifier Client' => ['permission' => 'edit_client', 'route' => '/clients', 'controller' => 'ClientController@index'],
        'Vente' => ['permission' => 'make_sale', 'route' => '/vente/create', 'controller' => 'VenteController@create'],
        'Annuler Ticket' => ['permission' => 'cancel_ticket', 'route' => '/assistant/annulation-ticket', 'controller' => 'AssistantController@annulationTickets'],
        'Remise' => ['permission' => 'apply_discount', 'route' => '/assistant/remise', 'controller' => 'AssistantController@remise'],
        'Arrêt Caisse' => ['permission' => 'close_cash_register', 'route' => '/assistant/arret-caisse', 'controller' => 'AssistantController@arretCaisse'],
        'Voir Statistiques' => ['permission' => 'view_statistics', 'route' => '/assistant/statistiques', 'controller' => 'AssistantController@statistiques'],
        'Mouvements Stock' => ['permission' => 'view_stock_movements', 'route' => '/assistant/mouvements-produits', 'controller' => 'AssistantController@mouvementsProduits'],
        'Préparation Commande' => ['permission' => 'prepare_orders', 'route' => '/assistant/preparation-commandes', 'controller' => 'AssistantController@preparationCommandes']
    ];

    foreach ($actions as $nom => $info) {
        echo "$nom:\n";
        echo "  Permission: {$info['permission']}\n";
        echo "  Route: {$info['route']}\n";
        echo "  Contrôleur: {$info['controller']}\n\n";
    }

    // 3. KPIs existants
    echo "=== 3. KPIS EXISTANTS ===\n";
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

    // 4. Routes existantes
    echo "=== 4. ROUTES EXISTANTES ===\n";
    $routes = [
        '/assistant/dashboard' => 'AssistantController@dashboard',
        '/assistant/api/dashboard' => 'AssistantController@apiDashboard',
        '/assistant/vente-session' => 'AssistantController@venteSession',
        '/assistant/commandes' => 'AssistantController@commandes',
        '/assistant/remise' => 'AssistantController@remise',
        '/assistant/annulation-ticket' => 'AssistantController@annulationTickets',
        '/assistant/arret-caisse' => 'AssistantController@arretCaisse',
        '/assistant/facturation' => 'AssistantController@facturation',
        '/assistant/preparation-commandes' => 'AssistantController@preparationCommandes',
        '/assistant/statistiques' => 'AssistantController@statistiques',
        '/assistant/mouvements-produits' => 'AssistantController@mouvementsProduits',
        '/assistant/codes-acces' => 'AssistantController@codesAcces',
        '/assistant/stock' => 'StockController@index'
    ];

    foreach ($routes as $route => $controller) {
        echo "$route → $controller\n";
    }
    echo "\n";

    // 5. Permissions
    echo "=== 5. PERMISSIONS ===\n";
    $stmt = $pdo->prepare("SELECT id, nom FROM roles WHERE nom = 'assistant'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role) {
        $stmt = $pdo->prepare("
            SELECT p.nom, p.description, p.module
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.module, p.nom
        ");
        $stmt->execute([$role['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Permissions du rôle assistant (" . count($permissions) . "):\n";
        foreach ($permissions as $perm) {
            echo "- {$perm['nom']} ({$perm['module']})\n";
        }
    }
    echo "\n";

    // 6. Données affichées
    echo "=== 6. DONNÉES AFFICHÉES ===\n";
    echo "API /assistant/api/dashboard retourne:\n";
    echo "- widgets: KPIs (8 indicateurs)\n";
    echo "- stats: statistiques additionnelles\n";
    echo "- stock_alerts: alertes stock\n";
    echo "- pending_orders: commandes en attente\n";
    echo "- cash_register: état caisse\n";
    echo "- recent_sales: ventes récentes\n";
    echo "- recent_activity: activité récente\n";
    echo "- recent_stock_movements: mouvements stock\n";
    echo "- notifications: notifications\n";
    echo "- updated_at: timestamp\n\n";

    // 7. Tables utilisées
    echo "=== 7. TABLES UTILISÉES ===\n";
    $tables = ['ventes', 'clients', 'produits', 'stock', 'supplier_orders', 'supplier_order_items', 'commandes', 'commande_items', 'fournisseurs', 'caisse_sessions', 'mouvements_caisse', 'utilisateurs', 'mouvements_stock', 'audit_logs'];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "$table: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }
    echo "\n";

    // 8. Éléments redondants
    echo "=== 8. ÉLÉMENTS REDONDANTS ===\n";
    echo "- Sous-titre de l'en-tête: 'Ventes, caisse, clients et suivi operationnel'\n";
    echo "- Section 'Résumé de la journée': 4 indicateurs secondaires redondants avec les KPIs principaux\n";
    echo "- Section 'Essentiel': titre et sous-titre inutiles\n";
    echo "- Section 'Autres actions': 5 actions secondaires redondantes avec le menu latéral\n";
    echo "- Sous-menu Suivi Client: 9 liens vers un module séparé\n";
    echo "- 4 tableaux au total (2 visibles + 2 cachés)\n\n";

    // 9. Liens incorrects potentiels
    echo "=== 9. LIENS INCORRECTS POTENTIELS ===\n";
    echo "À vérifier:\n";
    echo "- /clients (Modifier Client) → redirige vers ClientController@index (liste) au lieu de edit\n";
    echo "- /assistant/stock → redirige vers StockController@index (dashboard stock)\n";
    echo "- Liens Suivi Client → module séparé, potentiellement incohérent avec rôle Assistant\n\n";

    // 10. Fonctionnalités manquantes
    echo "=== 10. FONCTIONNALITÉS MANQUANTES ===\n";
    echo "- Section 'Alertes à traiter' centralisée\n";
    echo "- Section 'Activité récente' compacte\n";
    echo "- Zone caisse simple avec état de session\n";
    echo "- Boutons d'action directs (Voir, Réimprimer, Annuler) dans le tableau des ventes récentes\n\n";

    // 11. Risques éventuels
    echo "=== 11. RISQUES ÉVENTUELS ===\n";
    echo "- Annulation Ticket: l'Assistant peut annuler directement sans validation supérieure\n";
    echo "- Arrêt Caisse: l'Assistant peut clôturer sans validation supérieure\n";
    echo "- Statistiques: pourraient révéler des informations réservées\n";
    echo "- solde_caisse: donnée sensible encore présente dans le code (commentée)\n";
    echo "- Permissions suivi_client: 7 permissions non utilisées par le contrôleur\n\n";

    echo "=== FIN DE L'AUDIT ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
