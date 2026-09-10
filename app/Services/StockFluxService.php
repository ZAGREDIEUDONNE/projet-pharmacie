<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Flux de stock, valorisation et historique des mouvements.
 */
class StockFluxService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Recalcule valeur_stock = quantité × prix d'achat pour tous les produits.
     */
    public function recalculerValeursStock(): int
    {
        $sql = 'UPDATE stock s
                JOIN produits p ON p.id = s.produit_id
                SET s.valeur_stock = ROUND(s.quantite_disponible * p.prix_achat, 2),
                    s.updated_at = NOW()
                WHERE p.deleted_at IS NULL';
        return $this->db->exec($sql) ?: 0;
    }

    public function getValeurGlobale(): float
    {
        $this->recalculerValeursStock();
        return (float)$this->db->query('SELECT COALESCE(SUM(valeur_stock), 0) FROM stock')->fetchColumn();
    }

    public function getValeurParCategorie(): array
    {
        $sql = 'SELECT
                    COALESCE(c.nom, "Sans catégorie") AS categorie,
                    COUNT(DISTINCT p.id) AS nb_produits,
                    COALESCE(SUM(s.quantite_disponible), 0) AS quantite_totale,
                    COALESCE(SUM(ROUND(s.quantite_disponible * p.prix_achat, 2)), 0) AS valeur_stock
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                LEFT JOIN categories c ON c.id = p.categorie_id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL
                GROUP BY c.id, c.nom
                ORDER BY valeur_stock DESC';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getValeurParRayon(): array
    {
        $sql = 'SELECT
                    COALESCE(p.rayon, "Non défini") AS rayon,
                    COUNT(DISTINCT p.id) AS nb_produits,
                    COALESCE(SUM(s.quantite_disponible), 0) AS quantite_totale,
                    COALESCE(SUM(ROUND(s.quantite_disponible * p.prix_achat, 2)), 0) AS valeur_stock
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL
                GROUP BY p.rayon
                ORDER BY valeur_stock DESC';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Synthèse flux par produit : entrées, sorties, stock actuel.
     */
    public function getFluxProduits(array $filters = []): array
    {
        $where = ['p.deleted_at IS NULL', 'p.is_actif = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(p.nom LIKE :q OR p.code_cip LIKE :q OR p.dci LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['categorie_id'])) {
            $where[] = 'p.categorie_id = :categorie_id';
            $params['categorie_id'] = (int)$filters['categorie_id'];
        }

        $sql = 'SELECT
                    p.id,
                    p.code_cip,
                    p.nom,
                    p.dci,
                    p.rayon,
                    p.prix_achat,
                    COALESCE(c.nom, "—") AS categorie,
                    COALESCE(s.quantite_disponible, 0) AS stock_actuel,
                    COALESCE(s.quantite_theorique, 0) AS stock_theorique,
                    ROUND(COALESCE(s.quantite_disponible, 0) * p.prix_achat, 2) AS valeur_stock,
                    s.dernier_mouvement,
                    COALESCE(ent.total_entrees, 0) AS total_entrees,
                    COALESCE(sor.total_sorties, 0) AS total_sorties,
                    GREATEST(0, COALESCE(s.quantite_disponible, 0) - COALESCE(ent.total_entrees, 0) + COALESCE(sor.total_sorties, 0)) AS stock_initial_estime
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                LEFT JOIN categories c ON c.id = p.categorie_id
                LEFT JOIN (
                    SELECT produit_id, SUM(quantite) AS total_entrees
                    FROM mouvements_stock
                    WHERE type_mouvement IN ("ENTREE", "AJUSTEMENT")
                    AND quantite > 0
                    GROUP BY produit_id
                ) ent ON ent.produit_id = p.id
                LEFT JOIN (
                    SELECT produit_id, SUM(quantite) AS total_sorties
                    FROM mouvements_stock
                    WHERE type_mouvement IN ("SORTIE", "PERTE")
                    GROUP BY produit_id
                ) sor ON sor.produit_id = p.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.nom ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistoriqueMouvements(array $filters = [], int $limit = 200): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['produit_id'])) {
            $where[] = 'ms.produit_id = :produit_id';
            $params['produit_id'] = (int)$filters['produit_id'];
        }

        if (!empty($filters['type_mouvement'])) {
            $where[] = 'ms.type_mouvement = :type_mouvement';
            $params['type_mouvement'] = $filters['type_mouvement'];
        }

        if (!empty($filters['date_debut'])) {
            $where[] = 'DATE(ms.date_mouvement) >= :date_debut';
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $where[] = 'DATE(ms.date_mouvement) <= :date_fin';
            $params['date_fin'] = $filters['date_fin'];
        }

        $sql = 'SELECT
                    ms.*,
                    p.nom AS produit_nom,
                    p.code_cip,
                    u.username AS utilisateur_nom
                FROM mouvements_stock ms
                JOIN produits p ON p.id = ms.produit_id
                LEFT JOIN utilisateurs u ON u.id = ms.utilisateur_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ms.date_mouvement DESC, ms.id DESC
                LIMIT ' . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProduitsRupture(): array
    {
        $sql = 'SELECT p.id, p.nom, p.code_cip, COALESCE(s.quantite_disponible, 0) AS stock
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL
                AND COALESCE(s.quantite_disponible, 0) <= 0
                ORDER BY p.nom LIMIT 50';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProduitsAlerte(): array
    {
        $sql = 'SELECT p.id, p.nom, p.code_cip,
                       COALESCE(s.quantite_disponible, 0) AS stock,
                       COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0) AS seuil
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL
                AND COALESCE(s.quantite_disponible, 0) > 0
                AND COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0)
                ORDER BY stock ASC LIMIT 50';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
