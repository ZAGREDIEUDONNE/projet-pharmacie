-- =============================================
-- TABLES COMPTABILITÉ AVANCÉE - SYSCOA
-- =============================================

-- Table des exercices comptables
CREATE TABLE IF NOT EXISTS exercices_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercice INT UNIQUE NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('OUVERT', 'CLOTURE') DEFAULT 'OUVERT',
    solde_precedent DECIMAL(15,2) DEFAULT 0,
    total_debit DECIMAL(15,2) DEFAULT 0,
    total_credit DECIMAL(15,2) DEFAULT 0,
    solde_final DECIMAL(15,2) DEFAULT 0,
    date_cloture DATETIME NULL,
    utilisateur_cloture_id INT NULL,
    notes_cloture TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_cloture_id) REFERENCES utilisateurs(id),
    INDEX idx_exercices_exercice (exercice),
    INDEX idx_exercices_statut (statut),
    INDEX idx_exercices_dates (date_debut, date_fin)
);

-- Table des écritures comptables (déjà existante mais mise à jour pour SYSCOA)
ALTER TABLE ecritures_comptables 
ADD COLUMN IF NOT EXISTS solde_mouvement DECIMAL(15,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS numero_piece VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS reference_document VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS libelle_ecriture TEXT NOT NULL,
ADD COLUMN IF NOT EXISTS date_ecriture DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS date_validation DATETIME NULL,
ADD COLUMN IF NOT EXISTS utilisateur_validation_id INT NULL,
ADD COLUMN IF NOT EXISTS statut_ecriture ENUM('BROUILLON', 'VALIDEE', 'ANNULEE') DEFAULT 'VALIDEE';

-- Table du plan comptable SYSCOA (déjà existante mais mise à jour)
ALTER TABLE plan_comptable 
ADD COLUMN IF NOT EXISTS classe_syscoa CHAR(1) NOT NULL,
ADD COLUMN IF NOT EXISTS libelle_syscoa VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS type_compte ENUM('ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT', 'CAPITAL', 'TRESORIE', 'VALEUR_MOBILIERE', 'VALEUR_IMMOBILIERE') NULL,
ADD COLUMN IF NOT EXISTS solde_ouvert BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS compte_collectif BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS compte_tva BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS taux_tva DECIMAL(5,2) NULL;

-- Table des rapports comptables
CREATE TABLE IF NOT EXISTS rapports_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercice INT NOT NULL,
    type_rapport ENUM('BILAN', 'COMPTE_RESULTAT', 'JOURNAL_GENERAL', 'GRAND_LIVRE', 'BALANCE', 'ETAT_FINANCIER') NOT NULL,
    titre VARCHAR(200) NOT NULL,
    contenu JSON NOT NULL,
    format_export ENUM('PDF', 'EXCEL', 'CSV') DEFAULT 'PDF',
    date_generation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilisateur_generation_id INT NOT NULL,
    filename VARCHAR(255) NULL,
    taille_fichier INT DEFAULT 0,
    statut_rapport ENUM('GENERATION', 'GENEREE', 'ERREUR') DEFAULT 'GENERATION',
    message_erreur TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_generation_id) REFERENCES utilisateurs(id),
    INDEX idx_rapports_exercice (exercice),
    INDEX idx_rapports_type (type_rapport),
    INDEX idx_rapports_date (date_generation),
    INDEX idx_rapports_statut (statut_rapport)
);

-- Table des soldes comptables
CREATE TABLE IF NOT EXISTS soldes_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercice INT NOT NULL,
    compte_id INT NOT NULL,
    solde_initial DECIMAL(15,2) DEFAULT 0,
    total_debit DECIMAL(15,2) DEFAULT 0,
    total_credit DECIMAL(15,2) DEFAULT 0,
    solde_final DECIMAL(15,2) DEFAULT 0,
    date_calcul DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilisateur_calcul_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exercice) REFERENCES exercices_comptables(exercice),
    FOREIGN KEY (compte_id) REFERENCES plan_comptable(id),
    FOREIGN KEY (utilisateur_calcul_id) REFERENCES utilisateurs(id),
    INDEX idx_soldes_exercice (exercice),
    INDEX idx_soldes_compte (compte_id),
    INDEX idx_soldes_calcul (date_calcul)
);

-- Vue pour le bilan SYSCOA
CREATE OR REPLACE VIEW v_bilan_syscoa AS
SELECT 
    ec.exercice,
    pc.classe_syscoa,
    pc.libelle_syscoa,
    pc.numero_compte,
    pc.libelle_compte,
    pc.type_compte,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE 0 END) as total_debit,
    SUM(CASE WHEN ec.sens = 'CREDIT' THEN ec.montant ELSE 0 END) as total_credit,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE -ec.montant END) as solde
FROM ecritures_comptables ec
JOIN plan_comptable pc ON ec.compte_id = pc.id
WHERE pc.classe_syscoa IN ('1', '2', '3', '4', '5')
AND ec.statut_ecriture = 'VALIDEE'
AND ec.deleted_at IS NULL
GROUP BY ec.exercice, pc.classe_syscoa, pc.numero_compte, pc.libelle_compte, pc.type_compte
ORDER BY pc.classe_syscoa, pc.numero_compte;

-- Vue pour le compte de résultat SYSCOA
CREATE OR REPLACE VIEW v_compte_resultat_syscoa AS
SELECT 
    ec.exercice,
    pc.classe_syscoa,
    pc.libelle_syscoa,
    pc.numero_compte,
    pc.libelle_compte,
    pc.type_compte,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE 0 END) as total_debit,
    SUM(CASE WHEN ec.sens = 'CREDIT' THEN ec.montant ELSE 0 END) as total_credit,
    SUM(CASE WHEN ec.sens = 'CREDIT' THEN ec.montant ELSE -ec.montant END) as solde
FROM ecritures_comptables ec
JOIN plan_comptable pc ON ec.compte_id = pc.id
WHERE pc.classe_syscoa IN ('6', '7', '8')
AND ec.statut_ecriture = 'VALIDEE'
AND ec.deleted_at IS NULL
GROUP BY ec.exercice, pc.classe_syscoa, pc.numero_compte, pc.libelle_compte, pc.type_compte
ORDER BY pc.classe_syscoa, pc.numero_compte;

-- Vue pour la balance SYSCOA
CREATE OR REPLACE VIEW v_balance_syscoa AS
SELECT 
    ec.exercice,
    pc.numero_compte,
    pc.libelle_compte,
    pc.classe_syscoa,
    pc.libelle_syscoa,
    pc.type_compte,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE 0 END) as total_debit,
    SUM(CASE WHEN ec.sens = 'CREDIT' THEN ec.montant ELSE 0 END) as total_credit,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE -ec.montant END) as solde,
    COUNT(ec.id) as nombre_ecritures
FROM ecritures_comptables ec
JOIN plan_comptable pc ON ec.compte_id = pc.id
WHERE ec.statut_ecriture = 'VALIDEE'
AND ec.deleted_at IS NULL
GROUP BY ec.exercice, pc.numero_compte, pc.libelle_compte, pc.classe_syscoa, pc.libelle_syscoa, pc.type_compte
ORDER BY pc.numero_compte;

-- Vue pour la conformité SYSCOA
CREATE OR REPLACE VIEW v_conformite_syscoa AS
SELECT 
    ec.exercice,
    pc.classe_syscoa,
    COUNT(DISTINCT pc.numero_compte) as nombre_comptes,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE 0 END) as total_debit,
    SUM(CASE WHEN ec.sens = 'CREDIT' THEN ec.montant ELSE 0 END) as total_credit,
    SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE -ec.montant END) as solde,
    CASE 
        WHEN pc.classe_syscoa NOT IN ('1','2','3','4','5','6','7','8') THEN 'NON_CONFORME'
        ELSE 'CONFORME'
    END as conformite_classe,
    CASE 
        WHEN SUM(CASE WHEN ec.sens = 'DEBIT' THEN ec.montant ELSE 0 END) = SUM(CASE WHEN ec.sens = 'CREDIT' THEN ec.montant ELSE 0 END) THEN 'EQUILIBRE'
        ELSE 'DESEQUILIBRE'
    END as equilibre
FROM ecritures_comptables ec
JOIN plan_comptable pc ON ec.compte_id = pc.id
WHERE ec.statut_ecriture = 'VALIDEE'
AND ec.deleted_at IS NULL
GROUP BY ec.exercice, pc.classe_syscoa
ORDER BY ec.exercice, pc.classe_syscoa;

-- Triggers pour la comptabilité SYSCOA
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_exercice_cloture
AFTER UPDATE ON exercices_comptables
FOR EACH ROW
BEGIN
    IF OLD.statut != 'CLOTURE' AND NEW.statut = 'CLOTURE' THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_cloture_id, 
            'EXERCICE_CLOTURE', 
            'exercices_comptables', 
            NEW.id,
            JSON_OBJECT('ancien_statut', OLD.statut),
            JSON_OBJECT(
                'nouveau_statut', NEW.statut,
                'exercice', NEW.exercice,
                'solde_final', NEW.solde_final
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS tr_ecriture_validation
AFTER UPDATE ON ecritures_comptables
FOR EACH ROW
BEGIN
    IF OLD.statut_ecriture = 'BROUILLON' AND NEW.statut_ecriture = 'VALIDEE' THEN
        INSERT INTO audit_logs (
            utilisateur_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, date_action
        ) VALUES (
            NEW.utilisateur_validation_id, 
            'ECRITURE_VALIDATION', 
            'ecritures_comptables', 
            NEW.id,
            JSON_OBJECT('ancien_statut', OLD.statut_ecriture),
            JSON_OBJECT(
                'nouveau_statut', NEW.statut_ecriture,
                'montant', NEW.montant,
                'compte_id', NEW.compte_id
            ),
            CONNECTION_ID(),
            'SYSTEM_TRIGGER',
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Index pour optimisation
CREATE INDEX IF NOT EXISTS idx_ecritures_exercice ON ecritures_comptables(exercice);
CREATE INDEX IF NOT EXISTS idx_ecritures_date ON ecritures_comptables(date_ecriture);
CREATE INDEX IF NOT EXISTS idx_ecritures_statut ON ecritures_comptables(statut_ecriture);
CREATE INDEX IF NOT EXISTS idx_plan_syscoa ON plan_comptable(classe_syscoa, numero_compte);

-- Insertion des données de base SYSCOA
INSERT IGNORE INTO plan_comptable (numero_compte, libelle_compte, classe_syscoa, libelle_syscoa, type_compte) VALUES
-- Classe 1: Comptes de capitaux
('101', 'Capital social', '1', 'CAPITAL SOCIAL', 'CAPITAL'),
('105', 'Primes et réserves', '1', 'PRIMES ET RESERVES', 'CAPITAL'),
('106', 'Report à nouveau', '1', 'REPORT A NOUVEAU', 'CAPITAL'),

-- Classe 2: Comptes d'immobilisations
('21', 'Immobilisations incorporelles', '2', 'IMMOBILISATIONS INCORPORELLES', 'VALEUR_IMMOBILIERE'),
('23', 'Immobilisations corporelles', '2', 'IMMOBILISATIONS CORPORELLES', 'VALEUR_IMMOBILIERE'),
('28', 'Amortissements', '2', 'AMORTISSEMENTS', 'PASSIF'),

-- Classe 3: Comptes de stocks
('31', 'Marchandises', '3', 'MARCHANDISES', 'ACTIF'),
('35', 'Produits finis', '3', 'PRODUITS FINIS', 'ACTIF'),
('37', 'Stocks en cours', '3', 'STOCKS EN COURS', 'ACTIF'),

-- Classe 4: Comptes de tiers
('401', 'Fournisseurs', '4', 'FOURNISSEURS', 'PASSIF'),
('411', 'Clients', '4', 'CLIENTS', 'ACTIF'),
('44', 'Etat et collectivités', '4', 'ETAT ET COLLECTIVITES', 'ACTIF'),
('445', 'TVA déductible', '4', 'TVA DEDUCTIBLE', 'ACTIF'),
('4457', 'TVA collectée', '4', 'TVA COLLECTEE', 'PASSIF'),

-- Classe 5: Comptes de trésorerie
('51', 'Banques', '5', 'BANQUES', 'TRESORIE'),
('53', 'Caisse', '5', 'CAISSE', 'TRESORIE'),
('57', 'Chèques postaux', '5', 'CHEQUES POSTAUX', 'TRESORIE'),

-- Classe 6: Comptes de charges
('60', 'Achats', '6', 'ACHATS', 'CHARGE'),
('61', 'Services extérieurs', '6', 'SERVICES EXTERIEURS', 'CHARGE'),
('63', 'Impôts et taxes', '6', 'IMPÔTS ET TAXES', 'CHARGE'),
('64', 'Charges de personnel', '6', 'CHARGES DE PERSONNEL', 'CHARGE'),
('65', 'Autres charges', '6', 'AUTRES CHARGES', 'CHARGE'),
('68', 'Dotations aux amortissements', '6', 'DOTATIONS AUX AMORTISSEMENTS', 'CHARGE'),

-- Classe 7: Comptes de produits
('70', 'Ventes', '7', 'VENTES', 'PRODUIT'),
('71', 'Production stockée', '7', 'PRODUCTION STOCKEE', 'PRODUIT'),
('74', 'Subventions', '7', 'SUBVENTIONS', 'PRODUIT'),
('75', 'Autres produits', '7', 'AUTRES PRODUITS', 'PRODUIT'),
('78', 'Reprises sur amortissements', '7', 'REPRISES SUR AMORTISSEMENTS', 'PRODUIT'),

-- Classe 8: Comptes des autres charges et produits
('81', 'Charges exceptionnelles', '8', 'CHARGES EXCEPTIONNELLES', 'CHARGE'),
('83', 'Produits exceptionnels', '8', 'PRODUITS EXCEPTIONNELS', 'PRODUIT'),
('85', 'Participations', '8', 'PARTICIPATIONS', 'PRODUIT'),
('86', 'Reprises sur provisions', '8', 'REPRISES SUR PROVISIONS', 'PRODUIT');

-- Création de l'exercice courant
INSERT IGNORE INTO exercices_comptables (exercice, date_debut, date_fin, statut) VALUES
(2024, '2024-01-01', '2024-12-31', 'OUVERT');
