<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== INVENTAIRE DASHBOARD ASSISTANT ===\n\n";

    // Contrôleur
    echo "=== CONTRÔLEUR ===\n";
    echo "Classe: AssistantController\n";
    echo "Fichier: app/Controllers/AssistantController.php\n";
    echo "Rôle requis: 3 (ASSISTANT)\n\n";

    // Service
    echo "=== SERVICE ===\n";
    echo "Classe: AssistantDashboardService\n";
    echo "Fichier: app/Services/AssistantDashboardService.php\n\n";

    // Routes
    echo "=== ROUTES ===\n";
    $routes = [
        'GET /assistant/dashboard' => 'dashboard()',
        'GET /assistant/api/dashboard' => 'apiDashboard()',
        'GET /assistant/commandes' => 'commandes()',
        'GET /assistant/vente-session' => 'venteSession()',
        'GET /assistant/remise' => 'remise()',
        'GET /assistant/preparation-commandes' => 'preparationCommandes()',
        'GET /assistant/mouvements-produits' => 'mouvementsProduits()',
        'GET /assistant/codes-acces' => 'codesAcces()',
        'GET /assistant/annulation-ticket' => 'annulationTickets()',
        'GET /assistant/annulation' => 'annulationTickets()',
        'GET /assistant/arret-caisse' => 'arretCaisse()',
        'GET /assistant/facturation' => 'facturation()',
        'GET /assistant/impression' => 'facturation()',
        'GET /assistant/statistiques' => 'statistiques()',
        'GET /assistant/stock' => 'stock()',
    ];
    foreach ($routes as $route => $method) {
        echo "$route → $method\n";
    }
    echo "\n";

    // Actions disponibles
    echo "=== ACTIONS DISPONIBLES (CARDS) ===\n";
    $actions = [
        'Creer Client' => ['permission' => 'create_client', 'url' => '/clients/creer'],
        'Modifier Client' => ['permission' => 'edit_client', 'url' => '/clients'],
        'Vente' => ['permission' => 'make_sale', 'url' => '/vente/create'],
        'Annuler Ticket' => ['permission' => 'cancel_ticket', 'url' => '/assistant/annulation-ticket'],
        'Remise' => ['permission' => 'apply_discount', 'url' => '/assistant/remise'],
        'Arret Caisse' => ['permission' => 'close_cash_register', 'url' => '/assistant/arret-caisse'],
        'Voir Statistiques' => ['permission' => 'view_statistics', 'url' => '/assistant/statistiques'],
        'Mouvements Stock' => ['permission' => 'view_stock_movements', 'url' => '/assistant/mouvements-produits'],
        'Preparation Commande' => ['permission' => 'prepare_orders', 'url' => '/assistant/preparer-commande'],
    ];
    foreach ($actions as $action => $details) {
        echo "- $action\n";
        echo "  Permission: {$details['permission']}\n";
        echo "  URL: {$details['url']}\n";
    }
    echo "\n";

    // Widgets du dashboard
    echo "=== WIDGETS DASHBOARD ===\n";
    $widgets = [
        'ventes_jour' => 'Nombre de ventes du jour',
        'ca_jour' => 'Chiffre d\'affaires du jour',
        'montant_encaisse' => 'Montant encaissé du jour',
        'tickets_annules' => 'Tickets annulés du jour',
        'clients_actifs' => 'Nombre de clients actifs',
        'produits_alerte' => 'Produits en alerte stock',
        'commandes_attente' => 'Commandes en attente',
        'caisse_ouverte' => 'Caisse ouverte (0/1)',
        'solde_caisse' => 'Solde théorique caisse',
    ];
    foreach ($widgets as $key => $desc) {
        echo "- $key: $desc\n";
    }
    echo "\n";

    // Tables utilisées
    echo "=== TABLES UTILISÉES ===\n";
    $tables = [
        'ventes' => 'Ventes du jour, récentes, annulées',
        'clients' => 'Clients actifs',
        'produits' => 'Alertes stock',
        'stock' => 'Alertes stock',
        'supplier_orders' => 'Commandes fournisseurs en attente',
        'supplier_order_items' => 'Détails commandes fournisseurs',
        'commandes' => 'Ancien système commandes (fallback)',
        'commande_items' => 'Ancien système commandes (fallback)',
        'fournisseurs' => 'Fournisseurs pour commandes',
        'caisse_sessions' => 'Session caisse ouverte',
        'mouvements_caisse' => 'Mouvements caisse',
        'utilisateurs' => 'Caissier, vendeur',
        'mouvements_stock' => 'Mouvements stock récents',
        'audit_logs' => 'Activité récente',
    ];
    foreach ($tables as $table => $usage) {
        echo "- $table: $usage\n";
    }
    echo "\n";

    // Colonnes utilisées par table
    echo "=== COLONNES UTILISÉES PAR TABLE ===\n\n";

    // ventes
    echo "TABLE: ventes\n";
    echo "Colonnes: date_vente, montant_net, montant_paye, statut_vente, deleted_at, numero_facture, type_paiement, client_id, utilisateur_id\n\n";

    // clients
    echo "TABLE: clients\n";
    echo "Colonnes: is_actif, deleted_at, prenom, nom\n\n";

    // produits
    echo "TABLE: produits\n";
    echo "Colonnes: id, nom, code_cip, is_actif, deleted_at, stock_alerte, stock_securite\n\n";

    // stock
    echo "TABLE: stock\n";
    echo "Colonnes: produit_id, quantite_disponible, quantite_theorique\n\n";

    // supplier_orders
    echo "TABLE: supplier_orders\n";
    echo "Colonnes: id, numero_commande, date_commande, date_livraison_prevue, statut, montant_total, fournisseur_id\n\n";

    // supplier_order_items
    echo "TABLE: supplier_order_items\n";
    echo "Colonnes: supplier_order_id, quantite_commandee, quantite_recue\n\n";

    // commandes (fallback)
    echo "TABLE: commandes (fallback)\n";
    echo "Colonnes: id, numero_commande, date_commande, date_livraison_prevue, statut_commande, montant_total, fournisseur_id, deleted_at\n\n";

    // commande_items (fallback)
    echo "TABLE: commande_items (fallback)\n";
    echo "Colonnes: commande_id, quantite_commandee, quantite_receptionnee, quantite_livree\n\n";

    // fournisseurs
    echo "TABLE: fournisseurs\n";
    echo "Colonnes: id, nom\n\n";

    // caisse_sessions
    echo "TABLE: caisse_sessions\n";
    echo "Colonnes: statut_session, date_ouverture, montant_ouverture, caissier_id\n\n";

    // mouvements_caisse
    echo "TABLE: mouvements_caisse\n";
    echo "Colonnes: caisse_session_id, type_mouvement, montant\n\n";

    // utilisateurs
    echo "TABLE: utilisateurs\n";
    echo "Colonnes: id, username\n\n";

    // mouvements_stock
    echo "TABLE: mouvements_stock\n";
    echo "Colonnes: type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_type, date_mouvement, produit_id\n\n";

    // audit_logs
    echo "TABLE: audit_logs\n";
    echo "Colonnes: action, table_name, record_id, date_action, utilisateur_id\n\n";

    // Permissions
    echo "=== PERMISSIONS UTILISÉES ===\n";
    $permissions = [
        'create_client' => 'Créer un client',
        'edit_client' => 'Modifier un client',
        'make_sale' => 'Effectuer une vente',
        'cancel_ticket' => 'Annuler un ticket',
        'apply_discount' => 'Appliquer une remise',
        'close_cash_register' => 'Fermer la caisse',
        'view_statistics' => 'Voir les statistiques',
        'view_stock_movements' => 'Voir les mouvements de stock',
        'prepare_orders' => 'Préparer les commandes',
    ];
    foreach ($permissions as $perm => $desc) {
        echo "- $perm: $desc\n";
    }
    echo "\n";

    // Méthodes du service
    echo "=== MÉTHODES DU SERVICE ===\n";
    $methods = [
        'getOverview()' => 'Données complètes du dashboard',
        'getStats()' => 'Statistiques résumées',
        'getTodaySalesSummary()' => 'Résumé ventes du jour',
        'getLowStockAlerts()' => 'Alertes stock faible',
        'getPendingOrders()' => 'Commandes en attente',
        'getPendingSupplierOrders()' => 'Commandes fournisseurs en attente',
        'getCashRegisterSummary()' => 'Résumé caisse',
        'getRecentActivity()' => 'Activité récente',
        'getRecentSales()' => 'Ventes récentes',
        'getRecentStockMovements()' => 'Mouvements stock récents',
        'getNotifications()' => 'Notifications',
        'countWhere()' => 'Comptage générique',
        'tableExists()' => 'Vérification existence table',
    ];
    foreach ($methods as $method => $desc) {
        echo "- $method: $desc\n";
    }
    echo "\n";

    // Vues
    echo "=== VUES ===\n";
    $views = [
        'assistant/dashboard' => 'Vue principale du dashboard',
        'assistant/action-page' => 'Vue des pages d\'action',
    ];
    foreach ($views as $view => $desc) {
        echo "- $view: $desc\n";
    }
    echo "\n";

    // Données retournées par l'API
    echo "=== DONNÉES API DASHBOARD ===\n";
    echo "Structure de retour:\n";
    echo "{\n";
    echo "  success: boolean,\n";
    echo "  data: {\n";
    echo "    widgets: {...},\n";
    echo "    stats: {...},\n";
    echo "    stock_alerts: [...],\n";
    echo "    pending_orders: [...],\n";
    echo "    cash_register: {...},\n";
    echo "    recent_sales: [...],\n";
    echo "    recent_activity: [...],\n";
    echo "    recent_stock_movements: [...],\n";
    echo "    notifications: [...],\n";
    echo "    updated_at: string\n";
    echo "  },\n";
    echo "  actions: [...]\n";
    echo "}\n\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
