<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class CaisseSessionService
{
    private PDO $db;
    private RolePermissionService $rolePermissionService;

    public function __construct(PDO $db, RolePermissionService $rolePermissionService)
    {
        $this->db = $db;
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Ouvre une session de caisse (1, 2 ou 3)
     */
    public function ouvrirSession(int $userId, string $sessionNumber, float $montantOuverture): array
    {
        try {
            // Vérifier la permission
            if (!$this->rolePermissionService->hasPermission($userId, 'session_change')) {
                return [
                    'success' => false,
                    'message' => 'Permission refusée: changement de session non autorisé'
                ];
            }

            // Vérifier si l'utilisateur a déjà une session ouverte
            $sessionExistante = $this->getSessionActive($userId);
            if ($sessionExistante) {
                return [
                    'success' => false,
                    'message' => 'L\'utilisateur a déjà une session ouverte (' . $sessionExistante['session_number'] . ')'
                ];
            }

            // Vérifier si la session est déjà utilisée par un autre utilisateur
            $sessionUtilisee = $this->getSessionByNumber($sessionNumber);
            if ($sessionUtilisee) {
                return [
                    'success' => false,
                    'message' => 'La session ' . $sessionNumber . ' est déjà utilisée par ' . $sessionUtilisee['username']
                ];
            }

            $this->db->beginTransaction();

            // Créer la nouvelle session
            $sql = "INSERT INTO caisse_sessions 
                    (session_number, utilisateur_id, date_ouverture, montant_ouverture, statut) 
                    VALUES (?, ?, ?, ?, 'OUVERTE')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionNumber, $userId, date('Y-m-d H:i:s'), $montantOuverture]);
            $sessionId = $this->db->lastInsertId();

            // Mettre à jour la session active de l'utilisateur
            $sql = "UPDATE utilisateurs SET session_active = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionNumber, $userId]);

            $this->db->commit();

            // Logger l'opération
            $this->logSessionOperation($userId, 'SESSION_OUVERTURE', [
                'session_id' => $sessionId,
                'session_number' => $sessionNumber,
                'montant_ouverture' => $montantOuverture
            ]);

            return [
                'success' => true,
                'session_id' => $sessionId,
                'session_number' => $sessionNumber,
                'message' => 'Session ' . $sessionNumber . ' ouverte avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'ouverture de session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ferme une session de caisse
     */
    public function fermerSession(int $userId, float $montantFermeture, string $notes = ''): array
    {
        try {
            // Vérifier la permission
            if (!$this->rolePermissionService->hasPermission($userId, 'caisse_arret')) {
                return [
                    'success' => false,
                    'message' => 'Permission refusée: arrêt caisse non autorisé'
                ];
            }

            $sessionActive = $this->getSessionActive($userId);
            if (!$sessionActive) {
                return [
                    'success' => false,
                    'message' => 'Aucune session active trouvée pour cet utilisateur'
                ];
            }

            $this->db->beginTransaction();

            // Calculer les totaux
            $totauxSession = $this->calculerTotauxSession($sessionActive['id']);

            // Mettre à jour la session
            $sql = "UPDATE caisse_sessions 
                    SET date_fermeture = ?, 
                        montant_fermeture = ?, 
                        ventes_count = ?, 
                        total_ventes = ?, 
                        total_especes = ?, 
                        total_cartes = ?, 
                        total_cheques = ?, 
                        total_credits = ?, 
                        total_remises = ?, 
                        ecarts = ?, 
                        statut = 'FERMEE',
                        notes = ?
                    WHERE id = ?";
            
            $ecart = $montantFermeture - $sessionActive['montant_ouverture'] - $totauxSession['total_ventes'];
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                date('Y-m-d H:i:s'),
                $montantFermeture,
                $totauxSession['ventes_count'],
                $totauxSession['total_ventes'],
                $totauxSession['total_especes'],
                $totauxSession['total_cartes'],
                $totauxSession['total_cheques'],
                $totauxSession['total_credits'],
                $totauxSession['total_remises'],
                $ecart,
                $notes,
                $sessionActive['id']
            ]);

            // Archiver dans l'historique
            $sql = "INSERT INTO caisse_sessions_history 
                    (session_number, utilisateur_id, date_ouverture, date_fermeture, 
                     montant_ouverture, montant_fermeture, ventes_count, total_ventes, 
                     ecarts, statut_fermeture, utilisateur_fermeture_id, notes_fermeture) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'NORMALE', ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $sessionActive['session_number'],
                $sessionActive['utilisateur_id'],
                $sessionActive['date_ouverture'],
                date('Y-m-d H:i:s'),
                $sessionActive['montant_ouverture'],
                $montantFermeture,
                $totauxSession['ventes_count'],
                $totauxSession['total_ventes'],
                $ecart,
                $userId,
                $notes
            ]);

            // Mettre à jour l'utilisateur
            $sql = "UPDATE utilisateurs SET session_active = NULL WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            $this->db->commit();

            // Logger l'opération
            $this->logSessionOperation($userId, 'SESSION_FERMETURE', [
                'session_id' => $sessionActive['id'],
                'session_number' => $sessionActive['session_number'],
                'montant_fermeture' => $montantFermeture,
                'ecart' => $ecart,
                'ventes_count' => $totauxSession['ventes_count'],
                'total_ventes' => $totauxSession['total_ventes']
            ]);

            return [
                'success' => true,
                'session_number' => $sessionActive['session_number'],
                'ecart' => $ecart,
                'ventes_count' => $totauxSession['ventes_count'],
                'total_ventes' => $totauxSession['total_ventes'],
                'message' => 'Session fermée avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la fermeture de session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change la session d'un utilisateur
     */
    public function changerSession(int $userId, string $nouvelleSession, float $montantOuverture): array
    {
        try {
            // Vérifier la permission
            if (!$this->rolePermissionService->hasPermission($userId, 'session_change')) {
                return [
                    'success' => false,
                    'message' => 'Permission refusée: changement de session non autorisé'
                ];
            }

            $sessionActive = $this->getSessionActive($userId);
            
            if ($sessionActive) {
                // Fermer d'abord la session actuelle
                $fermeture = $this->fermerSession($userId, $sessionActive['montant_ouverture'], 'Changement automatique de session');
                if (!$fermeture['success']) {
                    return $fermeture;
                }
            }

            // Ouvrir la nouvelle session
            return $this->ouvrirSession($userId, $nouvelleSession, $montantOuverture);

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du changement de session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère la session active d'un utilisateur
     */
    public function getSessionActive(int $userId): ?array
    {
        $sql = "SELECT cs.*, u.username 
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.utilisateur_id = ? AND cs.statut = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère une session par son numéro
     */
    public function getSessionByNumber(string $sessionNumber): ?array
    {
        $sql = "SELECT cs.*, u.username 
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.session_number = ? AND cs.statut = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionNumber]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère toutes les sessions actives
     */
    public function getAllSessionsActives(): array
    {
        $sql = "SELECT cs.*, u.username, u.nom, u.prenom 
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.statut = 'OUVERTE'
                ORDER BY cs.session_number";
        
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'historique des sessions
     */
    public function getHistoriqueSessions(?int $userId = null, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT csh.*, u.username as utilisateur_nom, uf.username as fermeture_nom
                FROM caisse_sessions_history csh
                JOIN utilisateurs u ON csh.utilisateur_id = u.id
                LEFT JOIN utilisateurs uf ON csh.utilisateur_fermeture_id = uf.id
                WHERE 1=1";
        
        $params = [];
        
        if ($userId) {
            $sql .= " AND csh.utilisateur_id = ?";
            $params[] = $userId;
        }
        
        if ($dateDebut) {
            $sql .= " AND csh.date_ouverture >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND csh.date_fermeture <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " ORDER BY csh.date_fermeture DESC LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule les totaux d'une session
     */
    private function calculerTotauxSession(int $sessionId): array
    {
        $sql = "SELECT 
                    COUNT(v.id) as ventes_count,
                    SUM(v.montant_ttc) as total_ventes,
                    SUM(CASE WHEN v.type_paiement = 'ESPECE' THEN v.montant_ttc ELSE 0 END) as total_especes,
                    SUM(CASE WHEN v.type_paiement = 'CARTE' THEN v.montant_ttc ELSE 0 END) as total_cartes,
                    SUM(CASE WHEN v.type_paiement = 'CHEQUE' THEN v.montant_ttc ELSE 0 END) as total_cheques,
                    SUM(CASE WHEN v.is_credit = 1 THEN v.montant_ttc ELSE 0 END) as total_credits,
                    SUM(v.montant_remise) as total_remises
                FROM ventes v
                WHERE v.caisse_session_id = ? AND v.statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'ventes_count' => (int)($result['ventes_count'] ?? 0),
            'total_ventes' => (float)($result['total_ventes'] ?? 0),
            'total_especes' => (float)($result['total_especes'] ?? 0),
            'total_cartes' => (float)($result['total_cartes'] ?? 0),
            'total_cheques' => (float)($result['total_cheques'] ?? 0),
            'total_credits' => (float)($result['total_credits'] ?? 0),
            'total_remises' => (float)($result['total_remises'] ?? 0)
        ];
    }

    /**
     * Vérifie si une session est disponible
     */
    public function isSessionDisponible(string $sessionNumber): bool
    {
        $session = $this->getSessionByNumber($sessionNumber);
        return $session === null;
    }

    /**
     * Met en pause une session
     */
    public function mettreEnPauseSession(int $userId): array
    {
        try {
            $sessionActive = $this->getSessionActive($userId);
            if (!$sessionActive) {
                return [
                    'success' => false,
                    'message' => 'Aucune session active trouvée'
                ];
            }

            $sql = "UPDATE caisse_sessions SET statut = 'EN_PAUSE' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$sessionActive['id']]);

            if ($result) {
                $this->logSessionOperation($userId, 'SESSION_PAUSE', [
                    'session_id' => $sessionActive['id'],
                    'session_number' => $sessionActive['session_number']
                ]);

                return [
                    'success' => true,
                    'message' => 'Session mise en pause'
                ];
            }

            return [
                'success' => false,
                'message' => 'Erreur lors de la mise en pause'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reprend une session en pause
     */
    public function reprendreSession(int $userId): array
    {
        try {
            $sessionActive = $this->getSessionActive($userId);
            if (!$sessionActive || $sessionActive['statut'] !== 'EN_PAUSE') {
                return [
                    'success' => false,
                    'message' => 'Aucune session en pause trouvée'
                ];
            }

            $sql = "UPDATE caisse_sessions SET statut = 'OUVERTE' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$sessionActive['id']]);

            if ($result) {
                $this->logSessionOperation($userId, 'SESSION_REPRISE', [
                    'session_id' => $sessionActive['id'],
                    'session_number' => $sessionActive['session_number']
                ]);

                return [
                    'success' => true,
                    'message' => 'Session reprise'
                ];
            }

            return [
                'success' => false,
                'message' => 'Erreur lors de la reprise'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Logger les opérations de session
     */
    private function logSessionOperation(int $userId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs 
                    (utilisateur_id, action, table_name, new_values, ip_address, user_agent) 
                    VALUES (?, 'CAISSE_SESSION', 'caisse_sessions', ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log session operation: " . $e->getMessage());
        }
    }

    /**
     * Statistiques des sessions
     */
    public function getSessionsStatistics(?string $dateDebut = null, ?string $dateFin = null): array
    {
        try {
            $sql = "SELECT 
                        session_number,
                        COUNT(*) as nombre_sessions,
                        AVG(ventes_count) as ventes_moyennes,
                        AVG(total_ventes) as ca_moyen,
                        AVG(ecarts) as ecart_moyen,
                        SUM(CASE WHEN ecarts > 0 THEN 1 ELSE 0 END) as ecarts_positifs,
                        SUM(CASE WHEN ecarts < 0 THEN 1 ELSE 0 END) as ecarts_negatifs
                    FROM caisse_sessions_history
                    WHERE 1=1";
            
            $params = [];
            
            if ($dateDebut) {
                $sql .= " AND date_ouverture >= ?";
                $params[] = $dateDebut;
            }
            
            if ($dateFin) {
                $sql .= " AND date_fermeture <= ?";
                $params[] = $dateFin;
            }
            
            $sql .= " GROUP BY session_number ORDER BY session_number";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
}
