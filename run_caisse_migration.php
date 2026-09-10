<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=pharmacie;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table caisse_sessions
    echo "Création de la table caisse_sessions...\n";
    $sql = "CREATE TABLE IF NOT EXISTS caisse_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_session VARCHAR(50) UNIQUE NOT NULL,
        caissier_id INT NOT NULL,
        date_ouverture DATETIME NOT NULL,
        date_fermeture DATETIME NULL,
        montant_ouverture DECIMAL(10,2) DEFAULT 0,
        montant_fermeture DECIMAL(10,2) NULL,
        montant_theorique DECIMAL(10,2) NULL,
        montant_ventes DECIMAL(10,2) DEFAULT 0,
        ecart DECIMAL(10,2) DEFAULT 0,
        statut_session ENUM('OUVERTE', 'FERMEE', 'CONTROLEE') DEFAULT 'OUVERTE',
        notes_controle TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sql);
    echo "✓ Table caisse_sessions créée\n";
    
    // Créer la table mouvements_caisse
    echo "Création de la table mouvements_caisse...\n";
    $sql = "CREATE TABLE IF NOT EXISTS mouvements_caisse (
        id INT AUTO_INCREMENT PRIMARY KEY,
        caisse_session_id INT NOT NULL,
        type_mouvement ENUM('VENTE', 'REMBOURSEMENT', 'APPROVISIONNEMENT', 'RETRAIT', 'DECAISSEMENT') NOT NULL,
        montant DECIMAL(10,2) NOT NULL,
        moyen_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE', 'VIREMENT') DEFAULT 'ESPECE',
        reference VARCHAR(100) NULL,
        description TEXT NULL,
        date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
        utilisateur_id INT NOT NULL,
        vente_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sql);
    echo "✓ Table mouvements_caisse créée\n";
    
    // Créer les index
    echo "Création des index...\n";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_mouvements_session ON mouvements_caisse(caisse_session_id)",
        "CREATE INDEX IF NOT EXISTS idx_mouvements_type ON mouvements_caisse(type_mouvement)",
        "CREATE INDEX IF NOT EXISTS idx_mouvements_date ON mouvements_caisse(date_mouvement)",
        "CREATE INDEX IF NOT EXISTS idx_sessions_caissier ON caisse_sessions(caissier_id)",
        "CREATE INDEX IF NOT EXISTS idx_sessions_date ON caisse_sessions(date_ouverture)",
        "CREATE INDEX IF NOT EXISTS idx_sessions_statut ON caisse_sessions(statut_session)"
    ];
    
    foreach ($indexes as $indexSql) {
        try {
            $db->exec($indexSql);
            echo "✓ Index créé\n";
        } catch (PDOException $e) {
            // L'index existe peut-être déjà
            echo "⊘ Index existe déjà ou erreur ignorée\n";
        }
    }
    
    echo "\n=== Migration terminée avec succès ===\n";
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
