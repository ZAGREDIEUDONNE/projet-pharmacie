-- Migration: Ajout des champs pour la gestion des ordonnances par type de produit
-- Date: 18 juillet 2026

-- Ajouter le champ type_delivrance s'il n'existe pas
ALTER TABLE produits ADD COLUMN IF NOT EXISTS type_delivrance VARCHAR(50) DEFAULT 'MEDICAMENT_CONSEIL';

-- Ajouter le champ requires_prescription s'il n'existe pas
ALTER TABLE produits ADD COLUMN IF NOT EXISTS requires_prescription TINYINT(1) DEFAULT 0;

-- Mettre à jour les produits existants avec une valeur par défaut
UPDATE produits SET type_delivrance = 'MEDICAMENT_CONSEIL' WHERE type_delivrance IS NULL OR type_delivrance = '';
