<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class OptimizationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Optimise les performances globales du système
     */
    public function optimizeGlobalPerformance(): array
    {
        $optimizations = [];

        try {
            // Optimisation des requêtes SQL critiques
            $optimizations['sql_queries'] = $this->optimizeCriticalSQLQueries();
            
            // Indexation des tables principales
            $optimizations['indexing'] = $this->optimizeTableIndexing();
            
            // Mise en cache des données fréquentes
            $optimizations['caching'] = $this->implementDataCaching();
            
            // Réduction des appels inutiles
            $optimizations['calls_reduction'] = $this->reduceUnnecessaryCalls();
            
            // Optimisation de la configuration
            $optimizations['configuration'] = $this->optimizeConfiguration();

            return [
                'success' => true,
                'data' => $optimizations,
                'timestamp' => date('Y-m-d H:i:s'),
                'summary' => $this->generateOptimizationSummary($optimizations)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'optimisation globale',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Optimise les requêtes SQL critiques
     */
    private function optimizeCriticalSQLQueries(): array
    {
        $optimizations = [];

        // Créer des vues optimisées pour les requêtes fréquentes
        $views = [
            'vue_ventes_optimisee' => "
                CREATE OR REPLACE VIEW vue_ventes_optimisee AS
                SELECT 
                    v.id,
                    v.numero_ticket,
                    v.date_vente,
                    v.montant_ttc,
                    v.statut_vente,
                    v.utilisateur_id,
                    v.client_id,
                    c.nom as client_nom,
                    u.username as vendeur_nom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                WHERE v.statut_vente = 'VALIDEE'
            ",
            
            'vue_stock_critique' => "
                CREATE OR REPLACE VIEW vue_stock_critique AS
                SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    COALESCE(s.stock_minimum, 0) as stock_minimum,
                    CASE 
                        WHEN COALESCE(s.quantite_actuelle, 0) = 0 THEN 'RUPTURE'
                        WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_minimum, 0) THEN 'CRITIQUE'
                        ELSE 'NORMAL'
                    END as niveau_stock
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
            ",
            
            'vue_mouvements_rapides' => "
                CREATE OR REPLACE VIEW vue_mouvements_rapides AS
                SELECT 
                    m.id,
                    m.type_mouvement,
                    m.quantite,
                    m.date_mouvement,
                    p.nom as produit_nom,
                    u.username as operateur_nom
                FROM mouvements_stock m
                JOIN produits p ON m.produit_id = p.id
                LEFT JOIN utilisateurs u ON m.utilisateur_id = u.id
                WHERE m.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            "
        ];

        foreach ($views as $viewName => $createView) {
            try {
                $stmt = $this->db->prepare($createView);
                $stmt->execute();
                $optimizations['views_created'][] = $viewName;
            } catch (PDOException $e) {
                $optimizations['views_errors'][] = [
                    'view' => $viewName,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Créer des procédures stockées pour les opérations critiques
        $procedures = [
            'sp_vente_rapide' => "
                CREATE PROCEDURE sp_vente_rapide(
                    IN p_produit_id INT,
                    IN p_quantite INT,
                    IN p_client_id INT,
                    IN p_utilisateur_id INT,
                    OUT p_vente_id INT,
                    OUT p_statut VARCHAR(20)
                )
                BEGIN
                    DECLARE v_stock_actuel INT DEFAULT 0;
                    DECLARE v_prix_unitaire DECIMAL(10,2) DEFAULT 0;
                    DECLARE v_numero_ticket VARCHAR(50);
                    
                    -- Vérifier le stock
                    SELECT COALESCE(s.quantite_actuelle, 0) INTO v_stock_actuel
                    FROM stock s
                    WHERE s.produit_id = p_produit_id;
                    
                    IF v_stock_actuel < p_quantite THEN
                        SET p_statut = 'STOCK_INSUFFISANT';
                        SET p_vente_id = 0;
                    ELSE
                        -- Récupérer le prix
                        SELECT prix_vente INTO v_prix_unitaire
                        FROM produits
                        WHERE id = p_produit_id;
                        
                        -- Générer le numéro de ticket
                        SET v_numero_ticket = CONCAT('VT', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
                        
                        -- Insérer la vente
                        INSERT INTO ventes (
                            numero_ticket, client_id, utilisateur_id, date_vente,
                            montant_total, montant_ttc, statut_vente, created_at
                        ) VALUES (
                            v_numero_ticket, p_client_id, p_utilisateur_id, NOW(),
                            v_prix_unitaire * p_quantite, v_prix_unitaire * p_quantite * 1.18, 'VALIDEE', NOW()
                        );
                        
                        SET p_vente_id = LAST_INSERT_ID();
                        
                        -- Insérer l'article de vente
                        INSERT INTO vente_articles (
                            vente_id, produit_id, quantite, prix_unitaire, montant_total, created_at
                        ) VALUES (
                            p_vente_id, p_produit_id, p_quantite, v_prix_unitaire, v_prix_unitaire * p_quantite, NOW()
                        );
                        
                        -- Mettre à jour le stock
                        UPDATE stock 
                        SET quantite_actuelle = quantite_actuelle - p_quantite
                        WHERE produit_id = p_produit_id;
                        
                        SET p_statut = 'SUCCESS';
                    END IF;
                END
            ",
            
            'sp_mise_a_jour_stock' => "
                CREATE PROCEDURE sp_mise_a_jour_stock(
                    IN p_produit_id INT,
                    IN p_quantite INT,
                    IN p_type_mouvement VARCHAR(20),
                    IN p_utilisateur_id INT,
                    OUT p_statut VARCHAR(20)
                )
                BEGIN
                    DECLARE v_stock_actuel INT DEFAULT 0;
                    
                    -- Récupérer le stock actuel
                    SELECT COALESCE(quantite_actuelle, 0) INTO v_stock_actuel
                    FROM stock
                    WHERE produit_id = p_produit_id;
                    
                    IF p_type_mouvement = 'ENTREE' THEN
                        -- Ajouter au stock
                        UPDATE stock 
                        SET quantite_actuelle = v_stock_actuel + p_quantite,
                            updated_at = NOW()
                        WHERE produit_id = p_produit_id;
                        SET p_statut = 'SUCCESS';
                    ELSEIF p_type_mouvement = 'SORTIE' THEN
                        -- Vérifier si le stock est suffisant
                        IF v_stock_actuel >= p_quantite THEN
                            UPDATE stock 
                            SET quantite_actuelle = v_stock_actuel - p_quantite,
                                updated_at = NOW()
                            WHERE produit_id = p_produit_id;
                            SET p_statut = 'SUCCESS';
                        ELSE
                            SET p_statut = 'STOCK_INSUFFISANT';
                        END IF;
                    ELSE
                        SET p_statut = 'TYPE_INVALIDE';
                    END IF;
                END
            "
        ];

        foreach ($procedures as $procName => $createProcedure) {
            try {
                // Supprimer la procédure si elle existe
                $this->db->exec("DROP PROCEDURE IF EXISTS $procName");
                
                $stmt = $this->db->prepare($createProcedure);
                $stmt->execute();
                $optimizations['procedures_created'][] = $procName;
            } catch (PDOException $e) {
                $optimizations['procedures_errors'][] = [
                    'procedure' => $procName,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $optimizations;
    }

    /**
     * Optimise l'indexation des tables
     */
    private function optimizeTableIndexing(): array
    {
        $optimizations = [];

        // Index pour les tables critiques
        $indexes = [
            'ventes' => [
                'idx_ventes_date_statut' => 'date_vente, statut_vente',
                'idx_ventes_client' => 'client_id',
                'idx_ventes_utilisateur' => 'utilisateur_id',
                'idx_ventes_numero' => 'numero_ticket'
            ],
            'vente_articles' => [
                'idx_vente_articles_vente' => 'vente_id',
                'idx_vente_articles_produit' => 'produit_id',
                'idx_vente_articles_quantite' => 'quantite'
            ],
            'stock' => [
                'idx_stock_produit' => 'produit_id',
                'idx_stock_quantite' => 'quantite_actuelle',
                'idx_stock_minimum' => 'stock_minimum'
            ],
            'produits' => [
                'idx_produits_nom' => 'nom',
                'idx_produits_reference' => 'reference',
                'idx_produits_actif' => 'is_actif',
                'idx_produits_categorie' => 'categorie_id'
            ],
            'mouvements_stock' => [
                'idx_mouvements_stock_produit' => 'produit_id',
                'idx_mouvements_stock_date' => 'date_mouvement',
                'idx_mouvements_stock_type' => 'type_mouvement'
            ],
            'audit_logs' => [
                'idx_audit_logs_user' => 'user_id',
                'idx_audit_logs_date' => 'created_at',
                'idx_audit_logs_action' => 'action',
                'idx_audit_logs_module' => 'module'
            ],
            'caisse_sessions' => [
                'idx_caisse_sessions_date' => 'date_ouverture, date_fermeture',
                'idx_caisse_sessions_statut' => 'statut_session',
                'idx_caisse_sessions_utilisateur' => 'utilisateur_id'
            ]
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexName => $columns) {
                try {
                    // Vérifier si l'index existe
                    $sql = "SHOW INDEX FROM $table WHERE Key_name = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$indexName]);
                    
                    if ($stmt->rowCount() === 0) {
                        // Créer l'index
                        $sql = "CREATE INDEX $indexName ON $table ($columns)";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute();
                        
                        $optimizations['indexes_created'][] = "$table.$indexName";
                    }
                } catch (PDOException $e) {
                    $optimizations['indexes_errors'][] = [
                        'table' => $table,
                        'index' => $indexName,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $optimizations;
    }

    /**
     * Implémente le cache des données fréquentes
     */
    private function implementDataCaching(): array
    {
        $optimizations = [];

        // Configuration du cache MySQL
        $cacheSettings = [
            'query_cache_size' => 268435456, // 256MB
            'query_cache_type' => 'ON',
            'query_cache_limit' => 1048576, // 1MB
            'innodb_buffer_pool_size' => 1073741824 // 1GB
        ];

        foreach ($cacheSettings as $setting => $value) {
            try {
                $sql = "SET GLOBAL $setting = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $optimizations['cache_settings'][$setting] = 'SUCCESS';
            } catch (PDOException $e) {
                $optimizations['cache_settings'][$setting] = 'ERROR: ' . $e->getMessage();
            }
        }

        // Créer une table de cache pour les données fréquemment accédées
        $cacheTable = "
            CREATE TABLE IF NOT EXISTS cache_system (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_data LONGTEXT,
                cache_expires_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cache_expires (cache_expires_at)
            ) ENGINE=InnoDB
        ";

        try {
            $this->db->exec($cacheTable);
            $optimizations['cache_table'] = 'SUCCESS';
        } catch (PDOException $e) {
            $optimizations['cache_table'] = 'ERROR: ' . $e->getMessage();
        }

        // Précharger les données fréquemment accédées
        $preloadQueries = [
            'produits_actifs' => "SELECT * FROM produits WHERE is_actif = 1",
            'categories' => "SELECT * FROM categories WHERE is_actif = 1",
            'utilisateurs_actifs' => "SELECT id, username, nom, role_id FROM utilisateurs WHERE is_actif = 1",
            'fournisseurs_actifs' => "SELECT * FROM fournisseurs WHERE is_actif = 1"
        ];

        foreach ($preloadQueries as $cacheKey => $query) {
            try {
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $sql = "INSERT INTO cache_system (cache_key, cache_data, cache_expires_at) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        cache_data = VALUES(cache_data), cache_expires_at = VALUES(cache_expires_at)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$cacheKey, json_encode($data), $expiresAt]);
                
                $optimizations['preloaded_data'][] = $cacheKey;
            } catch (PDOException $e) {
                $optimizations['preload_errors'][] = [
                    'key' => $cacheKey,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $optimizations;
    }

    /**
     * Réduit les appels inutiles
     */
    private function reduceUnnecessaryCalls(): array
    {
        $optimizations = [];

        // Créer des fonctions pour agréger les données
        $functions = [
            'fn_ca_journalier' => "
                CREATE FUNCTION fn_ca_journalier(p_date DATE) RETURNS DECIMAL(10,2)
                READS SQL DATA
                DETERMINISTIC
                BEGIN
                    DECLARE v_ca DECIMAL(10,2);
                    
                    SELECT COALESCE(SUM(montant_ttc), 0) INTO v_ca
                    FROM ventes 
                    WHERE DATE(date_vente) = p_date 
                    AND statut_vente = 'VALIDEE';
                    
                    RETURN v_ca;
                END
            ",
            
            'fn_stock_actuel' => "
                CREATE FUNCTION fn_stock_actuel(p_produit_id INT) RETURNS INT
                READS SQL DATA
                DETERMINISTIC
                BEGIN
                    DECLARE v_stock INT;
                    
                    SELECT COALESCE(quantite_actuelle, 0) INTO v_stock
                    FROM stock 
                    WHERE produit_id = p_produit_id;
                    
                    RETURN v_stock;
                END
            ",
            
            'fn_total_ventes_mois' => "
                CREATE FUNCTION fn_total_ventes_mois(p_annee INT, p_mois INT) RETURNS INT
                READS SQL DATA
                DETERMINISTIC
                BEGIN
                    DECLARE v_total INT;
                    
                    SELECT COUNT(*) INTO v_total
                    FROM ventes 
                    WHERE YEAR(date_vente) = p_annee 
                    AND MONTH(date_vente) = p_mois 
                    AND statut_vente = 'VALIDEE';
                    
                    RETURN v_total;
                END
            "
        ];

        foreach ($functions as $funcName => $createFunction) {
            try {
                // Supprimer la fonction si elle existe
                $this->db->exec("DROP FUNCTION IF EXISTS $funcName");
                
                $stmt = $this->db->prepare($createFunction);
                $stmt->execute();
                $optimizations['functions_created'][] = $funcName;
            } catch (PDOException $e) {
                $optimizations['functions_errors'][] = [
                    'function' => $funcName,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Créer des triggers pour maintenir les données calculées
        $triggers = [
            'tr_vente_after_insert' => "
                CREATE TRIGGER tr_vente_after_insert
                AFTER INSERT ON ventes
                FOR EACH ROW
                BEGIN
                    -- Mettre à jour les statistiques de vente
                    INSERT INTO vente_stats (date_vente, total_ventes, montant_total, created_at)
                    VALUES (DATE(NEW.date_vente), 1, NEW.montant_ttc, NOW())
                    ON DUPLICATE KEY UPDATE 
                    total_ventes = total_ventes + 1,
                    montant_total = montant_total + NEW.montant_ttc;
                END
            ",
            
            'tr_mouvement_stock_after_insert' => "
                CREATE TRIGGER tr_mouvement_stock_after_insert
                AFTER INSERT ON mouvements_stock
                FOR EACH ROW
                BEGIN
                    -- Mettre à jour les statistiques de stock
                    INSERT INTO stock_stats (date_mouvement, type_mouvement, quantite, created_at)
                    VALUES (DATE(NEW.date_mouvement), NEW.type_mouvement, NEW.quantite, NOW())
                    ON DUPLICATE KEY UPDATE 
                    quantite = quantite + NEW.quantite;
                END
            "
        ];

        foreach ($triggers as $triggerName => $createTrigger) {
            try {
                // Supprimer le trigger s'il existe
                $this->db->exec("DROP TRIGGER IF EXISTS $triggerName");
                
                $stmt = $this->db->prepare($createTrigger);
                $stmt->execute();
                $optimizations['triggers_created'][] = $triggerName;
            } catch (PDOException $e) {
                $optimizations['triggers_errors'][] = [
                    'trigger' => $triggerName,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $optimizations;
    }

    /**
     * Optimise la configuration du système
     */
    private function optimizeConfiguration(): array
    {
        $optimizations = [];

        // Configuration MySQL optimisée
        $mysqlSettings = [
            'innodb_flush_log_at_trx_commit' => 2,
            'innodb_log_buffer_size' => 16777216, // 16MB
            'innodb_log_file_size' => 536870912, // 512MB
            'innodb_flush_method' => 'O_DIRECT',
            'innodb_file_per_table' => 1,
            'innodb_buffer_pool_instances' => 1,
            'max_connections' => 200,
            'table_open_cache' => 4000,
            'table_definition_cache' => 2000
        ];

        foreach ($mysqlSettings as $setting => $value) {
            try {
                $sql = "SET GLOBAL $setting = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $optimizations['mysql_settings'][$setting] = 'SUCCESS';
            } catch (PDOException $e) {
                $optimizations['mysql_settings'][$setting] = 'ERROR: ' . $e->getMessage();
            }
        }

        // Configuration PHP optimisée
        $phpSettings = [
            'memory_limit' => '512M',
            'max_execution_time' => 300,
            'max_input_time' => 300,
            'post_max_size' => '100M',
            'upload_max_filesize' => '50M',
            'max_input_vars' => 3000
        ];

        foreach ($phpSettings as $setting => $value) {
            if (ini_set($setting, $value)) {
                $optimizations['php_settings'][$setting] = 'SUCCESS';
            } else {
                $optimizations['php_settings'][$setting] = 'FAILED';
            }
        }

        return $optimizations;
    }

    /**
     * Génère un résumé des optimisations
     */
    private function generateOptimizationSummary(array $optimizations): array
    {
        $summary = [
            'total_optimizations' => 0,
            'successful_optimizations' => 0,
            'failed_optimizations' => 0,
            'categories' => []
        ];

        foreach ($optimizations as $category => $data) {
            $categorySummary = [
                'category' => $category,
                'total' => 0,
                'success' => 0,
                'failed' => 0
            ];

            foreach ($data as $key => $result) {
                if (is_array($result) && isset($result[0])) {
                    // Tableau de résultats
                    $categorySummary['total'] += count($result);
                } elseif (is_string($result) && $result === 'SUCCESS') {
                    $categorySummary['success']++;
                    $categorySummary['total']++;
                } elseif (strpos($result, 'ERROR') === 0) {
                    $categorySummary['failed']++;
                    $categorySummary['total']++;
                } elseif (is_array($result)) {
                    // Tableau associatif avec clés success/failed
                    foreach ($result as $subResult) {
                        if ($subResult === 'SUCCESS') {
                            $categorySummary['success']++;
                        } elseif (is_string($subResult) && strpos($subResult, 'ERROR') === 0) {
                            $categorySummary['failed']++;
                        }
                        $categorySummary['total']++;
                    }
                }
            }

            $summary['categories'][] = $categorySummary;
            $summary['total_optimizations'] += $categorySummary['total'];
            $summary['successful_optimizations'] += $categorySummary['success'];
            $summary['failed_optimizations'] += $categorySummary['failed'];
        }

        $summary['success_rate'] = $summary['total_optimizations'] > 0 ? 
            round(($summary['successful_optimizations'] / $summary['total_optimizations']) * 100, 2) : 0;

        $summary['overall_status'] = $summary['success_rate'] >= 80 ? 'SUCCESS' : 
                                     ($summary['success_rate'] >= 60 ? 'PARTIAL' : 'FAILED');

        return $summary;
    }

    /**
     * Mesure l'amélioration de performance
     */
    public function measurePerformanceImprovement(): array
    {
        $before = $this->benchmarkQueries('before');
        $after = $this->benchmarkQueries('after');

        return [
            'before' => $before,
            'after' => $after,
            'improvement' => $this->calculateImprovement($before, $after)
        ];
    }

    /**
     * Benchmark des requêtes
     */
    private function benchmarkQueries(string $phase): array
    {
        $queries = [
            'ventes_recentes' => "SELECT * FROM vue_ventes_optimisee ORDER BY date_vente DESC LIMIT 50",
            'stock_critique' => "SELECT * FROM vue_stock_critique WHERE niveau_stock != 'NORMAL'",
            'mouvements_semaine' => "SELECT * FROM vue_mouvements_rapides ORDER BY date_mouvement DESC"
        ];

        $results = [];

        foreach ($queries as $queryName => $query) {
            $startTime = microtime(true);
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $endTime = microtime(true);
            
            $results[$queryName] = [
                'execution_time' => round(($endTime - $startTime) * 1000, 2),
                'rows_returned' => $stmt->rowCount()
            ];
        }

        return $results;
    }

    /**
     * Calcule l'amélioration de performance
     */
    private function calculateImprovement(array $before, array $after): array
    {
        $improvement = [];

        foreach ($before as $queryName => $beforeMetrics) {
            if (isset($after[$queryName])) {
                $afterMetrics = $after[$queryName];
                
                $timeImprovement = $beforeMetrics['execution_time'] > 0 ? 
                    round((($beforeMetrics['execution_time'] - $afterMetrics['execution_time']) / $beforeMetrics['execution_time']) * 100, 2) : 0;
                
                $improvement[$queryName] = [
                    'time_before' => $beforeMetrics['execution_time'],
                    'time_after' => $afterMetrics['execution_time'],
                    'time_improvement_percent' => $timeImprovement,
                    'status' => $timeImprovement > 0 ? 'IMPROVED' : ($timeImprovement < -10 ? 'DEGRADED' : 'STABLE')
                ];
            }
        }

        $avgImprovement = count($improvement) > 0 ? 
            array_sum(array_column($improvement, 'time_improvement_percent')) / count($improvement) : 0;

        return [
            'queries' => $improvement,
            'average_improvement' => round($avgImprovement, 2),
            'overall_status' => $avgImprovement > 10 ? 'SIGNIFICANT' : 
                               ($avgImprovement > 0 ? 'MODERATE' : 'MINIMAL')
        ];
    }
}
