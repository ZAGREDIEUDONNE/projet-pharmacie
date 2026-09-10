<?php

namespace App\Models;

use PDO;
use PDOException;

class Lot
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée un nouveau lot
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO lots (
                    produit_id,
                    numero_lot,
                    date_fabrication,
                    date_peremption,
                    quantite_initiale,
                    quantite_restante,
                    prix_achat_unitaire,
                    fournisseur_id,
                    commande_id,
                    is_actif
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['produit_id'],
            $data['numero_lot'],
            $data['date_fabrication'],
            $data['date_peremption'],
            $data['quantite'],
            $data['quantite'],
            $data['prix_achat_unitaire'],
            $data['fournisseur_id'] ?? null,
            $data['commande_id'] ?? null,
            $data['is_actif'] ?? true
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Récupère un lot par son ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip,
                       f.nom as fournisseur_nom
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                LEFT JOIN fournisseurs f ON l.fournisseur_id = f.id
                WHERE l.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère un lot par son numéro et produit
     */
    public function findByNumero(int $produitId, string $numeroLot): ?array
    {
        $sql = "SELECT * FROM lots 
                WHERE produit_id = ? AND numero_lot = ? AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId, $numeroLot]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour un lot
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
        
        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        
        $sql = "UPDATE lots SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Met à jour la quantité d'un lot
     */
    public function updateQuantite(int $id, int $nouvelleQuantite): bool
    {
        $sql = "UPDATE lots SET 
                    quantite_restante = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nouvelleQuantite, $id]);
    }

    /**
     * Désactive un lot
     */
    public function desactiver(int $id): bool
    {
        $sql = "UPDATE lots SET is_actif = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les lots d'un produit
     */
    public function getByProduit(int $produitId, bool $actifOnly = true): array
    {
        $sql = "SELECT l.*, 
                       DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                       CASE 
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                           ELSE 'NORMAL'
                       END as niveau_peremption
                FROM lots l
                WHERE l.produit_id = ?";
        
        if ($actifOnly) {
            $sql .= " AND l.is_actif = 1";
        }
        
        $sql .= " ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les lots proches de péremption
     */
    public function getProchesPeremption(int $jours = 90): array
    {
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip,
                       f.nom as fournisseur_nom,
                       DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                       CASE 
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                           ELSE 'NORMAL'
                       END as niveau_peremption
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                LEFT JOIN fournisseurs f ON l.fournisseur_id = f.id
                WHERE l.is_actif = 1 
                AND l.quantite_restante > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jours]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les lots périmés
     */
    public function getPerimes(): array
    {
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip,
                       f.nom as fournisseur_nom
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                LEFT JOIN fournisseurs f ON l.fournisseur_id = f.id
                WHERE l.is_actif = 1 
                AND l.date_peremption <= CURDATE()
                AND l.quantite_restante > 0
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les lots FIFO pour un produit
     */
    public function getFIFO(int $produitId, int $quantiteRequise): array
    {
        $sql = "SELECT * FROM lots 
                WHERE produit_id = ? 
                AND quantite_restante > 0 
                AND is_actif = 1 
                AND date_peremption > CURDATE()
                ORDER BY date_fabrication ASC, date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        $lots = [];
        $quantiteRestante = $quantiteRequise;
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lot) {
            if ($quantiteRestante <= 0) {
                break;
            }
            
            $quantiteDisponible = min($quantiteRestante, $lot['quantite_restante']);
            $lot['quantite_utilisee'] = $quantiteDisponible;
            $lots[] = $lot;
            $quantiteRestante -= $quantiteDisponible;
        }
        
        return $lots;
    }

    /**
     * Vérifie la disponibilité par lot
     */
    public function verifierDisponibilite(int $produitId, int $quantite): array
    {
        $sql = "SELECT 
                    SUM(quantite_restante) as quantite_totale,
                    COUNT(*) as nombre_lots,
                    COUNT(CASE WHEN quantite_restante > 0 THEN 1 END) as lots_disponibles
                FROM lots 
                WHERE produit_id = ? 
                AND is_actif = 1 
                AND date_peremption > CURDATE()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'disponible' => $result['quantite_totale'] >= $quantite,
            'quantite_totale' => (int) $result['quantite_totale'],
            'nombre_lots' => (int) $result['nombre_lots'],
            'lots_disponibles' => (int) $result['lots_disponibles']
        ];
    }

    /**
     * Récupère la valeur totale des lots d'un produit
     */
    public function getValeurTotale(int $produitId): float
    {
        $sql = "SELECT SUM(quantite_restante * prix_achat_unitaire) as valeur_totale
                FROM lots 
                WHERE produit_id = ? AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        return (float) $stmt->fetchColumn();
    }

    /**
     * Récupère les lots par fournisseur
     */
    public function getByFournisseur(int $fournisseurId): array
    {
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip,
                       DATEDIFF(l.date_peremption, CURDATE()) as jours_restants
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                WHERE l.fournisseur_id = ? AND l.is_actif = 1
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fournisseurId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les lots d'une commande
     */
    public function getByCommande(int $commandeId): array
    {
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                WHERE l.commande_id = ?
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le prix de vente suggéré
     */
    public function updatePrixVenteSuggere(int $id, float $prix): bool
    {
        $sql = "UPDATE lots SET 
                    prix_vente_suggere = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$prix, $id]);
    }

    /**
     * Récupère les statistiques de lots
     */
    public function getStatistiques(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_lots,
                    COUNT(CASE WHEN is_actif = 1 THEN 1 END) as lots_actifs,
                    COUNT(CASE WHEN quantite_restante = 0 THEN 1 END) as lots_epuises,
                    COUNT(CASE WHEN date_peremption <= CURDATE() THEN 1 END) as lots_perimes,
                    COUNT(CASE WHEN DATEDIFF(date_peremption, CURDATE()) <= 30 THEN 1 END) as lots_urgents,
                    COUNT(CASE WHEN DATEDIFF(date_peremption, CURDATE()) <= 90 THEN 1 END) as lots_alertes,
                    SUM(quantite_restante * prix_achat_unitaire) as valeur_totale
                FROM lots";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte les lots
     */
    public function exporter(array $filtres = []): array
    {
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip,
                       f.nom as fournisseur_nom,
                       DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                       CASE 
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                           ELSE 'NORMAL'
                       END as niveau_peremption
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                LEFT JOIN fournisseurs f ON l.fournisseur_id = f.id";
        
        $params = [];
        $whereClauses = [];
        
        if (!empty($filtres['produit_id'])) {
            $whereClauses[] = "l.produit_id = ?";
            $params[] = $filtres['produit_id'];
        }
        
        if (!empty($filtres['fournisseur_id'])) {
            $whereClauses[] = "l.fournisseur_id = ?";
            $params[] = $filtres['fournisseur_id'];
        }
        
        if (isset($filtres['is_actif'])) {
            $whereClauses[] = "l.is_actif = ?";
            $params[] = $filtres['is_actif'];
        }
        
        if (!empty($filtres['niveau_peremption'])) {
            switch ($filtres['niveau_peremption']) {
                case 'PERIME':
                    $whereClauses[] = "l.date_peremption <= CURDATE()";
                    break;
                case 'URGENT':
                    $whereClauses[] = "DATEDIFF(l.date_peremption, CURDATE()) BETWEEN 1 AND 30";
                    break;
                case 'ALERTE':
                    $whereClauses[] = "DATEDIFF(l.date_peremption, CURDATE()) BETWEEN 31 AND 90";
                    break;
            }
        }
        
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $sql .= " ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un numéro de lot existe déjà pour un produit
     */
    public function numeroLotExiste(int $produitId, string $numeroLot, int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM lots 
                WHERE produit_id = ? AND numero_lot = ?";
        $params = [$produitId, $numeroLot];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les lots qui vont expirer bientôt pour alertes
     */
    public function getAlertesExpiration(): array
    {
        $sql = "SELECT 
                    l.id,
                    l.numero_lot,
                    l.date_peremption,
                    l.quantite_restante,
                    p.nom as produit_nom,
                    p.code_cip,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                WHERE l.is_actif = 1 
                AND l.quantite_restante > 0
                AND l.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
