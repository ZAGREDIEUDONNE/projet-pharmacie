<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;
use Throwable;

class ErrorHandlerService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;
    private array $errorTypes = [
        'DATABASE' => 'Erreur de base de données',
        'VALIDATION' => 'Erreur de validation',
        'AUTHENTICATION' => 'Erreur d\'authentification',
        'PERMISSION' => 'Erreur de permission',
        'SYSTEM' => 'Erreur système',
        'PERFORMANCE' => 'Erreur de performance',
        'SECURITY' => 'Erreur de sécurité',
        'BUSINESS_LOGIC' => 'Erreur logique métier',
        'NETWORK' => 'Erreur réseau',
        'FILE_SYSTEM' => 'Erreur système de fichiers',
        'EXTERNAL_API' => 'Erreur API externe'
    ];

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Gère une exception de manière centralisée
     */
    public function handleException(Throwable $exception, ?array $context = null): void
    {
        $errorType = $this->categorizeError($exception);
        $errorMessage = $exception->getMessage();
        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();
        $errorTrace = $exception->getTraceAsString();

        // Enregistrer dans la base de données
        $this->logError($errorType, $errorMessage, $errorFile, $errorLine, array_merge([
            'trace' => $errorTrace,
            'context' => $context ?? [],
            'user_id' => $_SESSION['user']['id'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ], $context ?? []));

        // Logger dans les logs système
        $this->logToSystemLog($errorType, $errorMessage, $errorFile, $errorLine, $context);

        // Notifier en cas d'erreur critique
        if ($this->isCriticalError($errorType, $exception)) {
            $this->notifyCriticalError($errorType, $exception, $context);
        }

        // Journaliser l'erreur dans l'audit
        $this->auditService->logEvent(
            'SYSTEM_ERROR',
            'ERROR_HANDLER',
            "Erreur système: $errorType",
            $errorMessage,
            [
                'error_type' => $errorType,
                'file' => $errorFile,
                'line' => $errorLine,
                'context' => $context ?? []
            ]
        );
    }

    /**
     * Gère une erreur PHP de manière centralisée
     */
    public function handleError(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
    {
        // Ne pas gérer les erreurs supprimées par @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = $this->categorizePhpError($errno);
        $errorMessage = $errstr;
        $errorFile = $errfile;
        $errorLine = $errline;

        // Enregistrer dans la base de données
        $this->logError($errorType, $errorMessage, $errorFile, $errorLine, [
            'error_code' => $errno,
            'user_id' => $_SESSION['user']['id'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Logger dans les logs système
        $this->logToSystemLog($errorType, $errorMessage, $errorFile, $errorLine);

        // Journaliser dans l'audit
        $this->auditService->logEvent(
            'PHP_ERROR',
            'ERROR_HANDLER',
            "Erreur PHP: $errorType",
            $errorMessage,
            [
                'error_code' => $errno,
                'file' => $errorFile,
                'line' => $errorLine
            ]
        );

        // Ne pas exécuter le gestionnaire d'erreurs PHP interne
        return true;
    }

    /**
     * Gère l'arrêt du script
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $errorType = 'FATAL_ERROR';
            $errorMessage = $error['message'];
            $errorFile = $error['file'];
            $errorLine = $error['line'];

            $this->logError($errorType, $errorMessage, $errorFile, $errorLine, [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'user_id' => $_SESSION['user']['id'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $this->auditService->logEvent(
                'FATAL_ERROR',
                'SHUTDOWN_HANDLER',
                "Erreur fatale détectée",
                $errorMessage,
                [
                    'file' => $errorFile,
                    'line' => $errorLine,
                    'memory_usage' => memory_get_usage(true)
                ]
            );
        }
    }

    /**
     * Catégorise une exception
     */
    private function categorizeError(Throwable $exception): string
    {
        if ($exception instanceof PDOException) {
            return 'DATABASE';
        }

        $className = get_class($exception);
        $message = strtolower($exception->getMessage());

        // Analyse basée sur le nom de l'exception
        if (strpos($className, 'Auth') !== false || strpos($message, 'auth') !== false) {
            return 'AUTHENTICATION';
        }

        if (strpos($className, 'Permission') !== false || strpos($message, 'permission') !== false) {
            return 'PERMISSION';
        }

        if (strpos($className, 'Validation') !== false || strpos($message, 'validation') !== false) {
            return 'VALIDATION';
        }

        if (strpos($className, 'Security') !== false || strpos($message, 'security') !== false || strpos($message, 'csrf') !== false) {
            return 'SECURITY';
        }

        if (strpos($className, 'Network') !== false || strpos($message, 'connection') !== false) {
            return 'NETWORK';
        }

        if (strpos($className, 'File') !== false || strpos($message, 'file') !== false) {
            return 'FILE_SYSTEM';
        }

        if (strpos($className, 'API') !== false || strpos($message, 'api') !== false) {
            return 'EXTERNAL_API';
        }

        return 'SYSTEM';
    }

    /**
     * Catégorise une erreur PHP
     */
    private function categorizePhpError(int $errno): string
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'FATAL_ERROR';
            
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            
            case E_STRICT:
            case E_RECOVERABLE_ERROR:
                return 'STRICT';
            
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
            
            default:
                return 'UNKNOWN';
        }
    }

    /**
     * Enregistre une erreur dans la base de données
     */
    private function logError(string $errorType, string $errorMessage, ?string $file = null, ?int $line = null, ?array $context = null): void
    {
        $sql = "INSERT INTO system_errors (
                    error_type, error_message, file, line, 
                    context, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $errorType,
            $errorMessage,
            $file,
            $line,
            $context ? json_encode($context) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Enregistre dans les logs système
     */
    private function logToSystemLog(string $errorType, string $errorMessage, ?string $file = null, ?int $line = null, ?array $context = null): void
    {
        $logMessage = sprintf(
            "[%s] %s: %s",
            date('Y-m-d H:i:s'),
            $errorType,
            $errorMessage
        );

        if ($file && $line) {
            $logMessage .= sprintf(" in %s on line %d", $file, $line);
        }

        if ($context) {
            $logMessage .= " - Context: " . json_encode($context);
        }

        error_log($logMessage . PHP_EOL, 3, __DIR__ . '/../../logs/system_errors.log');
    }

    /**
     * Vérifie si l'erreur est critique
     */
    private function isCriticalError(string $errorType, Throwable $exception): bool
    {
        $criticalTypes = ['DATABASE', 'SECURITY', 'FATAL_ERROR'];
        
        if (in_array($errorType, $criticalTypes)) {
            return true;
        }

        // Erreurs critiques basées sur le message
        $message = strtolower($exception->getMessage());
        $criticalKeywords = [
            'failed to connect',
            'access denied',
            'permission denied',
            'unauthorized',
            'invalid token',
            'csrf',
            'sql injection',
            'xss',
            'authentication failed'
        ];

        foreach ($criticalKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notifie une erreur critique
     */
    private function notifyCriticalError(string $errorType, Throwable $exception, ?array $context = null): void
    {
        // Envoyer un email aux administrateurs
        $this->sendCriticalErrorEmail($errorType, $exception, $context);
        
        // Logger la notification
        $this->auditService->logEvent(
            'CRITICAL_ERROR_NOTIFICATION',
            'ERROR_HANDLER',
            "Notification d'erreur critique envoyée",
            $exception->getMessage(),
            [
                'error_type' => $errorType,
                'notification_method' => 'EMAIL',
                'context' => $context ?? []
            ]
        );
    }

    /**
     * Envoie un email d'erreur critique
     */
    private function sendCriticalErrorEmail(string $errorType, Throwable $exception, ?array $context = null): void
    {
        $to = $_ENV['ADMIN_EMAIL'] ?? 'admin@pharmacie.com';
        $subject = "[URGENT] Erreur Critique - " . $errorType;
        
        $message = "Une erreur critique a été détectée dans le système de gestion de pharmacie.\n\n";
        $message .= "Type d'erreur: " . $errorType . "\n";
        $message .= "Message: " . $exception->getMessage() . "\n";
        $message .= "Fichier: " . $exception->getFile() . "\n";
        $message .= "Ligne: " . $exception->getLine() . "\n";
        $message .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        $message .= "URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "\n";
        
        if ($context) {
            $message .= "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }

        $headers = "From: " . ($_ENV['FROM_EMAIL'] ?? 'noreply@pharmacie.com') . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        mail($to, $subject, $message, $headers);
    }

    /**
     * Récupère les erreurs système
     */
    public function getSystemErrors(?string $dateDebut = null, ?string $dateFin = null, ?string $errorType = null, int $limit = 100): array
    {
        $sql = "SELECT * FROM system_errors WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateFin;
        }
        
        if ($errorType) {
            $sql .= " AND error_type = ?";
            $params[] = $errorType;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques d'erreurs
     */
    public function getErrorStatistics(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT 
                    error_type,
                    COUNT(*) as nombre_erreurs,
                    COUNT(DISTINCT file) as fichiers_affectes,
                    COUNT(DISTINCT DATE(created_at)) as nombre_jours,
                    MAX(created_at) as derniere_erreur,
                    AVG(CASE WHEN error_type = 'PERFORMANCE' THEN 1 ELSE 0 END) * 100 as pourcentage_performance
                FROM system_errors
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
        
        $sql .= " GROUP BY error_type ORDER BY nombre_erreurs DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return [
            'statistiques_par_type' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ]
        ];
    }

    /**
     * Génère un rapport d'erreurs
     */
    public function generateErrorReport(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $errors = $this->getSystemErrors($dateDebut, $dateFin);
        $statistics = $this->getErrorStatistics($dateDebut, $dateFin);
        
        // Analyse des erreurs critiques
        $criticalErrors = array_filter($errors, function($error) {
            return in_array($error['error_type'], ['DATABASE', 'SECURITY', 'FATAL_ERROR']);
        });
        
        // Analyse des erreurs de performance
        $performanceErrors = array_filter($errors, function($error) {
            return $error['error_type'] === 'PERFORMANCE';
        });
        
        // Analyse des erreurs par module
        $errorsByModule = [];
        foreach ($errors as $error) {
            $context = json_decode($error['context'], true);
            $module = $context['module'] ?? 'UNKNOWN';
            
            if (!isset($errorsByModule[$module])) {
                $errorsByModule[$module] = [
                    'module' => $module,
                    'nombre_erreurs' => 0,
                    'types_erreurs' => []
                ];
            }
            
            $errorsByModule[$module]['nombre_erreurs']++;
            
            if (!isset($errorsByModule[$module]['types_erreurs'][$error['error_type']])) {
                $errorsByModule[$module]['types_erreurs'][$error['error_type']] = 0;
            }
            
            $errorsByModule[$module]['types_erreurs'][$error['error_type']]++;
        }
        
        return [
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'statistiques_globales' => $statistics,
            'erreurs_critiques' => $criticalErrors,
            'erreurs_performance' => $performanceErrors,
            'erreurs_par_module' => array_values($errorsByModule),
            'recommandations' => $this->generateErrorRecommendations($statistics, $errors)
        ];
    }

    /**
     * Génère des recommandations basées sur les erreurs
     */
    private function generateErrorRecommendations(array $statistics, array $errors): array
    {
        $recommendations = [];
        
        // Analyse des erreurs de base de données
        $dbErrors = array_filter($statistics['statistiques_par_type'], function($stat) {
            return $stat['error_type'] === 'DATABASE';
        });
        
        if (!empty($dbErrors) && $dbErrors[0]['nombre_erreurs'] > 10) {
            $recommendations[] = [
                'type' => 'DATABASE',
                'priorite' => 'ÉLEVÉE',
                'message' => 'Nombre élevé d\'erreurs de base de données détecté',
                'actions' => [
                    'Vérifier la connexion à la base de données',
                    'Optimiser les requêtes SQL',
                    'Vérifier les index de la base de données',
                    'Augmenter le timeout de connexion'
                ]
            ];
        }
        
        // Analyse des erreurs de sécurité
        $securityErrors = array_filter($statistics['statistiques_par_type'], function($stat) {
            return $stat['error_type'] === 'SECURITY';
        });
        
        if (!empty($securityErrors) && $securityErrors[0]['nombre_erreurs'] > 0) {
            $recommendations[] = [
                'type' => 'SECURITY',
                'priorite' => 'CRITIQUE',
                'message' => 'Erreurs de sécurité détectées',
                'actions' => [
                    'Examiner immédiatement les logs de sécurité',
                    'Vérifier les tentatives d\'intrusion',
                    'Renforcer les politiques de sécurité',
                    'Analyser les adresses IP suspectes'
                ]
            ];
        }
        
        // Analyse des erreurs de performance
        $performanceErrors = array_filter($statistics['statistiques_par_type'], function($stat) {
            return $stat['error_type'] === 'PERFORMANCE';
        });
        
        if (!empty($performanceErrors) && $performanceErrors[0]['nombre_erreurs'] > 5) {
            $recommendations[] = [
                'type' => 'PERFORMANCE',
                'priorite' => 'MOYENNE',
                'message' => 'Problèmes de performance détectés',
                'actions' => [
                    'Analyser les requêtes lentes',
                    'Optimiser le cache',
                    'Vérifier l\'utilisation mémoire',
                    'Profiler les points critiques'
                ]
            ];
        }
        
        return $recommendations;
    }

    /**
     * Configure le gestionnaire d'erreurs
     */
    public function setupErrorHandling(): void
    {
        // Configurer le gestionnaire d'exceptions
        set_exception_handler([$this, 'handleException']);
        
        // Configurer le gestionnaire d'erreurs
        set_error_handler([$this, 'handleError']);
        
        // Configurer le gestionnaire d'arrêt
        register_shutdown_function([$this, 'handleShutdown']);
        
        // Niveau de rapport d'erreurs
        error_reporting(E_ALL);
        
        // Afficher les erreurs en développement uniquement
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        } else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
        }
        
        // Logger toutes les erreurs
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
    }

    /**
     * Nettoie les anciennes erreurs
     */
    public function cleanupOldErrors(int $daysToKeep = 30): int
    {
        $sql = "DELETE FROM system_errors WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$daysToKeep]);
        
        return $stmt->rowCount();
    }
}
