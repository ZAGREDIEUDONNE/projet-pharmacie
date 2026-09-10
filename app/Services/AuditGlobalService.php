<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class AuditGlobalService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Récupère le tableau de bord d'audit
     */
    public function getAuditDashboard(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d');
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-7 days'));
        
        // Statistiques générales
        $stats = $this->getGlobalStats($dateDebut, $dateFin);
        
        // Activités récentes
        $recentActivities = $this->auditService->getRecentActivities(10);
        
        // Événements système récents
        $systemEvents = $this->auditService->getSystemEvents(null, $dateDebut, $dateFin, 10);
        
        // Activités suspectes
        $suspiciousActivities = $this->auditService->analyzeSuspiciousActivities($dateDebut, $dateFin);
        
        // Top utilisateurs par activité
        $topUsers = $this->getTopUsers($dateDebut, $dateFin, 10);
        
        // Top modules par activité
        $topModules = $this->getTopModules($dateDebut, $dateFin, 10);
        
        return [
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'statistiques_globales' => $stats,
            'activites_recentes' => $recentActivities,
            'evenements_systeme' => $systemEvents,
            'activites_suspectes' => $suspiciousActivities,
            'top_utilisateurs' => $topUsers,
            'top_modules' => $topModules
        ];
    }

    /**
     * Récupère les statistiques globales
     */
    private function getGlobalStats(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as nombre_utilisateurs_actifs,
                    COUNT(DISTINCT module) as nombre_modules_actifs,
                    COUNT(DISTINCT action) as nombre_types_actions,
                    COUNT(CASE WHEN action LIKE '%CREATION%' THEN 1 END) as creations,
                    COUNT(CASE WHEN action LIKE '%MODIFICATION%' THEN 1 END) as modifications,
                    COUNT(CASE WHEN action LIKE '%SUPPRESSION%' THEN 1 END) as suppressions,
                    COUNT(CASE WHEN action LIKE '%CONNEXION%' THEN 1 END) as connexions,
                    COUNT(CASE WHEN action LIKE '%DECONNEXION%' THEN 1 END) as deconnexions,
                    COUNT(CASE WHEN action LIKE '%LOGIN_FAILED%' THEN 1 END) as echecs_connexion,
                    COUNT(CASE WHEN action LIKE '%PERMISSION_DENIED%' THEN 1 END) as permissions_refusees,
                    COUNT(CASE WHEN action LIKE '%UNAUTHORIZED_ACCESS%' THEN 1 END) as acces_non_autorise
                FROM audit_logs al
                WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateFin;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère les top utilisateurs par activité
     */
    private function getTopUsers(?string $dateDebut = null, ?string $dateFin = null, int $limit = 10): array
    {
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.nom,
                    COUNT(al.id) as nombre_actions,
                    COUNT(DISTINCT DATE(al.created_at)) as nombre_jours_actifs,
                    MAX(al.created_at) as derniere_action
                FROM utilisateurs u
                JOIN audit_logs al ON u.id = al.user_id
                WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY u.id, u.username, u.nom ORDER BY nombre_actions DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les top modules par activité
     */
    private function getTopModules(?string $dateDebut = null, ?string $dateFin = null, int $limit = 10): array
    {
        $sql = "SELECT 
                    al.module,
                    COUNT(*) as nombre_actions,
                    COUNT(DISTINCT user_id) as nombre_utilisateurs,
                    COUNT(DISTINCT action) as nombre_types_actions
                    MAX(al.created_at) as derniere_action
                FROM audit_logs al
                WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY al.module ORDER BY nombre_actions DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère un rapport complet d'audit
     */
    public function generateComprehensiveAuditReport(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d');
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        
        // Rapport d'audit
        $auditReport = $this->auditService->generateAuditReport($dateDebut, $dateFin);
        
        // Statistiques détaillées
        $detailedStats = $this->getDetailedStatistics($dateDebut, $dateFin);
        
        // Analyse de sécurité
        $securityAnalysis = $this->getSecurityAnalysis($dateDebut, $dateFin);
        
        // Analyse de performance
        $performanceAnalysis = $this->getPerformanceAnalysis($dateDebut, $dateFin);
        
        // Recommandations
        $recommendations = $this->generateRecommendations($detailedStats, $securityAnalysis);
        
        return [
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'rapport_audit' => $auditReport,
            'statistiques_detaillees' => $detailedStats,
            'analyse_securite' => $securityAnalysis,
            'analyse_performance' => $performanceAnalysis,
            'recommandations' => $recommendations
        ];
    }

    /**
     * Récupère les statistiques détaillées
     */
    private function getDetailedStatistics(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT 
                    al.module,
                    al.action,
                    COUNT(*) as nombre_actions,
                    COUNT(DISTINCT user_id) as nombre_utilisateurs,
                    AVG(TIMESTAMPDIFF(SECOND, al.created_at, al.created_at)) as temps_moyen_entre_actions
                FROM audit_logs al
                WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY al.module, al.action HAVING COUNT(*) > 0 ORDER BY nombre_actions DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Analyse la sécurité
     */
    private function getSecurityAnalysis(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN al.action = 'LOGIN_FAILED' THEN 1 END) as echecs_connexion,
                    COUNT(CASE WHEN al.action = 'PERMISSION_DENIED' THEN 1 END) as permissions_refusees,
                    COUNT(CASE WHEN al.action = 'UNAUTHORIZED_ACCESS' THEN 1 END) as acces_non_autorise,
                    COUNT(CASE WHEN al.action LIKE '%MULTIPLE_LOGIN%' THEN 1 END) as tentatives_multiples,
                    COUNT(DISTINCT al.user_id) as utilisateurs_concernes
                FROM audit_logs al
                WHERE al.action IN ('LOGIN_FAILED', 'PERMISSION_DENIED', 'UNAUTHORIZED_ACCESS', 'MULTIPLE_LOGIN')
                AND 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateFin;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $securityData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculer le score de risque
        $riskScore = 0;
        if ($securityData['echecs_connexion'] > 0) $riskScore += 3;
        if ($securityData['permissions_refusees'] > 0) $riskScore += 2;
        if ($securityData['acces_non_autorise'] > 0) $riskScore += 4;
        if ($securityData['tentatives_multiples'] > 0) $riskScore += 2;
        
        $riskLevel = 'FAIBLE';
        if ($riskScore >= 8) $riskLevel = 'ÉLEVÉ';
        elseif ($riskScore >= 4) $riskLevel = 'MOYEN';
        
        return [
            'donnees_securite' => $securityData,
            'score_risque' => $riskScore,
            'niveau_risque' => $riskLevel,
            'alertes' => $this->generateSecurityAlerts($securityData)
        ];
    }

    /**
     * Analyse la performance
     */
    private function getPerformanceAnalysis(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT 
                    al.module,
                    COUNT(*) as nombre_requetes,
                    AVG(TIMESTAMPDIFF(MICROSECOND, al.created_at, al.created_at)) as temps_moyen_reponse,
                    MAX(TIMESTAMPDIFF(MICROSECOND, al.created_at, al.created_at)) as temps_max_reponse,
                    COUNT(CASE WHEN al.action LIKE '%ERROR%' THEN 1 END) as erreurs_systeme
                FROM audit_logs al
                WHERE 1=1";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY al.module HAVING COUNT(*) > 0 ORDER BY temps_moyen_reponse DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $performanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Identifier les modules lents
        $slowModules = array_filter($performanceData, function($module) {
            return $module['temps_moyen_reponse'] > 1000; // > 1 seconde
        });
        
        return [
            'donnees_performance' => $performanceData,
            'modules_lents' => $slowModules,
            'alertes_performance' => $this->generatePerformanceAlerts($performanceData)
        ];
    }

    /**
     * Génère des alertes de sécurité
     */
    private function generateSecurityAlerts(array $securityData): array
    {
        $alerts = [];
        
        if ($securityData['echecs_connexion'] > 10) {
            $alerts[] = [
                'type' => 'SECURITY',
                'niveau' => 'ÉLEVÉ',
                'message' => 'Nombre élevé d\'échecs de connexion détecté',
                'valeur' => $securityData['echecs_connexion']
            ];
        }
        
        if ($securityData['acces_non_autorise'] > 5) {
            $alerts[] = [
                'type' => 'SECURITY',
                'niveau' => 'MOYEN',
                'message' => 'Tentatives d\'accès non autorisées détectées',
                'valeur' => $securityData['acces_non_autorise']
            ];
        }
        
        if ($securityData['utilisateurs_concernes'] > 20) {
            $alerts[] = [
                'type' => 'SECURITY',
                'niveau' => 'MOYEN',
                'message' => 'Activité suspecte détectée pour de nombreux utilisateurs',
                'valeur' => $securityData['utilisateurs_concernes']
            ];
        }
        
        return $alerts;
    }

    /**
     * Génère des alertes de performance
     */
    private function generatePerformanceAlerts(array $performanceData): array
    {
        $alerts = [];
        
        foreach ($performanceData as $module) {
            if ($module['temps_moyen_reponse'] > 2000) { // > 2 secondes
                $alerts[] = [
                    'type' => 'PERFORMANCE',
                    'niveau' => 'MOYEN',
                    'message' => 'Module ' . $module['module'] . ' lent détecté',
                    'module' => $module['module'],
                    'temps_moyen' => $module['temps_moyen_reponse']
                ];
            }
            
            if ($module['erreurs_systeme'] > 5) {
                $alerts[] = [
                    'type' => 'ERROR',
                    'niveau' => 'ÉLEVÉ',
                    'message' => 'Erreurs système détectées dans le module ' . $module['module'],
                    'module' => $module['module'],
                    'nombre_erreurs' => $module['erreurs_systeme']
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Génère des recommandations
     */
    private function generateRecommendations(array $stats, array $securityAnalysis): array
    {
        $recommendations = [];
        
        // Recommandations de sécurité
        if ($securityAnalysis['score_risque'] > 5) {
            $recommendations[] = [
                'type' => 'SECURITY',
                'priorite' => 'ÉLEVÉE',
                'message' => 'Renforcer les politiques de sécurité',
                'actions' => [
                    'Implémenter une politique de mots de passe robuste',
                    'Activer la double authentification',
                    'Bloquer les adresses IP suspectes',
                    'Augmenter la surveillance des accès'
                ]
            ];
        }
        
        // Recommandations de performance
        foreach ($stats['statistiques_detaillees'] as $module) {
            if ($module['temps_moyen_entre_actions'] > 500) { // > 500ms
                $recommendations[] = [
                    'type' => 'PERFORMANCE',
                    'priorite' => 'MOYEN',
                    'message' => 'Optimiser le module ' . $module['module'],
                    'actions' => [
                        'Analyser les requêtes SQL lentes',
                        'Optimiser les index de base de données',
                        'Implémenter le cache pour les données fréquemment accédées',
                        'Revoir la logique métier'
                    ]
                ];
            }
        }
        
        // Recommandations générales
        if ($stats['statistiques_globales']['echecs_connexion'] > 50) {
            $recommendations[] = [
                'type' => 'GENERAL',
                'priorite' => 'MOYEN',
                'message' => 'Taux d\'échecs de connexion élevé',
                'actions' => [
                    'Enquêter les tentatives d\'intrusion',
                    'Vérifier la politique de mots de passe',
                    'Considérer l\'implémentation de CAPTCHA'
                ]
            ];
        }
        
        return $recommendations;
    }

    /**
     * Exporte le rapport d'audit complet
     */
    public function exportComprehensiveReport(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $report = $this->generateComprehensiveAuditReport($dateDebut, $dateFin);
        
        return [
            'success' => true,
            'filename' => 'rapport_audit_complet_' . date('YmdHis') . '.json',
            'data' => $report,
            'export_date' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Configure les alertes automatiques
     */
    public function configureAutomaticAlerts(): array
    {
        // Récupérer la configuration actuelle
        $sql = "SELECT * FROM audit_config WHERE key IN ('alert_security_enabled', 'alert_performance_enabled', 'alert_email_enabled')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
        
        $configArray = [];
        foreach ($config as $item) {
            $configArray[$item['key']] = $item['value'];
        }
        
        return [
            'configuration' => $configArray,
            'alertes_actives' => [
                'securite' => $configArray['alert_security_enabled'] ?? false,
                'performance' => $configArray['alert_performance_enabled'] ?? false,
                'email' => $configArray['alert_email_enabled'] ?? false
            ]
        ];
    }

    /**
     * Met à jour la configuration des alertes
     */
    public function updateAlertConfig(string $key, bool $value): bool
    {
        $sql = "INSERT INTO audit_config (key, value, updated_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    value = VALUES(value), updated_at = VALUES(updated_at)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$key, $value ? '1' : '0']);
    }

    /**
     * Vérifie si les alertes sont configurées
     */
    public function isAlertConfigured(): bool
    {
        $sql = "SELECT COUNT(*) as count FROM audit_config WHERE key IN ('alert_security_enabled', 'alert_performance_enabled', 'alert_email_enabled') AND value = '1'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return (int) $stmt->fetchColumn() > 0;
    }
}
