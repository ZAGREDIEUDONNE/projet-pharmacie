-- =============================================
-- TABLES FACTURES - MODULE FACTURATION
-- =============================================

-- Table principale des factures
CREATE TABLE IF NOT EXISTS factures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_facture VARCHAR(50) UNIQUE NOT NULL,
    vente_id INT NULL,
    client_id INT NULL,
    type_facture ENUM('VENTE', 'AVOIR', 'NOTE_CREDIT') DEFAULT 'VENTE',
    date_emission DATETIME NOT NULL,
    date_echeance DATE NULL,
    montant_ht DECIMAL(12,2) DEFAULT 0,
    montant_tva DECIMAL(12,2) DEFAULT 0,
    montant_ttc DECIMAL(12,2) NOT NULL,
    montant_paye DECIMAL(12,2) DEFAULT 0,
    statut_paiement ENUM('IMPAYE', 'PARTIELLEMENT_PAYE', 'PAYE', 'ANNULE') DEFAULT 'IMPAYE',
    mode_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE_MONEY', 'VIREMENT') DEFAULT 'ESPECE',
    conditions_paiement VARCHAR(200) NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
    INDEX idx_factures_numero (numero_facture),
    INDEX idx_factures_date_emission (date_emission),
    INDEX idx_factures_client (client_id),
    INDEX idx_factures_statut (statut_paiement),
    INDEX idx_factures_created_by (created_by)
);

-- Table des articles de factures
CREATE TABLE IF NOT EXISTS facture_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facture_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire_ht DECIMAL(10,2) NOT NULL,
    montant_ht DECIMAL(12,2) NOT NULL,
    tva_taux DECIMAL(5,2) DEFAULT 0,
    montant_tva DECIMAL(12,2) DEFAULT 0,
    montant_ttc DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    INDEX idx_facture_articles_facture (facture_id),
    INDEX idx_facture_articles_produit (produit_id)
);

-- Table des paiements de factures
CREATE TABLE IF NOT EXISTS paiements_factures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facture_id INT NOT NULL,
    montant_paiement DECIMAL(12,2) NOT NULL,
    date_paiement DATETIME NOT NULL,
    mode_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE_MONEY', 'VIREMENT') NOT NULL,
    reference_paiement VARCHAR(100) NULL,
    notes TEXT NULL,
    utilisateur_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_paiements_factures_facture (facture_id),
    INDEX idx_paiements_factures_date (date_paiement),
    INDEX idx_paiements_factures_mode (mode_paiement)
);

-- Vue pour les factures impayées
CREATE OR REPLACE VIEW v_factures_impayees AS
SELECT 
    f.*,
    c.nom as client_nom,
    u.username as createur_nom,
    DATEDIFF(f.date_echeance, CURDATE()) as jours_retard,
    CASE 
        WHEN f.date_echeance < CURDATE() THEN 'RETARD'
        WHEN DATEDIFF(f.date_echeance, CURDATE()) <= 7 THEN 'URGENT'
        ELSE 'NORMAL'
    END as niveau_alerte,
    COUNT(fa.id) as nombre_articles
FROM factures f
LEFT JOIN clients c ON f.client_id = c.id
LEFT JOIN utilisateurs u ON f.created_by = u.id
LEFT JOIN facture_articles fa ON f.id = fa.facture_id
WHERE f.statut_paiement IN ('IMPAYE', 'PARTIELLEMENT_PAYE')
AND f.deleted_at IS NULL
GROUP BY f.id
ORDER BY f.date_echeance ASC;

-- Vue pour les factures par période
CREATE OR REPLACE VIEW v_factures_periode AS
SELECT 
    DATE(f.date_emission) as date_facture,
    COUNT(*) as nombre_factures,
    SUM(f.montant_ttc) as chiffre_affaires,
    SUM(f.montant_ht) as ca_ht,
    SUM(f.montant_tva) as total_tva,
    AVG(f.montant_ttc) as montant_moyen,
    COUNT(CASE WHEN f.statut_paiement = 'PAYE' THEN 1 END) as factures_payees,
    COUNT(CASE WHEN f.statut_paiement = 'IMPAYE' THEN 1 END) as factures_impayees,
    SUM(CASE WHEN f.statut_paiement = 'IMPAYE' THEN f.montant_ttc - f.montant_paye ELSE 0 END) as total_impaye
FROM factures f
WHERE f.deleted_at IS NULL
GROUP BY DATE(f.date_emission)
ORDER BY date_facture DESC;

-- Vue pour les statistiques de facturation
CREATE OR REPLACE VIEW v_statistiques_facturation AS
SELECT 
    YEAR(f.date_emission) as annee,
    MONTH(f.date_emission) as mois,
    COUNT(*) as nombre_factures,
    SUM(f.montant_ttc) as chiffre_affaires,
    SUM(f.montant_ht) as ca_ht,
    SUM(f.montant_tva) as total_tva,
    AVG(f.montant_ttc) as montant_moyen,
    COUNT(CASE WHEN f.statut_paiement = 'PAYE' THEN 1 END) as factures_payees,
    COUNT(CASE WHEN f.statut_paiement = 'IMPAYE' THEN 1 END) as factures_impayees,
    SUM(CASE WHEN f.statut_paiement = 'IMPAYE' THEN f.montant_ttc - f.montant_paye ELSE 0 END) as total_impaye,
    COUNT(CASE WHEN f.mode_paiement = 'ESPECE' THEN 1 END) as paiements_espece,
    COUNT(CASE WHEN f.mode_paiement = 'CARTE' THEN 1 END) as paiements_carte,
    COUNT(CASE WHEN f.mode_paiement = 'CHEQUE' THEN 1 END) as paiements_cheque,
    COUNT(CASE WHEN f.mode_paiement = 'VIREMENT' THEN 1 END) as paiements_virement
FROM factures f
WHERE f.deleted_at IS NULL
GROUP BY YEAR(f.date_emission), MONTH(f.date_emission)
ORDER BY annee DESC, mois DESC;

-- Trigger pour logger les opérations sur les factures
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_facture_insert
AFTER INSERT ON factures
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        utilisateur_id, action, table_name, record_id, 
        old_values, new_values, ip_address, user_agent, date_action
    ) VALUES (
        NEW.created_by, 
        'FACTURE_CREATION', 
        'factures', 
        NEW.id,
        NULL,
        JSON_OBJECT(
            'numero_facture', NEW.numero_facture,
            'type_facture', NEW.type_facture,
            'montant_ttc', NEW.montant_ttc,
            'client_id', NEW.client_id,
            'date_emission', NEW.date_emission
        ),
        CONNECTION_ID(),
        'SYSTEM_TRIGGER',
        NOW()
    );
END$$

CREATE TRIGGER IF NOT EXISTS tr_facture_update
AFTER UPDATE ON factures
FOR EACH ROW
BEGIN
    IF OLD.statut_paiement != NEW.statut_paiement THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.created_by, 
            'STATUT_PAIEMENT_UPDATE', 
            'factures', 
            NEW.id,
            JSON_OBJECT('ancien_statut', OLD.statut_paiement),
            JSON_OBJECT(
                'nouveau_statut', NEW.statut_paiement,
                'numero_facture', NEW.numero_facture,
                'montant_ttc', NEW.montant_ttc
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
    
    IF OLD.montant_paye != NEW.montant_paye THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.created_by, 
            'MONTANT_PAYE_UPDATE', 
            'factures', 
            NEW.id,
            JSON_OBJECT('ancien_montant', OLD.montant_paye),
            JSON_OBJECT(
                'nouveau_montant', NEW.montant_paye,
                'numero_facture', NEW.numero_facture,
                'reste_a_payer', NEW.montant_ttc - NEW.montant_paye
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Insertion de données de test
INSERT IGNORE INTO factures (numero_facture, vente_id, client_id, date_emission, montant_ht, montant_tva, montant_ttc, statut_paiement, mode_paiement, created_by) VALUES
('FAC20240101001', 1, 1, NOW(), 100000.00, 18000.00, 118000.00, 'IMPAYE', 'ESPECE', 1),
('FAC20240101002', 2, 2, NOW(), 75000.00, 13500.00, 88500.00, 'PARTIELLEMENT_PAYE', 'CARTE', 1),
('FAC20240101003', 3, 3, NOW(), 120000.00, 21600.00, 141600.00, 'PAYE', 'CHEQUE', 1);
