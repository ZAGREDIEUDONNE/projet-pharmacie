-- Index couvrants pour les listes, arrêtés et relevés du Suivi Client.
-- PREPARE est volontairement utilisé : CREATE INDEX IF NOT EXISTS n'est pas
-- pris en charge par tous les serveurs MySQL actuellement utilisés en pharmacie.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'client_reglements' AND index_name = 'idx_client_reglements_client_date') = 0, 'CREATE INDEX idx_client_reglements_client_date ON client_reglements (client_id, date_mouvement)', 'SELECT 1');
PREPARE suivi_client_index FROM @sql;
EXECUTE suivi_client_index;
DEALLOCATE PREPARE suivi_client_index;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'ventes' AND index_name = 'idx_ventes_client_credit_date') = 0, 'CREATE INDEX idx_ventes_client_credit_date ON ventes (client_id, is_credit, statut_vente, date_vente)', 'SELECT 1');
PREPARE suivi_client_index FROM @sql;
EXECUTE suivi_client_index;
DEALLOCATE PREPARE suivi_client_index;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bons' AND index_name = 'idx_bons_client_type_date') = 0, 'CREATE INDEX idx_bons_client_type_date ON bons (client_id, type_bon, statut_bon, date_emission)', 'SELECT 1');
PREPARE suivi_client_index FROM @sql;
EXECUTE suivi_client_index;
DEALLOCATE PREPARE suivi_client_index;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'clients' AND index_name = 'idx_clients_solde_nom') = 0, 'CREATE INDEX idx_clients_solde_nom ON clients (solde_credit, nom, prenom)', 'SELECT 1');
PREPARE suivi_client_index FROM @sql;
EXECUTE suivi_client_index;
DEALLOCATE PREPARE suivi_client_index;
