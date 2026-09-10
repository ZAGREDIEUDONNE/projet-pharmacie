<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\AuditService;

/**
 * Contrôleur pour les fonctions système
 */
class SystemController extends BaseController
{
    private AuditService $auditService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService($this->db);
    }

    /**
     * Affiche le formulaire de changement de date
     */
    public function dateForm(): void
    {
        $this->denyAssistantSystemAccess();
        $this->requirePermission('date.change');
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());

        try {
            // Récupérer la date actuelle du système
            $currentDate = $this->getCurrentSystemDate();

            $this->render('system/date', [
                'title' => 'Changement de Date Système',
                'currentDate' => $currentDate,
                'returnTo' => $returnTo
            ]);

        } catch (\Exception $e) {
            error_log("SystemController::dateForm - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement de la date système'];
            $this->render('system/date', [
                'title' => 'Changement de Date Système',
                'currentDate' => date('Y-m-d'),
                'returnTo' => $returnTo
            ]);
        }
    }

    /**
     * Change la date système
     */
    public function changeDate(): void
    {
        $this->denyAssistantSystemAccess();
        $this->requirePermission('date.change');
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/date/change?return_to=' . urlencode($returnTo));
            return;
        }

        try {
            // Récupérer les données du formulaire
            $newDate = $_POST['new_date'] ?? '';
            $reason = $_POST['reason'] ?? '';

            // Valider les données
            $errors = $this->validateDateData($newDate, $reason);
            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/date/change?return_to=' . urlencode($returnTo));
                return;
            }

            // Récupérer l'ancienne date
            $oldDate = $this->getCurrentSystemDate();

            // Mettre à jour la date système
            $result = $this->updateSystemDate($newDate, $reason, $_SESSION['user_id']);

            if ($result['success']) {
                $_SESSION['success'] = 'Date système changée avec succès';
                
                // Journaliser l'action
                $this->auditService->logAction(
                    $_SESSION['user_id'],
                    'CHANGE_DATE',
                    'system_config',
                    null,
                    ['date' => $oldDate],
                    [
                        'date' => $newDate,
                        'reason' => $reason
                    ],
                    null,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                );
                
                $this->redirect($returnTo);
            } else {
                $_SESSION['errors'] = [$result['message']];
                $_SESSION['old_input'] = $_POST;
                $this->redirect('/date/change?return_to=' . urlencode($returnTo));
            }

        } catch (\Exception $e) {
            error_log("SystemController::changeDate - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du changement de date'];
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/date/change?return_to=' . urlencode($returnTo));
        }
    }

    /**
     * Affiche les informations système
     */
    public function info(): void
    {
        $this->denyAssistantSystemAccess();
        $this->requirePermission('date.change');

        try {
            $systemInfo = $this->getSystemInfo();

            $this->render('system/info', [
                'title' => 'Informations Système',
                'systemInfo' => $systemInfo
            ]);

        } catch (\Exception $e) {
            error_log("SystemController::info - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement des informations système'];
            $this->redirectBack('/date/change');
        }
    }

    /**
     * Récupère la date actuelle du système
     */
    private function getCurrentSystemDate(): string
    {
        // Pour l'instant, on utilise la date du serveur
        // Dans un vrai système, cela pourrait venir d'une configuration
        try {
            $stmt = $this->db->prepare("SELECT config_value FROM system_config WHERE config_key = 'system_date'");
            $stmt->execute();
            $configuredDate = $stmt->fetchColumn();

            return $configuredDate ?: date('Y-m-d');
        } catch (\Exception $e) {
            error_log("SystemController::getCurrentSystemDate - " . $e->getMessage());
            return date('Y-m-d');
        }
    }

    private function denyAssistantSystemAccess(): void
    {
        $this->requireAuth();

        if ((int)($this->getCurrentUser()['role_id'] ?? 0) === 3) {
            http_response_code(403);
            echo 'Acces refuse';
            exit;
        }
    }

    /**
     * Met à jour la date système
     */
    private function updateSystemDate(string $newDate, string $reason, int $userId): array
    {
        try {
            // Dans un vrai système, cela mettrait à jour une configuration
            // Pour l'instant, on simule avec une table de log
            $sql = "INSERT INTO system_date_changes (old_date, new_date, reason, utilisateur_id, created_at) 
                    VALUES (:old_date, :new_date, :reason, :user_id, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                'old_date' => $this->getCurrentSystemDate(),
                'new_date' => $newDate,
                'reason' => $reason,
                'user_id' => $userId
            ]);

            if ($result) {
                // Mettre à jour la date système dans une table de config
                $sql = "INSERT INTO system_config (config_key, config_value)
                        VALUES ('system_date', :new_date)
                        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['new_date' => $newDate]);
            }

            return [
                'success' => $result,
                'message' => $result ? 'Date mise à jour' : 'Erreur lors de la mise à jour'
            ];

        } catch (\Exception $e) {
            error_log("SystemController::updateSystemDate - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la date'
            ];
        }
    }

    /**
     * Récupère les informations système
     */
    private function getSystemInfo(): array
    {
        return [
            'current_date' => $this->getCurrentSystemDate(),
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->getMySQLVersion(),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Récupère la version MySQL
     */
    private function getMySQLVersion(): string
    {
        try {
            $stmt = $this->db->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            return $result['version'] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Valide les données de changement de date
     */
    private function validateDateData(string $date, string $reason): array
    {
        $errors = [];

        if (empty($date)) {
            $errors[] = 'La nouvelle date est obligatoire';
        }

        // Valider le format de la date
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            $errors[] = 'Format de date invalide (AAAA-MM-JJ requis)';
        } else {
            // Vérifier que la date n'est pas dans le futur (plus de 1 jour)
            $now = new \DateTime();
            if ($dateObj > $now->modify('+1 day')) {
                $errors[] = 'La date ne peut pas être dans le futur';
            }
        }

        if (empty($reason)) {
            $errors[] = 'La raison du changement est obligatoire';
        }

        if (strlen($reason) < 5) {
            $errors[] = 'La raison doit contenir au moins 5 caractères';
        }

        return $errors;
    }

    /**
     * API pour récupérer la date système
     */
    public function apiDate(): void
    {
        $this->requirePermission('date.view');

        try {
            $currentDate = $this->getCurrentSystemDate();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'current_date' => $currentDate,
                    'formatted_date' => date('d/m/Y', strtotime($currentDate))
                ]
            ]);

        } catch (\Exception $e) {
            error_log("SystemController::apiDate - " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du chargement de la date système'
            ]);
        }
    }

    /**
     * API pour récupérer les informations système
     */
    public function apiInfo(): void
    {
        $this->requirePermission('date.change');

        try {
            $systemInfo = $this->getSystemInfo();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $systemInfo
            ]);

        } catch (\Exception $e) {
            error_log("SystemController::apiInfo - " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du chargement des informations système'
            ]);
        }
    }
}
