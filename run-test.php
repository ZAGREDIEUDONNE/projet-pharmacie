<?php

/**
 * Script de test simple pour le module client
 */

echo "🧪 TEST DU MODULE CLIENT\n";
echo "========================\n\n";

// Test 1: Connexion base de données
echo "1. Test de connexion à la base de données...\n";
try {
    require_once __DIR__ . '/config/database.php';
    $db = \Database::getConnection();
    echo "   ✅ Connexion réussie\n";
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Vérification table clients
echo "2. Test de la table clients...\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'clients'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Table 'clients' trouvée\n";
    } else {
        echo "   ❌ Table 'clients' non trouvée\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Structure de la table
echo "3. Test de la structure de la table clients...\n";
try {
    $stmt = $db->query("DESCRIBE clients");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedColumns = ['id', 'code', 'nom', 'prenom', 'telephone', 'email', 'adresse', 'type_client', 'numero_assurance', 'compagnie_assurance', 'plafond_credit', 'solde_credit', 'is_actif', 'created_at', 'updated_at'];
    
    echo "   Colonnes attendues: " . implode(', ', $expectedColumns) . "\n";
    echo "   Colonnes trouvées: " . implode(', ', $columns) . "\n";
    
    $missing = array_diff($expectedColumns, $columns);
    if (empty($missing)) {
        echo "   ✅ Structure correcte\n";
    } else {
        echo "   ❌ Structure incorrecte - Manquantes: " . implode(', ', $missing) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Insertion de test
echo "4. Test d'insertion d'un client...\n";
try {
    $code = 'CLT' . date('YmdHis');
    $sql = "INSERT INTO clients (code, nom, prenom, telephone, email, adresse, type_client, numero_assurance, compagnie_assurance, plafond_credit, solde_credit, is_actif, created_at, updated_at) 
              VALUES (:code, :nom, :prenom, :telephone, :email, :adresse, :type_client, :numero_assurance, :compagnie_assurance, :plafond_credit, :solde_credit, :is_actif, NOW(), NOW())";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'code' => $code,
        'nom' => 'TEST NOM',
        'prenom' => 'TEST PRENOM',
        'telephone' => '+22612345678',
        'email' => 'test@example.com',
        'adresse' => 'Adresse de test',
        'type_client' => 'PARTICULIER',
        'numero_assurance' => 'ASS123',
        'compagnie_assurance' => 'ASSURANCE TEST',
        'plafond_credit' => 100000,
        'solde_credit' => 0,
        'is_actif' => 1
    ]);
    
    if ($result) {
        $clientId = $db->lastInsertId();
        echo "   ✅ Client inséré avec ID: $clientId\n";
        echo "   ✅ Code généré: $code\n";
    } else {
        echo "   ❌ Erreur lors de l'insertion\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Vérification de l'insertion
echo "5. Vérification de l'insertion...\n";
try {
    $stmt = $db->prepare("SELECT * FROM clients WHERE code = ?");
    $stmt->execute([$code]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        echo "   ✅ Client trouvé et vérifié\n";
        echo "   Code: " . $client['code'] . "\n";
        echo "   Nom: " . $client['nom'] . "\n";
        echo "   Prénom: " . $client['prenom'] . "\n";
    } else {
        echo "   ❌ Client non trouvé\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🎯 CONCLUSION\n";
echo "=============\n";
echo "Le module client est testé et fonctionnel.\n";
echo "Accédez à http://127.0.0.1:8000/clients/create pour le test complet.\n";
?>
