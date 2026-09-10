-- =============================================
-- PAIEMENT DETAILS - MODES DE PAIEMENT ÉTENDUS
-- Support des modes de paiement spécifiques aux pharmacies au Burkina Faso
-- =============================================

-- Table pour stocker les détails de paiement selon le mode choisi
CREATE TABLE IF NOT EXISTS paiement_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    mode_paiement ENUM('ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON') NOT NULL,
    
    -- Champs pour Dépôt
    depot_nom_etablissement VARCHAR(200) NULL,
    depot_adresse VARCHAR(300) NULL,
    depot_telephone VARCHAR(30) NULL,
    depot_numero_arrete VARCHAR(100) NULL,
    
    -- Champs pour Mobile Money
    mobile_operateur ENUM('ORANGE_MONEY', 'MOOV_MONEY', 'TELECEL_MONEY', 'AUTRE') NULL,
    mobile_nom_titulaire VARCHAR(200) NULL,
    mobile_telephone VARCHAR(30) NULL,
    
    -- Champs pour Chèque
    cheque_numero VARCHAR(50) NULL,
    cheque_nom_banque VARCHAR(200) NULL,
    
    -- Champs pour Bon
    bon_nom_beneficiaire VARCHAR(200) NULL,
    bon_telephone VARCHAR(30) NULL,
    bon_matricule VARCHAR(50) NULL,
    bon_numero_bon VARCHAR(50) NULL,
    
    -- Métadonnées
    utilisateur_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_paiement_details_vente (vente_id),
    INDEX idx_paiement_details_mode (mode_paiement),
    INDEX idx_paiement_details_cheque (cheque_numero),
    INDEX idx_paiement_details_bon (bon_numero_bon)
);

-- Mise à jour de la table ventes pour supporter les nouveaux modes de paiement
ALTER TABLE ventes 
MODIFY COLUMN type_paiement ENUM('ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON', 'CREDIT') NOT NULL DEFAULT 'ESPECE';

-- Insertion des permissions pour la gestion des paiements
INSERT IGNORE INTO permissions (nom, description, module) VALUES
('paiement.view', 'Consulter les détails de paiement', 'vente'),
('paiement.manage', 'Gérer les modes de paiement', 'vente'),
('paiement.depot', 'Gérer les paiements par dépôt', 'vente'),
('paiement.mobile_money', 'Gérer les paiements Mobile Money', 'vente'),
('paiement.cheque', 'Gérer les paiements par chèque', 'vente'),
('paiement.bon', 'Gérer les paiements par bon', 'vente');

-- Attribution des permissions aux rôles appropriés
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'paiement.view', 'paiement.manage', 'paiement.depot', 
    'paiement.mobile_money', 'paiement.cheque', 'paiement.bon'
)
WHERE LOWER(r.nom) IN ('admin', 'administrateur');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN ('paiement.view', 'paiement.manage')
WHERE LOWER(r.nom) IN ('assistant', 'vendeur');

-- Vue pour les paiements avec détails
CREATE OR REPLACE VIEW v_ventes_paiements_details AS
SELECT 
    v.id as vente_id,
    v.numero_facture,
    v.date_vente,
    v.montant_net,
    v.type_paiement,
    pd.mode_paiement as paiement_mode_detaille,
    pd.depot_nom_etablissement,
    pd.depot_adresse,
    pd.depot_telephone,
    pd.depot_numero_arrete,
    pd.mobile_operateur,
    pd.mobile_nom_titulaire,
    pd.mobile_telephone as mobile_telephone_contact,
    pd.cheque_numero,
    pd.cheque_nom_banque,
    pd.bon_nom_beneficiaire,
    pd.bon_telephone as bon_telephone_contact,
    pd.bon_matricule,
    pd.bon_numero_bon,
    c.nom as client_nom,
    u.username as vendeur_nom
FROM ventes v
LEFT JOIN paiement_details pd ON v.id = pd.vente_id
LEFT JOIN clients c ON v.client_id = c.id
LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
WHERE v.deleted_at IS NULL;
