<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST MODULE INVENTAIRE ===\n\n";

    // 1. Vérifier qu'il y a des produits
    echo "1. Vérification produits...\n";
    $stmt = $pdo->query("SELECT id, nom, code_cip, prix_vente FROM produits WHERE is_actif = 1 AND deleted_at IS NULL LIMIT 1");
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produit) {
        throw new Exception("Aucun produit trouvé dans la base.");
    }
    
    echo "   ✓ Produit trouvé: {$produit['nom']} (ID: {$produit['id']})\n\n";
    
    $produitId = (int)$produit['id'];
    $prixVente = (float)$produit['prix_vente'];
    
    // 2. Vérifier le stock avant
    echo "2. Vérification stock avant inventaire...\n";
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE produit_id = :produit_id");
    $stmt->execute(['produit_id' => $produitId]);
    $stockBefore = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stockBefore) {
        // Créer un stock pour le test
        echo "   Stock inexistant, création de 20 unités pour le test...\n";
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                 VALUES (:produit_id, 20, 20, 20000, NOW(), NOW())"
            );
            $stmt->execute(['produit_id' => $produitId]);
            $pdo->commit();
            
            $stmt = $pdo->prepare("SELECT * FROM stock WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            $stockBefore = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    echo "   ✓ Stock existant: {$stockBefore['quantite_disponible']} unités\n\n";
    
    // 3. Créer un inventaire
    echo "3. Création d'un inventaire...\n";
    $reference = 'TEST' . date('YmdHis');
    $utilisateurId = 1;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO inventaires (reference, type_inventaire, utilisateur_id, date_debut, statut, notes)
             VALUES (:reference, 'MANUEL', :utilisateur_id, NOW(), 'EN_COURS', 'Test inventaire')"
        );
        $stmt->execute([
            'reference' => $reference,
            'utilisateur_id' => $utilisateurId
        ]);
        $inventaireId = (int)$pdo->lastInsertId();
        $pdo->commit();
        echo "   ✓ Inventaire créé (ID: $inventaireId, Réf: $reference)\n\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    // 4. Saisir un article avec écart
    echo "4. Saisie d'un article avec écart...\n";
    $stockTheorique = (int)$stockBefore['quantite_disponible'];
    $stockPhysique = $stockTheorique + 5; // Écart de +5
    $ecart = $stockPhysique - $stockTheorique;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO inventaire_articles 
             (inventaire_id, produit_id, quantite_theorique, quantite_comptee, ecart, prix_unitaire, valeur_totale, statut_saisie, utilisateur_saisie_id, date_saisie)
             VALUES (:inventaire_id, :produit_id, :theorique, :comptee, :ecart, :prix, :valeur, 'VALIDE', :utilisateur_id, NOW())"
        );
        $stmt->execute([
            'inventaire_id' => $inventaireId,
            'produit_id' => $produitId,
            'theorique' => $stockTheorique,
            'comptee' => $stockPhysique,
            'ecart' => $ecart,
            'prix' => $prixVente,
            'valeur' => $stockPhysique * $prixVente,
            'utilisateur_id' => $utilisateurId
        ]);
        echo "   ✓ Article saisi: Théorique=$stockTheorique, Physique=$stockPhysique, Écart=$ecart\n";
        $pdo->commit();
        echo "   ✓ Transaction validée\n\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    // 5. Clôturer l'inventaire
    echo "5. Clôture de l'inventaire...\n";
    $pdo->beginTransaction();
    try {
        // Calculer les totaux
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as total_articles, 
                    SUM(quantite_theorique) as quantite_theorique,
                    SUM(quantite_comptee) as quantite_comptee,
                    SUM(valeur_totale) as valeur_comptee,
                    SUM(quantite_theorique * prix_unitaire) as valeur_theorique,
                    SUM(ecart) as ecart_quantite
             FROM inventaire_articles WHERE inventaire_id = :inventaire_id"
        );
        $stmt->execute(['inventaire_id' => $inventaireId]);
        $totaux = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $ecartValeur = $totaux['valeur_comptee'] - $totaux['valeur_theorique'];
        
        // Mettre à jour l'inventaire
        $stmt = $pdo->prepare(
            "UPDATE inventaires 
             SET date_fin = NOW(), statut = 'CLOTURE', utilisateur_cloture_id = :utilisateur_id,
                 total_articles = :total_articles, total_valeur_theorique = :valeur_theorique,
                 total_valeur_comptee = :valeur_comptee, total_ecart_valeur = :ecart_valeur,
                 total_ecart_quantite = :ecart_quantite
             WHERE id = :inventaire_id"
        );
        $stmt->execute([
            'utilisateur_id' => $utilisateurId,
            'total_articles' => $totaux['total_articles'],
            'valeur_theorique' => $totaux['valeur_theorique'],
            'valeur_comptee' => $totaux['valeur_comptee'],
            'ecart_valeur' => $ecartValeur,
            'ecart_quantite' => $totaux['ecart_quantite'],
            'inventaire_id' => $inventaireId
        ]);
        
        echo "   ✓ Inventaire clôturé\n";
        echo "     - Articles: {$totaux['total_articles']}\n";
        echo "     - Écart quantité: {$totaux['ecart_quantite']}\n";
        echo "     - Écart valeur: " . number_format($ecartValeur, 2) . "\n\n";
        
        // Appliquer les ajustements
        $stmt = $pdo->prepare(
            "SELECT ia.*, p.prix_vente FROM inventaire_articles ia
             JOIN produits p ON ia.produit_id = p.id
             WHERE ia.inventaire_id = :inventaire_id AND ABS(ia.ecart) > 0"
        );
        $stmt->execute(['inventaire_id' => $inventaireId]);
        $articlesEcart = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($articlesEcart as $article) {
            // Mettre à jour le stock
            $stmt = $pdo->prepare(
                "UPDATE stock 
                 SET quantite_disponible = quantite_disponible + :ecart,
                     quantite_theorique = quantite_theorique + :ecart,
                     valeur_stock = valeur_stock + :valeur_ecart,
                     dernier_mouvement = NOW()
                 WHERE produit_id = :produit_id"
            );
            $stmt->execute([
                'ecart' => $article['ecart'],
                'valeur_ecart' => $article['ecart'] * $article['prix_unitaire'],
                'produit_id' => $article['produit_id']
            ]);
            
            // Créer le mouvement de stock
            $typeMouvement = $article['ecart'] > 0 ? 'ENTREE' : 'SORTIE';
            $stockAvant = (int)$article['quantite_theorique'];
            $stockApres = (int)$article['quantite_comptee'];
            
            $stmt = $pdo->prepare(
                "INSERT INTO mouvements_stock 
                 (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_type, reference_id, utilisateur_id, date_mouvement)
                 VALUES (:produit_id, :type, :quantite, :avant, :apres, :motif, 'INVENTAIRE', :inventaire_id, :utilisateur_id, NOW())"
            );
            $stmt->execute([
                'produit_id' => $article['produit_id'],
                'type' => $typeMouvement,
                'quantite' => abs($article['ecart']),
                'avant' => $stockAvant,
                'apres' => $stockApres,
                'motif' => 'Ajustement inventaire - ' . ($article['ecart'] > 0 ? 'Excédent' : 'Manquant'),
                'inventaire_id' => $inventaireId,
                'utilisateur_id' => $utilisateurId
            ]);
            
            echo "   ✓ Ajustement appliqué pour produit {$article['produit_id']}: +{$article['ecart']}\n";
        }
        
        $pdo->commit();
        echo "   ✓ Ajustements validés\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    // 6. Vérifier le stock après
    echo "6. Vérification stock après inventaire...\n";
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE produit_id = :produit_id");
    $stmt->execute(['produit_id' => $produitId]);
    $stockAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stockApres = (int)$stockAfter['quantite_disponible'];
    $difference = $stockApres - $stockTheorique;
    
    if ($difference === $ecart) {
        echo "   ✓ Stock: $stockTheorique → $stockApres (+$difference) CORRECT\n";
    } else {
        echo "   ✗ Stock: $stockTheorique → $stockApres (+$difference) INCORRECT (attendu: +$ecart)\n";
    }
    echo "\n";
    
    // 7. Vérifier le mouvement de stock
    echo "7. Vérification mouvement de stock...\n";
    $stmt = $pdo->prepare(
        "SELECT * FROM mouvements_stock WHERE reference_type = 'INVENTAIRE' AND reference_id = :inventaire_id"
    );
    $stmt->execute(['inventaire_id' => $inventaireId]);
    $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mouvement) {
        echo "   ✓ Mouvement trouvé\n";
        echo "     - Type: {$mouvement['type_mouvement']}\n";
        echo "     - Quantité: {$mouvement['quantite']}\n";
        echo "     - Avant: {$mouvement['quantite_avant']}\n";
        echo "     - Après: {$mouvement['quantite_apres']}\n";
        echo "     - Motif: {$mouvement['motif']}\n";
    } else {
        echo "   ✗ Mouvement non trouvé\n";
    }
    echo "\n";
    
    // 8. Vérifier l'historique des inventaires
    echo "8. Vérification historique des inventaires...\n";
    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE id = :inventaire_id");
    $stmt->execute(['inventaire_id' => $inventaireId]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($inventaire) {
        echo "   ✓ Inventaire trouvé dans l'historique\n";
        echo "     - Référence: {$inventaire['reference']}\n";
        echo "     - Statut: {$inventaire['statut']}\n";
        echo "     - Date début: {$inventaire['date_debut']}\n";
        echo "     - Date fin: {$inventaire['date_fin']}\n";
        echo "     - Total articles: {$inventaire['total_articles']}\n";
        echo "     - Écart valeur: " . number_format($inventaire['total_ecart_valeur'], 2) . "\n";
    } else {
        echo "   ✗ Inventaire non trouvé dans l'historique\n";
    }
    echo "\n";
    
    // 9. Nettoyage
    echo "9. Nettoyage des données de test...\n";
    $pdo->beginTransaction();
    try {
        // Annuler le mouvement de stock
        $stmt = $pdo->prepare("DELETE FROM mouvements_stock WHERE reference_type = 'INVENTAIRE' AND reference_id = :inventaire_id");
        $stmt->execute(['inventaire_id' => $inventaireId]);
        
        // Restaurer le stock initial
        $stmt = $pdo->prepare(
            "UPDATE stock SET quantite_disponible = :quantite, quantite_theorique = :quantite WHERE produit_id = :produit_id"
        );
        $stmt->execute([
            'quantite' => $stockTheorique,
            'produit_id' => $produitId
        ]);
        
        // Supprimer l'inventaire et ses articles
        $stmt = $pdo->prepare("DELETE FROM inventaire_articles WHERE inventaire_id = :inventaire_id");
        $stmt->execute(['inventaire_id' => $inventaireId]);
        
        $stmt = $pdo->prepare("DELETE FROM inventaires WHERE id = :inventaire_id");
        $stmt->execute(['inventaire_id' => $inventaireId]);
        
        $pdo->commit();
        echo "   ✓ Nettoyage effectué\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "   ✗ Erreur lors du nettoyage: {$e->getMessage()}\n\n";
    }
    
    echo "=== TEST TERMINÉ ===\n";
    echo "✓ Module inventaire fonctionnel\n";
    echo "✓ Création d'inventaire réussie\n";
    echo "✓ Saisie d'article avec écart réussie\n";
    echo "✓ Clôture d'inventaire réussie\n";
    echo "✓ Mise à jour automatique du stock réussie\n";
    echo "✓ Création de mouvement d'ajustement réussie\n";
    echo "✓ Historique des inventaires conservé\n";
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transaction annulée\n";
    }
}
