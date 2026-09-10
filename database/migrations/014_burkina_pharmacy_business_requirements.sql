-- =============================================
-- EXIGENCES METIER PHARMACIE BURKINA FASO
-- Remises, delivrance, fiche produit, receptions,
-- flux de stock, inventaires et suivi financier tiers.
-- =============================================

ALTER TABLE produits
    ADD COLUMN IF NOT EXISTS rayon VARCHAR(120) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS dci VARCHAR(200) NULL AFTER rayon,
    ADD COLUMN IF NOT EXISTS classe_pharmaceutique VARCHAR(120) NULL AFTER dci,
    ADD COLUMN IF NOT EXISTS forme_pharmaceutique VARCHAR(120) NULL AFTER classe_pharmaceutique,
    ADD COLUMN IF NOT EXISTS type_delivrance ENUM('MEDICAMENT_CONSEIL','HORS_LISTE','ORDONNANCIER','PSYCHOTROPE','ANTICANCEREUX') NOT NULL DEFAULT 'MEDICAMENT_CONSEIL' AFTER forme_pharmaceutique;

ALTER TABLE produits
    MODIFY fournisseur_id INT NULL;

CREATE TABLE IF NOT EXISTS role_discount_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    max_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_discount_limit (role_id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

INSERT INTO role_discount_limits (role_id, max_discount_percent)
SELECT id,
       CASE
           WHEN LOWER(nom) IN ('admin', 'administrateur') THEN 25.00
           WHEN LOWER(nom) IN ('assistant', 'pharmacien') THEN 15.00
           WHEN LOWER(nom) IN ('vendeur') THEN 10.00
           ELSE 0.00
       END
FROM roles
ON DUPLICATE KEY UPDATE max_discount_percent = VALUES(max_discount_percent);

ALTER TABLE receptions
    ADD COLUMN IF NOT EXISTS date_facture DATE NULL AFTER numero_facture,
    ADD COLUMN IF NOT EXISTS reference_facture VARCHAR(120) NULL AFTER date_facture,
    ADD COLUMN IF NOT EXISTS montant_facture DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER reference_facture;

CREATE TABLE IF NOT EXISTS client_reglements (
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
    INDEX idx_client_reglements_client (client_id),
    INDEX idx_client_reglements_date (date_mouvement)
);

CREATE TABLE IF NOT EXISTS fournisseur_reglements (
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
    INDEX idx_fournisseur_reglements_fournisseur (fournisseur_id),
    INDEX idx_fournisseur_reglements_date (date_mouvement)
);

CREATE TABLE IF NOT EXISTS remises_commerciales (
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
    INDEX idx_remises_tiers (tiers_type, tiers_id),
    INDEX idx_remises_reference (reference_type, reference_id)
);

INSERT IGNORE INTO permissions (nom, description, module) VALUES
('stock.flux', 'Consulter les flux detailles de stock', 'stock'),
('stock.inventory', 'Gerer les inventaires', 'stock'),
('finance.clients', 'Consulter les soldes et reglements clients', 'finance'),
('finance.suppliers', 'Consulter les soldes et reglements fournisseurs', 'finance'),
('discount.manage_limits', 'Gerer les plafonds de remise par role', 'vente');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN ('stock.flux', 'stock.inventory', 'finance.clients', 'finance.suppliers', 'discount.manage_limits')
WHERE LOWER(r.nom) IN ('admin', 'administrateur');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN ('stock.flux', 'stock.inventory')
WHERE LOWER(r.nom) IN ('assistant', 'pharmacien', 'charge_commande', 'charge de commande', 'commande');
