<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VÉRIFICATION TABLES INVENTAIRE ===\n\n";

    // Vérifier table inventaires
    echo "=== STRUCTURE TABLE 'inventaires' ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE inventaires");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} : {$col['Type']} " . ($col['Null'] === 'NO' ? 'NO' : 'YES');
            if ($col['Key'] === 'PRI') echo ' PRI';
            if ($col['Key'] === 'UNI') echo ' UNI';
            if ($col['Key'] === 'MUL') echo ' MUL';
            echo "\n";
        }
    } catch (Exception $e) {
        echo "  Table 'inventaires' non trouvée\n";
    }

    echo "\n";

    // Vérifier table inventaire_articles
    echo "=== STRUCTURE TABLE 'inventaire_articles' ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE inventaire_articles");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} : {$col['Type']} " . ($col['Null'] === 'NO' ? 'NO' : 'YES');
            if ($col['Key'] === 'PRI') echo ' PRI';
            if ($col['Key'] === 'UNI') echo ' UNI';
            if ($col['Key'] === 'MUL') echo ' MUL';
            echo "\n";
        }
    } catch (Exception $e) {
        echo "  Table 'inventaire_articles' non trouvée\n";
    }

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
