-- Unification moteur comptable SYSCOHADA
-- Colonnes de liaison operation -> ecriture

ALTER TABLE ventes ADD COLUMN IF NOT EXISTS ecriture_id INT NULL;
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS ecriture_id INT NULL;
ALTER TABLE mouvements_caisse ADD COLUMN IF NOT EXISTS ecriture_id INT NULL;
ALTER TABLE mouvements_stock ADD COLUMN IF NOT EXISTS ecriture_id INT NULL;

-- Synchronisation numero_compte = code (standard unique)
UPDATE plan_comptable SET code = numero_compte WHERE (code IS NULL OR code = '') AND id > 0;
UPDATE plan_comptable SET libelle = nom_compte WHERE (libelle IS NULL OR libelle = '') AND id > 0;
UPDATE plan_comptable SET classe_id = CAST(classe AS CHAR) WHERE classe_id IS NULL AND id > 0;
UPDATE plan_comptable SET type = type_compte WHERE type IS NULL AND id > 0;
