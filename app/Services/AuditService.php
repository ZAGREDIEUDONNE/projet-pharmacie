<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class AuditService
{
    private PDO $db;
    private AuditTranslationService $translationService;

    public function __construct(PDO $db, AuditTranslationService $translationService = null)
    {
        $this->db = $db;
        $this->translationService = $translationService ?? new AuditTranslationService();
    }

    /**
     * Enregistre une action dans l'audit avec la structure réelle de la table
     */
    public function logAction(?int $userId, string $action, string $tableName, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null, ?string $details = null, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        try {
            $sql = "INSERT INTO audit_logs (utilisateur_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, date_action)
                    VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);

            $this->logRequestContext((int)$this->db->lastInsertId());

        } catch (Exception $e) {
            error_log("AuditService::logAction - " . $e->getMessage());
        }
    }

    private function logRequestContext(int $auditLogId): void
    {
        if ($auditLogId <= 0 || !$this->tableExists('audit_context')) {
            return;
        }

        try {
            $sql = "INSERT INTO audit_context (audit_log_id, machine, request_uri, method, created_at)
                    VALUES (:audit_log_id, :machine, :request_uri, :method, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'audit_log_id' => $auditLogId,
                'machine' => gethostname() ?: null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            ]);
        } catch (Exception $e) {
            error_log("AuditService::logRequestContext - " . $e->getMessage());
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));
            return $stmt && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Récupère les logs d'audit pour une table spécifique
     */
    public function getAuditLogs(string $tableName, ?int $recordId = null, int $limit = 100): array
    {
        try {
            $sql = "SELECT al.*, u.username 
                    FROM audit_logs al
                    LEFT JOIN utilisateurs u ON al.utilisateur_id = u.id
                    WHERE al.table_name = :table_name";
            
            $params = ['table_name' => $tableName];
            
            if ($recordId) {
                $sql .= " AND al.record_id = :record_id";
                $params['record_id'] = $recordId;
            }
            
            $sql .= " ORDER BY al.date_action DESC LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("AuditService::getAuditLogs - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère tous les logs d'audit avec traduction
     */
    public function getAllAuditLogs(int $limit = 100, int $sinceId = 0): array
    {
        try {
            $sql = "SELECT al.*, u.username 
                    FROM audit_logs al
                    LEFT JOIN utilisateurs u ON al.utilisateur_id = u.id";
            $params = [];

            if ($sinceId > 0) {
                $sql .= " WHERE al.id > :since_id";
                $params['since_id'] = $sinceId;
            }

            $sql .= " ORDER BY al.date_action DESC, al.id DESC LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            if ($sinceId > 0) {
                $stmt->bindValue(':since_id', $sinceId, \PDO::PARAM_INT);
            }
            $stmt->execute();
            
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return array_map(fn(array $log) => $this->enrichAuditLog($log), $logs);

        } catch (\Exception $e) {
            error_log("AuditService::getAllAuditLogs - " . $e->getMessage());
            return [];
        }
    }

    private function enrichAuditLog(array $log): array
    {
        $details = null;
        if (!empty($log['new_values'])) {
            $details = json_decode((string)$log['new_values'], true);
        } elseif (!empty($log['old_values'])) {
            $details = json_decode((string)$log['old_values'], true);
        }

        $action = (string)($log['action'] ?? '');
        $log['action_fr'] = $this->translationService->getActionDescription($action, is_array($details) ? $details : null);
        $log['table_name_fr'] = $this->translationService->translateTableName((string)($log['table_name'] ?? ''));
        $log['action_category'] = $this->translationService->getActionCategory($action);
        $log['description_complete'] = $this->translationService->formatAuditMessage([
            'action' => $action,
            'created_at' => $log['date_action'] ?? null,
            'details' => $details,
        ]);

        return $log;
    }

    /**
     * Récupère les logs d'audit pour un utilisateur spécifique avec traduction
     */
    public function getUserAuditLogs(int $userId, int $limit = 50): array
    {
        try {
            $sql = "SELECT al.*, u.username 
                    FROM audit_logs al
                    LEFT JOIN utilisateurs u ON al.utilisateur_id = u.id
                    WHERE al.utilisateur_id = :user_id
                    ORDER BY al.date_action DESC 
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Ajouter les traductions
            foreach ($logs as &$log) {
                $details = $log['new_values'] ? json_decode($log['new_values'], true) : null;
                $log['action_fr'] = $this->translationService->getActionDescription($log['action'], $details);
                $log['description_complete'] = $this->translationService->formatAuditMessage($log);
            }
            
            return $logs;

        } catch (\Exception $e) {
            error_log("AuditService::getUserAuditLogs - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enregistre un événement système
     */
    public function logEvent(
        string $eventType, 
        string $eventName, 
        ?string $description = null, 
        ?array $data = null, 
        ?int $utilisateurId = null
    ): void {
        try {
            $sql = "INSERT INTO events (
                        event_type,
                        event_name,
                        description,
                        data,
                        utilisateur_id,
                        date_event
                    ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $eventType,
                $eventName,
                $description,
                $data ? json_encode($data) : null,
                $utilisateurId
            ]);
            
        } catch (Exception $e) {
            error_log("Erreur d'événement: " . $e->getMessage());
        }
    }

    /**
     * Récupère l'historique d'audit pour une table
     */
    public function getAuditHistory(string $tableName, ?int $recordId = null, int $limit = 100): array
    {
        $sql = "SELECT al.*, u.username 
                FROM audit_logs al
                LEFT JOIN utilisateurs u ON al.utilisateur_id = u.id
                WHERE al.table_name = ?";
        
        $params = [$tableName];
        
        if ($recordId) {
            $sql .= " AND al.record_id = ?";
            $params[] = $recordId;
        }
        
        $sql .= " ORDER BY al.date_action DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Décoder les JSON
        foreach ($results as &$result) {
            $result['old_values'] = $result['old_values'] ? json_decode($result['old_values'], true) : null;
            $result['new_values'] = $result['new_values'] ? json_decode($result['new_values'], true) : null;
        }
        
        return $results;
    }

    /**
     * Récupère les activités récentes d'un utilisateur
     */
    public function getUserActivities(int $utilisateurId, int $limit = 50): array
    {
        $sql = "SELECT * FROM audit_logs 
                WHERE utilisateur_id = ? 
                ORDER BY date_action DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utilisateurId, $limit]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$result) {
            $result['old_values'] = $result['old_values'] ? json_decode($result['old_values'], true) : null;
            $result['new_values'] = $result['new_values'] ? json_decode($result['new_values'], true) : null;
        }
        
        return $results;
    }

    /**
     * Génère le rapport d'audit pour une période
     */
    public function generateAuditReport(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT utilisateur_id) as nombre_utilisateurs,
                    COUNT(DISTINCT table_name) as nombre_tables,
                    action,
                    COUNT(*) as nombre_actions
                FROM audit_logs 
                WHERE date_action BETWEEN ? AND ?
                GROUP BY action
                ORDER BY nombre_actions DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
