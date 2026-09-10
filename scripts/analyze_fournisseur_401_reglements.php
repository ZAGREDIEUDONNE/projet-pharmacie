<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ÉTAPE 11 - ANALYSE COMPTE FOURNISSEUR 401 ET RÈGLEMENTS ===\n\n";

    // Vérifier si le compte 401 existe dans le plan comptable
    echo "=== COMPTE 401 - FOURNISSEURS ===\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM plan_comptable WHERE numero_compte LIKE '401%'");
    $stmt->execute();
    $compte401 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($compte401) {
        echo "Comptes 401 trouvés:\n";
        foreach ($compte401 as $compte) {
            echo "- {$compte['numero_compte']}: {$compte['nom_compte']} (Actif: {$compte['is_actif']}, Solde: {$compte['solde_actuel']})\n";
        }
    } else {
        echo "ATTENTION: Aucun compte 401 trouvé dans le plan comptable\n";
    }

    // Vérifier les écritures comptables liées aux fournisseurs
    echo "\n=== ÉCRITURES COMPTABLES LIÉES AUX FOURNISSEURS ===\n\n";
    
    $stmt = $pdo->prepare("
        SELECT ec.*, pc.numero_compte, pc.nom_compte
        FROM ecritures_comptables ec
        JOIN lignes_ecritures le ON ec.id = le.ecriture_id
        JOIN plan_comptable pc ON le.compte_id = pc.id
        WHERE pc.numero_compte LIKE '401%'
        ORDER BY ec.date_ecriture DESC
        LIMIT 10
    ");
    $stmt->execute();
    $ecrituresFournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($ecrituresFournisseurs) {
        echo "Dernières écritures sur comptes 401:\n";
        foreach ($ecrituresFournisseurs as $ecriture) {
            echo "- {$ecriture['date_ecriture']}: {$ecriture['numero_compte']} - {$ecriture['nom_compte']} (Ref: {$ecriture['reference_type']}-{$ecriture['reference_id']})\n";
        }
    } else {
        echo "Aucune écriture trouvée sur les comptes 401\n";
    }

    // Vérifier la table fournisseur_reglements
    echo "\n=== TABLE FOURNISSEUR_REGLEMENTS ===\n\n";
    
    $stmt = $pdo->query("DESCRIBE fournisseur_reglements");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Structure de la table:\n";
    foreach ($structure as $col) {
        echo "- {$col['Field']}: {$col['Type']} (Null: {$col['Null']}, Default: {$col['Default']})\n";
    }
    
    // Vérifier les données dans fournisseur_reglements
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fournisseur_reglements");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nNombre d'enregistrements: $total\n";
    
    if ($total > 0) {
        $stmt = $pdo->query("SELECT * FROM fournisseur_reglements LIMIT 5");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nDerniers règlements:\n";
        foreach ($data as $row) {
            echo "- ID {$row['id']}: Fournisseur {$row['fournisseur_id']}, Type {$row['type_mouvement']}, Montant {$row['montant']}\n";
        }
    }

    // Vérifier les réceptions avec écritures comptables
    echo "\n=== RÉCEPTIONS AVEC ÉCRITURES COMPTABLES ===\n\n";
    
    $stmt = $pdo->query("
        SELECT r.id, r.numero_reception, r.date_reception, r.montant_facture, r.ecriture_id
        FROM receptions r
        WHERE r.ecriture_id IS NOT NULL
        ORDER BY r.date_reception DESC
        LIMIT 5
    ");
    $receptionsAvecEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($receptionsAvecEcritures) {
        echo "Réceptions avec écritures comptables:\n";
        foreach ($receptionsAvecEcritures as $rec) {
            echo "- {$rec['numero_reception']}: Facture {$rec['montant_facture']}, Écriture ID {$rec['ecriture_id']}\n";
        }
    } else {
        echo "Aucune réception avec écriture comptable trouvée\n";
    }

    // Vérifier le solde fournisseur par rapport aux écritures comptables
    echo "\n=== ANALYSE SOLDE FOURNISSEUR ===\n\n";
    
    $stmt = $pdo->query("
        SELECT f.id, f.nom, 
               COALESCE(SUM(fr.montant), 0) as total_reglements
        FROM fournisseurs f
        LEFT JOIN fournisseur_reglements fr ON f.id = fr.fournisseur_id
        GROUP BY f.id, f.nom
        ORDER BY f.nom
        LIMIT 5
    ");
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Solde fournisseur (règlements):\n";
    foreach ($fournisseurs as $fournisseur) {
        echo "- {$fournisseur['nom']}: Total règlements {$fournisseur['total_reglements']}\n";
    }

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
