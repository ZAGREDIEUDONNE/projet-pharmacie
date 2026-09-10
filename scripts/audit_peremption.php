<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ANALYSE PÉREMPTION (LOTS VS STOCK.DATE_PEREMPTION) ===\n\n";

    // Vérifier la table lots
    echo "=== TABLE: lots ===\n";
    
    $stmt = $pdo->query("DESCRIBE lots");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    // Vérifier les données dans lots
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lots");
    $totalLots = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nTotal lots: $totalLots\n";
    
    // Vérifier stock.date_peremption
    echo "\n=== TABLE: stock ===\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stock WHERE date_peremption IS NOT NULL");
    $totalStockPeremption = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Stock avec date_peremption: $totalStockPeremption\n";
    
    // Vérifier l'utilisation dans le code
    echo "\n=== UTILISATION DANS LE CODE ===\n";
    
    $serviceFile = 'app/Services/ChargeCommandeService.php';
    $content = file_get_contents($serviceFile);
    
    $lotsUsage = substr_count($content, 'lots');
    $datePeremptionUsage = substr_count($content, 'date_peremption');
    
    echo "Références à 'lots' dans ChargeCommandeService: $lotsUsage\n";
    echo "Références à 'date_peremption' dans ChargeCommandeService: $datePeremptionUsage\n";
    
    // Vérifier les méthodes de péremption
    echo "\n=== MÉTHODES DE PÉREMPTION ===\n";
    
    $methods = [
        'countExpiringSoon',
        'countExpired',
        'getExpiringProducts',
        'getExpiredProducts'
    ];
    
    foreach ($methods as $method) {
        $exists = strpos($content, $method) !== false;
        echo "$method: " . ($exists ? 'OUI' : 'NON') . "\n";
    }
    
    // Vérifier les alertes de péremption dans le dashboard
    echo "\n=== ALERTES PÉREMPTION DASHBOARD ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_30j,
            COUNT(*) as total_60j,
            COUNT(*) as total_90j,
            COUNT(*) as total_expire
        FROM stock
        WHERE date_peremption IS NOT NULL
        AND quantite_disponible > 0
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Stock avec date_peremption et quantité > 0:\n";
    echo "  - Total 30j: {$result['total_30j']}\n";
    echo "  - Total 60j: {$result['total_60j']}\n";
    echo "  - Total 90j: {$result['total_90j']}\n";
    echo "  - Total expiré: {$result['total_expire']}\n";
    
    // Calculer les alertes réelles
    echo "\n=== CALCUL ALERTES RÉELLES ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as expire_30j
        FROM stock
        WHERE date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND quantite_disponible > 0
    ");
    $expire30j = $stmt->fetch(PDO::FETCH_ASSOC)['expire_30j'];
    echo "Produits expirant dans 30 jours: $expire30j\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as expire_60j
        FROM stock
        WHERE date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
        AND quantite_disponible > 0
    ");
    $expire60j = $stmt->fetch(PDO::FETCH_ASSOC)['expire_60j'];
    echo "Produits expirant dans 60 jours: $expire60j\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as expired
        FROM stock
        WHERE date_peremption < CURDATE()
        AND quantite_disponible > 0
    ");
    $expired = $stmt->fetch(PDO::FETCH_ASSOC)['expired'];
    echo "Produits expirés: $expired\n";
    
    // Conclusion
    echo "\n=== CONCLUSION ===\n";
    
    if ($totalLots > 0) {
        echo "La table lots existe et contient des données ($totalLots lots).\n";
        echo "Le système utilise probablement les lots pour la gestion de la péremption.\n";
    } else {
        echo "La table lots est vide ou non utilisée.\n";
    }
    
    if ($totalStockPeremption > 0) {
        echo "La table stock utilise date_peremption ($totalStockPeremption enregistrements).\n";
        echo "Le système utilise probablement stock.date_peremption pour la gestion de la péremption.\n";
    } else {
        echo "La table stock n'utilise pas date_peremption.\n";
    }
    
    if ($datePeremptionUsage > 0) {
        echo "ChargeCommandeService utilise date_peremption ($datePeremptionUsage références).\n";
    } else {
        echo "ChargeCommandeService n'utilise pas date_peremption.\n";
    }
    
    echo "\nRECOMMANDATION: ";
    if ($datePeremptionUsage > 0 && $totalStockPeremption > 0) {
        echo "Utiliser stock.date_peremption comme source principale pour CHARGE_COMMANDE.\n";
    } elseif ($lotsUsage > 0 && $totalLots > 0) {
        echo "Utiliser la table lots comme source principale pour CHARGE_COMMANDE.\n";
    } else {
        echo "Définir une source unique pour la gestion de la péremption.\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
