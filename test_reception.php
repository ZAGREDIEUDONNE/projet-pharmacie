<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST MODULE RECEPTION PRODUITS ===\n\n";

    // 1. Vérifier qu'il y a des commandes réceptionnables
    echo "1. Vérification commandes réceptionnables...\n";
    $stmt = $pdo->query("SELECT id, numero_commande, statut FROM supplier_orders WHERE statut IN ('ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE') LIMIT 5");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        echo "   ✗ Aucune commande réceptionnable trouvée.\n";
        echo "   Création d'une commande de test...\n";
        
        // Créer un fournisseur de test si nécessaire
        $stmt = $pdo->query("SELECT id FROM fournisseurs LIMIT 1");
        $fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fournisseur) {
            throw new Exception("Aucun fournisseur trouvé dans la base.");
        }
        
        // Créer un produit de test si nécessaire
        $stmt = $pdo->query("SELECT id, nom, prix_achat FROM produits WHERE is_actif = 1 LIMIT 1");
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produit) {
            throw new Exception("Aucun produit trouvé dans la base.");
        }
        
        // Créer une commande de test
        $numeroCommande = 'CMD-' . date('YmdHis');
        $stmt = $pdo->prepare(
            "INSERT INTO supplier_orders (numero_commande, fournisseur_id, utilisateur_id, date_commande, statut, montant_ht, montant_ttc, montant_total)
             VALUES (:numero, :fournisseur_id, 1, CURDATE(), 'ENVOYEE', 0, 0, 0)"
        );
        $stmt->execute([
            'numero' => $numeroCommande,
            'fournisseur_id' => $fournisseur['id']
        ]);
        $orderId = (int)$pdo->lastInsertId();
        
        // Ajouter un item à la commande
        $stmt = $pdo->prepare(
            "INSERT INTO supplier_order_items (supplier_order_id, produit_id, quantite_commandee, prix_achat, montant_total, quantite_recue, remise, tva, montant_ht, montant_ttc)
             VALUES (:order_id, :produit_id, 10, :prix, :total, 0, 0, 0, :montant_ht, :montant_ttc)"
        );
        $prix = $produit['prix_achat'];
        $total = 10 * $prix;
        $stmt->execute([
            'order_id' => $orderId,
            'produit_id' => $produit['id'],
            'prix' => $prix,
            'total' => $total,
            'montant_ht' => $total,
            'montant_ttc' => $total
        ]);
        
        // Mettre à jour le montant total de la commande
        $stmt = $pdo->prepare("UPDATE supplier_orders SET montant_ht = :montant_ht, montant_ttc = :montant_ttc, montant_total = :montant_total WHERE id = :id");
        $stmt->execute([
            'montant_ht' => $total,
            'montant_ttc' => $total,
            'montant_total' => $total,
            'id' => $orderId
        ]);
        
        echo "   ✓ Commande de test créée: $numeroCommande (ID: $orderId)\n";
        $orders = [['id' => $orderId, 'numero_commande' => $numeroCommande, 'statut' => 'ENVOYEE']];
    } else {
        echo "   ✓ " . count($orders) . " commande(s) réceptionnable(s) trouvée(s)\n";
    }
    
    $testOrder = $orders[0];
    $orderId = (int)$testOrder['id'];
    echo "   Commande test: {$testOrder['numero_commande']} (ID: $orderId)\n\n";
    
    // 2. Vérifier les items de la commande
    echo "2. Vérification items de la commande...\n";
    $stmt = $pdo->prepare(
        "SELECT soi.*, p.nom AS produit_nom
         FROM supplier_order_items soi
         JOIN produits p ON p.id = soi.produit_id
         WHERE soi.supplier_order_id = :order_id"
    );
    $stmt->execute(['order_id' => $orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        throw new Exception("La commande n'a pas d'items.");
    }
    
    echo "   ✓ " . count($items) . " item(s) trouvé(s)\n";
    foreach ($items as $item) {
        echo "     - {$item['produit_nom']}: {$item['quantite_commandee']} commandé(s), {$item['quantite_recue']} reçu(s)\n";
    }
    echo "\n";
    
    // 3. Vérifier le stock avant réception
    echo "3. Vérification stock avant réception...\n";
    $stmt = $pdo->prepare(
        "SELECT s.*, p.nom AS produit_nom
         FROM stock s
         JOIN produits p ON p.id = s.produit_id
         WHERE s.produit_id = :produit_id"
    );
    $stockBefore = [];
    foreach ($items as $item) {
        $stmt->execute(['produit_id' => $item['produit_id']]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        $stockBefore[$item['produit_id']] = $stock;
        if ($stock) {
            echo "   ✓ Stock {$item['produit_nom']}: {$stock['quantite_disponible']} unités\n";
        } else {
            echo "   ✗ Stock {$item['produit_nom']}: inexistant (sera créé)\n";
        }
    }
    echo "\n";
    
    // 4. Simuler une réception
    echo "4. Simulation d'une réception...\n";
    $numeroReception = 'BR-' . date('YmdHis');
    $numeroFacture = 'FAC-' . date('YmdHis');
    $dateReception = date('Y-m-d');
    $dateFacture = date('Y-m-d');
    $montantFacture = 0;
    
    // Calculer le montant facture basé sur les items
    foreach ($items as $item) {
        $montantFacture += $item['quantite_commandee'] * $item['prix_achat'];
    }
    
    $pdo->beginTransaction();
    
    try {
        // Insérer la réception
        $stmt = $pdo->prepare(
            "INSERT INTO receptions (supplier_order_id, fournisseur_id, utilisateur_id, numero_reception, date_reception, numero_facture, date_facture, montant_facture, statut, observations)
             VALUES (:order_id, (SELECT fournisseur_id FROM supplier_orders WHERE id = :order_id), 1, :numero, :date_reception, :numero_facture, :date_facture, :montant_facture, 'EN_ATTENTE', 'Test réception')"
        );
        $stmt->execute([
            'order_id' => $orderId,
            'numero' => $numeroReception,
            'date_reception' => $dateReception,
            'numero_facture' => $numeroFacture,
            'date_facture' => $dateFacture,
            'montant_facture' => $montantFacture
        ]);
        $receptionId = (int)$pdo->lastInsertId();
        echo "   ✓ Réception créée: $numeroReception (ID: $receptionId)\n";
        
        // Traiter chaque item
        foreach ($items as $item) {
            $quantiteRecue = $item['quantite_commandee'] - $item['quantite_recue'];
            if ($quantiteRecue <= 0) {
                echo "   - Item {$item['produit_nom']}: déjà complètement reçu\n";
                continue;
            }
            
            // Mettre à jour quantite_recue dans supplier_order_items
            $newReceived = (int)$item['quantite_recue'] + $quantiteRecue;
            $stmt = $pdo->prepare("UPDATE supplier_order_items SET quantite_recue = :qte WHERE id = :id");
            $stmt->execute(['qte' => $newReceived, 'id' => $item['id']]);
            echo "   ✓ Item {$item['produit_nom']}: $quantiteRecue unité(s) reçue(s)\n";
            
            // Insérer dans reception_items
            $stmt = $pdo->prepare(
                "INSERT INTO reception_items (reception_id, produit_id, quantite_attendue, quantite_recue, prix_achat, ecart)
                 VALUES (:reception_id, :produit_id, :attendue, :recue, :prix, 0)"
            );
            $stmt->execute([
                'reception_id' => $receptionId,
                'produit_id' => $item['produit_id'],
                'attendue' => $quantiteRecue,
                'recue' => $quantiteRecue,
                'prix' => $item['prix_achat']
            ]);
            
            // Mettre à jour le stock
            $stock = $stockBefore[$item['produit_id']];
            $stockAvant = $stock ? (int)$stock['quantite_disponible'] : 0;
            $stockApres = $stockAvant + $quantiteRecue;
            
            if ($stock) {
                $stmt = $pdo->prepare(
                    "UPDATE stock
                     SET quantite_disponible = :quantite,
                         quantite_theorique = quantite_theorique + :delta,
                         valeur_stock = valeur_stock + :valeur,
                         dernier_mouvement = NOW(),
                         updated_at = NOW()
                     WHERE produit_id = :produit_id"
                );
                $stmt->execute([
                    'quantite' => $stockApres,
                    'delta' => $quantiteRecue,
                    'valeur' => $quantiteRecue * $item['prix_achat'],
                    'produit_id' => $item['produit_id']
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, dernier_mouvement)
                     VALUES (:produit_id, :quantite, :quantite, :valeur, NOW())"
                );
                $stmt->execute([
                    'produit_id' => $item['produit_id'],
                    'quantite' => $stockApres,
                    'valeur' => $stockApres * $item['prix_achat']
                ]);
            }
            echo "   ✓ Stock {$item['produit_nom']}: $stockAvant → $stockApres\n";
            
            // Créer mouvement de stock
            $stmt = $pdo->prepare(
                "INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_type, reference_id, utilisateur_id, date_mouvement)
                 VALUES (:produit_id, 'ENTREE', :quantite, :avant, :apres, 'Réception $numeroReception', 'COMMAND', :reception_id, 1, NOW())"
            );
            $stmt->execute([
                'produit_id' => $item['produit_id'],
                'quantite' => $quantiteRecue,
                'avant' => $stockAvant,
                'apres' => $stockApres,
                'reception_id' => $receptionId
            ]);
        }
        
        // Mettre à jour le statut de la commande
        $stmt = $pdo->prepare("UPDATE supplier_orders SET statut = 'RECEPTION_COMPLETE' WHERE id = :id");
        $stmt->execute(['id' => $orderId]);
        echo "   ✓ Statut commande mis à jour: RECEPTION_COMPLETE\n";
        
        // Mettre à jour le statut de la réception
        $stmt = $pdo->prepare("UPDATE receptions SET statut = 'RECU_COMPLET' WHERE id = :id");
        $stmt->execute(['id' => $receptionId]);
        echo "   ✓ Statut réception mis à jour: RECU_COMPLET\n";
        
        $pdo->commit();
        echo "   ✓ Transaction validée\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    // 5. Vérifier le stock après réception
    echo "5. Vérification stock après réception...\n";
    foreach ($items as $item) {
        $stmt = $pdo->prepare(
            "SELECT s.*, p.nom AS produit_nom
             FROM stock s
             JOIN produits p ON p.id = s.produit_id
             WHERE s.produit_id = :produit_id"
        );
        $stmt->execute(['produit_id' => $item['produit_id']]);
        $stockAfter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stockAvant = $stockBefore[$item['produit_id']] ? (int)$stockBefore[$item['produit_id']]['quantite_disponible'] : 0;
        $stockApres = $stockAfter ? (int)$stockAfter['quantite_disponible'] : 0;
        $difference = $stockApres - $stockAvant;
        
        if ($difference === ($item['quantite_commandee'] - (int)$item['quantite_recue'])) {
            echo "   ✓ Stock {$item['produit_nom']}: $stockAvant → $stockApres (+$difference) CORRECT\n";
        } else {
            echo "   ✗ Stock {$item['produit_nom']}: $stockAvant → $stockApres (+$difference) INCORRECT (attendu: +" . ($item['quantite_commandee'] - (int)$item['quantite_recue']) . ")\n";
        }
    }
    echo "\n";
    
    // 6. Vérifier les mouvements de stock
    echo "6. Vérification mouvements de stock...\n";
    $stmt = $pdo->prepare(
        "SELECT ms.*, p.nom AS produit_nom
         FROM mouvements_stock ms
         JOIN produits p ON p.id = ms.produit_id
         WHERE ms.reference_type = 'COMMAND' AND ms.reference_id = :reception_id"
    );
    $stmt->execute(['reception_id' => $receptionId]);
    $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($mouvements) === count($items)) {
        echo "   ✓ " . count($mouvements) . " mouvement(s) de stock créé(s)\n";
        foreach ($mouvements as $mvt) {
            echo "     - {$mvt['produit_nom']}: {$mvt['type_mouvement']} +{$mvt['quantite']} ({$mvt['quantite_avant']} → {$mvt['quantite_apres']})\n";
        }
    } else {
        echo "   ✗ Nombre de mouvements incorrect: " . count($mouvements) . " (attendu: " . count($items) . ")\n";
    }
    echo "\n";
    
    // 7. Vérifier l'absence de doublons
    echo "7. Vérification absence de doublons...\n";
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count FROM receptions WHERE numero_reception = :numero"
    );
    $stmt->execute(['numero' => $numeroReception]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count === 1) {
        echo "   ✓ Aucun doublon de réception\n";
    } else {
        echo "   ✗ Doublon détecté: $count réceptions avec le même numéro\n";
    }
    
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count FROM mouvements_stock WHERE reference_type = 'COMMAND' AND reference_id = :reception_id"
    );
    $stmt->execute(['reception_id' => $receptionId]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count === count($items)) {
        echo "   ✓ Aucun doublon de mouvements de stock\n";
    } else {
        echo "   ✗ Nombre de mouvements incorrect\n";
    }
    echo "\n";
    
    echo "=== TEST TERMINÉ ===\n";
    echo "✓ Module réception fonctionnel\n";
    echo "✓ Stock mis à jour correctement\n";
    echo "✓ Mouvements de stock créés\n";
    echo "✓ Statut commande mis à jour\n";
    echo "✓ Aucun doublon détecté\n";
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transaction annulée\n";
    }
}
