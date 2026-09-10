-- Migration: Ajout des nouvelles formes pharmaceutiques
-- Date: 18 juillet 2026

-- Insérer les nouvelles formes pharmaceutiques si elles n'existent pas déjà
INSERT IGNORE INTO formes_pharmaceutiques (nom, created_at, updated_at) VALUES
('Comprimé', NOW(), NOW()),
('Gélule', NOW(), NOW()),
('Sirop', NOW(), NOW()),
('Suspension', NOW(), NOW()),
('Granulés', NOW(), NOW()),
('Goutte nasale', NOW(), NOW()),
('Goutte auriculaire', NOW(), NOW()),
('Collyre', NOW(), NOW()),
('Pommade cutanée', NOW(), NOW()),
('Pommade ophtalmique', NOW(), NOW()),
('Gel', NOW(), NOW()),
('Lotion', NOW(), NOW()),
('Tisane', NOW(), NOW()),
('Poudre', NOW(), NOW()),
('Consommable médical', NOW(), NOW()),
('Injectable', NOW(), NOW()),
('Vaccin', NOW(), NOW()),
('Sérum', NOW(), NOW()),
('Suppositoire', NOW(), NOW()),
('Comprimé gynécologique', NOW(), NOW()),
('Bain de bouche', NOW(), NOW()),
('Parapharmacie', NOW(), NOW()),
('Phytomédicament', NOW(), NOW()),
('Équipements médicaux', NOW(), NOW()),
('Autres', NOW(), NOW());
