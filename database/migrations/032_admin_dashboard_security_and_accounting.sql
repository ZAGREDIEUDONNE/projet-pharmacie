-- Phase 2 Dashboard Administrateur : traçabilité de contre-passation et RBAC.
-- Migration idempotente pour MySQL 8+.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ecritures_comptables'
      AND COLUMN_NAME = 'ecriture_origine_id'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE ecritures_comptables ADD COLUMN ecriture_origine_id INT NULL AFTER reference_id, ADD INDEX idx_ecritures_origine (ecriture_origine_id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @enum_has_annulation := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mouvements_stock'
      AND COLUMN_NAME = 'reference_type'
      AND COLUMN_TYPE LIKE '%ANNULATION_VENTE%'
);
SET @ddl := IF(
    @enum_has_annulation = 0,
    'ALTER TABLE mouvements_stock MODIFY COLUMN reference_type ENUM(''VENTE'',''COMMAND'',''INVENTAIRE'',''AJUSTEMENT'',''TRANSFERT'',''ANNULATION_VENTE'') NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE roles
SET is_actif = 1, statut = 1, updated_at = NOW()
WHERE id = 6;

INSERT INTO permissions (nom, code, description, module, action, statut, date_creation, created_at)
SELECT x.nom, x.nom, x.description, 'comptabilite', 'view', 1, NOW(), NOW()
FROM (
    SELECT 'comptabilite_view' AS nom, 'Accéder au module comptabilité' AS description UNION ALL
    SELECT 'plan_comptable_view', 'Voir le plan comptable' UNION ALL
    SELECT 'ecritures_view', 'Voir les écritures comptables' UNION ALL
    SELECT 'journaux_view', 'Voir les journaux comptables' UNION ALL
    SELECT 'journal_ventes_view', 'Voir le journal des ventes' UNION ALL
    SELECT 'journal_achats_view', 'Voir le journal des achats' UNION ALL
    SELECT 'journal_caisse_view', 'Voir le journal de caisse' UNION ALL
    SELECT 'grand_livre_view', 'Voir le grand livre' UNION ALL
    SELECT 'balance_view', 'Voir la balance générale' UNION ALL
    SELECT 'etats_financiers_view', 'Voir les états financiers' UNION ALL
    SELECT 'suivi_tiers_view', 'Voir le suivi des tiers' UNION ALL
    SELECT 'creances_view', 'Voir les créances clients' UNION ALL
    SELECT 'dettes_view', 'Voir les dettes fournisseurs' UNION ALL
    SELECT 'tva_view', 'Voir la TVA' UNION ALL
    SELECT 'integration_view', 'Voir l’intégration comptable'
) x
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.nom = x.nom);

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT 6, p.id, NOW()
FROM permissions p
LEFT JOIN role_permissions rp ON rp.role_id = 6 AND rp.permission_id = p.id
WHERE p.nom IN (
    'comptabilite_view', 'plan_comptable_view', 'ecritures_view', 'journaux_view',
    'journal_ventes_view', 'journal_achats_view', 'journal_caisse_view', 'grand_livre_view',
    'balance_view', 'etats_financiers_view', 'suivi_tiers_view', 'creances_view',
    'dettes_view', 'tva_view', 'integration_view'
) AND rp.id IS NULL;

-- L'action d'annulation utilise historiquement cancel_ticket ; elle est ajoutée
-- explicitement au rôle administrateur afin d'éviter tout bypass RBAC.
INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT 1, p.id, NOW()
FROM permissions p
LEFT JOIN role_permissions rp ON rp.role_id = 1 AND rp.permission_id = p.id
WHERE p.nom = 'cancel_ticket' AND rp.id IS NULL;
