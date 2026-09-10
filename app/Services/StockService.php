<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\MouvementStock;
use App\Models\Commande;
use App\Services\AuditService;
use PDO;
use PDOException;
use Exception;

class StockService
{
    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db, AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Vérifie la disponibilité d'un produit
     */
    public function verifierDisponibilite(int $produitId, int $quantiteRequise): array
    {
        $sql = "SELECT 
                    COALESCE(s.quantite_disponible, 0) as quantite_disponible,
                    COALESCE(s.quantite_theorique, 0) as quantite_theorique,
                    COALESCE(s.quantite_reservee, 0) as quantite_reservee,
                    s.id as stock_id,
                    p.stock_securite,
                    p.stock_alerte,
                    p.nom
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                WHERE p.id = ?
                AND p.is_actif = 1
                AND p.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock) {
            $stmtProduit = $this->db->prepare("SELECT id FROM produits WHERE id = ? AND is_actif = 1 AND deleted_at IS NULL");
            $stmtProduit->execute([$produitId]);
            if ($stmtProduit->fetchColumn()) {
                $stmtCreate = $this->db->prepare(
                    "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                     VALUES (?, 0, 0, 0, NOW(), NOW())"
                );
                $stmtCreate->execute([$produitId]);
                $stmt->execute([$produitId]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$stock) {
                return [
                'disponible' => false,
                'message' => 'Produit non trouvé',
                'quantite_disponible' => 0,
                'quantite_theorique' => 0
                ];
            }
        }
        
        if ($stock && $stock['stock_id'] === null) {
            $stmtCreate = $this->db->prepare(
                "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                 VALUES (?, 0, 0, 0, NOW(), NOW())"
            );
            $stmtCreate->execute([$produitId]);
        }

        $quantiteDisponible = (int)$stock['quantite_disponible'];
        $stockTheorique = (int)$stock['quantite_theorique'];
        
        return [
            'disponible' => $quantiteDisponible >= $quantiteRequise,
            'quantite_disponible' => $quantiteDisponible,
            'quantite_theorique' => $stockTheorique,
            'stock_securite' => $stock['stock_securite'],
            'stock_alerte' => $stock['stock_alerte'],
            'nom_produit' => $stock['nom'],
            'message' => $quantiteDisponible >= $quantiteRequise 
                ? 'Stock disponible' 
                : "Stock insuffisant: $quantiteDisponible disponible pour $quantiteRequise requise"
        ];
    }

    /**
     * Récupère le lot FIFO pour un produit
     * @deprecated Cette méthode n'est plus utilisée car le système fonctionne sans lots
     */
    public function getLotFIFO(int $produitId, int $quantite): ?array
    {
        // Retourne null car le système fonctionne maintenant sans lots
        return null;
    }

    /**
     * Déduit le stock pour une vente (FIFO)
     */
    public function deduireStock(int $produitId, int $quantite, ?int $lotId, string $referenceType, int $referenceId): void
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Récupérer le stock actuel
            $stockAvant = $this->getStockActuel($produitId);
            if ((int)$stockAvant['quantite_disponible'] < $quantite) {
                throw new Exception("Stock global insuffisant pour le produit ID: $produitId");
            }
            
            // Vérifier que le lot a suffisamment de quantité (si lot_id fourni)
            // Note: lot_id est ignoré car le système fonctionne sans lots
            // La vérification se fait uniquement sur le stock global
            
            // Mettre à jour le stock global
            $sql = "UPDATE stock SET 
                        quantite_disponible = quantite_disponible - ?,
                        quantite_theorique = GREATEST(quantite_theorique - ?, 0),
                        valeur_stock = GREATEST(valeur_stock - (? * (SELECT prix_achat FROM produits WHERE id = ?)), 0),
                        dernier_mouvement = NOW(),
                        updated_at = NOW()
                    WHERE produit_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$quantite, $quantite, $quantite, $produitId, $produitId]);
            
            // Enregistrer le mouvement de stock
            $this->enregistrerMouvement(
                $produitId,
                $lotId,
                'SORTIE',
                $quantite,
                $stockAvant['quantite_disponible'],
                $stockAvant['quantite_disponible'] - $quantite,
                $referenceType,
                $referenceId,
                "Vente - Produit ID: $produitId"
            );
            
            if ($ownsTransaction) {
                $this->db->commit();
            }
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw new Exception("Erreur lors de la déduction du stock: " . $e->getMessage());
        }
    }

    /**
     * Restaure le stock (annulation vente)
     */
    public function restaurerStock(int $produitId, int $quantite, ?int $lotId, string $referenceType, int $referenceId): void
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Récupérer le stock actuel
            $stockAvant = $this->getStockActuel($produitId);
            
            // Note: lot_id est ignoré car le système fonctionne sans lots
            // La restauration se fait uniquement sur le stock global
            
            // Restaurer le stock global
            $sql = "UPDATE stock SET 
                        quantite_disponible = quantite_disponible + ?,
                        quantite_theorique = quantite_theorique + ?,
                        valeur_stock = valeur_stock + (? * (SELECT prix_achat FROM produits WHERE id = ?)),
                        dernier_mouvement = NOW(),
                        updated_at = NOW()
                    WHERE produit_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$quantite, $quantite, $quantite, $produitId, $produitId]);
            
            // Enregistrer le mouvement de stock
            $this->enregistrerMouvement(
                $produitId,
                $lotId,
                'ENTREE',
                $quantite,
                $stockAvant['quantite_disponible'],
                $stockAvant['quantite_disponible'] + $quantite,
                $referenceType,
                $referenceId,
                "Annulation vente - Produit ID: $produitId"
            );
            
            if ($ownsTransaction) {
                $this->db->commit();
            }
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw new Exception("Erreur lors de la restauration du stock: " . $e->getMessage());
        }
    }

    /**
     * Ajoute du stock (réception commande)
     */
    public function ajouterStock(int $produitId, int $quantite, int $lotId, string $referenceType, int $referenceId): void
    {
        $this->db->beginTransaction();
        
        try {
            // Récupérer le stock actuel
            $stockAvant = $this->getStockActuel($produitId);
            
            // Mettre à jour le stock global
            $sql = "UPDATE stock SET 
                        quantite_disponible = quantite_disponible + ?,
                        quantite_theorique = quantite_theorique + ?,
                        valeur_stock = valeur_stock + (? * (SELECT prix_achat FROM produits WHERE id = ?)),
                        dernier_mouvement = NOW(),
                        updated_at = NOW()
                    WHERE produit_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$quantite, $quantite, $quantite, $produitId, $produitId]);
            
            // Enregistrer le mouvement de stock
            $this->enregistrerMouvement(
                $produitId,
                $lotId,
                'ENTREE',
                $quantite,
                $stockAvant['quantite_disponible'],
                $stockAvant['quantite_disponible'] + $quantite,
                $referenceType,
                $referenceId,
                "Réception commande - Produit ID: $produitId"
            );
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de l'ajout au stock: " . $e->getMessage());
        }
    }

    /**
     * Met à jour le stock théorique (réservations commandes)
     */
    public function mettreAJourStockTheorique(): void
    {
        $sql = "UPDATE stock s 
                SET quantite_theorique = GREATEST(
                    s.quantite_disponible - (
                        SELECT COALESCE(SUM(ci.quantite_commandee - ci.quantite_livree), 0)
                        FROM commande_items ci
                        JOIN commandes c ON ci.commande_id = c.id
                        WHERE ci.produit_id = s.produit_id 
                        AND c.statut_commande IN ('VALIDEE', 'PARTIELLEMENT_LIVREE')
                        AND c.deleted_at IS NULL
                    ), 0
                ),
                updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }

    /**
     * Récupère les produits en alerte de stock
     */
    public function getProduitsEnAlerte(): array
    {
        $sql = "SELECT 
                    p.id, p.nom, p.code_cip,
                    s.quantite_disponible, s.quantite_theorique,
                    p.stock_securite, p.stock_alerte,
                    CASE 
                        WHEN s.quantite_disponible <= p.stock_securite THEN 'CRITIQUE'
                        WHEN s.quantite_disponible <= p.stock_alerte THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END as niveau_stock
                FROM produits p
                JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1 
                AND p.deleted_at IS NULL
                AND s.quantite_disponible <= p.stock_alerte
                ORDER BY 
                    CASE 
                        WHEN s.quantite_disponible <= p.stock_securite THEN 1
                        ELSE 2
                    END,
                    s.quantite_disponible ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les produits proches de la péremption
     */
    public function getProduitsPeremptionProche(int $jours = 90): array
    {
        $sql = "SELECT 
                    p.id, p.nom, p.code_cip,
                    s.date_peremption,
                    s.quantite_disponible,
                    DATEDIFF(s.date_peremption, CURDATE()) as jours_restants,
                    CASE 
                        WHEN s.date_peremption IS NULL THEN 'NON_DEFINI'
                        WHEN DATEDIFF(s.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                        WHEN DATEDIFF(s.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                        WHEN DATEDIFF(s.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END as niveau_peremption
                FROM produits p
                JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1 
                AND p.deleted_at IS NULL
                AND s.quantite_disponible > 0
                AND s.date_peremption IS NOT NULL
                AND s.date_peremption <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY s.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jours]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistre un mouvement de stock
     */
    private function enregistrerMouvement(
        int $produitId, 
        ?int $lotId, 
        string $typeMouvement, 
        int $quantite, 
        int $quantiteAvant, 
        int $quantiteApres, 
        string $referenceType, 
        int $referenceId, 
        string $motif
    ): void {
        $sql = "INSERT INTO mouvements_stock (
                    produit_id, 
                    lot_id, 
                    type_mouvement, 
                    quantite, 
                    quantite_avant, 
                    quantite_apres, 
                    motif, 
                    reference_type, 
                    reference_id,
                    utilisateur_id,
                    date_mouvement
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $produitId,
            $lotId,
            $typeMouvement,
            $quantite,
            $quantiteAvant,
            $quantiteApres,
            $motif,
            $referenceType,
            $referenceId,
            $this->getCurrentUserId()
        ]);
    }

    /**
     * Récupère le stock actuel d'un produit
     */
    private function getStockActuel(int $produitId): array
    {
        $sql = "SELECT * FROM stock WHERE produit_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stock) {
            throw new Exception("Stock non trouvé pour le produit ID: $produitId");
        }
        
        return $stock;
    }

    private function getCurrentUserId(): int
    {
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new Exception("Utilisateur connecte introuvable pour le mouvement de stock");
        }

        return $userId;
    }

    /**
     * Calcule la valeur du stock total
     */
    public function getValeurStockTotal(): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_produits,
                    SUM(s.quantite_disponible) as quantite_totale,
                    SUM(s.valeur_stock) as valeur_totale,
                    AVG(s.valeur_stock) as valeur_moyenne
                FROM stock s
                JOIN produits p ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Génère le rapport de rotation des stocks
     */
    public function getRapportRotationStock(string $periode = 'mois'): array
    {
        $dateDebut = match($periode) {
            'jour' => 'DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
            'semaine' => 'DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
            'mois' => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
            'annee' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'
        };
        
        $sql = "SELECT 
                    p.id, p.nom, p.code_cip,
                    s.quantite_disponible,
                    s.valeur_stock,
                    COALESCE(SUM(vi.quantite), 0) as quantite_vendue,
                    COALESCE(SUM(vi.montant_total), 0) as chiffre_affaires,
                    CASE 
                        WHEN s.quantite_disponible > 0 THEN 
                            (COALESCE(SUM(vi.quantite), 0) / s.quantite_disponible)
                        ELSE 0 
                    END as rotation_stock
                FROM produits p
                JOIN stock s ON p.id = s.produit_id
                LEFT JOIN ventes_items vi ON p.id = vi.produit_id
                LEFT JOIN ventes v ON vi.vente_id = v.id
                WHERE p.is_actif = 1 
                AND p.deleted_at IS NULL
                AND v.date_vente >= $dateDebut
                GROUP BY p.id, p.nom, p.code_cip, s.quantite_disponible, s.valeur_stock
                ORDER BY rotation_stock DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
