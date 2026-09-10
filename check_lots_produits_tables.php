<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== STRUCTURE TABLE 'lots' ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE lots");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} : {$col['Type']} " . ($col['Null'] === 'NO' ? 'NO' : 'YES');
            if ($col['Key'] === 'PRI') echo ' PRI';
            if ($col['Key'] === 'UNI') echo ' UNI';
            if ($col['Key'] === 'MUL') echo ' MUL';
            echo "\n";
        }
    } catch (Exception $e) {
        echo "  Table 'lots' non trouvée\n";
    }

    echo "\n=== STRUCTURE TABLE 'produits' ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE produits");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} : {$col['Type']} " . ($col['Null'] === 'NO' ? 'NO' : 'YES');
            if ($col['Key'] === 'PRI') echo ' PRI';
            if ($col['Key'] === 'UNI') echo ' UNI';
            if ($col['Key'] === 'MUL') echo ' MUL';
            echo "\n";
        }
    } catch (Exception $e) {
        echo "  Table 'produits' non trouvée\n";
    }

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
