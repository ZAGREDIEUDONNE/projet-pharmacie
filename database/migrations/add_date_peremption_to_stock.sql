-- Migration pour ajouter la gestion des péremptions au niveau du stock global
-- Cela permet de gérer les péremptions sans dépendre des lots

-- Ajouter le champ date_peremption à la table stock
ALTER TABLE stock 
ADD COLUMN date_peremption DATE NULL 
AFTER dernier_mouvement;

-- Ajouter un index pour optimiser les requêtes de péremption
CREATE INDEX idx_stock_peremption ON stock(date_peremption);

-- Mettre à jour la vue des produits en péremption pour utiliser stock.date_peremption
DROP VIEW IF EXISTS vue_produits_peremption;

CREATE VIEW vue_produits_peremption AS
SELECT 
    p.id, p.nom, p.code_cip,
    s.date_peremption,
    s.quantite_disponible,
    DATEDIFF(s.date_peremption, CURDATE()) as jours_restants,
    CASE 
        WHEN s.date_peremption IS NULL THEN 'NON_DEFINI'
        WHEN DATEDIFF(s.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
        WHEN DATEDIFF(s.date_peremption, CURDATE()) <= 90 THEN 'URGENT'
        WHEN DATEDIFF(s.date_peremption, CURDATE()) <= 180 THEN 'ALERTE'
        ELSE 'NORMAL'
    END as niveau_peremption
FROM produits p
LEFT JOIN stock s ON p.id = s.produit_id
WHERE p.deleted_at IS NULL 
AND p.is_actif = TRUE
AND s.date_peremption IS NOT NULL
AND s.quantite_disponible > 0
ORDER BY s.date_peremption ASC;
