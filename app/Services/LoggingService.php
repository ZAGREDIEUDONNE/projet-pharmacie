<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class LoggingService
{
    private PDO $db;
    private string $logPath;
    private array $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->logPath = __DIR__ . '/../../logs/';
        
        // Créer le répertoire de logs s'il n'existe pas
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Enregistre un message de log
     */
    public function log(string $level, string $message, ?array $context = null, ?string $category = 'SYSTEM'): void
    {
        if (!isset($this->logLevels[$level])) {
            $level = 'INFO';
        }

        $logData = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'category' => $category,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user']['id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];

        // Enregistrer dans la base de données
        $this->logToDatabase($logData);

        // Enregistrer dans les fichiers de log
        $this->logToFile($logData);

        // Envoyer les logs critiques par email
        if ($level === 'CRITICAL') {
            $this->sendCriticalLogEmail($logData);
        }
    }

    /**
     * Enregistre dans la base de données
     */
    private function logToDatabase(array $logData): void
    {
        $sql = "INSERT INTO system_logs (
                    level, message, context, category, user_id,
                    ip_address, user_agent, request_uri, request_method, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($stmt);
        $stmt->execute([
            $logData['level'],
            $logData['message'],
            $logData['context'] ? json_encode($logData['context']) : null,
            $logData['category'],
            $logData['user_id'],
            $logData['ip_address'],
            $logData['user_agent'],
            $logData['request_uri'],
            $logData['request_method']
        ]);
    }

    /**
     * Enregistre dans les fichiers de log
     */
    private function logToFile(array $logData): void
    {
        $logFile = $this->logPath . $this->getLogFileName($logData['category']);
        
        $logEntry = sprintf(
            "[%s] %s [%s] %s %s\n",
            $logData['timestamp'],
            $logData['level'],
            $logData['category'],
            $logData['message'],
            $logData['context'] ? json_encode($logData['context']) : ''
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Récupère le nom du fichier de log selon la catégorie
     */
    private function getLogFileName(string $category): string
    {
        $date = date('Y-m-d');
        
        switch ($category) {
            case 'SECURITY':
                return "security_{$date}.log";
            case 'PERFORMANCE':
                return "performance_{$date}.log";
            case 'DATABASE':
                return "database_{$date}.log";
            case 'API':
                return "api_{$date}.log";
            case 'CAISSE':
                return "caisse_{$date}.log";
            case 'STOCK':
                return "stock_{$date}.log";
            case 'VENTES':
                return "ventes_{$date}.log";
            case 'COMPTABILITE':
                return "comptabilite_{$date}.log";
            default:
                return "system_{$date}.log";
        }
    }

    /**
     * Envoie un email pour les logs critiques
     */
    private function sendCriticalLogEmail(array $logData): void
    {
        $to = $_ENV['ADMIN_EMAIL'] ?? 'admin@pharmacie.com';
        $subject = "[CRITICAL] Erreur système - " . $logData['category'];
        
        $message = "Une erreur critique a été détectée dans le système:\n\n";
        $message .= "Date: " . $logData['timestamp'] . "\n";
        $message .= "Niveau: " . $logData['level'] . "\n";
        $message .= "Catégorie: " . $logData['category'] . "\n";
        $message .= "Message: " . $logData['message'] . "\n";
        
        if ($logData['context']) {
            $message .= "Contexte: " . json_encode($logData['context'], JSON_PRETTY_PRINT) . "\n";
        }
        
        $message .= "Utilisateur: " . ($logData['user_id'] ?? 'N/A') . "\n";
        $message .= "IP: " . $logData['ip_address'] . "\n";
        $message .= "URI: " . $logData['request_uri'] . "\n";

        $headers = "From: " . ($_ENV['FROM_EMAIL'] ?? 'noreply@pharmacie.com') . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        mail($to, $subject, $message, $headers);
    }

    /**
     * Log de niveau DEBUG
     */
    public function debug(string $message, ?array $context = null, ?string $category = 'SYSTEM'): void
    {
        $this->log('DEBUG', $message, $context, $category);
    }

    /**
     * Log de niveau INFO
     */
    public function info(string $message, ?array $context = null, ?string $category = 'SYSTEM'): void
    {
        $this->log('INFO', $message, $context, $category);
    }

    /**
     * Log de niveau WARNING
     */
    public function warning(string $message, ?array $context = null, ?string $category = 'SYSTEM'): void
    {
        $this->log('WARNING', $message, $context, $category);
    }

    /**
     * Log de niveau ERROR
     */
    public function error(string $message, ?array $context = null, ?string $category = 'SYSTEM'): void
    {
        $this->log('ERROR', $message, $context, $category);
    }

    /**
     * Log de niveau CRITICAL
     */
    public function critical(string $message, ?array $context = null, ?string $category = 'SYSTEM'): void
    {
        $this->log('CRITICAL', $message, $context, $category);
    }

    /**
     * Log de sécurité
     */
    public function security(string $message, ?array $context = null): void
    {
        $this->log('WARNING', $message, $context, 'SECURITY');
    }

    /**
     * Log de performance
     */
    public function performance(string $message, ?array $context = null): void
    {
        $this->log('INFO', $message, $context, 'PERFORMANCE');
    }

    /**
     * Log de base de données
     */
    public function database(string $message, ?array $context = null): void
    {
        $this->log('ERROR', $message, $context, 'DATABASE');
    }

    /**
     * Log API
     */
    public function api(string $message, ?array $context = null): void
    {
        $this->log('INFO', $message, $context, 'API');
    }

    /**
     * Log caisse
     */
    public function caisse(string $message, ?array $context = null): void
    {
        $this->log('INFO', $message, $context, 'CAISSE');
    }

    /**
     * Log stock
     */
    public function stock(string $message, ?array $context = null): void
    {
        $this->log('INFO', $message, $context, 'STOCK');
    }

    /**
     * Log ventes
     */
    public function ventes(string $message, ?array $context = null): void
    {
        $this->log('INFO', $message, $context, 'VENTES');
    }

    /**
     * Log comptabilité
     */
    public function comptabilite(string $message, ?array $context = null): void
    {
        $this->log('INFO', $message, $context, 'COMPTABILITE');
    }

    /**
     * Récupère les logs selon les filtres
     */
    public function getLogs(?string $level = null, ?string $category = null, ?string $dateDebut = null, ?string $dateFin = null, int $limit = 100): array
    {
        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];

        if ($level) {
            $sql .= " AND level = ?";
            $params[] = $level;
        }

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        if ($dateDebut) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateDebut;
        }

        if ($dateFin) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateFin;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des logs
     */
    public function getLogStatistics(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT 
                    level,
                    category,
                    COUNT(*) as nombre_logs,
                    COUNT(DISTINCT user_id) as nombre_utilisateurs,
                    COUNT(DISTINCT DATE(created_at)) as nombre_jours,
                    MAX(created_at) as dernier_log
                FROM system_logs
                WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY level, category ORDER BY nombre_logs DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les logs critiques
     */
    public function getCriticalLogs(?string $dateDebut = null, ?string $dateFin = null, int $limit = 50): array
    {
        return $this->getLogs('CRITICAL', null, $dateDebut, $dateFin, $limit);
    }

    /**
     * Récupère les logs de sécurité
     */
    public function getSecurityLogs(?string $dateDebut = null, ?string $dateFin = null, int $limit = 100): array
    {
        return $this->getLogs(null, 'SECURITY', $dateDebut, $dateFin, $limit);
    }

    /**
     * Récupère les logs de performance
     */
    public function getPerformanceLogs(?string $dateDebut = null, ?string $dateFin = null, int $limit = 100): array
    {
        return $this->getLogs(null, 'PERFORMANCE', $dateDebut, $dateFin, $limit);
    }

    /**
     * Exporte les logs
     */
    public function exportLogs(?string $level = null, ?string $category = null, ?string $dateDebut = null, ?string $dateFin = null, string $format = 'json'): array
    {
        $logs = $this->getLogs($level, $category, $dateDebut, $dateFin, 10000);
        
        $filename = "logs_{$category}_{$level}_" . date('YmdHis');
        
        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'filename' => $filename . '.json',
                    'content' => json_encode($logs, JSON_PRETTY_PRINT)
                ];
                
            case 'csv':
                $csvContent = $this->convertLogsToCSV($logs);
                return [
                    'success' => true,
                    'filename' => $filename . '.csv',
                    'content' => $csvContent
                ];
                
            case 'txt':
                $txtContent = $this->convertLogsToText($logs);
                return [
                    'success' => true,
                    'filename' => $filename . '.txt',
                    'content' => $txtContent
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Format d\'export non supporté'
                ];
        }
    }

    /**
     * Convertit les logs en CSV
     */
    private function convertLogsToCSV(array $logs): string
    {
        $csv = "Date,Niveau,Catégorie,Message,Utilisateur,IP\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,\"%s\",%s,%s\n",
                $log['created_at'],
                $log['level'],
                $log['category'],
                str_replace('"', '""', $log['message']),
                $log['user_id'] ?? 'N/A',
                $log['ip_address']
            );
        }
        
        return $csv;
    }

    /**
     * Convertit les logs en texte
     */
    private function convertLogsToText(array $logs): string
    {
        $text = "";
        
        foreach ($logs as $log) {
            $text .= sprintf(
                "[%s] %s [%s] %s\n",
                $log['created_at'],
                $log['level'],
                $log['category'],
                $log['message']
            );
            
            if ($log['context']) {
                $text .= "Context: " . json_encode($log['context'], JSON_PRETTY_PRINT) . "\n";
            }
            
            $text .= "User: " . ($log['user_id'] ?? 'N/A') . "\n";
            $text .= "IP: " . $log['ip_address'] . "\n";
            $text .= "URI: " . $log['request_uri'] . "\n";
            $text .= str_repeat("-", 80) . "\n";
        }
        
        return $text;
    }

    /**
     * Nettoie les anciens logs
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        // Nettoyer les logs de la base de données
        $sql = "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$daysToKeep]);
        $dbDeleted = $stmt->rowCount();

        // Nettoyer les fichiers de log
        $filesDeleted = 0;
        $files = glob($this->logPath . '*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < strtotime("-{$daysToKeep} days")) {
                if (unlink($file)) {
                    $filesDeleted++;
                }
            }
        }

        return [
            'database_deleted' => $dbDeleted,
            'files_deleted' => $filesDeleted,
            'total_deleted' => $dbDeleted + $filesDeleted
        ];
    }

    /**
     * Configure la rotation des logs
     */
    public function configureLogRotation(): array
    {
        $config = [
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'max_files' => 30,
            'compression' => true
        ];

        $rotated = [];
        $files = glob($this->logPath . '*.log');
        
        foreach ($files as $file) {
            if (filesize($file) > $config['max_file_size']) {
                $newName = str_replace('.log', '_' . date('YmdHis') . '.log', $file);
                
                if (rename($file, $newName)) {
                    $rotated[] = [
                        'original' => $file,
                        'rotated' => $newName,
                        'size' => filesize($newName)
                    ];
                    
                    // Compresser le fichier si configuré
                    if ($config['compression']) {
                        $this->compressLogFile($newName);
                    }
                }
            }
        }

        return [
            'success' => true,
            'rotated_files' => $rotated,
            'count' => count($rotated)
        ];
    }

    /**
     * Compresse un fichier de log
     */
    private function compressLogFile(string $file): bool
    {
        $compressed = $file . '.gz';
        
        $content = file_get_contents($file);
        $compressedContent = gzencode($content);
        
        if (file_put_contents($compressed, $compressedContent)) {
            unlink($file);
            return true;
        }
        
        return false;
    }

    /**
     * Analyse les logs pour détecter des patterns
     */
    public function analyzeLogPatterns(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $logs = $this->getLogs(null, null, $dateDebut, $dateFin, 10000);
        
        $patterns = [
            'error_frequency' => [],
            'security_events' => [],
            'performance_issues' => [],
            'user_activity' => []
        ];

        foreach ($logs as $log) {
            // Fréquence des erreurs
            if ($log['level'] === 'ERROR' || $log['level'] === 'CRITICAL') {
                $key = substr($log['message'], 0, 100);
                if (!isset($patterns['error_frequency'][$key])) {
                    $patterns['error_frequency'][$key] = [
                        'count' => 0,
                        'first_occurrence' => $log['created_at'],
                        'last_occurrence' => $log['created_at'],
                        'message' => $log['message']
                    ];
                }
                $patterns['error_frequency'][$key]['count']++;
                $patterns['error_frequency'][$key]['last_occurrence'] = $log['created_at'];
            }

            // Événements de sécurité
            if ($log['category'] === 'SECURITY') {
                $patterns['security_events'][] = [
                    'timestamp' => $log['created_at'],
                    'message' => $log['message'],
                    'ip_address' => $log['ip_address'],
                    'user_id' => $log['user_id']
                ];
            }

            // Problèmes de performance
            if ($log['category'] === 'PERFORMANCE') {
                $patterns['performance_issues'][] = [
                    'timestamp' => $log['created_at'],
                    'message' => $log['message'],
                    'context' => json_decode($log['context'], true)
                ];
            }

            // Activité utilisateur
            if ($log['user_id']) {
                $userId = $log['user_id'];
                if (!isset($patterns['user_activity'][$userId])) {
                    $patterns['user_activity'][$userId] = [
                        'count' => 0,
                        'first_activity' => $log['created_at'],
                        'last_activity' => $log['created_at'],
                        'categories' => []
                    ];
                }
                $patterns['user_activity'][$userId]['count']++;
                $patterns['user_activity'][$userId]['last_activity'] = $log['created_at'];
                $patterns['user_activity'][$userId]['categories'][] = $log['category'];
            }
        }

        // Trier par fréquence
        uasort($patterns['error_frequency'], fn($a, $b) => $b['count'] - $a['count']);
        
        return [
            'period' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'total_logs' => count($logs)
            ],
            'patterns' => $patterns,
            'recommendations' => $this->generateLogRecommendations($patterns)
        ];
    }

    /**
     * Génère des recommandations basées sur l'analyse des logs
     */
    private function generateLogRecommendations(array $patterns): array
    {
        $recommendations = [];

        // Recommandations basées sur les erreurs fréquentes
        if (!empty($patterns['error_frequency'])) {
            $topError = reset($patterns['error_frequency']);
            if ($topError['count'] > 10) {
                $recommendations[] = [
                    'type' => 'ERROR_FREQUENCY',
                    'priority' => 'HIGH',
                    'message' => 'Erreur fréquente détectée',
                    'details' => $topError['message'],
                    'count' => $topError['count'],
                    'actions' => [
                        'Investiguer la cause racine',
                        'Corriger le code concerné',
                        'Ajouter une gestion d\'erreur',
                        'Mettre en place une alerte'
                    ]
                ];
            }
        }

        // Recommandations basées sur les événements de sécurité
        if (!empty($patterns['security_events'])) {
            $recommendations[] = [
                'type' => 'SECURITY',
                'priority' => 'CRITICAL',
                'message' => 'Événements de sécurité détectés',
                'count' => count($patterns['security_events']),
                'actions' => [
                    'Analyser les adresses IP suspectes',
                    'Bloquer les accès non autorisés',
                    'Renforcer les politiques de sécurité',
                    'Notifier l\'administrateur'
                ]
            ];
        }

        // Recommandations basées sur les problèmes de performance
        if (!empty($patterns['performance_issues'])) {
            $recommendations[] = [
                'type' => 'PERFORMANCE',
                'priority' => 'MEDIUM',
                'message' => 'Problèmes de performance détectés',
                'count' => count($patterns['performance_issues']),
                'actions' => [
                    'Optimiser les requêtes lentes',
                    'Ajouter du cache',
                    'Analyser les goulots d\'étranglement',
                    'Surveiller les ressources système'
                ]
            ];
        }

        return $recommendations;
    }
}
