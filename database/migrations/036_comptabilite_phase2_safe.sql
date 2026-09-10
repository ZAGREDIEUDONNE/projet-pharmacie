-- Phase 2 comptabilité: additive and compatible with the current real schema.
-- Review before applying. This file is deliberately not auto-executed by PHP.

UPDATE plan_comptable
SET code = numero_compte
WHERE numero_compte IN ('44561', '44571', '581', '75', '6031')
  AND (code IS NULL OR code = '');

UPDATE plan_comptable
SET libelle = nom_compte
WHERE numero_compte IN ('44561', '44571', '581', '75', '6031')
  AND (libelle IS NULL OR libelle = '');

ALTER TABLE classes_comptes ENGINE = InnoDB;

-- The application enforces one open period transactionally. A seed is only
-- created when no period exists and uses the actual lower-case enum values.
INSERT INTO exercices_comptables (exercice, date_debut, date_fin, statut)
SELECT DATE_FORMAT(CURDATE(), '%Y'), DATE_FORMAT(CURDATE(), '%Y-01-01'), DATE_FORMAT(CURDATE(), '%Y-12-31'), 'ouvert'
WHERE NOT EXISTS (SELECT 1 FROM exercices_comptables);
