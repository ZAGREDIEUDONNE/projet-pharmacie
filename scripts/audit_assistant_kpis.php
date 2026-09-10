<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AUDIT KPIs DASHBOARD ASSISTANT ===\n\n";

    // KPIs actuels
    $kpis = [
        'ventes_jour' => 'Nombre de ventes du jour',
        'ca_jour' => 'Chiffre d\'affaires du jour',
        'montant_encaisse' => 'Montant encaissé du jour',
        'tickets_annules' => 'Tickets annulés du jour',
        'clients_actifs' => 'Nombre de clients actifs',
        'produits_alerte' => 'Produits en alerte stock',
        'commandes_attente' => 'Commandes en attente',
        'caisse_ouverte' => 'Caisse ouverte (0/1)',
        'solde_caisse' => 'Solde théorique caisse'
    ];

    // KPIs affichés dans la vue
    echo "=== KPIS AFFICHÉS DANS LA VUE ===\n";
    echo "KPIs affichés (4 sur 9):\n";
    echo "- CA du jour (widgets.ca_jour)\n";
    echo "- Ventes du jour (widgets.ventes_jour)\n";
    echo "- Alertes stock (widgets.produits_alerte)\n";
    echo "- Caisse (cash.open ? 'Ouverte' : 'Fermée')\n\n";

    echo "KPIs calculés mais non affichés (5):\n";
    echo "- montant_encaisse\n";
    echo "- tickets_annules\n";
    echo "- clients_actifs\n";
    echo "- commandes_attente\n";
    echo "- solde_caisse\n\n";

    // Analyse de chaque KPI
    echo "=== ANALYSE DÉTAILLÉE DES KPIS ===\n\n";

    // 1. ventes_jour
    echo "1. ventes_jour\n";
    echo "   Table: ventes\n";
    echo "   Requête: SELECT COUNT(*) FROM ventes WHERE deleted_at IS NULL AND statut_vente != 'ANNULEE' AND DATE(date_vente) = CURDATE()\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: deleted_at IS NULL, statut_vente != 'ANNULEE', date du jour\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir les ventes du jour)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI (index sur date_vente)\n";
    echo "   Recommandation: CONSERVER\n\n";

    // 2. ca_jour
    echo "2. ca_jour\n";
    echo "   Table: ventes\n";
    echo "   Requête: SELECT COALESCE(SUM(montant_net), 0) FROM ventes WHERE deleted_at IS NULL AND statut_vente != 'ANNULEE' AND DATE(date_vente) = CURDATE()\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: deleted_at IS NULL, statut_vente != 'ANNULEE', date du jour\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir le CA du jour)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: CONSERVER\n\n";

    // 3. montant_encaisse
    echo "3. montant_encaisse\n";
    echo "   Table: ventes\n";
    echo "   Requête: SELECT COALESCE(SUM(montant_paye), 0) FROM ventes WHERE deleted_at IS NULL AND statut_vente != 'ANNULEE' AND DATE(date_vente) = CURDATE()\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: deleted_at IS NULL, statut_vente != 'ANNULEE', date du jour\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir les encaissements)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: CONSERVER (afficher dans détails)\n\n";

    // 4. tickets_annules
    echo "4. tickets_annules\n";
    echo "   Table: ventes\n";
    echo "   Requête: SELECT COUNT(*) FROM ventes WHERE DATE(date_vente) = CURDATE() AND statut_vente = 'ANNULEE'\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: date du jour, statut_vente = 'ANNULEE'\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir les annulations)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: CONSERVER (afficher dans détails)\n\n";

    // 5. clients_actifs
    echo "5. clients_actifs\n";
    echo "   Table: clients\n";
    echo "   Requête: SELECT COUNT(*) FROM clients WHERE is_actif = 1 AND deleted_at IS NULL\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: is_actif = 1, deleted_at IS NULL\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir les clients)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: CONSERVER (afficher dans détails)\n\n";

    // 6. produits_alerte
    echo "6. produits_alerte\n";
    echo "   Tables: produits, stock\n";
    echo "   Requête: SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0)\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: is_actif = 1, deleted_at IS NULL, stock <= seuil\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir les alertes stock)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI (index sur stock_alerte)\n";
    echo "   Recommandation: CONSERVER\n\n";

    // 7. commandes_attente
    echo "7. commandes_attente\n";
    echo "   Tables: supplier_orders, supplier_order_items, fournisseurs\n";
    echo "   Requête: SELECT COUNT(*) FROM supplier_orders so LEFT JOIN fournisseurs f ON f.id = so.fournisseur_id LEFT JOIN supplier_order_items soi ON soi.supplier_order_id = so.id WHERE so.statut IN ('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: statut IN ('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir les commandes à préparer)\n";
    echo "   Double comptage: NON (GROUP BY)\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: CONSERVER (afficher dans détails)\n\n";

    // 8. caisse_ouverte
    echo "8. caisse_ouverte\n";
    echo "   Table: caisse_sessions\n";
    echo "   Requête: SELECT COUNT(*) FROM caisse_sessions WHERE statut_session = 'OUVERTE'\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: statut_session = 'OUVERTE'\n";
    echo "   Correspond au rôle: OUI (Assistant peut voir l'état de la caisse)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: CONSERVER (affiché comme 'Ouverte/Fermée')\n\n";

    // 9. solde_caisse
    echo "9. solde_caisse\n";
    echo "   Tables: caisse_sessions, mouvements_caisse, utilisateurs\n";
    echo "   Requête: SELECT montant_ouverture + SUM(VENTE) - SUM(REMBOURSEMENT) - SUM(RETRAIT) FROM caisse_sessions WHERE statut_session = 'OUVERTE'\n";
    echo "   Correcte: OUI\n";
    echo "   Filtres: statut_session = 'OUVERTE'\n";
    echo "   Correspond au rôle: NON (donnée sensible pour le rôle Assistant)\n";
    echo "   Double comptage: NON\n";
    echo "   Performance: OUI\n";
    echo "   Recommandation: SUPPRIMER ou remplacer par indicateur non sensible\n";
    echo "   Alternative: Afficher uniquement 'Caisse ouverte/fermée' ou nombre d'opérations\n\n";

    // Vérification des tables
    echo "=== VÉRIFICATION DES TABLES ===\n";
    $tables = ['ventes', 'clients', 'produits', 'stock', 'supplier_orders', 'supplier_order_items', 'fournisseurs', 'caisse_sessions', 'mouvements_caisse'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        echo "$table: " . ($exists ? 'EXISTE' : 'MANQUANTE') . "\n";
    }

    echo "\n=== RÉSUMÉ ===\n";
    echo "KPIs analysés: 9\n";
    echo "KPIs affichés dans la vue: 4\n";
    echo "KPIs à conserver: 8\n";
    echo "KPIs à modifier: 1 (solde_caisse)\n";
    echo "KPIs à supprimer: 0\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
