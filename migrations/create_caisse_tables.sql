-- Migration: Création des tables pour le module Caisse
-- Date: 19 juillet 2026
-- Description: Crée les tables caisse_sessions et mouvements_caisse

-- Table caisse_sessions
CREATE TABLE IF NOT EXISTS caisse_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_session VARCHAR(50) UNIQUE NOT NULL,
    caissier_id INT NOT NULL,
    date_ouverture DATETIME NOT NULL,
    date_fermeture DATETIME NULL,
    montant_ouverture DECIMAL(10,2) DEFAULT 0,
    montant_fermeture DECIMAL(10,2) NULL,
    montant_theorique DECIMAL(10,2) NULL,
    montant_ventes DECIMAL(10,2) DEFAULT 0,
    ecart DECIMAL(10,2) DEFAULT 0,
    statut_session ENUM('OUVERTE', 'FERMEE', 'CONTROLEE') DEFAULT 'OUVERTE',
    notes_controle TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table mouvements_caisse
CREATE TABLE IF NOT EXISTS mouvements_caisse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caisse_session_id INT NOT NULL,
    type_mouvement ENUM('VENTE', 'REMBOURSEMENT', 'APPROVISIONNEMENT', 'RETRAIT', 'DECAISSEMENT') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    moyen_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE', 'VIREMENT') DEFAULT 'ESPECE',
    reference VARCHAR(100) NULL,
    description TEXT NULL,
    date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT NOT NULL,
    vente_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caisse_session_id) REFERENCES caisse_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index pour optimiser les requêtes
CREATE INDEX idx_mouvements_session ON mouvements_caisse(caisse_session_id);
CREATE INDEX idx_mouvements_type ON mouvements_caisse(type_mouvement);
CREATE INDEX idx_mouvements_date ON mouvements_caisse(date_mouvement);
CREATE INDEX idx_sessions_caissier ON caisse_sessions(caissier_id);
CREATE INDEX idx_sessions_date ON caisse_sessions(date_ouverture);
CREATE INDEX idx_sessions_statut ON caisse_sessions(statut_session);
