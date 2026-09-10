<?php

namespace App\Models;

use PDO;
use PDOException;

class CaisseSession
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle session de caisse
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO caisse_sessions (
                    numero_session, 
                    caissier_id, 
                    date_ouverture, 
                    montant_ouverture,
                    montant_fermeture,
                    montant_ventes,
                    montant_theorique,
                    ecart,
                    statut_session,
                    notes_controle
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['numero_session'],
            $data['caissier_id'],
            $data['date_ouverture'] ?? date('Y-m-d H:i:s'),
            $data['montant_ouverture'] ?? 0,
            $data['montant_fermeture'] ?? 0,
            $data['montant_ventes'] ?? 0,
            $data['montant_theorique'] ?? 0,
            $data['ecart'] ?? 0,
            $data['statut_session'] ?? 'OUVERTE',
            $data['notes_controle'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Récupère une session par son ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT cs.*, 
                       u.username as caissier_nom,
                       u.nom as caissier_prenom
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.caissier_id = u.id
                WHERE cs.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère une session par son numéro
     */
    public function findByNumero(string $numeroSession): ?array
    {
        $sql = "SELECT * FROM caisse_sessions WHERE numero_session = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroSession]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère la session ouverte d'un caissier
     */
    public function findOuverteByCaissier(int $caissierId): ?array
    {
        $sql = "SELECT * FROM caisse_sessions 
                WHERE caissier_id = ? AND statut_session = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$caissierId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour une session
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE caisse_sessions SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Ferme une session de caisse
     */
    public function fermer(int $id, array $data): bool
    {
        $sql = "UPDATE caisse_sessions SET 
                    date_fermeture = NOW(),
                    montant_fermeture = ?,
                    montant_theorique = ?,
                    ecart = ? - ?,
                    statut_session = 'FERMEE',
                    notes_controle = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['montant_fermeture'],
            $data['montant_theorique'],
            $data['montant_fermeture'],
            $data['montant_theorique'],
            $data['notes_controle'] ?? null,
            $id
        ]);
    }

    /**
     * Marque une session comme contrôlée
     */
    public function controler(int $id): bool
    {
        $sql = "UPDATE caisse_sessions SET 
                    statut_session = 'CONTROLEE',
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Ajoute un montant de vente à la session
     */
    public function ajouterVente(int $id, float $montant): bool
    {
        $sql = "UPDATE caisse_sessions SET 
                    montant_ventes = montant_ventes + ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$montant, $id]);
    }

    /**
     * Récupère l'historique des sessions d'un caissier
     */
    public function getHistoriqueCaissier(int $caissierId, int $limit = 50): array
    {
        $sql = "SELECT cs.*, 
                       COUNT(mc.id) as nombre_mouvements,
                       SUM(CASE WHEN mc.type_mouvement = 'VENTE' THEN mc.montant ELSE 0 END) as total_ventes
                FROM caisse_sessions cs
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.caissier_id = ?
                GROUP BY cs.id
                ORDER BY cs.date_ouverture DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$caissierId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sessions d'une période
     */
    public function getSessionsPeriode(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT cs.*, 
                       u.username as caissier_nom
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.caissier_id = u.id
                WHERE cs.date_ouverture BETWEEN ? AND ?
                ORDER BY cs.date_ouverture DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule le montant théorique d'une session
     */
    public function calculerMontantTheorique(int $sessionId): float
    {
        $sql = "SELECT 
                    montant_ouverture + 
                    COALESCE(SUM(CASE WHEN mc.type_mouvement = 'VENTE' THEN mc.montant ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN mc.type_mouvement = 'REMBOURSEMENT' THEN mc.montant ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN mc.type_mouvement = 'RETRAIT' THEN mc.montant ELSE 0 END), 0)
                FROM caisse_sessions cs
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        return (float) $stmt->fetchColumn();
    }

    /**
     * Récupère les sessions avec écarts
     */
    public function getSessionsAvecEcarts(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT cs.*, 
                       u.username as caissier_nom,
                       ABS(cs.ecart) as ecart_absolu
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.caissier_id = u.id
                WHERE cs.date_ouverture BETWEEN ? AND ?
                AND cs.statut_session IN ('FERMEE', 'CONTROLEE')
                AND cs.ecart != 0
                ORDER BY ABS(cs.ecart) DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des sessions
     */
    public function getStatistiques(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_sessions,
                    SUM(montant_ventes) as total_ventes,
                    AVG(montant_ventes) as moyenne_ventes,
                    SUM(montant_ouverture) as total_ouvertures,
                    AVG(ecart) as ecart_moyen,
                    COUNT(CASE WHEN ecart > 0 THEN 1 END) as sessions_ecart_positif,
                    COUNT(CASE WHEN ecart < 0 THEN 1 END) as sessions_ecart_negatif,
                    COUNT(CASE WHEN ecart = 0 THEN 1 END) as sessions_equilibrees,
                    SUM(CASE WHEN statut_session = 'CONTROLEE' THEN 1 ELSE 0 END) as sessions_controlees
                FROM caisse_sessions 
                WHERE date_ouverture BETWEEN ? AND ?
                AND statut_session IN ('FERMEE', 'CONTROLEE')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un caissier a une session ouverte
     */
    public function hasSessionOuverte(int $caissierId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM caisse_sessions 
                WHERE caissier_id = ? AND statut_session = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$caissierId]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Génère un numéro de session unique
     */
    public function genererNumeroSession(): string
    {
        $prefix = 'CS' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM caisse_sessions WHERE DATE(date_ouverture) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $prefix . str_pad($result['count'] + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère les sessions actives (ouvertes)
     */
    public function getSessionsActives(): array
    {
        $sql = "SELECT cs.*, 
                       u.username as caissier_nom,
                       TIMESTAMPDIFF(MINUTE, cs.date_ouverture, NOW()) as duree_minutes
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.caissier_id = u.id
                WHERE cs.statut_session = 'OUVERTE'
                ORDER BY cs.date_ouverture";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Force la fermeture de toutes les sessions ouvertes (admin)
     */
    public function fermerToutesSessionsOuvertes(): int
    {
        $sql = "UPDATE caisse_sessions SET 
                    statut_session = 'FERMEE',
                    date_fermeture = NOW(),
                    updated_at = NOW()
                WHERE statut_session = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}
