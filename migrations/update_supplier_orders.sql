-- Migration pour ajouter les fonctionnalités manquantes aux commandes fournisseurs
-- Date: 19 juillet 2026

-- Ajouter le statut EN_ATTENTE à supplier_orders
ALTER TABLE supplier_orders 
MODIFY COLUMN statut ENUM('BROUILLON','EN_ATTENTE','ENVOYEE','VALIDEE','RECEPTION_PARTIELLE','RECEPTION_COMPLETE','ANNULEE') NOT NULL;

-- Ajouter la colonne reference_commande à supplier_orders
ALTER TABLE supplier_orders 
ADD COLUMN reference_commande VARCHAR(100) NULL AFTER numero_commande;

-- Ajouter les colonnes remise_globale et tva_globale à supplier_orders
ALTER TABLE supplier_orders 
ADD COLUMN remise_globale DECIMAL(10,2) DEFAULT 0.00 AFTER montant_total,
ADD COLUMN tva_globale DECIMAL(10,2) DEFAULT 0.00 AFTER remise_globale,
ADD COLUMN montant_ht DECIMAL(12,2) DEFAULT 0.00 AFTER tva_globale,
ADD COLUMN montant_ttc DECIMAL(12,2) DEFAULT 0.00 AFTER montant_ht;

-- Ajouter les colonnes remise et tva à supplier_order_items
ALTER TABLE supplier_order_items 
ADD COLUMN remise DECIMAL(10,2) DEFAULT 0.00 AFTER montant_total,
ADD COLUMN tva DECIMAL(10,2) DEFAULT 0.00 AFTER remise,
ADD COLUMN montant_ht DECIMAL(12,2) DEFAULT 0.00 AFTER tva,
ADD COLUMN montant_ttc DECIMAL(12,2) DEFAULT 0.00 AFTER montant_ht;

-- Mettre à jour montant_total pour stocker le montant TTC
ALTER TABLE supplier_orders 
MODIFY COLUMN montant_total DECIMAL(12,2) NOT NULL;

-- Ajouter un index sur reference_commande
ALTER TABLE supplier_orders 
ADD INDEX idx_reference_commande (reference_commande);
