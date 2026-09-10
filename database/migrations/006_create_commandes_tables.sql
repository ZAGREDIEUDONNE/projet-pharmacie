-- =============================================
-- TABLES COMMANDES - MODULE COMMANDES FOURNISSEURS
-- =============================================

-- Table principale des commandes (déjà existante mais mise à jour pour workflow complet)
ALTER TABLE commandes 
ADD COLUMN IF NOT EXISTS date_validation DATETIME NULL,
ADD COLUMN IF NOT EXISTS date_annulation DATETIME NULL,
ADD COLUMN IF NOT EXISTS utilisateur_validation_id INT NULL,
ADD COLUMN IF NOT EXISTS utilisateur_annulation_id INT NULL,
ADD COLUMN IF NOT EXISTS motif_annulation TEXT NULL,
ADD COLUMN IF NOT EXISTS date_cloture DATETIME NULL,
ADD COLUMN IF NOT EXISTS utilisateur_cloture_id INT NULL,
ADD COLUMN IF NOT EXISTS notes_cloture TEXT NULL;

-- Table des articles de commande (déjà existante mais mise à jour)
ALTER TABLE commande_items 
ADD COLUMN IF NOT EXISTS quantite_receptionnee INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS date_reception DATETIME NULL,
ADD COLUMN IF NOT EXISTS notes_reception TEXT NULL,
ADD COLUMN IF NOT EXISTS statut_reception ENUM('EN_ATTENTE', 'REÇU', 'PARTIELLEMENT_REÇU', 'NON_REÇU') DEFAULT 'EN_ATTENTE';

-- Vue pour le workflow des commandes
CREATE OR REPLACE VIEW v_workflow_commandes AS
SELECT 
    c.*,
    f.nom as fournisseur_nom,
    u1.username as createur_nom,
    u2.username as validateur_nom,
    u3.username as receptionneur_nom,
    u4.username as clotureur_nom,
    CASE 
        WHEN c.statut_commande = 'BROUILLON' THEN 'EN_CREATION'
        WHEN c.statut_commande = 'VALIDEE' THEN 'EN_VALIDATION'
        WHEN c.statut_commande = 'PARTIELLEMENT_LIVREE' THEN 'EN_RECEPTION_PARTIEL'
        WHEN c.statut_commande = 'LIVREE' THEN 'EN_RECEPTION_COMPLETE'
        WHEN c.statut_commande = 'ANNULEE' THEN 'ANNULEE'
        ELSE 'INCONNU'
    END as etat_actuel,
    DATEDIFF(CURDATE(), c.date_commande) as jours_anciennete,
    COUNT(ci.id) as nombre_articles,
    SUM(ci.quantite_commandee) as quantite_totale,
    SUM(ci.quantite_receptionnee) as quantite_recue,
    SUM(ci.quantite_commandee - ci.quantite_receptionnee) as quantite_en_attente
FROM commandes c
LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
LEFT JOIN utilisateurs u1 ON c.utilisateur_id = u1.id
LEFT JOIN utilisateurs u2 ON c.utilisateur_validation_id = u2.id
LEFT JOIN utilisateurs u3 ON c.utilisateur_reception_id = u3.id
LEFT JOIN utilisateurs u4 ON c.utilisateur_cloture_id = u4.id
LEFT JOIN commande_items ci ON c.id = ci.commande_id
WHERE c.deleted_at IS NULL
GROUP BY c.id
ORDER BY c.date_commande DESC;

-- Vue pour les commandes en attente de validation
CREATE OR REPLACE VIEW v_commandes_en_attente_validation AS
SELECT 
    c.*,
    f.nom as fournisseur_nom,
    u.username as createur_nom,
    DATEDIFF(CURDATE(), c.date_commande) as jours_en_attente
FROM commandes c
LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
WHERE c.statut_commande = 'BROUILLON'
AND c.deleted_at IS NULL
ORDER BY c.date_commande ASC;

-- Vue pour les commandes en attente de livraison
CREATE OR REPLACE VIEW v_commandes_en_attente_livraison AS
SELECT 
    c.*,
    f.nom as fournisseur_nom,
    u1.username as validateur_nom,
    DATEDIFF(c.date_livraison_prevue, CURDATE()) as jours_restants
FROM commandes c
LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
LEFT JOIN utilisateurs u1 ON c.utilisateur_validation_id = u1.id
WHERE c.statut_commande = 'VALIDEE'
AND c.deleted_at IS NULL
ORDER BY c.date_livraison_prevue ASC;

-- Vue pour les réceptions partielles
CREATE OR REPLACE VIEW v_receptions_partielles AS
SELECT 
    c.*,
    f.nom as fournisseur_nom,
    u1.username as receptionneur_nom,
    COUNT(ci.id) as nombre_articles_recus,
    SUM(ci.quantite_receptionnee) as quantite_totale_recue,
    SUM(ci.quantite_commandee - ci.quantite_receptionnee) as quantite_en_attente
FROM commandes c
LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
LEFT JOIN utilisateurs u1 ON c.utilisateur_reception_id = u1.id
LEFT JOIN commande_items ci ON c.id = ci.commande_id
WHERE c.statut_commande = 'PARTIELLEMENT_LIVREE'
AND ci.statut_reception IN ('REÇU', 'PARTIELLEMENT_REÇU')
AND c.deleted_at IS NULL
GROUP BY c.id
ORDER BY c.date_livraison_prevue DESC;

-- Triggers pour le workflow des commandes
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_commande_validation
AFTER UPDATE ON commandes
FOR EACH ROW
BEGIN
    IF OLD.statut_commande = 'BROUILLON' AND NEW.statut_commande = 'VALIDEE' THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_validation_id, 
            'COMMANDE_VALIDATION', 
            'commandes', 
            NEW.id,
            JSON_OBJECT('ancien_statut', OLD.statut_commande),
            JSON_OBJECT(
                'nouveau_statut', NEW.statut_commande,
                'numero_commande', NEW.numero_commande,
                'fournisseur_id', NEW.fournisseur_id
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS tr_commande_reception
AFTER UPDATE ON commandes
FOR EACH ROW
BEGIN
    IF OLD.statut_commande IN ('VALIDEE', 'PARTIELLEMENT_LIVREE') 
       AND NEW.statut_commande IN ('PARTIELLEMENT_LIVREE', 'LIVREE') THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_reception_id, 
            'COMMANDE_RECEPTION', 
            'commandes', 
            NEW.id,
            JSON_OBJECT(
                'ancien_statut', OLD.statut_commande,
                'nouveau_statut', NEW.statut_commande,
                'numero_commande', NEW.numero_commande
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS tr_commande_annulation
AFTER UPDATE ON commandes
FOR EACH ROW
BEGIN
    IF OLD.statut_commande != 'ANNULEE' AND NEW.statut_commande = 'ANNULEE' THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_annulation_id, 
            'COMMANDE_ANNULATION', 
            'commandes', 
            NEW.id,
            JSON_OBJECT(
                'ancien_statut', OLD.statut_commande,
                'nouveau_statut', NEW.statut_commande,
                'numero_commande', NEW.numero_commande,
                'motif_annulation', NEW.motif_annulation
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Index pour optimisation
CREATE INDEX IF NOT EXISTS idx_commandes_workflow ON commandes(statut_commande, date_commande);
CREATE INDEX IF NOT EXISTS idx_commandes_fournisseur ON commandes(fournisseur_id, statut_commande);
CREATE INDEX IF NOT EXISTS idx_commandes_dates ON commandes(date_commande, date_livraison_prevue);
CREATE INDEX IF NOT EXISTS idx_commande_items_reception ON commande_items(commande_id, statut_reception);

-- Insertion de données de test pour le workflow
INSERT IGNORE INTO commandes (numero_commande, fournisseur_id, utilisateur_id, date_commande, date_livraison_prevue, montant_total, statut_commande, created_by) VALUES
('CMD2024012001', 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 250000.00, 'BROUILLON', 1),
('CMD2024012002', 2, 1, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 150000.00, 'VALIDEE', 1),
('CMD2024012003', 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 21 DAY), 300000.00, 'PARTIELLEMENT_LIVREE', 1);

INSERT IGNORE INTO commande_items (commande_id, produit_id, quantite_commandee, prix_unitaire, montant_total) VALUES
(1, 1, 50, 5000.00, 250000.00),
(2, 2, 30, 5000.00, 150000.00),
(3, 3, 60, 5000.00, 300000.00);
