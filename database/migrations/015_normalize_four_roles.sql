-- Normalisation des 4 rôles métier uniquement
-- 1 administrateur, 2 vendeur, 3 assistant, 4 charge_commande

SET FOREIGN_KEY_CHECKS = 0;

UPDATE utilisateurs u
INNER JOIN roles r ON r.id = u.role_id
SET u.role_id = CASE
    WHEN LOWER(COALESCE(r.nom, '')) IN ('admin', 'administrateur')
         OR UPPER(COALESCE(r.code, '')) IN ('ADMIN', 'ADMINISTRATEUR') THEN 1
    WHEN LOWER(COALESCE(r.nom, '')) = 'vendeur'
         OR UPPER(COALESCE(r.code, '')) = 'VENDEUR' THEN 2
    WHEN LOWER(COALESCE(r.nom, '')) = 'assistant'
         OR UPPER(COALESCE(r.code, '')) = 'ASSISTANT' THEN 3
    WHEN LOWER(COALESCE(r.nom, '')) IN ('charge_commande', 'charge de commande', 'commande', 'pharmacien')
         OR UPPER(COALESCE(r.code, '')) IN ('CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE', 'COMMANDE', 'PHARMACIEN') THEN 4
    ELSE 2
END;

DELETE FROM role_permissions;
DELETE FROM roles;

INSERT INTO roles (id, nom, description) VALUES
(1, 'administrateur', 'Accès complet système et administration'),
(2, 'vendeur', 'Point de vente et caisse'),
(3, 'assistant', 'Vente avancée, stock et actions métier'),
(4, 'charge_commande', 'Stock, commandes fournisseurs et réceptions');

SET FOREIGN_KEY_CHECKS = 1;
