-- =============================================
-- TABLE RÈGLEMENTS CLIENTS
-- Module Suivi Client
-- =============================================

CREATE TABLE reglements_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    mode_paiement ENUM('ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON') NOT NULL DEFAULT 'ESPECE',
    reference VARCHAR(100),
    notes TEXT,
    utilisateur_id INT NOT NULL,
    date_reglement DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Index pour optimisation
CREATE INDEX idx_reglements_client ON reglements_clients(client_id);
CREATE INDEX idx_reglements_date ON reglements_clients(date_reglement);
CREATE INDEX idx_reglements_utilisateur ON reglements_clients(utilisateur_id);

-- Contrainte CHECK pour le montant positif
ALTER TABLE reglements_clients ADD CONSTRAINT chk_montant_reglement_positif 
    CHECK (montant > 0);
