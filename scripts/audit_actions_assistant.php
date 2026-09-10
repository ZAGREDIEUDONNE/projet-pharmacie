<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ANALYSE DÉTAILLÉE DES ACTIONS DU DASHBOARD ===\n\n";

    // Analyse de chaque action
    $actions = [
        [
            'nom' => 'Créer Client',
            'permission' => 'create_client',
            'route' => '/clients/create',
            'controller' => 'ClientController@create',
            'risque' => 'FAIBLE',
            'coherence' => 'OK - Création de client est une tâche opérationnelle courante'
        ],
        [
            'nom' => 'Modifier Client',
            'permission' => 'edit_client',
            'route' => '/clients',
            'controller' => 'ClientController@index',
            'risque' => 'FAIBLE',
            'coherence' => 'OK - Modification de client est une tâche opérationnelle courante'
        ],
        [
            'nom' => 'Vente',
            'permission' => 'make_sale',
            'route' => '/vente/create',
            'controller' => 'VenteController@create',
            'risque' => 'MOYEN',
            'coherence' => 'OK - La vente est la fonction principale de l\'Assistant'
        ],
        [
            'nom' => 'Annuler Ticket',
            'permission' => 'cancel_ticket',
            'route' => '/assistant/annulation-ticket',
            'controller' => 'AssistantController@annulationTickets',
            'risque' => 'ÉLEVÉ',
            'coherence' => 'À VALIDER - Nécessite vérification du processus d\'annulation'
        ],
        [
            'nom' => 'Remise',
            'permission' => 'apply_discount',
            'route' => '/assistant/remise',
            'controller' => 'AssistantController@remise',
            'risque' => 'MOYEN',
            'coherence' => 'À VALIDER - Nécessite vérification des limites de remise'
        ],
        [
            'nom' => 'Arrêt Caisse',
            'permission' => 'close_cash_register',
            'route' => '/assistant/arret-caisse',
            'controller' => 'AssistantController@arretCaisse',
            'risque' => 'ÉLEVÉ',
            'coherence' => 'À VALIDER - Nécessite vérification si l\'Assistant peut réellement clôturer'
        ],
        [
            'nom' => 'Voir Statistiques',
            'permission' => 'view_statistics',
            'route' => '/assistant/statistiques',
            'controller' => 'AssistantController@statistiques',
            'risque' => 'MOYEN',
            'coherence' => 'À VALIDER - Nécessite vérification des statistiques accessibles'
        ],
        [
            'nom' => 'Mouvements Stock',
            'permission' => 'view_stock_movements',
            'route' => '/assistant/mouvements-produits',
            'controller' => 'AssistantController@mouvementsProduits',
            'risque' => 'FAIBLE',
            'coherence' => 'OK - Consultation des mouvements est cohérente avec le rôle'
        ],
        [
            'nom' => 'Préparation Commande',
            'permission' => 'prepare_orders',
            'route' => '/assistant/preparation-commandes',
            'controller' => 'AssistantController@preparationCommandes',
            'risque' => 'FAIBLE',
            'coherence' => 'OK - Préparation des commandes est une tâche opérationnelle'
        ]
    ];

    foreach ($actions as $action) {
        echo "=== {$action['nom']} ===\n";
        echo "Permission: {$action['permission']}\n";
        echo "Route: {$action['route']}\n";
        echo "Contrôleur: {$action['controller']}\n";
        echo "Niveau de risque: {$action['risque']}\n";
        echo "Cohérence: {$action['coherence']}\n\n";
    }

    // Vérification spécifique pour l'annulation de ticket
    echo "=== VÉRIFICATION SPÉCIFIQUE: ANNULATION TICKET ===\n";
    echo "Route: /vente/cancel-ticket\n";
    echo "Contrôleur: VenteController@cancelTicket\n";
    echo "Permission requise: cancel_ticket\n";
    echo "Question: L'Assistant peut-il annuler directement ou faut-il une validation supérieure?\n";
    echo "Recommandation: Vérifier la logique dans VenteController@cancelTicket\n\n";

    // Vérification spécifique pour les remises
    echo "=== VÉRIFICATION SPÉCIFIQUE: REMISES ===\n";
    echo "Permission: apply_discount\n";
    echo "Question: Y a-t-il des limites de remise selon le rôle?\n";
    echo "Recommandation: Vérifier DiscountLimitService\n\n";

    // Vérification spécifique pour l'arrêt de caisse
    echo "=== VÉRIFICATION SPÉCIFIQUE: ARRÊT CAISSE ===\n";
    echo "Route: /caisse/fermeture\n";
    echo "Contrôleur: CaisseController@fermeture\n";
    echo "Permission requise: close_cash_register\n";
    echo "Question: L'Assistant peut-il réellement clôturer une caisse?\n";
    echo "Recommandation: Vérifier la logique dans CaisseController@fermeture\n\n";

    // Vérification spécifique pour les statistiques
    echo "=== VÉRIFICATION SPÉCIFIQUE: STATISTIQUES ===\n";
    echo "Routes accessibles:\n";
    echo "- /clients/statistiques (ClientController@statistiques)\n";
    echo "- /caisse/etat (CaisseController@etat)\n";
    echo "- /stock/rapports (StockController@rapports)\n";
    echo "Question: Ces statistiques révèlent-elles des informations réservées au Gérant/Admin?\n";
    echo "Recommandation: Vérifier le contenu de ces statistiques\n\n";

    echo "=== FIN DE L'ANALYSE ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
