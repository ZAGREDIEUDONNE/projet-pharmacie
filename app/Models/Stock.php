<?php

namespace App\Models;

use PDO;
use PDOException;

class Stock
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère le stock d'un produit
     */
    public function getStockProduit(int $produitId): ?array
    {
        $sql = "SELECT s.*, p.nom, p.code_cip, p.prix_achat, p.prix_vente,
                       p.stock_securite, p.stock_alerte
                FROM stock s
                JOIN produits p ON s.produit_id = p.id
                WHERE s.produit_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour le stock d'un produit
     */
    public function updateStock(int $produitId, array $data): bool
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
        $values[] = $produitId;
        
        $sql = "UPDATE stock SET " . implode(', ', $fields) . " WHERE produit_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Déduit du stock
     */
    public function deduireStock(int $produitId, int $quantite): bool
    {
        $sql = "UPDATE stock SET 
                    quantite_disponible = quantite_disponible - ?,
                    quantite_theorique = quantite_theorique - ?,
                    dernier_mouvement = NOW(),
                    updated_at = NOW()
                WHERE produit_id = ? AND quantite_disponible >= ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$quantite, $quantite, $produitId, $quantite]);
    }

    /**
     * Ajoute du stock
     */
    public function ajouterStock(int $produitId, int $quantite, float $valeurAjoutee = 0): bool
    {
        $sql = "UPDATE stock SET 
                    quantite_disponible = quantite_disponible + ?,
                    quantite_theorique = quantite_theorique + ?,
                    valeur_stock = valeur_stock + ?,
                    dernier_mouvement = NOW(),
                    updated_at = NOW()
                WHERE produit_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$quantite, $quantite, $valeurAjoutee, $produitId]);
    }

    /**
     * Met à jour le stock théorique uniquement
     */
    public function updateStockTheorique(int $produitId, int $nouveauStock): bool
    {
        $sql = "UPDATE stock SET 
                    quantite_theorique = ?,
                    updated_at = NOW()
                WHERE produit_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nouveauStock, $produitId]);
    }

    /**
     * Récupère tous les produits avec leur stock
     */
    public function getStocksComplets(): array
    {
        $sql = "SELECT 
                    p.id, p.nom, p.code_cip, p.prix_vente,
                    s.quantite_disponible, s.quantite_theorique, s.valeur_stock,
                    s.dernier_mouvement,
                    c.nom as categorie,
                    f.nom as fournisseur,
                    p.stock_securite, p.stock_alerte,
                    CASE 
                        WHEN s.quantite_disponible <= p.stock_securite THEN 'CRITIQUE'
                        WHEN s.quantite_disponible <= p.stock_alerte THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END as niveau_stock
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                WHERE p.deleted_at IS NULL AND p.is_actif = 1
                ORDER BY p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Récupère les produits en rupture de stock
     */
    public function getProduitsEnRupture(): array
    {
        $sql = "SELECT 
                    p.id, p.nom, p.code_cip,
                    s.quantite_disponible, s.quantite_theorique,
                    p.stock_securite, p.stock_alerte,
                    s.dernier_mouvement
                FROM produits p
                JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1 
                AND p.deleted_at IS NULL
                AND s.quantite_disponible = 0
                ORDER BY s.dernier_mouvement DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule la valeur totale du stock
     */
    public function getValeurStockTotal(): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_produits,
                    SUM(s.quantite_disponible) as quantite_totale,
                    SUM(s.valeur_stock) as valeur_totale,
                    AVG(s.valeur_stock) as valeur_moyenne,
                    SUM(CASE WHEN s.quantite_disponible <= p.stock_alerte THEN 1 ELSE 0 END) as produits_alerte,
                    SUM(CASE WHEN s.quantite_disponible = 0 THEN 1 ELSE 0 END) as produits_rupture
                FROM stock s
                JOIN produits p ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les mouvements de stock d'un produit
     */
    public function getMouvementsProduit(int $produitId, int $limit = 100): array
    {
        $sql = "SELECT ms.*, 
                       u.username as utilisateur_nom,
                       l.numero_lot
                FROM mouvements_stock ms
                LEFT JOIN utilisateurs u ON ms.utilisateur_id = u.id
                LEFT JOIN lots l ON ms.lot_id = l.id
                WHERE ms.produit_id = ?
                ORDER BY ms.date_mouvement DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les mouvements de stock récents
     */
    public function getMouvementsRecents(int $limit = 50): array
    {
        $sql = "SELECT ms.*, 
                       p.nom as produit_nom, p.code_cip,
                       u.username as utilisateur_nom,
                       l.numero_lot
                FROM mouvements_stock ms
                JOIN produits p ON ms.produit_id = p.id
                LEFT JOIN utilisateurs u ON ms.utilisateur_id = u.id
                LEFT JOIN lots l ON ms.lot_id = l.id
                ORDER BY ms.date_mouvement DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie la disponibilité d'un produit
     */
    public function verifierDisponibilite(int $produitId, int $quantite): array
    {
        $sql = "SELECT 
                    s.quantite_disponible,
                    s.quantite_theorique,
                    p.stock_securite,
                    p.stock_alerte,
                    p.nom
                FROM stock s
                JOIN produits p ON s.produit_id = p.id
                WHERE s.produit_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock) {
            return [
                'disponible' => false,
                'message' => 'Produit non trouvé',
                'quantite_disponible' => 0
            ];
        }
        
        $quantiteDisponible = $stock['quantite_disponible'];
        
        return [
            'disponible' => $quantiteDisponible >= $quantite,
            'quantite_disponible' => $quantiteDisponible,
            'quantite_theorique' => $stock['quantite_theorique'],
            'stock_securite' => $stock['stock_securite'],
            'stock_alerte' => $stock['stock_alerte'],
            'nom_produit' => $stock['nom'],
            'message' => $quantiteDisponible >= $quantite 
                ? 'Stock disponible' 
                : "Stock insuffisant: $quantiteDisponible disponible pour $quantite requise"
        ];
    }

    /**
     * Initialise le stock pour un produit
     */
    public function initialiserStock(int $produitId): bool
    {
        $sql = "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock) 
                VALUES (?, 0, 0, 0)
                ON DUPLICATE KEY UPDATE 
                quantite_disponible = 0, quantite_theorique = 0, valeur_stock = 0";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$produitId]);
    }

    /**
     * Met à jour la valeur du stock
     */
    public function updateValeurStock(int $produitId): bool
    {
        $sql = "UPDATE stock s
                SET valeur_stock = (
                    SELECT SUM(l.quantite_restante * l.prix_achat_unitaire)
                    FROM lots l
                    WHERE l.produit_id = s.produit_id AND l.is_actif = 1
                ),
                updated_at = NOW()
                WHERE s.produit_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$produitId]);
    }

    /**
     * Met à jour tous les stocks théoriques
     */
    public function updateAllStocksTheoriques(): int
    {
        $sql = "UPDATE stock s 
                SET quantite_theorique = (
                    SELECT COALESCE(SUM(l.quantite_restante), 0) 
                    FROM lots l 
                    WHERE l.produit_id = s.produit_id AND l.is_actif = 1
                ) - (
                    SELECT COALESCE(SUM(ci.quantite_commandee - ci.quantite_livree), 0)
                    FROM commande_items ci
                    JOIN commandes c ON ci.commande_id = c.id
                    WHERE ci.produit_id = s.produit_id 
                    AND c.statut_commande IN ('VALIDEE', 'PARTIELLEMENT_LIVREE')
                    AND c.deleted_at IS NULL
                ),
                updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Génère le rapport de rotation des stocks
     */
    public function getRapportRotation(string $periode = 'mois'): array
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

    /**
     * Récupère les statistiques de stock
     */
    public function getStatistiques(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_produits,
                    SUM(quantite_disponible) as stock_total,
                    SUM(valeur_stock) as valeur_stock_totale,
                    COUNT(CASE WHEN quantite_disponible <= stock_alerte THEN 1 END) as produits_alerte,
                    COUNT(CASE WHEN quantite_disponible = 0 THEN 1 END) as produits_rupture,
                    AVG(valeur_stock) as valeur_moyenne_produit
                FROM stock s
                JOIN produits p ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
