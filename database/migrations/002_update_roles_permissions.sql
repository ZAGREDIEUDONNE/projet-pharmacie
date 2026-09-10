-- =============================================
-- MISE À JOUR RÔLES ET PERMISSIONS - SYSTÈME GRANULAIRE
-- =============================================

-- Nettoyage des données existantes
DELETE FROM role_permissions;
DELETE FROM permissions;
DELETE FROM roles;

-- =============================================
-- RÔLES OBLIGATOIRES
-- =============================================

INSERT INTO roles (id, nom, description) VALUES
(1, 'administrateur', 'Administrateur - Accès complet système'),
(2, 'vendeur', 'Vendeur - Point de vente et caisse'),
(3, 'assistant', 'Assistant - Vente avancée et stock'),
(4, 'charge_commande', 'Chargé de commande - Stock et fournisseurs');

-- =============================================
-- PERMISSIONS GRANULAIRES PAR ACTION
-- =============================================

-- Permissions VENDEUR
INSERT INTO permissions (nom, description, module) VALUES
('vente_create', 'Créer des ventes', 'ventes'),
('session_change', 'Changer de session caisse', 'caisse'),
('date_change', 'Changer la date système', 'system');

-- Permissions CHARGÉ DE COMMANDE
INSERT INTO permissions (nom, description, module) VALUES
('commande_manage', 'Gérer les commandes', 'commandes'),
('remise_apply', 'Appliquer des remises', 'ventes');

-- Permissions ASSISTANT
INSERT INTO permissions (nom, description, module) VALUES
('ticket_annuler', 'Annuler un ticket', 'ventes'),
('vente_corriger', 'Corriger une vente', 'ventes'),
('caisse_arret', 'Arrêter la caisse', 'caisse'),
('facture_imprimer', 'Imprimer factures/reçus', 'ventes'),
('commande_preparer', 'Préparer les commandes', 'commandes'),
('statistiques_view', 'Voir les statistiques', 'reporting'),
('stock_consulter', 'Consulter le stock', 'stock'),
('assistant_acces_avance', 'Accès assistant avancé (code 2)', 'system');

-- Permissions ADMINISTRATEUR
INSERT INTO permissions (nom, description, module) VALUES
('users_manage', 'Gérer les utilisateurs', 'admin'),
('products_manage', 'Gérer les produits', 'admin'),
('stock_manage', 'Gérer le stock complet', 'admin'),
('caisse_manage', 'Gérer la caisse complète', 'admin'),
('audit_view', 'Voir les logs d\'audit', 'admin'),
('system_config', 'Configurer le système', 'admin');

-- =============================================
-- ASSIGNATION DES PERMISSIONS AUX RÔLES
-- =============================================

-- Permissions VENDEUR (id: 2)
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 2, id FROM permissions WHERE nom IN (
    'vente_create', 'session_change', 'date_change'
);

-- Permissions CHARGÉ DE COMMANDE (id: 4)
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 4, id FROM permissions WHERE nom IN (
    'vente_create', 'session_change', 'date_change',
    'commande_manage', 'remise_apply'
);

-- Permissions ASSISTANT (id: 3)
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 3, id FROM permissions WHERE nom IN (
    'vente_create', 'session_change', 'date_change',
    'commande_manage', 'remise_apply',
    'ticket_annuler', 'vente_corriger', 'caisse_arret',
    'facture_imprimer', 'commande_preparer', 'statistiques_view',
    'stock_consulter'
);

-- Permissions ADMINISTRATEUR (id: 1)
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions; -- Toutes les permissions

-- =============================================
-- TABLE DOUBLES AUTHENTIFICATION ASSISTANT
-- =============================================

CREATE TABLE IF NOT EXISTS assistant_auth_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    code_type ENUM('CAISSE', 'AVANCE') NOT NULL,
    code_secret VARCHAR(10) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    last_used TIMESTAMP NULL,
    usage_count INT DEFAULT 0,
    max_usage INT DEFAULT 100,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_code_type (utilisateur_id, code_type)
);

-- =============================================
-- TABLE SESSIONS DE CAISSE (1 À 3)
-- =============================================

CREATE TABLE IF NOT EXISTS caisse_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_number ENUM('1', '2', '3') NOT NULL,
    utilisateur_id INT NOT NULL UNIQUE,
    date_ouverture DATETIME NOT NULL,
    date_fermeture DATETIME NULL,
    montant_ouverture DECIMAL(12,2) DEFAULT 0,
    montant_fermeture DECIMAL(12,2) NULL,
    ventes_count INT DEFAULT 0,
    total_ventes DECIMAL(12,2) DEFAULT 0,
    total_especes DECIMAL(12,2) DEFAULT 0,
    total_cartes DECIMAL(12,2) DEFAULT 0,
    total_cheques DECIMAL(12,2) DEFAULT 0,
    total_credits DECIMAL(12,2) DEFAULT 0,
    total_remises DECIMAL(12,2) DEFAULT 0,
    ecarts DECIMAL(12,2) DEFAULT 0,
    statut ENUM('OUVERTE', 'FERMEE', 'EN_PAUSE') DEFAULT 'OUVERTE',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_session_number (session_number),
    INDEX idx_utilisateur_session (utilisateur_id),
    INDEX idx_statut (statut)
);

-- =============================================
-- TABLE HISTORIQUE SESSIONS
-- =============================================

CREATE TABLE IF NOT EXISTS caisse_sessions_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_number ENUM('1', '2', '3') NOT NULL,
    utilisateur_id INT NOT NULL,
    date_ouverture DATETIME NOT NULL,
    date_fermeture DATETIME NOT NULL,
    montant_ouverture DECIMAL(12,2) DEFAULT 0,
    montant_fermeture DECIMAL(12,2) DEFAULT 0,
    ventes_count INT DEFAULT 0,
    total_ventes DECIMAL(12,2) DEFAULT 0,
    ecarts DECIMAL(12,2) DEFAULT 0,
    statut_fermeture ENUM('NORMALE', 'FORCEE', 'ANOMALIE') DEFAULT 'NORMALE',
    utilisateur_fermeture_id INT NULL,
    notes_fermeture TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (utilisateur_fermeture_id) REFERENCES utilisateurs(id),
    INDEX idx_date_fermeture (date_fermeture),
    INDEX idx_utilisateur_history (utilisateur_id)
);

-- =============================================
-- MISE À JOUR TABLE UTILISATEURS
-- =============================================

-- Ajouter colonne pour le code d'accès assistant
ALTER TABLE utilisateurs 
ADD COLUMN IF NOT EXISTS assistant_code VARCHAR(10) NULL,
ADD COLUMN IF NOT EXISTS session_active ENUM('1', '2', '3') NULL,
ADD COLUMN IF NOT EXISTS last_auth_code DATETIME NULL;

-- =============================================
-- TRIGGERS POUR TRAÇABILITÉ
-- =============================================

DELIMITER $$

-- Trigger pour tracer les changements de session
CREATE TRIGGER IF NOT EXISTS tr_session_change
AFTER UPDATE ON caisse_sessions
FOR EACH ROW
BEGIN
    IF OLD.statut != NEW.statut OR OLD.utilisateur_id != NEW.utilisateur_id THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent
        ) VALUES (
            NEW.utilisateur_id, 
            'SESSION_CHANGE', 
            'caisse_sessions', 
            NEW.id,
            JSON_OBJECT(
                'old_statut', OLD.statut,
                'new_statut', NEW.statut,
                'old_utilisateur', OLD.utilisateur_id,
                'new_utilisateur', NEW.utilisateur_id
            ),
            JSON_OBJECT(
                'session_number', NEW.session_number,
                'date_ouverture', NEW.date_ouverture,
                'statut', NEW.statut
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER'
        );
    END IF;
END$$

-- Trigger pour tracer les annulations de tickets
CREATE TRIGGER IF NOT EXISTS tr_ticket_annulation
AFTER UPDATE ON ventes
FOR EACH ROW
BEGIN
    IF OLD.statut_vente != 'ANNULEE' AND NEW.statut_vente = 'ANNULEE' THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent
        ) VALUES (
            NEW.utilisateur_id, 
            'TICKET_ANNULATION', 
            'ventes', 
            NEW.id,
            JSON_OBJECT(
                'old_statut', OLD.statut_vente,
                'montant', OLD.montant_ttc
            ),
            JSON_OBJECT(
                'new_statut', NEW.statut_vente,
                'date_annulation', NEW.date_vente,
                'motif', NEW.notes
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER'
        );
    END IF;
END$$

-- Trigger pour tracer les corrections de ventes
CREATE TRIGGER IF NOT EXISTS tr_vente_correction
AFTER UPDATE ON ventes
FOR EACH ROW
BEGIN
    IF OLD.montant_ttc != NEW.montant_ttc 
       OR OLD.montant_total != NEW.montant_total 
       OR OLD.montant_remise != NEW.montant_remise THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent
        ) VALUES (
            NEW.utilisateur_id, 
            'VENTE_CORRECTION', 
            'ventes', 
            NEW.id,
            JSON_OBJECT(
                'old_montant_ttc', OLD.montant_ttc,
                'old_montant_total', OLD.montant_total,
                'old_remise', OLD.montant_remise
            ),
            JSON_OBJECT(
                'new_montant_ttc', NEW.montant_ttc,
                'new_montant_total', NEW.montant_total,
                'new_remise', NEW.montant_remise,
                'date_correction', NEW.date_vente
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER'
        );
    END IF;
END$$

-- Trigger pour tracer les modifications de stock
CREATE TRIGGER IF NOT EXISTS tr_stock_modification
AFTER UPDATE ON stock
FOR EACH ROW
BEGIN
    IF OLD.quantite_disponible != NEW.quantite_disponible THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent
        ) VALUES (
            (SELECT utilisateur_id FROM mouvements_stock 
             WHERE produit_id = NEW.produit_id 
             ORDER BY date_mouvement DESC LIMIT 1),
            'STOCK_MODIFICATION', 
            'stock', 
            NEW.id,
            JSON_OBJECT(
                'old_quantite', OLD.quantite_disponible,
                'old_valeur', OLD.valeur_stock
            ),
            JSON_OBJECT(
                'new_quantite', NEW.quantite_disponible,
                'new_valeur', NEW.valeur_stock,
                'date_modification', NOW()
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER'
        );
    END IF;
END$$

DELIMITER ;

-- =============================================
-- INDEX POUR PERFORMANCE
-- =============================================

CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_name);
CREATE INDEX IF NOT EXISTS idx_audit_logs_date_action ON audit_logs(date_action, action);
CREATE INDEX IF NOT EXISTS idx_assistant_auth_user ON assistant_auth_codes(utilisateur_id);
CREATE INDEX IF NOT EXISTS idx_assistant_auth_type ON assistant_auth_codes(code_type);
CREATE INDEX IF NOT EXISTS idx_sessions_date ON caisse_sessions(date_ouverture);
CREATE INDEX IF NOT EXISTS idx_sessions_statut ON caisse_sessions(statut);
