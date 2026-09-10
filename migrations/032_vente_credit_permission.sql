-- Permission métier distincte : vendre à crédit implique une créance client.
-- La migration est idempotente et n'accorde aucun droit par défaut.
INSERT INTO permissions (nom, module, description)
SELECT 'vente.credit', 'vente', 'Autoriser la finalisation d''une vente à crédit'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE nom = 'vente.credit');
