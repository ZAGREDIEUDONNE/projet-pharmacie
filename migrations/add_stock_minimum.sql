-- Migration: Ajout du champ stock_minimum à la table produits
-- Date: 19 juillet 2026
-- Description: Ajoute le champ stock_minimum manquant

ALTER TABLE produits ADD COLUMN stock_minimum INT DEFAULT 0 AFTER stock;
