-- =============================================
-- AJOUT DU RÔLE COMPTABLE
-- =============================================
-- Ce fichier ajoute le rôle COMPTABLE (ID 6) pour correspondre à RoleCatalog.php
-- Note: Le rôle COMPTABLE existe déjà avec ID 6 dans la base, cette migration est conservée pour référence

-- Insertion du rôle COMPTABLE (déjà exécuté avec ID 6)
INSERT IGNORE INTO roles (id, nom, description) VALUES
(6, 'COMPTABLE', 'Responsable de la comptabilité et du suivi comptable de la pharmacie');

-- Permissions comptables
INSERT IGNORE INTO permissions (nom, description, module) VALUES
('comptabilite_view', 'Accéder au module comptabilité', 'comptabilite'),
('plan_comptable_view', 'Voir le plan comptable', 'comptabilite'),
('ecritures_view', 'Voir les écritures comptables', 'comptabilite'),
('journaux_view', 'Voir les journaux comptables', 'comptabilite'),
('journal_ventes_view', 'Voir le journal des ventes', 'comptabilite'),
('journal_achats_view', 'Voir le journal des achats', 'comptabilite'),
('journal_caisse_view', 'Voir le journal de caisse', 'comptabilite'),
('grand_livre_view', 'Voir le grand livre', 'comptabilite'),
('balance_view', 'Voir la balance générale', 'comptabilite'),
('etats_financiers_view', 'Voir les états financiers', 'comptabilite'),
('suivi_tiers_view', 'Voir le suivi des tiers', 'comptabilite'),
('creances_view', 'Voir les créances clients', 'comptabilite'),
('dettes_view', 'Voir les dettes fournisseurs', 'comptabilite'),
('tva_view', 'Voir la TVA', 'comptabilite'),
('integration_view', 'Voir l\'intégration comptable', 'comptabilite');

-- Attribution des permissions au rôle COMPTABLE
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE nom IN (
    'comptabilite_view',
    'plan_comptable_view',
    'ecritures_view',
    'journaux_view',
    'journal_ventes_view',
    'journal_achats_view',
    'journal_caisse_view',
    'grand_livre_view',
    'balance_view',
    'etats_financiers_view',
    'suivi_tiers_view',
    'creances_view',
    'dettes_view',
    'tva_view',
    'integration_view'
);
