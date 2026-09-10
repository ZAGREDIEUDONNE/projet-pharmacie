-- =============================================
-- MIGRATION: AJOUT DES CHAMPS DE GESTION CLIENTS
-- Pour une gestion complète des clients depuis le Dashboard Admin
-- =============================================

-- Ajout des champs manquants pour une gestion complète des clients
ALTER TABLE clients 
ADD COLUMN matricule VARCHAR(50) UNIQUE AFTER prenom,
ADD COLUMN date_naissance DATE AFTER matricule,
ADD COLUMN age INT AFTER date_naissance,
ADD COLUMN telephone_secondaire VARCHAR(20) AFTER telephone,
ADD COLUMN ville VARCHAR(100) AFTER adresse,
ADD COLUMN numero_ifu VARCHAR(50) AFTER compagnie_assurance,
ADD COLUMN numero_rccm VARCHAR(50) AFTER numero_ifu,
ADD COLUMN solde_initial DECIMAL(10,2) DEFAULT 0 AFTER plafond_credit,
ADD COLUMN notes TEXT AFTER solde_credit,
ADD COLUMN utilisateur_creation_id INT AFTER created_at,
ADD COLUMN utilisateur_modification_id INT AFTER updated_at;

-- Ajout des clés étrangères pour les utilisateurs
ALTER TABLE clients 
ADD CONSTRAINT fk_client_user_creation FOREIGN KEY (utilisateur_creation_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_client_user_modification FOREIGN KEY (utilisateur_modification_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

-- Ajout d'index pour optimiser les recherches
CREATE INDEX idx_clients_matricule ON clients(matricule);
CREATE INDEX idx_clients_ville ON clients(ville);
CREATE INDEX idx_clients_type_statut ON clients(type_client, is_actif);
CREATE INDEX idx_clients_utilisateur_creation ON clients(utilisateur_creation_id);

-- Mise à jour des données existantes pour les champs nouvellement ajoutés
-- Générer des matricules pour les clients existants
UPDATE clients 
SET matricule = CONCAT('CLI', LPAD(id, 6, '0'))
WHERE matricule IS NULL AND deleted_at IS NULL;

-- Mettre à jour l'âge à partir de la date de naissance si disponible
UPDATE clients 
SET age = TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())
WHERE date_naissance IS NOT NULL AND age IS NULL AND deleted_at IS NULL;
