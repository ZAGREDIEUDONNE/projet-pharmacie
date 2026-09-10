-- Création de la table client_reglements pour gérer les règlements clients et les brouillons
CREATE TABLE IF NOT EXISTS client_reglements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    type_mouvement ENUM('CREDIT', 'DEBIT', 'RISTOURNE', 'ESCOMPTE') NOT NULL DEFAULT 'CREDIT',
    montant DECIMAL(10,2) NOT NULL,
    mode_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE', 'VIREMENT') NOT NULL DEFAULT 'ESPECE',
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    utilisateur_id INT NOT NULL,
    date_mouvement DATETIME NOT NULL,
    statut ENUM('BROUILLON', 'VALIDE', 'SUPPRIME') NOT NULL DEFAULT 'BROUILLON',
    numero_brouillon VARCHAR(50) NULL,
    validated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_client_id (client_id),
    INDEX idx_statut (statut),
    INDEX idx_date_mouvement (date_mouvement),
    INDEX idx_numero_brouillon (numero_brouillon),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
