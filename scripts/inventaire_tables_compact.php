<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$groups = [
    'Sécurité & utilisateurs' => ['utilisateurs','roles','permissions','role_permissions','utilisateur_permissions','assistant_auth_codes','acces_caisse','acces_avance','auth_logs','login_attempts','token_blacklist'],
    'Produits & stock' => ['produits','categories','fournisseurs','lots','stock','mouvements_stock','stock_entries','product_price_history','unites','stock_stats'],
    'Ventes & ordonnances' => ['ventes','ventes_items','ventes_credit','ordonnances','vente_ordonnances','vente_ordonnance_items','vente_stats'],
    'Clients & finance tiers' => ['clients','client_reglements','remises_commerciales','role_discount_limits'],
    'Commandes & réceptions' => ['commandes','commande_items','supplier_orders','supplier_order_items','receptions','reception_items','fournisseur_reglements'],
    'Caisse' => ['caisse_sessions','caisse_sessions_history','mouvements_caisse'],
    'Comptabilité SYSCOHADA' => ['plan_comptable','classes_comptes','journaux_comptables','ecritures_comptables','lignes_ecritures','exercices_comptables','tva_taux'],
    'Facturation' => ['factures','facture_articles','paiements_factures'],
    'Inventaire' => ['inventaires','inventaire_articles','inventaire_etat_stock','regularisations_stock'],
    'Bons & documents' => ['bons','bons_articles'],
    'Audit & traçabilité' => ['audit_logs','events','trace_annulations','trace_corrections_stock','system_logs','system_errors'],
    'Système' => ['system_config','system_date_changes','cache_system','scheduled_backups'],
    'Vues SQL' => [],
];

$assigned = [];
foreach ($groups as $g => &$list) {
    foreach ($list as $t) $assigned[$t] = $g;
}
unset($list);

foreach ($tables as $t) {
    if (str_starts_with($t, 'v_') || str_starts_with($t, 'vue_')) {
        $groups['Vues SQL'][] = $t;
    } elseif (!isset($assigned[$t])) {
        $groups['Autres'][] = $t;
    }
}

foreach ($groups as $name => $tbls) {
    if (empty($tbls)) continue;
    echo "\n=== $name (" . count($tbls) . ") ===\n";
    foreach ($tbls as $table) {
        if (!in_array($table, $tables, true)) continue;
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        echo "$table: " . implode(', ', $cols) . "\n";
    }
}
