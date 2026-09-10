-- =============================================
-- TABLES INVENTAIRES - MODULE INVENTAIRE
-- =============================================

-- Table principale des inventaires
CREATE TABLE IF NOT EXISTS inventaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) UNIQUE NOT NULL,
    type_inventaire ENUM('MANUEL', 'AUTOMATIQUE', 'PERIODIQUE') DEFAULT 'MANUEL',
    utilisateur_id INT NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NULL,
    date_fin_prevue DATETIME NULL,
    statut ENUM('EN_COURS', 'CLOTURE', 'ANNULE') DEFAULT 'EN_COURS',
    notes TEXT NULL,
    total_articles INT DEFAULT 0,
    total_valeur_theorique DECIMAL(15,2) DEFAULT 0,
    total_valeur_comptee DECIMAL(15,2) DEFAULT 0,
    total_ecart_valeur DECIMAL(15,2) DEFAULT 0,
    total_ecart_quantite INT DEFAULT 0,
    notes_cloture TEXT NULL,
    utilisateur_cloture_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (utilisateur_cloture_id) REFERENCES utilisateurs(id),
    INDEX idx_inventaires_reference (reference),
    INDEX idx_inventaires_statut (statut),
    INDEX idx_inventaires_date_debut (date_debut),
    INDEX idx_inventaires_utilisateur (utilisateur_id)
);

-- Table des articles d'inventaire
CREATE TABLE IF NOT EXISTS inventaire_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventaire_id INT NOT NULL,
    produit_id INT NOT NULL,
    lot_id INT NULL,
    quantite_theorique INT DEFAULT 0,
    quantite_comptee INT NOT NULL,
    ecart INT DEFAULT 0,
    prix_unitaire DECIMAL(10,2) DEFAULT 0,
    valeur_totale DECIMAL(12,2) DEFAULT 0,
    statut_saisie ENUM('VALIDE', 'A_CORRIGER') DEFAULT 'VALIDE',
    notes TEXT NULL,
    utilisateur_saisie_id INT NOT NULL,
    date_saisie DATETIME NOT NULL,
    date_modification DATETIME NULL,
    utilisateur_modification_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventaire_id) REFERENCES inventaires(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id),
    FOREIGN KEY (utilisateur_saisie_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (utilisateur_modification_id) REFERENCES utilisateurs(id),
    INDEX idx_inventaire_articles_inventaire (inventaire_id),
    INDEX idx_inventaire_articles_produit (produit_id),
    INDEX idx_inventaire_articles_lot (lot_id),
    INDEX idx_inventaire_articles_statut (statut_saisie)
);

-- Table d'état du stock avant inventaire
CREATE TABLE IF NOT EXISTS inventaire_etat_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventaire_id INT NOT NULL,
    produit_id INT NOT NULL,
    lot_id INT NULL,
    quantite_stock INT DEFAULT 0,
    valeur_stock DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventaire_id) REFERENCES inventaires(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id),
    INDEX idx_inventaire_etat_stock_inventaire (inventaire_id),
    INDEX idx_inventaire_etat_stock_produit (produit_id)
);

-- Table des régularisations de stock
CREATE TABLE IF NOT EXISTS regularisations_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventaire_id INT NOT NULL,
    produit_id INT NOT NULL,
    lot_id INT NULL,
    type_regularisation ENUM('MANQUANT', 'EXCEDENT', 'PERIME', 'DEGRADE') DEFAULT 'MANQUANT',
    quantite_theorique INT NOT NULL,
    quantite_comptee INT NOT NULL,
    ecart INT NOT NULL,
    valeur_ecart DECIMAL(12,2) DEFAULT 0,
    statut_regularisation ENUM('EN_ATTENTE', 'VALIDEE', 'ANNULEE') DEFAULT 'EN_ATTENTE',
    motif_regularisation TEXT NULL,
    date_regularisation DATETIME NULL,
    utilisateur_regularisation_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (inventaire_id) REFERENCES inventaires(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id),
    FOREIGN KEY (utilisateur_regularisation_id) REFERENCES utilisateurs(id),
    INDEX idx_regularisations_stock_inventaire (inventaire_id),
    INDEX idx_regularisations_stock_produit (produit_id),
    INDEX idx_regularisations_stock_statut (statut_regularisation)
);

-- Vue pour les inventaires en cours
CREATE OR REPLACE VIEW v_inventaires_en_cours AS
SELECT 
    i.*,
    u1.username as createur_nom,
    u1.nom as createur_prenom,
    COUNT(ia.id) as nombre_articles_saisis,
    SUM(ia.valeur_totale) as valeur_saisie,
    COUNT(CASE WHEN ia.statut_saisie = 'A_CORRIGER' THEN 1 END) as articles_a_corriger,
    DATEDIFF(CURRENT_DATE(), i.date_debut) as jours_en_cours
FROM inventaires i
LEFT JOIN utilisateurs u1 ON i.utilisateur_id = u1.id
LEFT JOIN inventaire_articles ia ON i.id = ia.inventaire_id
WHERE i.statut = 'EN_COURS'
AND i.deleted_at IS NULL
GROUP BY i.id
ORDER BY i.date_debut DESC;

-- Vue pour les écarts d'inventaire
CREATE OR REPLACE VIEW v_ecarts_inventaire AS
SELECT 
    i.reference,
    i.date_debut,
    p.nom as produit_nom,
    ia.quantite_theorique,
    ia.quantite_comptee,
    ia.ecart,
    ia.valeur_totale,
    ia.prix_unitaire,
    ia.statut_saisie,
    l.numero_lot,
    l.date_peremption,
    CASE 
        WHEN ia.ecart < 0 THEN 'MANQUANT'
        WHEN ia.ecart > 0 THEN 'EXCEDENT'
        ELSE 'CORRECT'
    END as type_ecart,
    ABS(ia.ecart) as ecart_absolu,
    CASE 
        WHEN ABS(ia.ecart) > 10 THEN 'CRITIQUE'
        WHEN ABS(ia.ecart) > 5 THEN 'ELEVE'
        ELSE 'FAIBLE'
    END as niveau_ecart
FROM inventaires i
JOIN inventaire_articles ia ON i.id = ia.inventaire_id
JOIN produits p ON ia.produit_id = p.id
LEFT JOIN lots l ON ia.lot_id = l.id
WHERE i.statut = 'CLOTURE'
AND i.deleted_at IS NULL
AND ia.ecart != 0
ORDER BY ABS(ia.ecart) DESC;

-- Trigger pour logger les opérations d'inventaire
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_inventaire_insert
AFTER INSERT ON inventaires
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        utilisateur_id, action, table_name, record_id, 
        new_values, ip_address, user_agent, date_action
    ) VALUES (
        NEW.utilisateur_id, 
        'INVENTAIRE_CREATION', 
        'inventaires', 
        NEW.id,
        JSON_OBJECT(
            'reference', NEW.reference,
            'type_inventaire', NEW.type_inventaire,
            'date_debut', NEW.date_debut
        ),
        CONNECTION_ID(),
        'SYSTEM_TRIGGER',
        NOW()
    );
END$$

CREATE TRIGGER IF NOT EXISTS tr_inventaire_update
AFTER UPDATE ON inventaires
FOR EACH ROW
BEGIN
    IF OLD.statut != NEW.statut THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_cloture_id, 
            'INVENTAIRE_STATUT_CHANGE', 
            'inventaires', 
            NEW.id,
            JSON_OBJECT('ancien_statut', OLD.statut),
            JSON_OBJECT(
                'nouveau_statut', NEW.statut,
                'reference', NEW.reference,
                'date_debut', NEW.date_debut
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

DELIMITER ;
