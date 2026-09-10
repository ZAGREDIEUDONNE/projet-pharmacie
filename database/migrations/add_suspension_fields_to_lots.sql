-- Migration: Ajouter les champs de suspension à la table lots
-- Date: 2026-07-15
-- Description: Permettre de suspendre et réactiver les lots avec traçabilité

-- Ajouter le champ statut_lot
ALTER TABLE lots ADD COLUMN statut_lot ENUM('ACTIF', 'SUSPENDU') DEFAULT 'ACTIF' AFTER is_actif;

-- Ajouter le champ motif_suspension
ALTER TABLE lots ADD COLUMN motif_suspension TEXT NULL AFTER statut_lot;

-- Ajouter le champ date_suspension
ALTER TABLE lots ADD COLUMN date_suspension DATE NULL AFTER motif_suspension;

-- Ajouter le champ utilisateur_suspension_id
ALTER TABLE lots ADD COLUMN utilisateur_suspension_id INT NULL AFTER date_suspension;

-- Ajouter la clé étrangère pour l'utilisateur de suspension
ALTER TABLE lots ADD CONSTRAINT fk_lots_suspension_utilisateur 
    FOREIGN KEY (utilisateur_suspension_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

-- Mettre à jour les lots existants pour qu'ils soient ACTIF par défaut
UPDATE lots SET statut_lot = 'ACTIF' WHERE statut_lot IS NULL;
