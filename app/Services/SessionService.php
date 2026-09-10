<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Service de gestion des 3 sessions de caisse numérotées
 */
class SessionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère la session active d'un utilisateur
     */
    public function getActiveSession(int $userId, int $sessionNumber): ?array
    {
        $sql = "SELECT cs.* FROM caisse_sessions cs 
                WHERE cs.utilisateur_id = :user_id 
                AND cs.numero_session = :session_number 
                AND cs.statut = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'session_number' => $sessionNumber
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les sessions actives
     */
    public function getAllActiveSessions(): array
    {
        $sql = "SELECT cs.*, u.username, u.role 
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.statut = 'OUVERTE'
                ORDER BY cs.numero_session";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ouvre une nouvelle session de caisse
     */
    public function openSession(int $userId, float $montantOuverture, int $sessionNumber): int
    {
        try {
            $this->db->beginTransaction();

            // Vérifier qu'aucune session n'est déjà ouverte pour cet utilisateur
            $existingSession = $this->getActiveSession($userId, $sessionNumber);
            if ($existingSession) {
                throw new Exception("Session {$sessionNumber} déjà ouverte pour cet utilisateur");
            }

            // Insérer la nouvelle session
            $sql = "INSERT INTO caisse_sessions (
                        utilisateur_id, 
                        numero_session, 
                        montant_ouverture, 
                        date_ouverture, 
                        statut, 
                        date_creation
                    ) VALUES (
                        :user_id, 
                        :session_number, 
                        :montant_ouverture, 
                        NOW(), 
                        'OUVERTE', 
                        NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'session_number' => $sessionNumber,
                'montant_ouverture' => $montantOuverture
            ]);

            $sessionId = $this->db->lastInsertId();
            
            // Journaliser l'ouverture
            $this->logSessionAction($sessionId, 'OUVERTURE', 'Session ouverte');

            $this->db->commit();
            return $sessionId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Ferme une session de caisse
     */
    public function closeSession(int $sessionId, float $montantFermeture): bool
    {
        try {
            $this->db->beginTransaction();

            // Récupérer la session
            $session = $this->getSessionById($sessionId);
            if (!$session || $session['statut'] !== 'OUVERTE') {
                throw new Exception("Session {$sessionId} invalide ou déjà fermée");
            }

            // Calculer le montant théorique
            $montantTheorique = $this->calculateMontantTheorique($sessionId);

            // Mettre à jour la session
            $sql = "UPDATE caisse_sessions SET 
                        montant_fermeture = :montant_fermeture,
                        montant_theorique = :montant_theorique,
                        date_fermeture = NOW(),
                        statut = 'FERMEE'
                    WHERE id = :session_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'session_id' => $sessionId,
                'montant_fermeture' => $montantFermeture,
                'montant_theorique' => $montantTheorique
            ]);

            // Journaliser la fermeture
            $this->logSessionAction($sessionId, 'FERMETURE', 'Session fermée');

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Récupère une session par son ID
     */
    public function getSessionById(int $sessionId): ?array
    {
        $sql = "SELECT * FROM caisse_sessions WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $sessionId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule le montant théorique d'une session
     */
    private function calculateMontantTheorique(int $sessionId): float
    {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN type_mouvement = 'ENTREE' THEN montant ELSE 0 END), 0) +
                    cs.montant_ouverture
                ) as montant_theorique
                FROM caisse_sessions cs
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.id = :session_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['session_id' => $sessionId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['montant_theorique'] ?? 0);
    }

    /**
     * Journalise les actions de session
     */
    private function logSessionAction(int $sessionId, string $action, string $description): void
    {
        $sql = "INSERT INTO session_logs (
                        session_id, 
                        action, 
                        description, 
                        date_action, 
                        utilisateur_id
                    ) VALUES (
                        :session_id, 
                        :action, 
                        :description, 
                        NOW(), 
                        (SELECT utilisateur_id FROM caisse_sessions WHERE id = :session_id)
                    )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'session_id' => $sessionId,
            'action' => $action,
            'description' => $description
        ]);
    }

    /**
     * Vérifie si un utilisateur peut ouvrir une session spécifique
     */
    public function canOpenSession(int $userId, int $sessionNumber): bool
    {
        $session = $this->getActiveSession($userId, $sessionNumber);
        return !$session; // Peut ouvrir si aucune session active pour ce numéro
    }

    /**
     * Vérifie si un utilisateur peut fermer une session
     */
    public function canCloseSession(int $userId, int $sessionId): bool
    {
        $session = $this->getSessionById($sessionId);
        if (!$session) {
            return false;
        }

        return $session['utilisateur_id'] === $userId && $session['statut'] === 'OUVERTE';
    }

    /**
     * Récupère les statistiques des sessions
     */
    public function getSessionStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN statut = 'OUVERTE' THEN 1 END) as sessions_ouvertes,
                    COUNT(CASE WHEN statut = 'FERMEE' THEN 1 END) as sessions_fermees,
                    SUM(montant_ouverture) as total_ouvertures,
                    SUM(montant_fermeture) as total_fermetures
                FROM caisse_sessions 
                WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
