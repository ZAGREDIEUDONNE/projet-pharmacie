<?php

/**
 * Migration métier Burkina — compatible MySQL/MariaDB sans ADD COLUMN IF NOT EXISTS.
 * Usage: php scripts/migrer_burkina_safe.php
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

$steps = [];

try {
    $productColumns = [
        'rayon' => "ALTER TABLE produits ADD COLUMN rayon VARCHAR(120) NULL AFTER description",
        'dci' => "ALTER TABLE produits ADD COLUMN dci VARCHAR(200) NULL AFTER rayon",
        'classe_pharmaceutique' => "ALTER TABLE produits ADD COLUMN classe_pharmaceutique VARCHAR(120) NULL AFTER dci",
        'forme_pharmaceutique' => "ALTER TABLE produits ADD COLUMN forme_pharmaceutique VARCHAR(120) NULL AFTER classe_pharmaceutique",
        'type_delivrance' => "ALTER TABLE produits ADD COLUMN type_delivrance ENUM('MEDICAMENT_CONSEIL','HORS_LISTE','ORDONNANCIER','PSYCHOTROPE','ANTICANCEREUX') NOT NULL DEFAULT 'MEDICAMENT_CONSEIL' AFTER forme_pharmaceutique",
    ];

    foreach ($productColumns as $col => $sql) {
        if (!columnExists($pdo, 'produits', $col)) {
            $pdo->exec($sql);
            $steps[] = "Colonne produits.$col ajoutée";
        }
    }

    $pdo->exec('ALTER TABLE produits MODIFY fournisseur_id INT NULL');
    $steps[] = 'fournisseur_id nullable';

    $receptionColumns = [
        'date_facture' => "ALTER TABLE receptions ADD COLUMN date_facture DATE NULL AFTER numero_facture",
        'reference_facture' => "ALTER TABLE receptions ADD COLUMN reference_facture VARCHAR(120) NULL AFTER date_facture",
        'montant_facture' => "ALTER TABLE receptions ADD COLUMN montant_facture DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER reference_facture",
    ];

    foreach ($receptionColumns as $col => $sql) {
        if (tableExists($pdo, 'receptions') && !columnExists($pdo, 'receptions', $col)) {
            $pdo->exec($sql);
            $steps[] = "Colonne receptions.$col ajoutée";
        }
    }

    if (!tableExists($pdo, 'role_discount_limits')) {
        $pdo->exec("CREATE TABLE role_discount_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL,
            max_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_discount_limit (role_id),
            FOREIGN KEY (role_id) REFERENCES roles(id)
        )");
        $steps[] = 'Table role_discount_limits créée';
    }

    $pdo->exec("INSERT INTO role_discount_limits (role_id, max_discount_percent)
        SELECT id,
               CASE
                   WHEN LOWER(nom) IN ('admin', 'administrateur') THEN 25.00
                   WHEN LOWER(nom) IN ('assistant', 'pharmacien') THEN 15.00
                   WHEN LOWER(nom) IN ('vendeur') THEN 10.00
                   ELSE 0.00
               END
        FROM roles
        ON DUPLICATE KEY UPDATE max_discount_percent = VALUES(max_discount_percent)");
    $steps[] = 'Plafonds remise initialisés';

    if (!tableExists($pdo, 'client_reglements')) {
        $pdo->exec("CREATE TABLE client_reglements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            vente_id INT NULL,
            type_mouvement ENUM('DEBIT','CREDIT','RISTOURNE','ESCOMPTE') NOT NULL,
            montant DECIMAL(12,2) NOT NULL,
            mode_paiement VARCHAR(40) NULL,
            reference VARCHAR(120) NULL,
            notes TEXT NULL,
            utilisateur_id INT NULL,
            date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id),
            FOREIGN KEY (vente_id) REFERENCES ventes(id),
            INDEX idx_client_reglements_client (client_id)
        )");
        $steps[] = 'Table client_reglements créée';
    }

    if (!tableExists($pdo, 'fournisseur_reglements')) {
        $pdo->exec("CREATE TABLE fournisseur_reglements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fournisseur_id INT NOT NULL,
            reception_id INT NULL,
            type_mouvement ENUM('DEBIT','CREDIT','RISTOURNE','ESCOMPTE') NOT NULL,
            montant DECIMAL(12,2) NOT NULL,
            mode_paiement VARCHAR(40) NULL,
            reference VARCHAR(120) NULL,
            notes TEXT NULL,
            utilisateur_id INT NULL,
            date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
            FOREIGN KEY (reception_id) REFERENCES receptions(id),
            INDEX idx_fournisseur_reglements_fournisseur (fournisseur_id)
        )");
        $steps[] = 'Table fournisseur_reglements créée';
    }

    if (!tableExists($pdo, 'remises_commerciales')) {
        $pdo->exec("CREATE TABLE remises_commerciales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tiers_type ENUM('CLIENT','FOURNISSEUR') NOT NULL,
            tiers_id INT NOT NULL,
            reference_type VARCHAR(60) NULL,
            reference_id INT NULL,
            type_avantage ENUM('RISTOURNE','ESCOMPTE') NOT NULL,
            montant DECIMAL(12,2) NOT NULL DEFAULT 0,
            pourcentage DECIMAL(5,2) NULL,
            motif TEXT NULL,
            utilisateur_id INT NULL,
            date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_remises_tiers (tiers_type, tiers_id)
        )");
        $steps[] = 'Table remises_commerciales créée';
    }

    echo json_encode(['success' => true, 'steps' => $steps], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'steps' => $steps], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
