-- =============================================
-- MISE À JOUR DES TYPES DE CLIENTS - PHARMACIE BURKINA FASO
-- Remplacement des types de clients existants par les types spécifiques
-- aux pharmacies burkinabè
-- =============================================

-- Mettre à jour l'ENUM du champ type_client dans la table clients
ALTER TABLE clients 
MODIFY COLUMN type_client ENUM('ORDINAIRE', 'COURANT', 'COURANT_DEPOT', 'COURANT_BON', 'COURANT_CARNET', 'AUTRES_CLIENTS') NOT NULL DEFAULT 'ORDINAIRE';

-- Migration des données existantes vers les nouveaux types
-- Anciens types vers nouveaux types:
-- ORDINAIRE, PARTICULIER, SOUS_CLIENT, BENEFICIAIRE, PRESCRIPTEUR -> ORDINAIRE
-- ASSURE -> COURANT
-- ENTREPRISE -> AUTRES_CLIENTS

UPDATE clients SET type_client = 'ORDINAIRE' 
WHERE type_client IN ('ORDINAIRE', 'PARTICULIER', 'SOUS_CLIENT', 'BENEFICIAIRE', 'PRESCRIPTEUR')
AND deleted_at IS NULL;

UPDATE clients SET type_client = 'COURANT' 
WHERE type_client = 'ASSURE' 
AND deleted_at IS NULL;

UPDATE clients SET type_client = 'AUTRES_CLIENTS' 
WHERE type_client = 'ENTREPRISE' 
AND deleted_at IS NULL;

-- Mettre à jour les statistiques dans le modèle Client pour refléter les nouveaux types
-- (Ceci sera fait dans le code PHP, mais le SQL est prêt)

-- Créer une vue pour les statistiques par type de client
CREATE OR REPLACE VIEW v_statistiques_clients_par_type AS
SELECT 
    type_client,
    COUNT(*) as nombre_clients,
    SUM(solde_credit) as total_solde_credit,
    SUM(plafond_credit) as total_plafond_credit,
    COUNT(CASE WHEN solde_credit > 0 THEN 1 END) as clients_debiteurs
FROM clients
WHERE deleted_at IS NULL
GROUP BY type_client;

-- Mettre à jour les permissions si nécessaire pour les nouveaux types
-- (Les permissions existantes couvrent déjà la gestion des clients)
