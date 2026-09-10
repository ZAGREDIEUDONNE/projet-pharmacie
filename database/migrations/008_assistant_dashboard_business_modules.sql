-- =============================================
-- ASSISTANT DASHBOARD - MODULES METIER
-- Migration non destructive
-- =============================================

INSERT IGNORE INTO permissions (nom, description, module) VALUES
('create_client', 'Creer un client depuis le dashboard assistant', 'clients'),
('edit_client', 'Modifier un client depuis le dashboard assistant', 'clients'),
('make_sale', 'Realiser une vente directe', 'ventes'),
('cancel_ticket', 'Annuler un ticket avec motif et restauration stock', 'ventes'),
('apply_discount', 'Appliquer une remise dans les limites du role', 'ventes'),
('close_cash_register', 'Proceder a l arret de caisse', 'caisse'),
('view_statistics', 'Consulter les statistiques operationnelles', 'reporting'),
('view_stock_movements', 'Consulter les mouvements de stock', 'stock'),
('prepare_orders', 'Preparer et suivre les commandes', 'commandes');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'create_client',
    'edit_client',
    'make_sale',
    'cancel_ticket',
    'apply_discount',
    'close_cash_register',
    'view_statistics',
    'view_stock_movements',
    'prepare_orders'
)
WHERE LOWER(r.nom) IN ('assistant', 'administrateur');

CREATE TABLE IF NOT EXISTS audit_context (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_log_id INT NOT NULL,
    machine VARCHAR(120) NULL,
    request_uri VARCHAR(255) NULL,
    method VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_log_id) REFERENCES audit_logs(id) ON DELETE CASCADE,
    INDEX idx_audit_context_log (audit_log_id)
);

CREATE TABLE IF NOT EXISTS vente_brouillons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    client_id INT NULL,
    contenu JSON NOT NULL,
    statut ENUM('BROUILLON', 'REPRIS', 'ABANDONNE', 'TRANSFORME') DEFAULT 'BROUILLON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_vente_brouillons_user_statut (utilisateur_id, statut)
);

CREATE TABLE IF NOT EXISTS validations_manager (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demandeur_id INT NOT NULL,
    manager_id INT NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    payload JSON NULL,
    statut ENUM('EN_ATTENTE', 'VALIDEE', 'REFUSEE', 'EXPIREE') DEFAULT 'EN_ATTENTE',
    motif TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (manager_id) REFERENCES utilisateurs(id),
    INDEX idx_validations_manager_statut (statut, module, action)
);

CREATE TABLE IF NOT EXISTS preparation_commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    preparateur_id INT NULL,
    statut ENUM('A_PREPARER', 'EN_PREPARATION', 'CONTROLEE', 'PRETE', 'ANNULEE') DEFAULT 'A_PREPARER',
    commentaire TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    FOREIGN KEY (preparateur_id) REFERENCES utilisateurs(id),
    UNIQUE KEY unique_preparation_commande (commande_id),
    INDEX idx_preparation_commandes_statut (statut)
);

CREATE INDEX idx_ventes_date_statut ON ventes(date_vente, statut_vente);
CREATE INDEX idx_ventes_utilisateur_date ON ventes(utilisateur_id, date_vente);
CREATE INDEX idx_mouvements_stock_type_date ON mouvements_stock(type_mouvement, date_mouvement);
CREATE INDEX idx_commandes_statut_livraison ON commandes(statut_commande, date_livraison_prevue);
CREATE INDEX idx_audit_table_record_date ON audit_logs(table_name, record_id, date_action);
