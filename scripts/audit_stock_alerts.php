<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== UNIFICATION RÈGLES ALERTES STOCK ===\n\n";

    // Règles attendues:
    // STOCK = 0 → RUPTURE
    // STOCK <= stock_alerte → STOCK FAIBLE
    // STOCK <= stock_securite → STOCK CRITIQUE

    echo "=== RÈGLES ATTENDUES ===\n";
    echo "- RUPTURE: quantite_disponible = 0\n";
    echo "- STOCK FAIBLE: quantite_disponible <= stock_alerte\n";
    echo "- STOCK CRITIQUE: quantite_disponible <= stock_securite\n\n";

    // Vérifier les méthodes dans ChargeCommandeService
    $serviceFile = 'app/Services/ChargeCommandeService.php';
    $content = file_get_contents($serviceFile);

    echo "=== MÉTHODES D'ALERTE DANS CHARGECOMMANDE SERVICE ===\n";
    
    $methods = [
        'countOutOfStock',
        'countCriticalStock',
        'countLowStock'
    ];
    
    foreach ($methods as $method) {
        $exists = strpos($content, $method) !== false;
        echo "$method: " . ($exists ? 'OUI' : 'NON') . "\n";
        
        if ($exists) {
            // Extraire la logique de la méthode
            $pattern = "/function $method\(\).*?\{.*?return.*?;/s";
            preg_match($pattern, $content, $matches);
            if (!empty($matches[0])) {
                echo "  Logique: " . substr($matches[0], 0, 200) . "...\n";
            }
        }
    }

    // Calculer les alertes selon les règles attendues
    echo "\n=== CALCUL ALERTES SELON RÈGLES ATTENDUES ===\n";
    
    // RUPTURE
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM stock s
        JOIN produits p ON s.produit_id = p.id
        WHERE s.quantite_disponible = 0
        AND p.is_actif = 1
        AND p.deleted_at IS NULL
    ");
    $rupture = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "RUPTURE (stock = 0): $rupture\n";
    
    // STOCK FAIBLE
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM stock s
        JOIN produits p ON s.produit_id = p.id
        WHERE s.quantite_disponible > 0
        AND s.quantite_disponible <= p.stock_alerte
        AND p.is_actif = 1
        AND p.deleted_at IS NULL
    ");
    $faible = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "STOCK FAIBLE (stock > 0 AND stock <= stock_alerte): $faible\n";
    
    // STOCK CRITIQUE
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM stock s
        JOIN produits p ON s.produit_id = p.id
        WHERE s.quantite_disponible > 0
        AND s.quantite_disponible <= p.stock_securite
        AND p.is_actif = 1
        AND p.deleted_at IS NULL
    ");
    $critique = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "STOCK CRITIQUE (stock > 0 AND stock <= stock_securite): $critique\n";

    // Vérifier les données de stock_alerte et stock_securite
    echo "\n=== DONNÉES SEUILS ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(stock_alerte) as avec_alerte,
            COUNT(stock_securite) as avec_securite,
            AVG(stock_alerte) as avg_alerte,
            AVG(stock_securite) as avg_securite
        FROM produits
        WHERE is_actif = 1
        AND deleted_at IS NULL
    ");
    $seuils = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Produits actifs: {$seuils['total']}\n";
    echo "Avec stock_alerte: {$seuils['avec_alerte']}\n";
    echo "Avec stock_securite: {$seuils['avec_securite']}\n";
    echo "Moyenne stock_alerte: " . round($seuils['avg_alerte'] ?? 0, 2) . "\n";
    echo "Moyenne stock_securite: " . round($seuils['avg_securite'] ?? 0, 2) . "\n";
    
    // Vérifier les produits sans seuils
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM produits
        WHERE is_actif = 1
        AND deleted_at IS NULL
        AND (stock_alerte IS NULL OR stock_securite IS NULL)
    ");
    $sansSeuils = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Produits sans seuils (alerte ou sécurité): $sansSeuils\n";

    // Vérifier la cohérence des seuils
    echo "\n=== COHÉRENCE DES SEUILS ===\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM produits
        WHERE is_actif = 1
        AND deleted_at IS NULL
        AND stock_securite > stock_alerte
    ");
    $incoherent = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($incoherent > 0) {
        echo "ATTENTION: $incoherent produits ont stock_securite > stock_alerte (incohérent)\n";
        echo "Normalement: stock_securite <= stock_alerte\n";
    } else {
        echo "Tous les produits ont des seuils cohérents (stock_securite <= stock_alerte)\n";
    }

    // Vérifier les alertes dans le dashboard
    echo "\n=== ALERTES DASHBOARD ===\n";
    
    // Simuler l'appel à getDashboardData
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT CASE WHEN s.quantite_disponible = 0 THEN s.produit_id END) as ruptures,
            COUNT(DISTINCT CASE WHEN s.quantite_disponible > 0 AND s.quantite_disponible <= p.stock_alerte THEN s.produit_id END) as stock_sous_seuil,
            COUNT(DISTINCT CASE WHEN s.quantite_disponible > 0 AND s.quantite_disponible <= p.stock_securite THEN s.produit_id END) as stock_critique
        FROM stock s
        JOIN produits p ON s.produit_id = p.id
        WHERE p.is_actif = 1
        AND p.deleted_at IS NULL
    ");
    $dashboardAlerts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Dashboard:\n";
    echo "  Ruptures: {$dashboardAlerts['ruptures']}\n";
    echo "  Stock sous seuil: {$dashboardAlerts['stock_sous_seuil']}\n";
    echo "  Stock critique: {$dashboardAlerts['stock_critique']}\n";
    
    // Comparer
    echo "\n=== COMPARAISON ===\n";
    echo "Rupture: Calculée=$rupture, Dashboard={$dashboardAlerts['ruptures']} " . ($rupture == $dashboardAlerts['ruptures'] ? "[OK]" : "[DIFFÉRENT]") . "\n";
    echo "Stock faible: Calculée=$faible, Dashboard={$dashboardAlerts['stock_sous_seuil']} " . ($faible == $dashboardAlerts['stock_sous_seuil'] ? "[OK]" : "[DIFFÉRENT]") . "\n";
    echo "Stock critique: Calculée=$critique, Dashboard={$dashboardAlerts['stock_critique']} " . ($critique == $dashboardAlerts['stock_critique'] ? "[OK]" : "[DIFFÉRENT]") . "\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
