<?php

/**
 * Script de test simple pour le module client
 */

echo "🧪 TEST SIMPLE DU MODULE CLIENT\n";
echo "=================================\n\n";

// Test 1: Vérifier les fichiers essentiels
echo "1. Vérification des fichiers...\n";
$requiredFiles = [
    __DIR__ . '/app/Core/BaseController.php',
    __DIR__ . '/app/Services/RBACService.php',
    __DIR__ . '/app/Services/AuditService.php',
    __DIR__ . '/app/Controllers/ClientController.php',
    __DIR__ . '/config/database.php'
];

$allFilesExist = true;
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        echo "   ❌ Manquant: $file\n";
        $allFilesExist = false;
    }
}

if ($allFilesExist) {
    echo "   ✅ Tous les fichiers essentiels présents\n";
} else {
    echo "   ❌ Certains fichiers manquent\n";
}

echo "\n";

// Test 2: Test des classes
echo "2. Test des classes...\n";
try {
    // Test de la classe Database
    require_once __DIR__ . '/config/database.php';
    $db = \Database::getConnection();
    echo "   ✅ Classe Database fonctionnelle\n";
    
    // Test de la table clients
    $stmt = $db->query("SHOW TABLES LIKE 'clients'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Table 'clients' existe\n";
        
        // Test de la structure
        $stmt = $db->query("DESCRIBE clients");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $expectedColumns = ['id', 'code', 'nom', 'prenom', 'telephone', 'email', 'adresse', 'type_client', 'numero_assurance', 'compagnie_assurance', 'plafond_credit', 'solde_credit', 'is_actif', 'created_at', 'updated_at'];
        
        $missingColumns = array_diff($expectedColumns, $columns);
        if (empty($missingColumns)) {
            echo "   ✅ Structure de la table correcte\n";
        } else {
            echo "   ❌ Colonnes manquantes: " . implode(', ', $missingColumns) . "\n";
        }
    } else {
        echo "   ❌ Table 'clients' n'existe pas\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test du formulaire
echo "3. Test du formulaire client...\n";
echo "   URL: http://127.0.0.1:8000/clients/create\n";
echo "   Champs attendus: nom, prenom, telephone, email, adresse, type_client, numero_assurance, compagnie_assurance, plafond_credit\n";
echo "   Code généré automatiquement: CLT + timestamp\n";
echo "   Valeurs par défaut: solde_credit = 0, is_actif = 1\n";

echo "\n";

// Test 4: Instructions
echo "4. Instructions de test:\n";
echo "   a. Ouvrir http://127.0.0.1:8000/login\n";
echo "   b. Se connecter avec un utilisateur ayant la permission client.create\n";
echo "   c. Naviguer vers http://127.0.0.1:8000/clients/create\n";
echo "   d. Remplir le formulaire avec des données de test\n";
echo "   e. Cliquer sur 'Enregistrer le Client'\n";
echo "   f. Vérifier le message de succès\n";
echo "   g. Vérifier dans la base de données que le client a été créé\n";
echo "   h. Vérifier les logs PHP pour d'éventuelles erreurs\n";

echo "\n";
echo "🎯 CONCLUSION\n";
echo "Le module client est prêt pour le test.\n";
echo "Accédez à l'URL ci-dessus pour tester.\n";
?>
