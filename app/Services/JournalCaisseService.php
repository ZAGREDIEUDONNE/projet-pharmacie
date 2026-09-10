<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class JournalCaisseService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère le journal de caisse avec filtres
     */
    public function getJournal(array $filters = []): array
    {
        $sql = "SELECT mc.*, 
                       cs.numero_session,
                       u.username as utilisateur_nom
                FROM mouvements_caisse mc
                LEFT JOIN caisse_sessions cs ON mc.caisse_session_id = cs.id
                LEFT JOIN utilisateurs u ON mc.utilisateur_id = u.id
                WHERE mc.supprime = 0";
        
        $params = [];
        
        // Filtre période
        if (!empty($filters['date_debut'])) {
            $sql .= " AND DATE(mc.date_mouvement) >= ?";
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= " AND DATE(mc.date_mouvement) <= ?";
            $params[] = $filters['date_fin'];
        }
        
        // Filtre utilisateur
        if (!empty($filters['utilisateur_id'])) {
            $sql .= " AND mc.utilisateur_id = ?";
            $params[] = $filters['utilisateur_id'];
        }
        
        // Filtre session
        if (!empty($filters['session_id'])) {
            $sql .= " AND mc.caisse_session_id = ?";
            $params[] = $filters['session_id'];
        }
        
        // Filtre type d'opération
        if (!empty($filters['type_operation'])) {
            $sql .= " AND mc.type_mouvement = ?";
            $params[] = $filters['type_operation'];
        }
        
        // Filtre référence
        if (!empty($filters['reference'])) {
            $sql .= " AND (mc.reference LIKE ? OR mc.description LIKE ?)";
            $params[] = "%{$filters['reference']}%";
            $params[] = "%{$filters['reference']}%";
        }
        
        // Filtre montant minimum
        if (!empty($filters['montant_min'])) {
            $sql .= " AND mc.montant >= ?";
            $params[] = $filters['montant_min'];
        }
        
        // Filtre montant maximum
        if (!empty($filters['montant_max'])) {
            $sql .= " AND mc.montant <= ?";
            $params[] = $filters['montant_max'];
        }
        
        $sql .= " ORDER BY mc.date_mouvement DESC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques du journal
     */
    public function getStatistiques(array $filters = []): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN mc.type_mouvement IN ('VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE') THEN mc.montant ELSE 0 END), 0) as total_encaissements,
                    COALESCE(SUM(CASE WHEN mc.type_mouvement IN ('REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE') THEN mc.montant ELSE 0 END), 0) as total_decaissements,
                    COUNT(*) as total_operations,
                    SUM(CASE WHEN mc.type_mouvement = 'ANNULATION_VENTE' THEN 1 ELSE 0 END) as nombre_annulations,
                    SUM(CASE WHEN mc.type_mouvement = 'REMBOURSEMENT' THEN 1 ELSE 0 END) as nombre_remboursements,
                    COALESCE((SELECT solde_apres FROM mouvements_caisse WHERE supprime = 0 ORDER BY date_mouvement DESC LIMIT 1), 0) as solde_actuel
                FROM mouvements_caisse mc
                WHERE mc.supprime = 0";
        
        $params = [];
        
        // Appliquer les mêmes filtres de période
        if (!empty($filters['date_debut'])) {
            $sql .= " AND DATE(mc.date_mouvement) >= ?";
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= " AND DATE(mc.date_mouvement) <= ?";
            $params[] = $filters['date_fin'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le détail d'une opération
     */
    public function getOperationDetail(int $id): array
    {
        $sql = "SELECT mc.*, 
                       cs.numero_session,
                       u.username as utilisateur_nom
                FROM mouvements_caisse mc
                LEFT JOIN caisse_sessions cs ON mc.caisse_session_id = cs.id
                LEFT JOIN utilisateurs u ON mc.utilisateur_id = u.id
                WHERE mc.id = ? AND mc.supprime = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $operation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$operation) {
            throw new Exception("Opération non trouvée");
        }
        
        return $operation;
    }

    /**
     * Récupère la liste des utilisateurs pour les filtres
     */
    public function getUtilisateurs(): array
    {
        $sql = "SELECT DISTINCT u.id, u.username 
                FROM utilisateurs u
                INNER JOIN mouvements_caisse mc ON u.id = mc.utilisateur_id
                WHERE mc.supprime = 0
                ORDER BY u.username";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des sessions pour les filtres
     */
    public function getSessions(): array
    {
        $sql = "SELECT DISTINCT cs.id, cs.numero_session, cs.date_ouverture
                FROM caisse_sessions cs
                INNER JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE mc.supprime = 0
                ORDER BY cs.date_ouverture DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte le journal en format Excel
     */
    public function exportExcel(array $filters = []): string
    {
        $operations = $this->getJournal($filters);
        
        $output = "\xEF\xBB\xBF<table border='1'>";
        $output .= "<thead><tr>";
        $output .= "<th>Date</th>";
        $output .= "<th>Heure</th>";
        $output .= "<th>Type d'opération</th>";
        $output .= "<th>Référence</th>";
        $output .= "<th>Utilisateur</th>";
        $output .= "<th>Entrée (FCFA)</th>";
        $output .= "<th>Sortie (FCFA)</th>";
        $output .= "<th>Solde après (FCFA)</th>";
        $output .= "<th>Observation</th>";
        $output .= "</tr></thead><tbody>";
        
        foreach ($operations as $op) {
            $date = date('d/m/Y', strtotime($op['date_mouvement']));
            $heure = date('H:i', strtotime($op['date_mouvement']));
            $type = $this->getLibelleType($op['type_mouvement']);
            $reference = $op['reference'] ?? '-';
            $utilisateur = $op['utilisateur_nom'] ?? '-';
            $observation = $op['description'] ?? '-';
            
            $entree = in_array($op['type_mouvement'], ['VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE']) 
                ? number_format($op['montant'], 0, ',', ' ') 
                : '';
            $sortie = in_array($op['type_mouvement'], ['REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE']) 
                ? number_format($op['montant'], 0, ',', ' ') 
                : '';
            $solde = number_format($op['solde_apres'], 0, ',', ' ');
            
            $output .= "<tr>";
            $output .= "<td>{$date}</td>";
            $output .= "<td>{$heure}</td>";
            $output .= "<td>{$type}</td>";
            $output .= "<td>{$reference}</td>";
            $output .= "<td>{$utilisateur}</td>";
            $output .= "<td>{$entree}</td>";
            $output .= "<td>{$sortie}</td>";
            $output .= "<td>{$solde}</td>";
            $output .= "<td>{$observation}</td>";
            $output .= "</tr>";
        }
        
        $output .= "</tbody></table>";
        
        return $output;
    }

    /**
     * Retourne le libellé d'un type d'opération
     */
    private function getLibelleType(string $type): string
    {
        $libelles = [
            'VENTE' => 'Vente comptant',
            'REMBOURSEMENT' => 'Remboursement',
            'APPROVISIONNEMENT' => 'Approvisionnement',
            'RETRAIT' => 'Retrait',
            'DECAISSEMENT' => 'Décaissement',
            'OUVERTURE_CAISSE' => 'Ouverture caisse',
            'FERMETURE_CAISSE' => 'Fermeture caisse',
            'ENCAISSEMENT_CLIENT' => 'Encaissement client',
            'REGLEMENT_CREANCE' => 'Règlement créance',
            'ACOMPTE_CLIENT' => 'Acompte client',
            'ANNULATION_VENTE' => 'Annulation vente',
            'CORRECTION_CAISSE' => 'Correction caisse',
            'AJUSTEMENT_CAISSE' => 'Ajustement caisse',
            'DEPOT_BANCAIRE' => 'Dépôt bancaire',
            'RETRAIT_BANCAIRE' => 'Retrait bancaire'
        ];
        
        return $libelles[$type] ?? $type;
    }

    /**
     * Vérifie si un type est une entrée
     */
    public function isEntree(string $type): bool
    {
        return in_array($type, [
            'VENTE',
            'APPROVISIONNEMENT',
            'ENCAISSEMENT_CLIENT',
            'REGLEMENT_CREANCE',
            'ACOMPTE_CLIENT',
            'DEPOT_BANCAIRE'
        ]);
    }

    /**
     * Vérifie si un type est une sortie
     */
    public function isSortie(string $type): bool
    {
        return in_array($type, [
            'REMBOURSEMENT',
            'DECAISSEMENT',
            'RETRAIT',
            'ANNULATION_VENTE',
            'RETRAIT_BANCAIRE'
        ]);
    }
}
