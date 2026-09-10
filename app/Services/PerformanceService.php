<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class PerformanceService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Optimise les requêtes SQL critiques
     */
    public function optimizeCriticalQueries(): array
    {
        $optimizations = [];
        
        try {
            // Vérifier et créer les index manquants
            $optimizations['indexes'] = $this->checkAndCreateIndexes();
            
            // Optimiser les requêtes de caisse
            $optimizations['caisse'] = $this->optimizeCaisseQueries();
            
            // Optimiser les requêtes de stock
            $optimizations['stock'] = $this->optimizeStockQueries();
            
            // Optimiser les requêtes de ventes
            $optimizations['ventes'] = $this->optimizeVentesQueries();
            
            // Optimiser les requêtes d'audit
            $optimizations['audit'] = $this->optimizeAuditQueries();
            
            return [
                'success' => true,
                'message' => 'Optimisation des requêtes terminée',
                'optimizations' => $optimizations,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'optimisation',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie et crée les index nécessaires
     */
    private function checkAndCreateIndexes(): array
    {
        $indexes = [];
        
        // Index pour les ventes
        $indexes[] = [
            'table' => 'ventes',
            'name' => 'idx_ventes_date_statut',
            'columns' => 'date_vente, statut_vente'
        ];
        
        $indexes[] = [
            'table' => 'ventes',
            'name' => 'idx_ventes_client',
            'columns' => 'client_id'
        ];
        
        $indexes[] = [
            'table' => 'ventes',
            'name' => 'idx_ventes_numero',
            'columns' => 'numero_ticket'
        ];
        
        // Index pour les articles de vente
        $indexes[] = [
            'table' => 'vente_articles',
            'name' => 'idx_vente_articles_vente',
            'columns' => 'vente_id'
        ];
        
        $indexes[] = [
            'table' => 'vente_articles',
            'name' => 'idx_vente_articles_produit',
            'columns' => 'produit_id'
        ];
        
        // Index pour le stock
        $indexes[] = [
            'table' => 'stock',
            'name' => 'idx_stock_produit',
            'columns' => 'produit_id'
        ];
        
        $indexes[] = [
            'table' => 'stock',
            'name' => 'idx_stock_quantite',
            'columns' => 'quantite_actuelle'
        ];
        
        // Index pour les lots
        $indexes[] = [
            'table' => 'lots',
            'name' => 'idx_lots_produit',
            'columns' => 'produit_id'
        ];
        
        $indexes[] = [
            'table' => 'lots',
            'name' => 'idx_lots_peremption',
            'columns' => 'date_peremption'
        ];
        
        // Index pour les mouvements de stock
        $indexes[] = [
            'table' => 'mouvements_stock',
            'name' => 'idx_mouvements_stock_produit',
            'columns' => 'produit_id'
        ];
        
        $indexes[] = [
            'table' => 'mouvements_stock',
            'name' => 'idx_mouvements_stock_date',
            'columns' => 'date_mouvement'
        ];
        
        // Index pour les clients
        $indexes[] = [
            'table' => 'clients',
            'name' => 'idx_clients_nom',
            'columns' => 'nom'
        ];
        
        $indexes[] = [
            'table' => 'clients',
            'name' => 'idx_clients_telephone',
            'columns' => 'telephone'
        ];
        
        // Index pour les produits
        $indexes[] = [
            'table' => 'produits',
            'name' => 'idx_produits_nom',
            'columns' => 'nom'
        ];
        
        $indexes[] = [
            'table' => 'produits',
            'name' => 'idx_produits_code',
            'columns' => 'code_barre'
        ];
        
        $indexes[] = [
            'table' => 'produits',
            'name' => 'idx_produits_reference',
            'columns' => 'reference'
        ];
        
        // Index pour les sessions de caisse
        $indexes[] = [
            'table' => 'caisse_sessions',
            'name' => 'idx_caisse_sessions_date',
            'columns' => 'date_ouverture, date_fermeture'
        ];
        
        $indexes[] = [
            'table' => 'caisse_sessions',
            'name' => 'idx_caisse_sessions_statut',
            'columns' => 'statut_session'
        ];
        
        // Index pour les mouvements de caisse
        $indexes[] = [
            'table' => 'mouvements_caisse',
            'name' => 'idx_mouvements_caisse_session',
            'columns' => 'caisse_session_id'
        ];
        
        $indexes[] = [
            'table' => 'mouvements_caisse',
            'name' => 'idx_mouvements_caisse_date',
            'columns' => 'date_mouvement'
        ];
        
        // Index pour les logs d'audit
        $indexes[] = [
            'table' => 'audit_logs',
            'name' => 'idx_audit_logs_user',
            'columns' => 'user_id'
        ];
        
        $indexes[] = [
            'table' => 'audit_logs',
            'name' => 'idx_audit_logs_date',
            'columns' => 'created_at'
        ];
        
        $indexes[] = [
            'table' => 'audit_logs',
            'name' => 'idx_audit_logs_module',
            'columns' => 'module'
        ];
        
        $indexes[] = [
            'table' => 'audit_logs',
            'name' => 'idx_audit_logs_action',
            'columns' => 'action'
        ];
        
        $createdIndexes = [];
        
        foreach ($indexes as $index) {
            try {
                // Vérifier si l'index existe déjà
                $sql = "SHOW INDEX FROM {$index['table']} WHERE Key_name = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$index['name']]);
                
                if ($stmt->rowCount() === 0) {
                    // Créer l'index
                    $sql = "CREATE INDEX {$index['name']} ON {$index['table']} ({$index['columns']})";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute();
                    
                    $createdIndexes[] = $index['name'];
                }
            } catch (PDOException $e) {
                // Ignorer les erreurs d'index déjà existants
                continue;
            }
        }
        
        return [
            'total_checked' => count($indexes),
            'created' => $createdIndexes,
            'created_count' => count($createdIndexes)
        ];
    }

    /**
     * Optimise les requêtes de caisse
     */
    private function optimizeCaisseQueries(): array
    {
        $optimizations = [];
        
        // Optimiser la recherche de produits pour la caisse
        $sql = "
            CREATE OR REPLACE VIEW vue_produits_caisse AS
            SELECT 
                p.id,
                p.nom,
                p.reference,
                p.code_barre,
                p.prix_vente,
                p.prix_achat,
                COALESCE(s.quantite_actuelle, 0) as stock_disponible,
                COALESCE(l.quantite, 0) as lot_disponible,
                l.date_peremption,
                p.is_actif,
                p.categorie_id
            FROM produits p
            LEFT JOIN stock s ON p.id = s.produit_id AND s.quantite_actuelle > 0
            LEFT JOIN lots l ON p.id = l.produit_id AND l.quantite > 0 AND l.is_actif = 1
            WHERE p.is_actif = 1
            GROUP BY p.id
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $optimizations['vue_produits_caisse'] = 'Vue créée avec succès';
        } catch (PDOException $e) {
            $optimizations['vue_produits_caisse'] = 'Erreur: ' . $e->getMessage();
        }
        
        // Optimiser la requête de vente rapide
        $sql = "
            CREATE OR REPLACE PROCEDURE sp_vente_rapide(
                IN p_produit_id INT,
                IN p_quantite INT,
                IN p_client_id INT,
                IN p_utilisateur_id INT,
                OUT p_vente_id INT,
                OUT p_message VARCHAR(255)
            )
            BEGIN
                DECLARE v_stock_disponible INT DEFAULT 0;
                DECLARE v_prix_unitaire DECIMAL(10,2) DEFAULT 0;
                DECLARE v_numero_ticket VARCHAR(50);
                
                -- Vérifier le stock
                SELECT COALESCE(s.quantite_actuelle, 0) INTO v_stock_disponible
                FROM stock s
                WHERE s.produit_id = p_produit_id;
                
                IF v_stock_disponible < p_quantite THEN
                    SET p_message = 'Stock insuffisant';
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
                        numero_ticket,
                        client_id,
                        utilisateur_id,
                        date_vente,
                        montant_total,
                        montant_ttc,
                        statut_vente,
                        created_at
                    ) VALUES (
                        v_numero_ticket,
                        p_client_id,
                        p_utilisateur_id,
                        NOW(),
                        v_prix_unitaire * p_quantite,
                        v_prix_unitaire * p_quantite * 1.18,
                        'VALIDEE',
                        NOW()
                    );
                    
                    SET p_vente_id = LAST_INSERT_ID();
                    
                    -- Insérer l'article de vente
                    INSERT INTO vente_articles (
                        vente_id,
                        produit_id,
                        quantite,
                        prix_unitaire,
                        montant_total,
                        created_at
                    ) VALUES (
                        p_vente_id,
                        p_produit_id,
                        p_quantite,
                        v_prix_unitaire,
                        v_prix_unitaire * p_quantite,
                        NOW()
                    );
                    
                    -- Mettre à jour le stock
                    UPDATE stock 
                    SET quantite_actuelle = quantite_actuelle - p_quantite
                    WHERE produit_id = p_produit_id;
                    
                    SET p_message = 'Vente effectuée avec succès';
                END IF;
            END
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $optimizations['sp_vente_rapide'] = 'Procédure créée avec succès';
        } catch (PDOException $e) {
            $optimizations['sp_vente_rapide'] = 'Erreur: ' . $e->getMessage();
        }
        
        return $optimizations;
    }

    /**
     * Optimise les requêtes de stock
     */
    private function optimizeStockQueries(): array
    {
        $optimizations = [];
        
        // Vue pour l'état du stock
        $sql = "
            CREATE OR REPLACE VIEW vue_etat_stock AS
            SELECT 
                p.id as produit_id,
                p.nom as produit_nom,
                p.reference,
                p.categorie_id,
                COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                COALESCE(s.stock_minimum, 0) as stock_minimum,
                COALESCE(s.stock_alerte, 0) as stock_alerte,
                CASE 
                    WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_minimum, 0) THEN 'CRITIQUE'
                    WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_alerte, 0) THEN 'ALERTE'
                    ELSE 'NORMAL'
                END as niveau_stock,
                p.prix_vente,
                (COALESCE(s.quantite_actuelle, 0) * p.prix_vente) as valeur_stock,
                p.is_actif
            FROM produits p
            LEFT JOIN stock s ON p.id = s.produit_id
            WHERE p.is_actif = 1
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $optimizations['vue_etat_stock'] = 'Vue créée avec succès';
        } catch (PDOException $e) {
            $optimizations['vue_etat_stock'] = 'Erreur: ' . $e->getMessage();
        }
        
        return $optimizations;
    }

    /**
     * Optimise les requêtes de ventes
     */
    private function optimizeVentesQueries(): array
    {
        $optimizations = [];
        
        // Vue pour les statistiques de ventes
        $sql = "
            CREATE OR REPLACE VIEW vue_statistiques_ventes AS
            SELECT 
                DATE(v.date_vente) as date_vente,
                COUNT(v.id) as nombre_ventes,
                SUM(v.montant_total) as montant_total,
                SUM(v.montant_ttc) as montant_ttc,
                COUNT(DISTINCT v.client_id) as nombre_clients,
                AVG(v.montant_total) as panier_moyen
            FROM ventes v
            WHERE v.statut_vente = 'VALIDEE'
            GROUP BY DATE(v.date_vente)
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $optimizations['vue_statistiques_ventes'] = 'Vue créée avec succès';
        } catch (PDOException $e) {
            $optimizations['vue_statistiques_ventes'] = 'Erreur: ' . $e->getMessage();
        }
        
        return $optimizations;
    }

    /**
     * Optimise les requêtes d'audit
     */
    private function optimizeAuditQueries(): array
    {
        $optimizations = [];
        
        // Vue pour les activités récentes
        $sql = "
            CREATE OR REPLACE VIEW vue_activites_recentes AS
            SELECT 
                al.id,
                al.action,
                al.module,
                al.description,
                al.created_at,
                u.username,
                u.nom as user_nom
            FROM audit_logs al
            LEFT JOIN utilisateurs u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $optimizations['vue_activites_recentes'] = 'Vue créée avec succès';
        } catch (PDOException $e) {
            $optimizations['vue_activites_recentes'] = 'Erreur: ' . $e->getMessage();
        }
        
        return $optimizations;
    }

    /**
     * Mesure les performances des requêtes
     */
    public function measureQueryPerformance(): array
    {
        $queries = [];
        
        // Test de performance pour la recherche de produits
        $start = microtime(true);
        $sql = "SELECT * FROM vue_produits_caisse LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $end = microtime(true);
        
        $queries['recherche_produits'] = [
            'query' => $sql,
            'execution_time' => ($end - $start) * 1000, // en millisecondes
            'rows' => $stmt->rowCount(),
            'status' => ($end - $start) * 1000 < 200 ? 'OK' : 'SLOW'
        ];
        
        // Test de performance pour l'état du stock
        $start = microtime(true);
        $sql = "SELECT * FROM vue_etat_stock WHERE niveau_stock = 'ALERTE' LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $end = microtime(true);
        
        $queries['etat_stock'] = [
            'query' => $sql,
            'execution_time' => ($end - $start) * 1000,
            'rows' => $stmt->rowCount(),
            'status' => ($end - $start) * 1000 < 200 ? 'OK' : 'SLOW'
        ];
        
        // Test de performance pour les statistiques de ventes
        $start = microtime(true);
        $sql = "SELECT * FROM vue_statistiques_ventes WHERE date_vente >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $end = microtime(true);
        
        $queries['statistiques_ventes'] = [
            'query' => $sql,
            'execution_time' => ($end - $start) * 1000,
            'rows' => $stmt->rowCount(),
            'status' => ($end - $start) * 1000 < 200 ? 'OK' : 'SLOW'
        ];
        
        // Test de performance pour les activités récentes
        $start = microtime(true);
        $sql = "SELECT * FROM vue_activites_recentes LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $end = microtime(true);
        
        $queries['activites_recentes'] = [
            'query' => $sql,
            'execution_time' => ($end - $start) * 1000,
            'rows' => $stmt->rowCount(),
            'status' => ($end - $start) * 1000 < 200 ? 'OK' : 'SLOW'
        ];
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'queries' => $queries,
            'summary' => [
                'total_queries' => count($queries),
                'slow_queries' => count(array_filter($queries, fn($q) => $q['status'] === 'SLOW')),
                'avg_execution_time' => array_sum(array_column($queries, 'execution_time')) / count($queries)
            ]
        ];
    }

    /**
     * Configure le cache pour les requêtes fréquentes
     */
    public function configureCache(): array
    {
        $configurations = [];
        
        // Activer le cache de requêtes MySQL
        $sql = "SET GLOBAL query_cache_size = 268435456"; // 256MB
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $configurations['query_cache_size'] = '256MB configuré';
        } catch (PDOException $e) {
            $configurations['query_cache_size'] = 'Erreur: ' . $e->getMessage();
        }
        
        $sql = "SET GLOBAL query_cache_type = ON";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $configurations['query_cache_type'] = 'Activé';
        } catch (PDOException $e) {
            $configurations['query_cache_type'] = 'Erreur: ' . $e->getMessage();
        }
        
        $sql = "SET GLOBAL query_cache_limit = 1048576"; // 1MB par résultat
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $configurations['query_cache_limit'] = '1MB configuré';
        } catch (PDOException $e) {
            $configurations['query_cache_limit'] = 'Erreur: ' . $e->getMessage();
        }
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'configurations' => $configurations
        ];
    }

    /**
     * Analyse les performances de la base de données
     */
    public function analyzeDatabasePerformance(): array
    {
        $analysis = [];
        
        // Taille des tables
        $sql = "
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS table_size_mb,
                table_rows,
                ROUND((data_length / 1024 / 1024), 2) AS data_size_mb,
                ROUND((index_length / 1024 / 1024), 2) AS index_size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $analysis['table_sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $analysis['table_sizes'] = 'Erreur: ' . $e->getMessage();
        }
        
        // Requêtes lentes
        $sql = "SHOW STATUS LIKE 'Slow_queries'";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $slowQueries = $stmt->fetch(PDO::FETCH_ASSOC);
            $analysis['slow_queries'] = $slowQueries['Value'];
        } catch (PDOException $e) {
            $analysis['slow_queries'] = 'Erreur: ' . $e->getMessage();
        }
        
        // Cache hit ratio
        $sql = "SHOW STATUS LIKE 'Qcache_hits'";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cacheHits = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sql = "SHOW STATUS LIKE 'Qcache_inserts'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cacheInserts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total = $cacheHits['Value'] + $cacheInserts['Value'];
            $hitRatio = $total > 0 ? ($cacheHits['Value'] / $total) * 100 : 0;
            
            $analysis['cache_hit_ratio'] = [
                'hits' => $cacheHits['Value'],
                'inserts' => $cacheInserts['Value'],
                'ratio' => round($hitRatio, 2)
            ];
        } catch (PDOException $e) {
            $analysis['cache_hit_ratio'] = 'Erreur: ' . $e->getMessage();
        }
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'analysis' => $analysis
        ];
    }

    /**
     * Génère un rapport de performance
     */
    public function generatePerformanceReport(): array
    {
        $queryPerformance = $this->measureQueryPerformance();
        $dbAnalysis = $this->analyzeDatabasePerformance();
        $cacheConfig = $this->configureCache();
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'query_performance' => $queryPerformance,
            'database_analysis' => $dbAnalysis,
            'cache_configuration' => $cacheConfig,
            'recommendations' => $this->generatePerformanceRecommendations($queryPerformance, $dbAnalysis)
        ];
    }

    /**
     * Génère des recommandations de performance
     */
    private function generatePerformanceRecommendations(array $queryPerformance, array $dbAnalysis): array
    {
        $recommendations = [];
        
        // Recommandations basées sur les requêtes lentes
        $slowQueries = array_filter($queryPerformance['queries'], fn($q) => $q['status'] === 'SLOW');
        if (!empty($slowQueries)) {
            $recommendations[] = [
                'type' => 'QUERIES',
                'priority' => 'HIGH',
                'message' => 'Requêtes lentes détectées',
                'actions' => [
                    'Optimiser les index sur les tables concernées',
                    'Réécrire les requêtes complexes',
                    'Utiliser des vues matérialisées',
                    'Implémenter le cache applicatif'
                ],
                'slow_queries' => array_keys($slowQueries)
            ];
        }
        
        // Recommandations basées sur la taille des tables
        if (isset($dbAnalysis['table_sizes']) && is_array($dbAnalysis['table_sizes'])) {
            $largeTables = array_filter($dbAnalysis['table_sizes'], fn($t) => $t['table_size_mb'] > 100);
            if (!empty($largeTables)) {
                $recommendations[] = [
                    'type' => 'TABLE_SIZE',
                    'priority' => 'MEDIUM',
                    'message' => 'Tables volumineuses détectées',
                    'actions' => [
                        'Archiver les anciennes données',
                        'Optimiser les index',
                        'Considérer la partitionnement',
                        'Nettoyer les données inutiles'
                    ],
                    'large_tables' => array_column($largeTables, 'table_name')
                ];
            }
        }
        
        // Recommandations basées sur le cache
        if (isset($dbAnalysis['cache_hit_ratio']['ratio']) && $dbAnalysis['cache_hit_ratio']['ratio'] < 80) {
            $recommendations[] = [
                'type' => 'CACHE',
                'priority' => 'MEDIUM',
                'message' => 'Ratio de cache faible',
                'actions' => [
                    'Augmenter la taille du cache',
                    'Optimiser les requêtes pour le cache',
                    'Implémenter le cache applicatif',
                    'Analyser les patterns d\'accès'
                ],
                'cache_hit_ratio' => $dbAnalysis['cache_hit_ratio']['ratio']
            ];
        }
        
        return $recommendations;
    }
}
