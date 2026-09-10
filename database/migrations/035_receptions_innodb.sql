-- Atomicité des réceptions fournisseurs.
--
-- Cette migration ne modifie ni les colonnes, ni les index, ni les contraintes,
-- ni les données. Elle convertit seulement les tables MyISAM réellement utilisées
-- dans le flux receiveOrder() afin que le ROLLBACK couvre tout le flux.
--
-- Chaque conversion est conditionnelle : sur une installation déjà en InnoDB,
-- l'instruction exécutée est un DO inoffensif.

SET @atomicity_schema := DATABASE();

SET @atomicity_sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = @atomicity_schema
              AND TABLE_NAME = 'supplier_orders'
              AND ENGINE = 'MyISAM'
        ),
        'ALTER TABLE `supplier_orders` ENGINE=InnoDB',
        'DO 0'
    )
);
PREPARE atomicity_stmt FROM @atomicity_sql;
EXECUTE atomicity_stmt;
DEALLOCATE PREPARE atomicity_stmt;

SET @atomicity_sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = @atomicity_schema
              AND TABLE_NAME = 'supplier_order_items'
              AND ENGINE = 'MyISAM'
        ),
        'ALTER TABLE `supplier_order_items` ENGINE=InnoDB',
        'DO 0'
    )
);
PREPARE atomicity_stmt FROM @atomicity_sql;
EXECUTE atomicity_stmt;
DEALLOCATE PREPARE atomicity_stmt;

SET @atomicity_sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = @atomicity_schema
              AND TABLE_NAME = 'receptions'
              AND ENGINE = 'MyISAM'
        ),
        'ALTER TABLE `receptions` ENGINE=InnoDB',
        'DO 0'
    )
);
PREPARE atomicity_stmt FROM @atomicity_sql;
EXECUTE atomicity_stmt;
DEALLOCATE PREPARE atomicity_stmt;

SET @atomicity_sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = @atomicity_schema
              AND TABLE_NAME = 'reception_items'
              AND ENGINE = 'MyISAM'
        ),
        'ALTER TABLE `reception_items` ENGINE=InnoDB',
        'DO 0'
    )
);
PREPARE atomicity_stmt FROM @atomicity_sql;
EXECUTE atomicity_stmt;
DEALLOCATE PREPARE atomicity_stmt;

SET @atomicity_sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = @atomicity_schema
              AND TABLE_NAME = 'stock_entries'
              AND ENGINE = 'MyISAM'
        ),
        'ALTER TABLE `stock_entries` ENGINE=InnoDB',
        'DO 0'
    )
);
PREPARE atomicity_stmt FROM @atomicity_sql;
EXECUTE atomicity_stmt;
DEALLOCATE PREPARE atomicity_stmt;

-- Branche receiveOrder() activée lorsqu'une facture fournisseur est renseignée.
SET @atomicity_sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = @atomicity_schema
              AND TABLE_NAME = 'fournisseur_reglements'
              AND ENGINE = 'MyISAM'
        ),
        'ALTER TABLE `fournisseur_reglements` ENGINE=InnoDB',
        'DO 0'
    )
);
PREPARE atomicity_stmt FROM @atomicity_sql;
EXECUTE atomicity_stmt;
DEALLOCATE PREPARE atomicity_stmt;
