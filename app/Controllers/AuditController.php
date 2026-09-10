<?php

namespace App\Controllers;

use App\Services\AuditGlobalService;
use App\Services\SecurityService;
use Exception;

class AuditController
{
    private AuditGlobalService $auditGlobalService;
    private SecurityService $securityService;

    public function __construct(
        AuditGlobalService $auditGlobalService,
        SecurityService $securityService
    ) {
        $this->auditGlobalService = $auditGlobalService;
        $this->securityService = $securityService;
    }

    /**
     * Tableau de bord d'audit
     */
    public function dashboard(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $auditData = $this->auditGlobalService->getAuditDashboard($dateDebut, $dateFin);

        require_once __DIR__ . '/../Views/audit/dashboard.php';
    }

    /**
     * Journal d'activité avec filtres
     */
    public function journal(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $utilisateur = $_GET['utilisateur'] ?? null;
        $module = $_GET['module'] ?? null;
        $action = $_GET['action'] ?? null;

        // Récupérer les logs selon les filtres
        $logs = [];
        
        if ($utilisateur) {
            $logs = $this->auditGlobalService->getUserAuditHistory($utilisateur, $dateDebut, $dateFin);
        } elseif ($module) {
            $logs = $this->auditGlobalService->getModuleAuditHistory($module, $dateDebut, $dateFin);
        } elseif ($action) {
            $logs = $this->auditGlobalService->getActionAuditHistory($action, $dateDebut, $dateFin);
        } else {
            $logs = $this->auditGlobalService->getSystemEvents(null, $dateDebut, $dateFin, 100);
        }

        require_once __DIR__ . '/../Views/audit/journal.php';
    }

    /**
     * Rapport d'audit détaillé
     */
    public function rapport(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $report = $this->auditGlobalService->generateComprehensiveAuditReport($dateDebut, $dateFin);

        require_once __DIR__ . '/../Views/audit/rapport.php';
    }

    /**
     * Analyse de sécurité
     */
    public function securite(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        // Récupérer les activités suspectes
        $suspiciousActivities = $this->auditGlobalService->analyzeSuspiciousActivities($dateDebut, $dateFin);

        // Récupérer les statistiques de sécurité
        $securityStats = $this->securityService->detectAttackPatterns('');

        // Récupérer les adresses IP bloquées
        $sql = "SELECT ip, reason, created_at, is_active 
                FROM ip_blacklist 
                WHERE created_at >= ? AND created_at <= ?
                ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $blacklistedIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../Views/audit/securite.php';
    }

    /**
     * Analyse de performance
     */
    public function performance(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        // Récupérer les erreurs système
        $errors = $this->auditGlobalService->getSystemErrors('PERFORMANCE', $dateDebut, $dateFin, 100);

        // Analyser les temps de réponse
        $responseTimes = [];
        foreach ($errors as $error) {
            $context = json_decode($error['context'], true);
            if (isset($context['response_time'])) {
                $responseTimes[] = $context['response_time'];
            }
        }

        $performanceStats = [
            'avg_response_time' => count($responseTimes) > 0 ? array_sum($responseTimes) / count($responseTimes) : 0,
            'max_response_time' => count($responseTimes) > 0 ? max($responseTimes) : 0,
            'min_response_time' => count($responseTimes) > 0 ? min($responseTimes) : 0,
            'total_errors' => count($errors),
            'slow_modules' => array_filter($errors, function($error) {
                $context = json_decode($error['context'], true);
                return isset($context['response_time']) && $context['response_time'] > 1000; // > 1 seconde
            })
        ];

        require_once __DIR__ . '/../Views/audit/performance.php';
    }

    /**
     * Export des données d'audit
     */
    public function export(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        $type = $_GET['type'] ?? 'journal';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $format = $_GET['format'] ?? 'json';

        try {
            switch ($type) {
                case 'journal':
                    $data = $this->auditGlobalService->exportAuditLogs($dateDebut, $dateFin);
                    break;
                case 'rapport':
                    $data = $this->auditGlobalService->exportComprehensiveReport($dateDebut, $dateFin);
                    break;
                case 'securite':
                    $data = $this->auditGlobalService->analyzeSuspiciousActivities($dateDebut, $dateFin);
                    break;
                case 'performance':
                    $data = $this->auditGlobalService->getSystemErrors('PERFORMANCE', $dateDebut, $dateFin);
                    break;
                default:
                    throw new Exception("Type d'export non valide: $type");
            }

            $filename = "audit_{$type}_" . date('YmdHis');

            switch ($format) {
                case 'json':
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
                    echo json_encode($data, JSON_PRETTY_PRINT);
                    break;

                case 'csv':
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
                    
                    $output = fopen('php://output', 'w');
                    
                    if ($type === 'journal') {
                        fputcsv($output, ['Date', 'Utilisateur', 'Action', 'Module', 'Description', 'IP']);
                        foreach ($data['logs'] as $log) {
                            fputcsv($output, [
                                $log['created_at'],
                                $log['username'],
                                $log['action'],
                                $log['module'],
                                $log['description'],
                                $log['ip_address']
                            ]);
                        }
                    } elseif ($type === 'rapport') {
                        fputcsv($output, ['Type', 'Message', 'Date']);
                        foreach ($data['recommandations'] as $rec) {
                            fputcsv($output, [
                                $rec['type'],
                                $rec['message'],
                                date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    
                    fclose($output);
                    break;

                case 'pdf':
                    // Pour le PDF, on pourrait utiliser une bibliothèque comme TCPDF
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                    echo "Export PDF non implémenté";
                    break;
            }

        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Configuration des alertes
     */
    public function alertes(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config = $_POST;
            
            // Valider et sauvegarder la configuration
            $results = [];
            
            if (isset($config['alert_security'])) {
                $results['security'] = $this->auditGlobalService->updateAlertConfig('alert_security_enabled', $config['alert_security']);
            }
            
            if (isset($config['alert_performance'])) {
                $results['performance'] = $this->auditGlobalService->updateAlertConfig('alert_performance_enabled', $config['alert_performance']);
            }
            
            if (isset($config['alert_email'])) {
                $results['email'] = $this->auditGlobalService->updateAlertConfig('alert_email_enabled', $config['alert_email']);
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Configuration des alertes mise à jour',
                'results' => $results
            ]);
            exit;
        }

        $config = $this->auditGlobalService->configureAutomaticAlerts();

        require_once __DIR__ . '/../Views/audit/alertes.php';
    }

    /**
     * API pour les statistiques en temps réel
     */
    public function api(): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            echo json_encode([
                'success' => false,
                'message' => 'Accès non autorisé'
            ]);
            exit;
        }

        $action = $_GET['action'] ?? '';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-24 hours'));
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        try {
            switch ($action) {
                case 'dashboard_stats':
                    $data = $this->auditGlobalService->getAuditDashboard($dateDebut, $dateFin);
                    break;

                case 'security_analysis':
                    $data = $this->auditGlobalService->analyzeSuspiciousActivities($dateDebut, $dateFin);
                    break;

                case 'performance_analysis':
                    $data = $this->auditGlobalService->getSystemErrors('PERFORMANCE', $dateDebut, $dateFin, 50);
                    break;

                case 'recent_activities':
                    $data = $this->auditGlobalService->getSystemEvents(null, $dateDebut, $dateFin, 20);
                    break;

                case 'user_activity':
                    $userId = $_GET['user_id'] ?? null;
                    if (!$userId) {
                        throw new Exception('ID utilisateur requis');
                    }
                    $data = $this->auditGlobalService->getUserAuditHistory($userId, $dateDebut, $dateFin, 50);
                    break;

                default:
                    throw new Exception("Action API non valide: $action");
            }

            echo json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Nettoyage des logs
     */
    public function cleanup(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role']['code'] !== 'ADMIN') {
            header('Location: /dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $daysToKeep = intval($_POST['days_to_keep'] ?? 30);
            
            $results = [];
            $results['audit_logs'] = $this->auditGlobalService->cleanupOldLogs($daysToKeep);
            $results['system_errors'] = $this->auditGlobalService->cleanupOldErrors($daysToKeep);
            $results['system_events'] = $this->auditGlobalService->cleanupOldEvents($daysToKeep);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Nettoyage des logs terminé',
                'results' => $results,
                'total_deleted' => array_sum($results)
            ]);
            exit;
        }

        require_once __DIR__ . '/../Views/audit/cleanup.php';
    }
}
