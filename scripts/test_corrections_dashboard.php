<?php
require_once __DIR__ . '/../config/database.php';

echo "=== TEST DES CORRECTIONS DASHBOARD CHARGE_COMMANDE ===\n\n";

try {
    $pdo = Database::getConnection();
    
    // Test 1: Vérifier les lots expirés
    echo "1. Test des alertes de péremption (lots expirés)\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM lots l
        JOIN produits p ON p.id = l.produit_id
        WHERE p.is_actif = 1
            AND p.deleted_at IS NULL
            AND l.is_actif = 1
            AND l.quantite_restante > 0
            AND l.date_peremption < CURDATE()
    ");
    $stmt->execute();
    $lotsExpires = (int)$stmt->fetchColumn();
    echo "   Lots expirés détectés : $lotsExpires\n\n";
    
    // Test 2: Vérifier les produits en stock faible (<= stock_alerte)
    echo "2. Test des produits en stock faible\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM produits p
        LEFT JOIN stock s ON s.produit_id = p.id
        WHERE p.is_actif = 1
            AND p.deleted_at IS NULL
            AND COALESCE(s.quantite_disponible, 0) > 0
            AND COALESCE(s.quantite_disponible, 0) <= p.stock_alerte
    ");
    $stmt->execute();
    $stockFaible = (int)$stmt->fetchColumn();
    echo "   Produits en stock faible (<= seuil) : $stockFaible\n\n";
    
    // Test 3: Vérifier les produits en stock critique
    echo "3. Test des produits en stock critique\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM produits p
        LEFT JOIN stock s ON s.produit_id = p.id
        WHERE p.is_actif = 1
            AND p.deleted_at IS NULL
            AND p.stock_securite > 0
            AND COALESCE(s.quantite_disponible, 0) <= p.stock_securite
            AND COALESCE(s.quantite_disponible, 0) > 0
    ");
    $stmt->execute();
    $stockCritique = (int)$stmt->fetchColumn();
    echo "   Produits en stock critique : $stockCritique\n\n";
    
    // Test 4: Vérifier les commandes en retard
    echo "4. Test des commandes en retard\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM supplier_orders
        WHERE statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
            AND date_livraison_prevue < CURDATE()
    ");
    $stmt->execute();
    $commandesRetard = (int)$stmt->fetchColumn();
    echo "   Commandes en retard : $commandesRetard\n\n";
    
    // Test 5: Vérifier les commandes ouvertes
    echo "5. Test des commandes ouvertes\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM supplier_orders
        WHERE statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
    ");
    $stmt->execute();
    $commandesOuvertes = (int)$stmt->fetchColumn();
    echo "   Commandes ouvertes : $commandesOuvertes\n\n";
    
    // Test 6: Vérifier les réceptions sur 7 jours
    echo "6. Test des réceptions sur 7 jours\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM receptions
        WHERE date_reception >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $receptions7j = (int)$stmt->fetchColumn();
    echo "   Réceptions sur 7 jours : $receptions7j\n\n";
    
    // Test 7: Vérifier les permissions CHARGE_COMMANDE
    echo "7. Test des permissions CHARGE_COMMANDE\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM role_permissions rp
        JOIN permissions p ON p.id = rp.permission_id
        JOIN roles r ON r.id = rp.role_id
        WHERE r.nom = 'CHARGE_COMMANDE'
    ");
    $stmt->execute();
    $permissionsCount = (int)$stmt->fetchColumn();
    echo "   Permissions CHARGE_COMMANDE : $permissionsCount\n\n";
    
    // Test 8: Vérifier Database::checkTables()
    echo "8. Test Database::checkTables()\n";
    $checkResult = Database::checkTables();
    if ($checkResult['success']) {
        echo "   Tables existantes : " . $checkResult['total_existing'] . "/" . $checkResult['total_required'] . "\n";
    } else {
        echo "   Erreur : " . ($checkResult['message'] ?? 'Erreur inconnue') . "\n";
    }
    echo "\n";
    
    // Test 9: Vérifier les statistiques stock_by_category
    echo "9. Test stock_by_category\n";
    $stmt = $pdo->query("
        SELECT COALESCE(c.nom, 'Non classe') AS categorie,
                COALESCE(SUM(s.quantite_disponible), 0) AS total
        FROM produits p
        LEFT JOIN stock s ON s.produit_id = p.id
        LEFT JOIN categories c ON c.id = p.categorie_id
        WHERE p.is_actif = 1 AND p.deleted_at IS NULL
        GROUP BY COALESCE(c.nom, 'Non classe')
        ORDER BY total DESC
        LIMIT 8
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Catégories trouvées : " . count($categories) . "\n";
    foreach ($categories as $cat) {
        echo "   - " . $cat['categorie'] . ": " . $cat['total'] . "\n";
    }
    echo "\n";
    
    // Test 10: Vérifier top_used_products
    echo "10. Test top_used_products (90 jours)\n";
    $stmt = $pdo->query("
        SELECT p.nom, p.code_cip, COALESCE(SUM(ms.quantite), 0) AS total
        FROM mouvements_stock ms
        JOIN produits p ON p.id = ms.produit_id
        WHERE ms.type_mouvement = 'SORTIE'
        AND ms.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY p.id, p.nom, p.code_cip
        ORDER BY total DESC
        LIMIT 8
    ");
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Top produits trouvés : " . count($topProducts) . "\n";
    foreach ($topProducts as $prod) {
        echo "   - " . $prod['nom'] . ": " . $prod['total'] . "\n";
    }
    echo "\n";
    
    echo "=== RÉSUMÉ ===\n";
    echo "Lots expirés détectés : $lotsExpires\n";
    echo "Produits en stock faible : $stockFaible\n";
    echo "Produits en stock critique : $stockCritique\n";
    echo "Commandes en retard : $commandesRetard\n";
    echo "Commandes ouvertes : $commandesOuvertes\n";
    echo "Réceptions sur 7 jours : $receptions7j\n";
    echo "Permissions CHARGE_COMMANDE : $permissionsCount\n";
    echo "Tables vérifiées : " . $checkResult['total_existing'] . "/" . $checkResult['total_required'] . "\n";
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
