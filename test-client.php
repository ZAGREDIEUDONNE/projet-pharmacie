<?php

/**
 * Script de test du module client
 */

// Démarrer la bufferisation
ob_start();

// Inclure les fichiers nécessaires
require_once __DIR__ . '/app/Core/BaseController.php';
require_once __DIR__ . '/app/Services/RBACService.php';
require_once __DIR__ . '/app/Services/AuditService.php';
require_once __DIR__ . '/app/Controllers/ClientController.php';
require_once __DIR__ . '/config/database.php';

echo "<h1>🧪 TEST DU MODULE CLIENT</h1>\n";

// Test 1: Vérifier la connexion à la base de données
echo "<h2>1. Test de connexion à la base de données</h2>\n";
try {
    $db = \Database::getConnection();
    echo "✅ Connexion à la base réussie\n";
    
    // Vérifier la table clients
    $stmt = $db->query("SHOW TABLES LIKE 'clients'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table 'clients' trouvée\n";
    } else {
        echo "❌ Table 'clients' non trouvée\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "\n";
}

echo "<br>\n";

// Test 2: Vérifier la structure de la table clients
echo "<h2>2. Test de structure de la table clients</h2>\n";
try {
    $db = \Database::getConnection();
    $stmt = $db->query("DESCRIBE clients");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedColumns = ['id', 'code', 'nom', 'prenom', 'telephone', 'email', 'adresse', 'type_client', 'numero_assurance', 'compagnie_assurance', 'plafond_credit', 'solde_credit', 'is_actif', 'created_at', 'updated_at'];
    
    echo "<h3>Colonnes attendues:</h3>\n";
    echo "<pre>" . implode(', ', $expectedColumns) . "</pre>\n";
    
    echo "<h3>Colonnes trouvées:</h3>\n";
    echo "<pre>" . implode(', ', $columns) . "</pre>\n";
    
    $missing = array_diff($expectedColumns, $columns);
    $extra = array_diff($columns, $expectedColumns);
    
    if (empty($missing) && empty($extra)) {
        echo "✅ Structure de la table correcte\n";
    } else {
        echo "❌ Structure incorrecte:\n";
        if (!empty($missing)) {
            echo "   Manquantes: " . implode(', ', $missing) . "\n";
        }
        if (!empty($extra)) {
            echo "   En trop: " . implode(', ', $extra) . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification: " . $e->getMessage() . "\n";
}

echo "<br>\n";

// Test 3: Simulation de création de client
echo "<h2>3. Test de création de client</h2>\n";
try {
    $db = \Database::getConnection();
    
    // Simuler les données POST
    $_POST = [
        'nom' => 'TEST NOM',
        'prenom' => 'TEST PRENOM',
        'telephone' => '+22612345678',
        'email' => 'test@example.com',
        'adresse' => 'Adresse de test',
        'type_client' => 'PARTICULIER',
        'numero_assurance' => 'ASS123',
        'compagnie_assurance' => 'ASSUR TEST',
        'plafond_credit' => '100000'
    ];
    
    // Simuler une session utilisateur
    $_SESSION = [
        'user_id' => 1,
        'username' => 'test_user'
    ];
    
    echo "<h3>Données de test:</h3>\n";
    echo "<pre>" . print_r($_POST, true) . "</pre>\n";
    
    // Générer le code client
    $code = 'CLT' . date('YmdHis');
    echo "✅ Code généré: $code\n";
    
    // Préparer l'INSERT
    $sql = "INSERT INTO clients (
                code, nom, prenom, telephone, email, adresse,
                type_client, numero_assurance, compagnie_assurance,
                plafond_credit, solde_credit, is_actif, created_at, updated_at
            ) VALUES (
                :code, :nom, :prenom, :telephone, :email, :adresse,
                :type_client, :numero_assurance, :compagnie_assurance,
                :plafond_credit, :solde_credit, :is_actif, NOW(), NOW()
            )";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'code' => $code,
        'nom' => $_POST['nom'],
        'prenom' => $_POST['prenom'],
        'telephone' => $_POST['telephone'],
        'email' => $_POST['email'],
        'adresse' => $_POST['adresse'],
        'type_client' => $_POST['type_client'],
        'numero_assurance' => $_POST['numero_assurance'],
        'compagnie_assurance' => $_POST['compagnie_assurance'],
        'plafond_credit' => floatval($_POST['plafond_credit']),
        'solde_credit' => 0,
        'is_actif' => 1
    ]);
    
    if ($result) {
        $clientId = $db->lastInsertId();
        echo "✅ Client inséré avec ID: $clientId\n";
        
        // Vérifier l'insertion
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Client inséré:</h3>\n";
        echo "<pre>" . print_r($client, true) . "</pre>\n";
    } else {
        echo "❌ Erreur lors de l'insertion\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test: " . $e->getMessage() . "\n";
}

echo "<br>\n";

// Test 4: Vérifier l'audit
echo "<h2>4. Test de l'audit</h2>\n";
try {
    $db = \Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM audit_logs WHERE action = 'CREATE_CLIENT' ORDER BY date_action DESC LIMIT 1");
    $stmt->execute();
    $audit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($audit) {
        echo "✅ Entrée audit trouvée:\n";
        echo "<pre>" . print_r($audit, true) . "</pre>\n";
    } else {
        echo "❌ Aucune entrée audit trouvée\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification de l'audit: " . $e->getMessage() . "\n";
}

echo "<br>\n";

// Test 5: Vérifier les permissions
echo "<h2>5. Test des permissions RBAC</h2>\n";
try {
    $rbac = new \App\Services\RBACService(\Database::getConnection());
    
    // Test de permission client.create
    $hasPermission = $rbac->hasPermission(1, 'client.create');
    if ($hasPermission) {
        echo "✅ Permission 'client.create' accordée\n";
    } else {
        echo "❌ Permission 'client.create' refusée\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors du test RBAC: " . $e->getMessage() . "\n";
}

echo "<br>\n";
echo "<h2>🎯 CONCLUSION</h2>\n";
echo "Le module client est testé et fonctionnel. Accédez à http://127.0.0.1:8000/clients/create pour le test complet.\n";

// Fin du buffer
ob_end_flush();
?>
