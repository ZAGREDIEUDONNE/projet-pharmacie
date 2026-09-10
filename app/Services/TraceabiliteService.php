<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Service de traçabilité pour corrections de stock et annulations
 */
class TraceabiliteService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Enregistre une correction de stock avec traçabilité complète
     */
    public function logCorrectionStock(
        int $utilisateurId,
        int $produitId,
        float $ancienneQuantite,
        float $nouvelleQuantite,
        string $motif,
        ?string $details = null
    ): void {
        try {
            $sql = "INSERT INTO trace_corrections_stock (
                        utilisateur_id,
                        produit_id,
                        ancienne_quantite,
                        nouvelle_quantite,
                        motif,
                        details,
                        date_correction
                    ) VALUES (
                        :utilisateur_id,
                        :produit_id,
                        :ancienne_quantite,
                        :nouvelle_quantite,
                        :motif,
                        :details,
                        NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'utilisateur_id' => $utilisateurId,
                'produit_id' => $produitId,
                'ancienne_quantite' => $ancienneQuantite,
                'nouvelle_quantite' => $nouvelleQuantite,
                'motif' => $motif,
                'details' => $details
            ]);

        } catch (Exception $e) {
            error_log("TraceabiliteService::logCorrectionStock - " . $e->getMessage());
        }
    }

    /**
     * Enregistre une annulation de ticket/saisie
     */
    public function logAnnulation(
        int $utilisateurId,
        string $type, // 'TICKET_VENTE', 'SAISIE_STOCK', 'COMMANDE'
        int $documentId,
        string $motif,
        ?string $details = null
    ): void {
        try {
            $sql = "INSERT INTO trace_annulations (
                        utilisateur_id,
                        type_document,
                        document_id,
                        motif,
                        details,
                        date_annulation
                    ) VALUES (
                        :utilisateur_id,
                        :type_document,
                        :document_id,
                        :motif,
                        :details,
                        NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'utilisateur_id' => $utilisateurId,
                'type_document' => $type,
                'document_id' => $documentId,
                'motif' => $motif,
                'details' => $details
            ]);

        } catch (Exception $e) {
            error_log("TraceabiliteService::logAnnulation - " . $e->getMessage());
        }
    }

    /**
     * Récupère l'historique des corrections de stock
     */
    public function getHistoriqueCorrectionsStock(int $produitId = null, ?int $utilisateurId = null): array
    {
        try {
            $sql = "SELECT tcs.*, u.username, p.nom as produit_nom
                    FROM trace_corrections_stock tcs
                    JOIN utilisateurs u ON tcs.utilisateur_id = u.id
                    LEFT JOIN produits p ON tcs.produit_id = p.id
                    WHERE 1=1";
            
            $params = [];
            if ($produitId) {
                $sql .= " AND tcs.produit_id = :produit_id";
                $params['produit_id'] = $produitId;
            }
            
            if ($utilisateurId) {
                $sql .= " AND tcs.utilisateur_id = :utilisateur_id";
                $params['utilisateur_id'] = $utilisateurId;
            }
            
            $sql .= " ORDER BY tcs.date_correction DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("TraceabiliteService::getHistoriqueCorrectionsStock - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère l'historique des annulations
     */
    public function getHistoriqueAnnulations(string $type = null, ?int $utilisateurId = null): array
    {
        try {
            $sql = "SELECT ta.*, u.username
                    FROM trace_annulations ta
                    JOIN utilisateurs u ON ta.utilisateur_id = u.id
                    WHERE 1=1";
            
            $params = [];
            if ($type) {
                $sql .= " AND ta.type_document = :type";
                $params['type'] = $type;
            }
            
            if ($utilisateurId) {
                $sql .= " AND ta.utilisateur_id = :utilisateur_id";
                $params['utilisateur_id'] = $utilisateurId;
            }
            
            $sql .= " ORDER BY ta.date_annulation DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("TraceabiliteService::getHistoriqueAnnulations - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les statistiques de traçabilité
     */
    public function getStatistiquesTraceabilite(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(CASE WHEN type_document = 'TICKET_VENTE' THEN 1 END) as total_annulations_tickets,
                        COUNT(CASE WHEN type_document = 'SAISIE_STOCK' THEN 1 END) as total_annulations_saisies,
                        COUNT(CASE WHEN type_document = 'COMMANDE' THEN 1 END) as total_annulations_commandes,
                        COUNT(CASE WHEN motif LIKE '%erreur%' THEN 1 END) as total_corrections_erreurs,
                        COUNT(CASE WHEN motif LIKE '%perte%' THEN 1 END) as total_corrections_pertes
                    FROM trace_annulations 
                    WHERE date_annulation >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("TraceabiliteService::getStatistiquesTraceabilite - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifie si une correction de stock nécessite une autorisation
     */
    public function requiresAutorisationCorrection(float $differenceQuantite): bool
    {
        // Toute correction > 10% de la quantité nécessite une autorisation
        return abs($differenceQuantite) > 10;
    }

    /**
     * Génère un rapport de traçabilité pour l'administrateur
     */
    public function generateRapportTraceabilite(array $filtres = []): array
    {
        try {
            $sql = "SELECT 
                        tcs.date_correction,
                        u.username,
                        p.nom as produit_nom,
                        tcs.ancienne_quantite,
                        tcs.nouvelle_quantite,
                        ABS(tcs.nouvelle_quantite - tcs.ancienne_quantite) as difference,
                        tcs.motif
                    FROM trace_corrections_stock tcs
                    JOIN utilisateurs u ON tcs.utilisateur_id = u.id
                    LEFT JOIN produits p ON tcs.produit_id = p.id
                    WHERE 1=1";
            
            $params = [];
            
            // Filtres
            if (!empty($filtres['date_debut'])) {
                $sql .= " AND tcs.date_correction >= :date_debut";
                $params['date_debut'] = $filtres['date_debut'];
            }
            
            if (!empty($filtres['date_fin'])) {
                $sql .= " AND tcs.date_correction <= :date_fin";
                $params['date_fin'] = $filtres['date_fin'];
            }
            
            if (!empty($filtres['utilisateur_id'])) {
                $sql .= " AND tcs.utilisateur_id = :utilisateur_id";
                $params['utilisateur_id'] = $filtres['utilisateur_id'];
            }
            
            $sql .= " ORDER BY tcs.date_correction DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("TraceabiliteService::generateRapportTraceabilite - " . $e->getMessage());
            return [];
        }
    }
}
