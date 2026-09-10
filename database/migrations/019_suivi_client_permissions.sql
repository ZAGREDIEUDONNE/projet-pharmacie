-- Module Suivi Client: permissions canoniques et attributions de roles.
-- Cette migration est idempotente et correspond au schema RBAC actif
-- (permissions.nom, roles.nom, role_permissions).

INSERT IGNORE INTO permissions (nom, description, module) VALUES
('suivi_client.view', 'Consulter le module Suivi Client', 'suivi_client'),
('suivi_client.create', 'Creer des elements du suivi client', 'suivi_client'),
('suivi_client.update', 'Modifier des elements du suivi client', 'suivi_client'),
('suivi_client.delete', 'Supprimer des elements du suivi client', 'suivi_client'),
('suivi_client.reglement', 'Saisir et reprendre un reglement client', 'suivi_client'),
('suivi_client.solde', 'Consulter les soldes clients', 'suivi_client'),
('suivi_client.releve', 'Consulter et imprimer les releves clients', 'suivi_client');

-- L'administrateur doit conserver toutes les permissions, y compris celles
-- ajoutees apres la creation du role.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE LOWER(r.nom) IN ('admin', 'administrateur');

-- Le role Pharmacien est attribue lorsqu'il existe. Dans cette application,
-- l'ancien role Assistant represente egalement le pharmacien operationnel.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'suivi_client.view',
    'suivi_client.create',
    'suivi_client.update',
    'suivi_client.delete',
    'suivi_client.reglement',
    'suivi_client.solde',
    'suivi_client.releve'
)
WHERE LOWER(r.nom) IN ('pharmacien', 'assistant');

-- Vendeur : consultation, reglements et editions courantes.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'suivi_client.view',
    'suivi_client.reglement',
    'suivi_client.solde',
    'suivi_client.releve'
)
WHERE LOWER(r.nom) = 'vendeur';

-- Charge de commande : consultation des comptes, soldes et releves.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'suivi_client.view',
    'suivi_client.solde',
    'suivi_client.releve'
)
WHERE LOWER(r.nom) IN ('charge_commande', 'charge de commande');
