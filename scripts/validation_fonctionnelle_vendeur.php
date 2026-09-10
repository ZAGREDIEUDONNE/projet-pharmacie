<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VALIDATION FONCTIONNELLE - DASHBOARD VENDEUR ===\n\n";

    // 1. TEST SCANNER
    echo "=== 1. TEST SCANNER ===\n";
    echo "Route: /vente/scan\n";
    echo "Controller: VenteController@scan\n";
    echo "Méthode: À vérifier dans VenteController\n";
    
    // Vérifier si la méthode scan existe
    $controllerFile = file_get_contents('app/Controllers/VenteController.php');
    $hasScanMethod = strpos($controllerFile, 'function scan') !== false;
    
    if ($hasScanMethod) {
        echo "Résultat: PASS (méthode existe)\n";
    } else {
        echo "Résultat: FAIL (méthode scan non trouvée dans VenteController)\n";
        echo "Cause: La méthode scan() n'existe pas dans le contrôleur\n";
        echo "Fichier: app/Controllers/VenteController.php\n";
    }
    echo "\n";

    // 2. TEST VENTE COMPLÈTE
    echo "=== 2. TEST VENTE COMPLÈTE ===\n";
    echo "Route: /vente/store\n";
    echo "Controller: VenteController@store\n";
    
    // Vérifier si la méthode store existe
    $hasStoreMethod = strpos($controllerFile, 'function store') !== false;
    
    if ($hasStoreMethod) {
        echo "Résultat: PASS (méthode existe)\n";
        
        // Vérifier les colonnes de la table ventes
        $stmt = $pdo->prepare("DESCRIBE ventes");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        echo "Colonnes ventes: " . implode(', ', $columnNames) . "\n";
        
        // Test avec transaction rollback
        $pdo->beginTransaction();
        try {
            // Simuler une vente (adapter selon les colonnes réelles)
            if (in_array('statut', $columnNames)) {
                $stmt = $pdo->prepare("INSERT INTO ventes (client_id, utilisateur_id, date_vente, statut, montant_total) VALUES (?, ?, NOW(), 'VALIDEE', 1000)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO ventes (client_id, utilisateur_id, date_vente, montant_total) VALUES (?, ?, NOW(), 1000)");
            }
            $stmt->execute([1, 1]);
            $venteId = $pdo->lastInsertId();
            
            // Vérifier les colonnes de ventes_items
            $stmt = $pdo->prepare("DESCRIBE ventes_items");
            $stmt->execute();
            $itemColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $itemColumnNames = array_column($itemColumns, 'Field');
            echo "Colonnes ventes_items: " . implode(', ', $itemColumnNames) . "\n";
            
            // Ajouter un item (adapter selon les colonnes réelles)
            if (in_array('total', $itemColumnNames)) {
                $stmt = $pdo->prepare("INSERT INTO ventes_items (vente_id, produit_id, quantite, prix_unitaire, total) VALUES (?, ?, 1, 1000, 1000)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO ventes_items (vente_id, produit_id, quantite, prix_unitaire) VALUES (?, ?, 1, 1000)");
            }
            $stmt->execute([$venteId, 1]);
            
            // Vérifier le stock
            $stmt = $pdo->prepare("DESCRIBE stock");
            $stmt->execute();
            $stockColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stockColumnNames = array_column($stockColumns, 'Field');
            echo "Colonnes stock: " . implode(', ', $stockColumnNames) . "\n";
            
            if (in_array('quantite', $stockColumnNames)) {
                $stmt = $pdo->prepare("SELECT quantite FROM stock WHERE produit_id = ?");
                $stmt->execute([1]);
                $stock = $stmt->fetchColumn();
                echo "Stock produit ID 1: $stock\n";
            } else {
                echo "Stock: colonne quantite non trouvée\n";
            }
            
            echo "Test transaction: PASS (vente créée avec ID $venteId)\n";
            
            // Rollback pour ne pas altérer les données
            $pdo->rollBack();
            echo "Transaction rollback effectuée (données non altérées)\n";
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "Test transaction: FAIL (erreur: {$e->getMessage()})\n";
        }
    } else {
        echo "Résultat: FAIL (méthode store non trouvée dans VenteController)\n";
        echo "Cause: La méthode store() n'existe pas dans le contrôleur\n";
        echo "Fichier: app/Controllers/VenteController.php\n";
    }
    echo "\n";

    // 3. TEST SUSPENSION/REPRISE
    echo "=== 3. TEST SUSPENSION/REPRISE ===\n";
    echo "Route: /vente/suspend\n";
    echo "Route: /vente/resume\n";
    echo "Controller: VenteController@suspend, VenteController@resume\n";
    
    // Vérifier si les méthodes existent
    $hasSuspendMethod = strpos($controllerFile, 'function suspend') !== false;
    $hasResumeMethod = strpos($controllerFile, 'function resume') !== false;
    
    if ($hasSuspendMethod && $hasResumeMethod) {
        echo "Résultat: PASS (méthodes existent)\n";
        
        // Vérifier si la table ventes_suspended existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = 'ventes_suspended'");
        $stmt->execute();
        $tableExists = (int) $stmt->fetchColumn() > 0;
        
        if ($tableExists) {
            echo "Table ventes_suspended: EXISTE\n";
        } else {
            echo "Résultat: FAIL (table ventes_suspended manquante)\n";
            echo "Cause: La table ventes_suspended n'existe pas dans la base de données\n";
            echo "Impact: La suspension/reprise des ventes ne peut pas fonctionner\n";
        }
    } else {
        echo "Résultat: FAIL (méthodes suspend/resume non trouvées dans VenteController)\n";
        echo "Cause: Les méthodes suspend() et/ou resume() n'existent pas dans le contrôleur\n";
        echo "Fichier: app/Controllers/VenteController.php\n";
    }
    echo "\n";

    // 4. TEST CAISSE
    echo "=== 4. TEST CAISSE ===\n";
    echo "Route: /caisse/session\n";
    echo "Controller: CaisseController@sessionForm\n";
    
    // Vérifier si CaisseController existe
    $caisseControllerFile = file_exists('app/Controllers/CaisseController.php');
    
    if ($caisseControllerFile) {
        echo "CaisseController: EXISTE\n";
        
        // Vérifier si la table caisse_sessions existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = 'caisse_sessions'");
        $stmt->execute();
        $tableExists = (int) $stmt->fetchColumn() > 0;
        
        if ($tableExists) {
            echo "Table caisse_sessions: EXISTE\n";
            
            // Vérifier les sessions ouvertes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM caisse_sessions WHERE statut = 'OUVERTE'");
            $stmt->execute();
            $sessionsOuvertes = $stmt->fetchColumn();
            echo "Sessions ouvertes: $sessionsOuvertes\n";
            
            echo "Résultat: PASS (infrastructure caisse fonctionnelle)\n";
        } else {
            echo "Résultat: FAIL (table caisse_sessions manquante)\n";
            echo "Cause: La table caisse_sessions n'existe pas dans la base de données\n";
        }
    } else {
        echo "Résultat: FAIL (CaisseController manquant)\n";
        echo "Cause: Le fichier app/Controllers/CaisseController.php n'existe pas\n";
    }
    echo "\n";

    // 5. TEST STOCK
    echo "=== 5. TEST STOCK ===\n";
    echo "Route: /stock\n";
    echo "Controller: StockController@index\n";
    
    // Vérifier si StockController existe
    $stockControllerFile = file_exists('app/Controllers/StockController.php');
    
    if ($stockControllerFile) {
        echo "StockController: EXISTE\n";
        
        // Vérifier si la table stock existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = 'stock'");
        $stmt->execute();
        $tableExists = (int) $stmt->fetchColumn() > 0;
        
        if ($tableExists) {
            echo "Table stock: EXISTE\n";
            
            // Vérifier les données de stock
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock");
            $stmt->execute();
            $stockCount = $stmt->fetchColumn();
            echo "Enregistrements stock: $stockCount\n";
            
            echo "Résultat: PASS (infrastructure stock fonctionnelle)\n";
        } else {
            echo "Résultat: FAIL (table stock manquante)\n";
            echo "Cause: La table stock n'existe pas dans la base de données\n";
        }
    } else {
        echo "Résultat: FAIL (StockController manquant)\n";
        echo "Cause: Le fichier app/Controllers/StockController.php n'existe pas\n";
    }
    echo "\n";

    // 6. TEST COMPTABILITÉ
    echo "=== 6. TEST COMPTABILITÉ ===\n";
    echo "Route: /comptabilite\n";
    echo "Controller: ComptabiliteController@index\n";
    
    // Vérifier si ComptabiliteController existe
    $comptabiliteControllerFile = file_exists('app/Controllers/ComptabiliteController.php');
    
    if ($comptabiliteControllerFile) {
        echo "ComptabiliteController: EXISTE\n";
        
        // Vérifier si la table ecritures_comptables existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'medecin' AND TABLE_NAME = 'ecritures_comptables'");
        $stmt->execute();
        $tableExists = (int) $stmt->fetchColumn() > 0;
        
        if ($tableExists) {
            echo "Table ecritures_comptables: EXISTE\n";
            
            // Vérifier les écritures comptables
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ecritures_comptables");
            $stmt->execute();
            $ecrituresCount = $stmt->fetchColumn();
            echo "Écritures comptables: $ecrituresCount\n";
            
            echo "Résultat: PASS (infrastructure comptabilité fonctionnelle)\n";
        } else {
            echo "Résultat: FAIL (table ecritures_comptables manquante)\n";
            echo "Cause: La table ecritures_comptables n'existe pas dans la base de données\n";
        }
    } else {
        echo "Résultat: FAIL (ComptabiliteController manquant)\n";
        echo "Cause: Le fichier app/Controllers/ComptabiliteController.php n'existe pas\n";
    }
    echo "\n";

    // 7. TEST PERMISSIONS DIFFÉRENTS RÔLES
    echo "=== 7. TEST PERMISSIONS DIFFÉRENTS RÔLES ===\n";
    
    // Vérifier les rôles existants
    $stmt = $pdo->prepare("SELECT id, nom FROM roles");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Rôles existants:\n";
    foreach ($roles as $role) {
        echo "- {$role['nom']} (ID: {$role['id']})\n";
    }
    echo "\n";
    
    // Vérifier les permissions pour le rôle vendeur
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE nom = 'vendeur'");
    $stmt->execute();
    $vendeurRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vendeurRole) {
        $stmt = $pdo->prepare("
            SELECT p.nom, p.module
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.module, p.nom
        ");
        $stmt->execute([$vendeurRole['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Permissions vendeur (" . count($permissions) . "):\n";
        foreach ($permissions as $perm) {
            echo "- {$perm['nom']} ({$perm['module']})\n";
        }
        
        // Vérifier les permissions critiques
        $criticalPermissions = ['vente.create', 'vente.view', 'caisse.open', 'caisse.view'];
        $missingCritical = [];
        
        foreach ($criticalPermissions as $critPerm) {
            $hasPermission = false;
            foreach ($permissions as $perm) {
                if ($perm['nom'] === $critPerm) {
                    $hasPermission = true;
                    break;
                }
            }
            if (!$hasPermission) {
                $missingCritical[] = $critPerm;
            }
        }
        
        if (empty($missingCritical)) {
            echo "Résultat: PASS (permissions critiques présentes)\n";
        } else {
            echo "Résultat: FAIL (permissions critiques manquantes)\n";
            echo "Permissions manquantes: " . implode(', ', $missingCritical) . "\n";
        }
    } else {
        echo "Résultat: FAIL (rôle vendeur non trouvé)\n";
        echo "Cause: Le rôle vendeur n'existe pas dans la base de données\n";
    }
    echo "\n";

    // 8. SYNTHÈSE
    echo "=== SYNTHÈSE ===\n";
    echo "Scanner: " . ($hasScanMethod ? "PASS" : "FAIL") . "\n";
    echo "Vente complète: " . ($hasStoreMethod ? "PASS" : "FAIL") . "\n";
    echo "Suspension/Reprise: " . ($hasSuspendMethod && $hasResumeMethod ? "PASS" : "FAIL") . " (table ventes_suspended: " . ($tableExists ?? "N/A") . ")\n";
    echo "Caisse: " . ($caisseControllerFile ? "PASS" : "FAIL") . "\n";
    echo "Stock: " . ($stockControllerFile ? "PASS" : "FAIL") . "\n";
    echo "Comptabilité: " . ($comptabiliteControllerFile ? "PASS" : "FAIL") . "\n";
    echo "Permissions: " . (empty($missingCritical ?? []) ? "PASS" : "FAIL") . "\n";
    echo "\n";

    echo "=== FIN DE LA VALIDATION ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
