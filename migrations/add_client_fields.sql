-- Migration: Ajout des champs manquants pour le module Clients complet
-- Date: 19 juillet 2026
-- Description: Ajoute toutes les colonnes nécessaires pour un module Clients complet
-- Note: Utilise les noms de colonnes existants dans ClientController

-- Ajout du champ code (généré automatiquement)
ALTER TABLE clients ADD COLUMN code VARCHAR(20) UNIQUE AFTER id;

-- Ajout du champ prenom
ALTER TABLE clients ADD COLUMN prenom VARCHAR(100) AFTER nom;

-- Ajout du champ age (calculé automatiquement)
ALTER TABLE clients ADD COLUMN age INT AFTER date_naissance;

-- Ajout du champ statut (is_actif)
ALTER TABLE clients ADD COLUMN is_actif TINYINT(1) DEFAULT 1 AFTER type_client;

-- Ajout du champ telephone_secondaire
ALTER TABLE clients ADD COLUMN telephone_secondaire VARCHAR(20) AFTER telephone;

-- Ajout du champ email
ALTER TABLE clients ADD COLUMN email VARCHAR(100) AFTER telephone_secondaire;

-- Ajout du champ ville (extrait de l'adresse ou nouveau champ)
ALTER TABLE clients ADD COLUMN ville VARCHAR(100) AFTER adresse;

-- Ajout du champ IFU (numero_ifu)
ALTER TABLE clients ADD COLUMN numero_ifu VARCHAR(50) AFTER ville;

-- Ajout du champ RCCM (numero_rccm)
ALTER TABLE clients ADD COLUMN numero_rccm VARCHAR(50) AFTER numero_ifu;

-- Ajout du champ numero_assurance
ALTER TABLE clients ADD COLUMN numero_assurance VARCHAR(50) AFTER numero_rccm;

-- Ajout du champ compagnie_assurance
ALTER TABLE clients ADD COLUMN compagnie_assurance VARCHAR(100) AFTER numero_assurance;

-- Ajout du champ solde_initial
ALTER TABLE clients ADD COLUMN solde_initial DECIMAL(10,2) DEFAULT 0 AFTER plafond;

-- Ajout du champ notes (observations)
ALTER TABLE clients ADD COLUMN notes TEXT AFTER solde;

-- Ajout du champ utilisateur_creation_id
ALTER TABLE clients ADD COLUMN utilisateur_creation_id INT AFTER notes;

-- Ajout du champ utilisateur_modification_id
ALTER TABLE clients ADD COLUMN utilisateur_modification_id INT AFTER utilisateur_creation_id;

-- Ajout du champ updated_at
ALTER TABLE clients ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER utilisateur_modification_id;

-- Ajout du champ deleted_at pour soft delete
ALTER TABLE clients ADD COLUMN deleted_at TIMESTAMP NULL AFTER updated_at;

-- Génération des codes clients pour les enregistrements existants
UPDATE clients SET code = CONCAT('CLI', LPAD(id, 6, '0')) WHERE code IS NULL OR code = '';

-- Génération des matricules pour les enregistrements existants (si vide)
UPDATE clients SET matricule = CONCAT('MAT', LPAD(id, 6, '0')) WHERE matricule IS NULL OR matricule = '';

-- Calcul de l'âge pour les enregistrements existants
UPDATE clients SET age = TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) WHERE date_naissance IS NOT NULL AND age IS NULL;

-- Initialisation du solde_initial avec le solde actuel pour les enregistrements existants
UPDATE clients SET solde_initial = solde WHERE solde_initial IS NULL OR solde_initial = 0;

-- Initialisation de is_actif pour les enregistrements existants
UPDATE clients SET is_actif = 1 WHERE is_actif IS NULL;
