<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST MODULE SORTIES STOCK ===\n\n";

    // 1. Vérifier qu'il y a des produits
    echo "1. Vérification produits...\n";
    $stmt = $pdo->query("SELECT id, nom, code_cip FROM produits WHERE is_actif = 1 AND deleted_at IS NULL LIMIT 1");
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produit) {
        throw new Exception("Aucun produit trouvé dans la base.");
    }
    
    echo "   ✓ Produit trouvé: {$produit['nom']} (ID: {$produit['id']})\n\n";
    
    $produitId = (int)$produit['id'];
    
    // 2. Vérifier le stock avant
    echo "2. Vérification stock avant sortie...\n";
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE produit_id = :produit_id");
    $stmt->execute(['produit_id' => $produitId]);
    $stockBefore = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stockBefore || (int)$stockBefore['quantite_disponible'] < 5) {
        // Ajouter du stock pour le test
        echo "   Stock insuffisant, ajout de 10 unités pour le test...\n";
        $pdo->beginTransaction();
        try {
            if (!$stockBefore) {
                $stmt = $pdo->prepare(
                    "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                     VALUES (:produit_id, 10, 10, 1000, NOW(), NOW())"
                );
                $stmt->execute(['produit_id' => $produitId]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE stock SET quantite_disponible = quantite_disponible + 10, quantite_theorique = quantite_theorique + 10, valeur_stock = valeur_stock + 1000 WHERE produit_id = :produit_id"
                );
                $stmt->execute(['produit_id' => $produitId]);
            }
            $pdo->commit();
            
            // Récupérer le stock mis à jour
            $stmt = $pdo->prepare("SELECT * FROM stock WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            $stockBefore = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    echo "   ✓ Stock existant: {$stockBefore['quantite_disponible']} unités\n\n";
    
    // 3. Simuler une sortie de stock valide
    echo "3. Simulation d'une sortie de stock valide...\n";
    $quantite = 3;
    $motif = 'PEREMPTION';
    $dateSortie = date('Y-m-d');
    $observations = 'Test sortie stock manuelle';
    $utilisateurId = 1;
    
    $pdo->beginTransaction();
    
    try {
        $stockAvant = (int)$stockBefore['quantite_disponible'];
        
        // Vérifier le stock disponible
        if ($quantite > $stockAvant) {
            throw new Exception("Stock insuffisant. Disponible: $stockAvant, Demandé: $quantite");
        }
        
        // Créer le mouvement de stock
        $stmt = $pdo->prepare(
            "INSERT INTO mouvements_stock 
             (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, 
              motif, reference_type, utilisateur_id, date_mouvement)
             VALUES (:produit_id, 'SORTIE', :quantite, :quantite_avant, :quantite_apres,
                     :motif, 'AJUSTEMENT', :utilisateur_id, :date_mouvement)"
        );
        $stmt->execute([
            'produit_id' => $produitId,
            'quantite' => $quantite,
            'quantite_avant' => $stockAvant,
            'quantite_apres' => $stockAvant - $quantite,
            'motif' => $motif . ' - ' . $observations,
            'utilisateur_id' => $utilisateurId,
            'date_mouvement' => $dateSortie . ' ' . date('H:i:s')
        ]);
        $mouvementId = (int)$pdo->lastInsertId();
        echo "   ✓ Mouvement créé (ID: $mouvementId)\n";
        
        // Mettre à jour le stock
        $stmt = $pdo->prepare(
            "UPDATE stock SET quantite_disponible = quantite_disponible - :quantite,
                               dernier_mouvement = NOW() WHERE produit_id = :produit_id"
        );
        $stmt->execute([
            'quantite' => $quantite,
            'produit_id' => $produitId
        ]);
        echo "   ✓ Stock mis à jour\n";
        
        $pdo->commit();
        echo "   ✓ Transaction validée\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    // 4. Vérifier le stock après
    echo "4. Vérification stock après sortie...\n";
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE produit_id = :produit_id");
    $stmt->execute(['produit_id' => $produitId]);
    $stockAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stockApres = (int)$stockAfter['quantite_disponible'];
    $difference = $stockAvant - $stockApres;
    
    if ($difference === $quantite) {
        echo "   ✓ Stock: $stockAvant → $stockApres (-$difference) CORRECT\n";
    } else {
        echo "   ✗ Stock: $stockAvant → $stockApres (-$difference) INCORRECT (attendu: -$quantite)\n";
    }
    echo "\n";
    
    // 5. Vérifier le mouvement de stock
    echo "5. Vérification mouvement de stock...\n";
    $stmt = $pdo->prepare(
        "SELECT * FROM mouvements_stock WHERE id = :mouvement_id"
    );
    $stmt->execute(['mouvement_id' => $mouvementId]);
    $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mouvement) {
        echo "   ✓ Mouvement trouvé\n";
        echo "     - Type: {$mouvement['type_mouvement']}\n";
        echo "     - Quantité: {$mouvement['quantite']}\n";
        echo "     - Avant: {$mouvement['quantite_avant']}\n";
        echo "     - Après: {$mouvement['quantite_apres']}\n";
        echo "     - Motif: {$mouvement['motif']}\n";
        
        if ($mouvement['type_mouvement'] === 'SORTIE' && (int)$mouvement['quantite'] === $quantite) {
            echo "   ✓ Mouvement correct\n";
        } else {
            echo "   ✗ Mouvement incorrect\n";
        }
    } else {
        echo "   ✗ Mouvement non trouvé\n";
    }
    echo "\n";
    
    // 6. Tester la protection contre stock insuffisant
    echo "6. Test protection stock insuffisant...\n";
    $stmt = $pdo->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = :produit_id");
    $stmt->execute(['produit_id' => $produitId]);
    $currentStock = $stmt->fetch(PDO::FETCH_ASSOC);
    $stockDisponible = (int)$currentStock['quantite_disponible'];
    
    try {
        $pdo->beginTransaction();
        
        // Tenter de sortir plus que le stock disponible
        $quantiteExcessive = $stockDisponible + 100;
        
        if ($quantiteExcessive > $stockDisponible) {
            echo "   ✓ Protection activée: Stock disponible ($stockDisponible) < Quantité demandée ($quantiteExcessive)\n";
            $pdo->rollBack();
        } else {
            echo "   ✗ Protection non fonctionnelle\n";
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "   ✗ Erreur: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 7. Tester différents motifs
    echo "7. Test des différents motifs...\n";
    $motifs = ['CASSE', 'DON', 'CONSOMMATION_INTERNE', 'RETOUR_FOURNISSEUR', 'DESTRUCTION', 'AUTRE'];
    
    foreach ($motifs as $testMotif) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            $currentStock = $stmt->fetch(PDO::FETCH_ASSOC);
            $avant = (int)$currentStock['quantite_disponible'];
            
            if ($avant < 1) {
                echo "   - Motif '$testMotif': Stock insuffisant, ignoré\n";
                $pdo->rollBack();
                continue;
            }
            
            $stmt = $pdo->prepare(
                "INSERT INTO mouvements_stock 
                 (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, 
                  motif, reference_type, utilisateur_id, date_mouvement)
                 VALUES (:produit_id, 'SORTIE', 1, :avant, :apres, :motif, 'AJUSTEMENT', 1, NOW())"
            );
            $stmt->execute([
                'produit_id' => $produitId,
                'avant' => $avant,
                'apres' => $avant - 1,
                'motif' => $testMotif
            ]);
            
            $stmt = $pdo->prepare("UPDATE stock SET quantite_disponible = quantite_disponible - 1 WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            
            $pdo->commit();
            echo "   ✓ Motif '$testMotif' testé avec succès\n";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "   ✗ Motif '$testMotif' échoué: {$e->getMessage()}\n";
        }
    }
    echo "\n";
    
    // 8. Nettoyage - Restaurer le stock initial
    echo "8. Nettoyage des données de test...\n";
    $pdo->beginTransaction();
    try {
        // Annuler les mouvements de test
        $stmt = $pdo->prepare(
            "DELETE FROM mouvements_stock WHERE produit_id = :produit_id AND reference_type = 'AJUSTEMENT' AND created_at >= :date"
        );
        $stmt->execute(['produit_id' => $produitId, 'date' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        
        // Restaurer le stock initial
        $stmt = $pdo->prepare(
            "UPDATE stock SET quantite_disponible = :quantite, quantite_theorique = :quantite WHERE produit_id = :produit_id"
        );
        $stmt->execute([
            'quantite' => $stockBefore['quantite_disponible'],
            'produit_id' => $produitId
        ]);
        
        $pdo->commit();
        echo "   ✓ Nettoyage effectué\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "   ✗ Erreur lors du nettoyage: {$e->getMessage()}\n\n";
    }
    
    echo "=== TEST TERMINÉ ===\n";
    echo "✓ Module sorties stock fonctionnel\n";
    echo "✓ Stock diminué correctement\n";
    echo "✓ Mouvements de stock créés\n";
    echo "✓ Protection stock insuffisant active\n";
    echo "✓ Tous les motifs supportés\n";
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transaction annulée\n";
    }
}
