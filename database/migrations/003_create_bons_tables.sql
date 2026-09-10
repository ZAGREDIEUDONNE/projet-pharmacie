-- =============================================
-- TABLES BONS - MODULE GESTION BONS
-- =============================================

-- Table principale des bons
CREATE TABLE IF NOT EXISTS bons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_bon ENUM('LIVRAISON', 'RETOUR', 'AVOIR', 'REMISE', 'GARANTIE') NOT NULL,
    numero_bon VARCHAR(50) UNIQUE NOT NULL,
    reference_id INT NULL,
    reference_type ENUM('COMMANDE', 'VENTE', 'INVENTAIRE') NULL,
    fournisseur_id INT NULL,
    client_id INT NULL,
    utilisateur_id INT NOT NULL,
    date_emission DATETIME NOT NULL,
    date_echeance DATE NULL,
    date_livraison_prevue DATE NULL,
    montant_total DECIMAL(12,2) DEFAULT 0,
    statut_bon ENUM('EMIS', 'VALIDE', 'UTILISE', 'ANNULE', 'EXPIRE') DEFAULT 'EMIS',
    conditions_paiement VARCHAR(200) NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    date_traitement DATETIME NULL,
    utilisateur_traitement_id INT NULL,
    notes_traitement TEXT NULL,
    date_utilisation DATETIME NULL,
    utilisateur_utilisation_id INT NULL,
    vente_id INT NULL,
    date_annulation DATETIME NULL,
    utilisateur_annulation_id INT NULL,
    motif_annulation TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
    FOREIGN KEY (utilisateur_traitement_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (utilisateur_utilisation_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (utilisateur_annulation_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id),
    INDEX idx_bons_type (type_bon),
    INDEX idx_bons_numero (numero_bon),
    INDEX idx_bons_statut (statut_bon),
    INDEX idx_bons_date_emission (date_emission),
    INDEX idx_bons_date_echeance (date_echeance),
    INDEX idx_bons_fournisseur (fournisseur_id),
    INDEX idx_bons_client (client_id),
    INDEX idx_bons_utilisateur (utilisateur_id)
);

-- Table des articles des bons
CREATE TABLE IF NOT EXISTS bons_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bon_id INT NOT NULL,
    produit_id INT NOT NULL,
    lot_id INT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(12,2) NOT NULL,
    remise DECIMAL(5,2) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bon_id) REFERENCES bons(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id),
    INDEX idx_bons_articles_bon (bon_id),
    INDEX idx_bons_articles_produit (produit_id),
    INDEX idx_bons_articles_lot (lot_id)
);

-- Vue pour les bons en attente
CREATE OR REPLACE VIEW v_bons_en_attente AS
SELECT 
    b.*,
    f.nom as fournisseur_nom,
    c.nom as client_nom,
    u.username as createur_nom,
    DATEDIFF(b.date_echeance, CURDATE()) as jours_restants,
    CASE 
        WHEN b.date_echeance < CURDATE() THEN 'EXPIRE'
        WHEN DATEDIFF(b.date_echeance, CURDATE()) <= 7 THEN 'URGENT'
        WHEN DATEDIFF(b.date_echeance, CURDATE()) <= 30 THEN 'ATTENTION'
        ELSE 'NORMAL'
    END as niveau_alerte,
    COUNT(ba.id) as nombre_articles
FROM bons b
LEFT JOIN fournisseurs f ON b.fournisseur_id = f.id
LEFT JOIN clients c ON b.client_id = c.id
LEFT JOIN utilisateurs u ON b.created_by = u.id
LEFT JOIN bons_articles ba ON b.id = ba.bon_id
WHERE b.statut_bon IN ('EMIS', 'VALIDE')
AND b.deleted_at IS NULL
GROUP BY b.id
ORDER BY b.date_echeance ASC;

-- Vue pour les bons utilisés
CREATE OR REPLACE VIEW v_bons_utilises AS
SELECT 
    b.*,
    v.numero_facture as vente_associee,
    u1.username as createur_nom,
    u2.username as utilisateur_nom,
    f.nom as fournisseur_nom,
    c.nom as client_nom,
    DATEDIFF(b.date_utilisation, b.date_emission) as delai_utilisation_jours
FROM bons b
LEFT JOIN ventes v ON b.vente_id = v.id
LEFT JOIN utilisateurs u1 ON b.created_by = u1.id
LEFT JOIN utilisateurs u2 ON b.utilisateur_utilisation_id = u2.id
LEFT JOIN fournisseurs f ON b.fournisseur_id = f.id
LEFT JOIN clients c ON b.client_id = c.id
WHERE b.statut_bon = 'UTILISE'
AND b.deleted_at IS NULL
ORDER BY b.date_utilisation DESC;

-- Trigger pour logger les opérations sur les bons
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_bon_insert
AFTER INSERT ON bons
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        utilisateur_id, action, table_name, record_id, 
        old_values, new_values, ip_address, user_agent, date_action
    ) VALUES (
        NEW.created_by, 
        'BON_CREATION', 
        'bons', 
        NEW.id,
        NULL,
        JSON_OBJECT(
            'type_bon', NEW.type_bon,
            'numero_bon', NEW.numero_bon,
            'montant_total', NEW.montant_total,
            'fournisseur_id', NEW.fournisseur_id,
            'client_id', NEW.client_id
        ),
        CONNECTION_ID(),
        'SYSTEM_TRIGGER',
        NOW()
    );
END$$

CREATE TRIGGER IF NOT EXISTS tr_bon_update
AFTER UPDATE ON bons
FOR EACH ROW
BEGIN
    IF OLD.statut_bon != NEW.statut_bon THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_traitement_id, 
            'BON_STATUT_CHANGE', 
            'bons', 
            NEW.id,
            JSON_OBJECT('ancien_statut', OLD.statut_bon),
            JSON_OBJECT(
                'nouveau_statut', NEW.statut_bon,
                'type_bon', NEW.type_bon,
                'numero_bon', NEW.numero_bon
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
    
    IF OLD.montant_total != NEW.montant_total THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_traitement_id, 
            'BON_MONTANT_MODIFIE', 
            'bons', 
            NEW.id,
            JSON_OBJECT('ancien_montant', OLD.montant_total),
            JSON_OBJECT(
                'nouveau_montant', NEW.montant_total,
                'type_bon', NEW.type_bon,
                'numero_bon', NEW.numero_bon
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Insertion des données de test
INSERT IGNORE INTO bons (type_bon, numero_bon, fournisseur_id, utilisateur_id, date_emission, montant_total, statut_bon, created_by) VALUES
('LIVRAISON', 'BL20240101001', 1, 1, NOW(), 150000.00, 'EMIS', 1),
('RETOUR', 'BR20240101002', 1, 1, NOW(), 25000.00, 'EMIS', 1),
('AVOIR', 'BA20240101001', 2, 1, NOW(), 75000.00, 'EMIS', 1);
