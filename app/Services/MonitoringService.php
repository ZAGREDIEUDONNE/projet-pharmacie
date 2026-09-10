<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class MonitoringService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les métriques de monitoring système
     */
    public function getSystemMetrics(): array
    {
        $metrics = [];

        try {
            // Temps de réponse API
            $metrics['api_response_time'] = $this->getAPIResponseTime();
            
            // Requêtes lentes SQL
            $metrics['slow_queries'] = $this->getSlowQueries();
            
            // Logs d'erreurs système
            $metrics['system_errors'] = $this->getSystemErrors();
            
            // Charge des opérations critiques
            $metrics['critical_operations'] = $this->getCriticalOperations();
            
            // Utilisation des ressources
            $metrics['resource_usage'] = $this->getResourceUsage();
            
            // Performance de la base de données
            $metrics['database_performance'] = $this->getDatabasePerformance();

            return [
                'success' => true,
                'data' => $metrics,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des métriques système',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mesure le temps de réponse de l'API
     */
    private function getAPIResponseTime(): array
    {
        $endpoints = [
            '/api/dashboard/kpis',
            '/api/ventes/recentes',
            '/api/stock/etat',
            '/api/caisse/session',
            '/api/clients/liste'
        ];

        $responseTimes = [];

        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            
            // Simuler une requête API (mesure du temps de réponse)
            $this->simulateAPIRequest($endpoint);
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000; // en millisecondes
            
            $responseTimes[] = [
                'endpoint' => $endpoint,
                'response_time' => round($responseTime, 2),
                'status' => $responseTime < 200 ? 'OK' : ($responseTime < 500 ? 'SLOW' : 'CRITICAL')
            ];
        }

        $avgResponseTime = array_sum(array_column($responseTimes, 'response_time')) / count($responseTimes);

        return [
            'endpoints' => $responseTimes,
            'average_response_time' => round($avgResponseTime, 2),
            'slow_endpoints' => array_filter($responseTimes, fn($r) => $r['response_time'] > 200),
            'critical_endpoints' => array_filter($responseTimes, fn($r) => $r['response_time'] > 500)
        ];
    }

    /**
     * Simule une requête API pour mesurer le temps de réponse
     */
    private function simulateAPIRequest(string $endpoint): void
    {
        // Simuler différentes requêtes selon l'endpoint
        switch ($endpoint) {
            case '/api/dashboard/kpis':
                $sql = "SELECT COUNT(*) as total_ventes FROM ventes WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case '/api/ventes/recentes':
                $sql = "SELECT * FROM ventes ORDER BY date_vente DESC LIMIT 10";
                break;
            case '/api/stock/etat':
                $sql = "SELECT p.*, s.quantite_actuelle FROM produits p LEFT JOIN stock s ON p.id = s.produit_id LIMIT 50";
                break;
            case '/api/caisse/session':
                $sql = "SELECT * FROM caisse_sessions WHERE statut_session = 'OUVERTE'";
                break;
            case '/api/clients/liste':
                $sql = "SELECT * FROM clients WHERE is_actif = 1 LIMIT 20";
                break;
            default:
                $sql = "SELECT 1";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }

    /**
     * Récupère les requêtes lentes SQL
     */
    private function getSlowQueries(): array
    {
        $queries = [];

        // Requêtes de ventes
        $startTime = microtime(true);
        $sql = "SELECT 
                    v.*, c.nom as client_nom, u.username as vendeur_nom
                    FROM ventes v
                    LEFT JOIN clients c ON v.client_id = c.id
                    LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                    WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    ORDER BY v.date_vente DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $endTime = microtime(true);
        
        $queries[] = [
            'name' => 'ventes_with_details',
            'query' => $sql,
            'execution_time' => round(($endTime - $startTime) * 1000, 2),
            'rows_returned' => $stmt->rowCount(),
            'status' => ($endTime - $startTime) * 1000 < 200 ? 'OK' : 'SLOW'
        ];

        // Requêtes de stock
        $startTime = microtime(true);
        $sql = "SELECT 
                    p.*, s.quantite_actuelle, s.stock_minimum, l.date_peremption
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    LEFT JOIN lots l ON p.id = l.produit_id AND l.is_actif = 1
                    WHERE p.is_actif = 1
                    ORDER BY p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $endTime = microtime(true);
        
        $queries[] = [
            'name' => 'stock_with_lots',
            'query' => $sql,
            'execution_time' => round(($endTime - $startTime) * 1000, 2),
            'rows_returned' => $stmt->rowCount(),
            'status' => ($endTime - $startTime) * 1000 < 200 ? 'OK' : 'SLOW'
        ];

        // Requêtes d'audit
        $startTime = microtime(true);
        $sql = "SELECT 
                    al.*, u.username
                    FROM audit_logs al
                    LEFT JOIN utilisateurs u ON al.user_id = u.id
                    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY al.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $endTime = microtime(true);
        
        $queries[] = [
            'name' => 'audit_logs_recent',
            'query' => $sql,
            'execution_time' => round(($endTime - $startTime) * 1000, 2),
            'rows_returned' => $stmt->rowCount(),
            'status' => ($endTime - $startTime) * 1000 < 200 ? 'OK' : 'SLOW'
        ];

        $slowQueries = array_filter($queries, fn($q) => $q['execution_time'] > 200);

        return [
            'queries' => $queries,
            'slow_queries' => $slowQueries,
            'average_execution_time' => round(array_sum(array_column($queries, 'execution_time')) / count($queries), 2),
            'total_slow_queries' => count($slowQueries)
        ];
    }

    /**
     * Récupère les erreurs système
     */
    private function getSystemErrors(): array
    {
        $sql = "SELECT 
                    error_type,
                    COUNT(*) as nombre_erreurs,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as erreurs_1h,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as erreurs_24h,
                    MAX(created_at) as derniere_erreur
                FROM system_errors 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY error_type
                ORDER BY erreurs_24h DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Erreurs critiques
        $sql = "SELECT 
                    error_message,
                    file,
                    line,
                    created_at
                FROM system_errors 
                WHERE error_type IN ('DATABASE', 'SECURITY', 'FATAL_ERROR')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $criticalErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'errors_by_type' => $errors,
            'critical_errors' => $criticalErrors,
            'total_errors_24h' => array_sum(array_column($errors, 'erreurs_24h')),
            'total_errors_1h' => array_sum(array_column($errors, 'erreurs_1h')),
            'error_rate' => $this->calculateErrorRate()
        ];
    }

    /**
     * Calcule le taux d'erreur
     */
    private function calculateErrorRate(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_operations,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as operations_1h,
                    (SELECT COUNT(*) FROM system_errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as errors_1h
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $errorRate = $result['operations_1h'] > 0 ? ($result['errors_1h'] / $result['operations_1h']) * 100 : 0;

        return [
            'error_rate_1h' => round($errorRate, 2),
            'operations_1h' => $result['operations_1h'],
            'errors_1h' => $result['errors_1h'],
            'status' => $errorRate < 5 ? 'OK' : ($errorRate < 10 ? 'WARNING' : 'CRITICAL')
        ];
    }

    /**
     * Récupère la charge des opérations critiques
     */
    private function getCriticalOperations(): array
    {
        $operations = [];

        // Charge des ventes
        $sql = "SELECT 
                    COUNT(*) as ventes_24h,
                    COUNT(CASE WHEN date_vente >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as ventes_1h,
                    AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) / 1000 as temps_moyen_traitement_ms,
                    MAX(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) / 1000 as temps_max_traitement_ms
                FROM ventes 
                WHERE statut_vente = 'VALIDEE'
                AND date_vente >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $ventesLoad = $stmt->fetch(PDO::FETCH_ASSOC);

        $operations['ventes'] = [
            'name' => 'Ventes',
            'operations_24h' => $ventesLoad['ventes_24h'],
            'operations_1h' => $ventesLoad['ventes_1h'],
            'temps_moyen_traitement' => round($ventesLoad['temps_moyen_traitement_ms'] ?? 0, 2),
            'temps_max_traitement' => round($ventesLoad['temps_max_traitement_ms'] ?? 0, 2),
            'status' => ($ventesLoad['temps_moyen_traitement_ms'] ?? 0) < 200 ? 'OK' : 'SLOW'
        ];

        // Charge du stock
        $sql = "SELECT 
                    COUNT(*) as mouvements_24h,
                    COUNT(CASE WHEN date_mouvement >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as mouvements_1h,
                    AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) / 1000 as temps_moyen_traitement_ms
                FROM mouvements_stock 
                WHERE date_mouvement >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stockLoad = $stmt->fetch(PDO::FETCH_ASSOC);

        $operations['stock'] = [
            'name' => 'Stock',
            'operations_24h' => $stockLoad['mouvements_24h'],
            'operations_1h' => $stockLoad['mouvements_1h'],
            'temps_moyen_traitement' => round($stockLoad['temps_moyen_traitement_ms'] ?? 0, 2),
            'status' => ($stockLoad['temps_moyen_traitement_ms'] ?? 0) < 100 ? 'OK' : 'SLOW'
        ];

        // Charge de la caisse
        $sql = "SELECT 
                    COUNT(*) as mouvements_24h,
                    COUNT(CASE WHEN date_mouvement >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as mouvements_1h,
                    AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) / 1000 as temps_moyen_traitement_ms
                FROM mouvements_caisse 
                WHERE date_mouvement >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $caisseLoad = $stmt->fetch(PDO::FETCH_ASSOC);

        $operations['caisse'] = [
            'name' => 'Caisse',
            'operations_24h' => $caisseLoad['mouvements_24h'],
            'operations_1h' => $caisseLoad['mouvements_1h'],
            'temps_moyen_traitement' => round($caisseLoad['temps_moyen_traitement_ms'] ?? 0, 2),
            'status' => ($caisseLoad['temps_moyen_traitement_ms'] ?? 0) < 50 ? 'OK' : 'SLOW'
        ];

        return $operations;
    }

    /**
     * Récupère l'utilisation des ressources
     */
    private function getResourceUsage(): array
    {
        $resources = [];

        // Utilisation de la base de données
        $sql = "SHOW STATUS LIKE 'Threads_connected'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $threadsConnected = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "SHOW STATUS LIKE 'Max_used_connections'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $maxConnections = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "SHOW STATUS LIKE 'Questions'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $questions = $stmt->fetch(PDO::FETCH_ASSOC);

        $resources['database'] = [
            'threads_connected' => (int) $threadsConnected['Value'],
            'max_used_connections' => (int) $maxConnections['Value'],
            'total_queries' => (int) $questions['Value'],
            'queries_per_second' => round((int) $questions['Value'] / (time() - strtotime('today')), 2)
        ];

        // Utilisation du disque (approximation)
        $sql = "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY size_mb DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $tableSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resources['disk_usage'] = [
            'largest_tables' => $tableSizes,
            'total_size_mb' => array_sum(array_column($tableSizes, 'size_mb'))
        ];

        // Utilisation mémoire (approximation)
        $resources['memory'] = [
            'php_memory_limit' => ini_get('memory_limit'),
            'php_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'php_peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
        ];

        return $resources;
    }

    /**
     * Récupère la performance de la base de données
     */
    private function getDatabasePerformance(): array
    {
        $performance = [];

        // Cache命中率
        $sql = "SHOW STATUS LIKE 'Qcache_hits'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $cacheHits = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "SHOW STATUS LIKE 'Qcache_inserts'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $cacheInserts = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalQueries = (int) $cacheHits['Value'] + (int) $cacheInserts['Value'];
        $hitRatio = $totalQueries > 0 ? ((int) $cacheHits['Value'] / $totalQueries) * 100 : 0;

        $performance['cache'] = [
            'hit_ratio' => round($hitRatio, 2),
            'hits' => (int) $cacheHits['Value'],
            'inserts' => (int) $cacheInserts['Value'],
            'status' => $hitRatio > 80 ? 'OK' : ($hitRatio > 50 ? 'WARNING' : 'POOR')
        ];

        // Requêtes lentes
        $sql = "SHOW STATUS LIKE 'Slow_queries'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $slowQueries = $stmt->fetch(PDO::FETCH_ASSOC);

        $performance['slow_queries'] = [
            'total_slow_queries' => (int) $slowQueries['Value'],
            'status' => (int) $slowQueries['Value'] < 10 ? 'OK' : 'WARNING'
        ];

        // Connexions
        $sql = "SHOW STATUS LIKE 'Connections'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $connections = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "SHOW STATUS LIKE 'Aborted_connects'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $abortedConnections = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalConnections = (int) $connections['Value'];
        $abortedRate = $totalConnections > 0 ? ((int) $abortedConnections['Value'] / $totalConnections) * 100 : 0;

        $performance['connections'] = [
            'total_connections' => $totalConnections,
            'aborted_connections' => (int) $abortedConnections['Value'],
            'aborted_rate' => round($abortedRate, 2),
            'status' => $abortedRate < 1 ? 'OK' : 'WARNING'
        ];

        return $performance;
    }

    /**
     * Génère un rapport de performance complet
     */
    public function generatePerformanceReport(): array
    {
        $metrics = $this->getSystemMetrics();
        
        if (!$metrics['success']) {
            return $metrics;
        }

        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => $this->generatePerformanceSummary($metrics['data']),
            'details' => $metrics['data'],
            'recommendations' => $this->generatePerformanceRecommendations($metrics['data'])
        ];

        return [
            'success' => true,
            'data' => $report
        ];
    }

    /**
     * Génère un résumé de performance
     */
    private function generatePerformanceSummary(array $metrics): array
    {
        $summary = [];

        // API Performance
        $apiMetrics = $metrics['api_response_time'];
        $summary['api_performance'] = [
            'status' => count($apiMetrics['critical_endpoints']) > 0 ? 'CRITICAL' : 
                        (count($apiMetrics['slow_endpoints']) > 0 ? 'WARNING' : 'OK'),
            'average_response_time' => $apiMetrics['average_response_time'],
            'slow_endpoints_count' => count($apiMetrics['slow_endpoints'])
        ];

        // Database Performance
        $dbMetrics = $metrics['database_performance'];
        $summary['database_performance'] = [
            'status' => $dbMetrics['cache']['status'] === 'POOR' ? 'CRITICAL' : 
                        ($dbMetrics['cache']['status'] === 'WARNING' || $dbMetrics['slow_queries']['status'] === 'WARNING' ? 'WARNING' : 'OK'),
            'cache_hit_ratio' => $dbMetrics['cache']['hit_ratio'],
            'slow_queries_count' => $dbMetrics['slow_queries']['total_slow_queries']
        ];

        // Error Rate
        $errorMetrics = $metrics['system_errors'];
        $summary['error_rate'] = [
            'status' => $errorMetrics['error_rate']['status'],
            'error_rate_1h' => $errorMetrics['error_rate']['error_rate_1h'],
            'total_errors_24h' => $errorMetrics['total_errors_24h']
        ];

        // Overall Status
        $statuses = array_column($summary, 'status');
        $summary['overall_status'] = in_array('CRITICAL', $statuses) ? 'CRITICAL' : 
                                     (in_array('WARNING', $statuses) ? 'WARNING' : 'OK');

        return $summary;
    }

    /**
     * Génère des recommandations de performance
     */
    private function generatePerformanceRecommendations(array $metrics): array
    {
        $recommendations = [];

        // Recommandations API
        $apiMetrics = $metrics['api_response_time'];
        if (!empty($apiMetrics['critical_endpoints'])) {
            $recommendations[] = [
                'type' => 'API_PERFORMANCE',
                'priority' => 'HIGH',
                'message' => 'Endpoints critiques détectés',
                'details' => 'Temps de réponse > 500ms',
                'actions' => [
                    'Optimiser les requêtes SQL',
                    'Ajouter du cache',
                    'Analyser les goulots d\'étranglement',
                    'Considérer l\'asynchronisme'
                ]
            ];
        }

        // Recommandations Base de données
        $dbMetrics = $metrics['database_performance'];
        if ($dbMetrics['cache']['status'] === 'POOR') {
            $recommendations[] = [
                'type' => 'DATABASE_CACHE',
                'priority' => 'HIGH',
                'message' => 'Cache命中率 faible',
                'details' => 'Hit ratio: ' . $dbMetrics['cache']['hit_ratio'] . '%',
                'actions' => [
                    'Augmenter la taille du cache',
                    'Optimiser les requêtes',
                    'Ajouter des index',
                    'Configurer le cache applicatif'
                ]
            ];
        }

        // Recommandations Erreurs
        $errorMetrics = $metrics['system_errors'];
        if ($errorMetrics['error_rate']['status'] === 'CRITICAL') {
            $recommendations[] = [
                'type' => 'ERROR_RATE',
                'priority' => 'CRITICAL',
                'message' => 'Taux d\'erreur élevé',
                'details' => 'Taux d\'erreur: ' . $errorMetrics['error_rate']['error_rate_1h'] . '%',
                'actions' => [
                    'Analyser les logs d\'erreurs',
                    'Corriger les bugs critiques',
                    'Améliorer la gestion des erreurs',
                    'Mettre en place des alertes'
                ]
            ];
        }

        // Recommandations Ressources
        $resourceMetrics = $metrics['resource_usage'];
        if ($resourceMetrics['database']['threads_connected'] > 50) {
            $recommendations[] = [
                'type' => 'RESOURCE_USAGE',
                'priority' => 'MEDIUM',
                'message' => 'Utilisation élevée des connexions',
                'details' => 'Threads connected: ' . $resourceMetrics['database']['threads_connected'],
                'actions' => [
                    'Optimiser le pool de connexions',
                    'Réduire la durée des transactions',
                    'Analyser les requêtes longues',
                    'Considérer le load balancing'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Exporte les métriques de monitoring
     */
    public function exportMetrics(string $format = 'json'): array
    {
        $metrics = $this->getSystemMetrics();
        
        if (!$metrics['success']) {
            return $metrics;
        }

        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'filename' => 'monitoring_metrics_' . date('YmdHis') . '.json',
                    'content' => json_encode($metrics['data'], JSON_PRETTY_PRINT)
                ];
                
            case 'csv':
                $csvContent = $this->convertMetricsToCSV($metrics['data']);
                return [
                    'success' => true,
                    'filename' => 'monitoring_metrics_' . date('YmdHis') . '.csv',
                    'content' => $csvContent
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Format d\'export non supporté'
                ];
        }
    }

    /**
     * Convertit les métriques en CSV
     */
    private function convertMetricsToCSV(array $metrics): string
    {
        $csv = "Type,Metric,Value,Status,Timestamp\n";
        
        foreach ($metrics as $category => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subKey => $subValue) {
                            $csv .= sprintf(
                                '"%s","%s","%s","%s","%s"\n',
                                $category,
                                $key . '_' . $subKey,
                                is_scalar($subValue) ? $subValue : json_encode($subValue),
                                $value['status'] ?? 'UNKNOWN',
                                date('Y-m-d H:i:s')
                            );
                        }
                    } else {
                        $csv .= sprintf(
                            '"%s","%s","%s","%s","%s"\n',
                            $category,
                            $key,
                            is_scalar($value) ? $value : json_encode($value),
                            'OK',
                            date('Y-m-d H:i:s')
                        );
                    }
                }
            }
        }
        
        return $csv;
    }
}
