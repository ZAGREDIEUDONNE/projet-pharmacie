<?php
require 'config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Exécution de la migration 028...\n";

    // Exécuter chaque instruction individuellement
    $permissions = [
        ['create_client', 'Créer un client', 'clients'],
        ['edit_client', 'Modifier un client', 'clients'],
        ['make_sale', 'Effectuer une vente', 'vente'],
        ['cancel_ticket', 'Annuler un ticket', 'vente'],
        ['apply_discount', 'Appliquer une remise', 'vente'],
        ['close_cash_register', 'Fermer la caisse', 'caisse'],
        ['view_statistics', 'Voir les statistiques', 'statistiques'],
        ['view_stock_movements', 'Voir les mouvements de stock', 'stock'],
        ['prepare_orders', 'Préparer les commandes', 'commandes']
    ];

    // Récupérer l'ID du rôle assistant
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE nom = 'assistant'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo "ERREUR: Le rôle 'assistant' n'existe pas.\n";
        exit;
    }

    $roleId = $role['id'];
    echo "Rôle assistant ID: $roleId\n\n";

    // Insérer les permissions manquantes
    foreach ($permissions as $perm) {
        $nom = $perm[0];
        $description = $perm[1];
        $module = $perm[2];

        // Vérifier si la permission existe
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE nom = ?");
        $stmt->execute([$nom]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $stmt = $pdo->prepare("INSERT INTO permissions (nom, description, module, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$nom, $description, $module]);
            echo "Permission créée: $nom\n";
        } else {
            echo "Permission existe déjà: $nom\n";
        }
    }

    // Associer les permissions au rôle
    echo "\nAssociation des permissions au rôle...\n";
    foreach ($permissions as $perm) {
        $nom = $perm[0];

        // Récupérer l'ID de la permission
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE nom = ?");
        $stmt->execute([$nom]);
        $permData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($permData) {
            $permId = $permData['id'];

            // Vérifier si l'association existe
            $stmt = $pdo->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $stmt->execute([$roleId, $permId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$roleId, $permId]);
                echo "Association créée: $nom\n";
            } else {
                echo "Association existe déjà: $nom\n";
            }
        }
    }

    echo "\nMigration 028 exécutée avec succès.\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
