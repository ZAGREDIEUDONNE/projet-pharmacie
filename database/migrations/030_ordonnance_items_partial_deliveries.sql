-- Une même ordonnance peut être délivrée en plusieurs ventes.
-- La contrainte historique empêchait de tracer le même produit lors d'une délivrance partielle.
ALTER TABLE vente_ordonnance_items DROP INDEX IF EXISTS unique_ordonnance_produit;
ALTER TABLE vente_ordonnance_items ADD UNIQUE KEY IF NOT EXISTS unique_ordonnance_vente_produit (ordonnance_id, vente_id, produit_id);
