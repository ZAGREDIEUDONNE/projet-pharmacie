<?php

namespace App\Models;

use PDO;
use PDOException;

class Vente
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle vente
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO ventes (
                    numero_facture, 
                    client_id, 
                    utilisateur_id, 
                    caisse_session_id,
                    date_vente, 
                    montant_total, 
                    montant_remise, 
                    montant_net, 
                    montant_paye, 
                    montant_restant, 
                    type_paiement, 
                    statut_vente, 
                    is_credit, 
                    echeance_credit, 
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['numero_facture'],
            $data['client_id'] ?? null,
            $data['utilisateur_id'],
            $data['caisse_session_id'] ?? null,
            $data['date_vente'] ?? date('Y-m-d H:i:s'),
            $data['montant_total'],
            $data['montant_remise'] ?? 0,
            $data['montant_net'],
            $data['montant_paye'] ?? 0,
            $data['montant_restant'] ?? $data['montant_net'],
            $data['type_paiement'] ?? 'ESPECE',
            $data['statut_vente'] ?? 'EN_COURS',
            $data['is_credit'] ?? false,
            $data['echeance_credit'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Récupère une vente par son ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT v.*, 
                       c.nom as client_nom, c.telephone as client_telephone, c.type_client,
                       u.username as vendeur_nom,
                       cs.numero_session
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                LEFT JOIN caisse_sessions cs ON v.caisse_session_id = cs.id
                WHERE v.id = ? AND v.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère une vente par son numéro de facture
     */
    public function findByNumeroFacture(string $numeroFacture): ?array
    {
        $sql = "SELECT * FROM ventes 
                WHERE numero_facture = ? AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroFacture]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour une vente
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
        
        $sql = "UPDATE ventes SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Annule une vente (soft delete)
     */
    public function annuler(int $id): bool
    {
        $sql = "UPDATE ventes SET 
                    statut_vente = 'ANNULEE',
                    deleted_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les articles d'une vente
     */
    public function getArticles(int $venteId): array
    {
        $sql = "SELECT vi.*, 
                       p.nom as produit_nom, p.code_cip,
                       l.numero_lot, l.date_peremption
                FROM ventes_items vi
                JOIN produits p ON vi.produit_id = p.id
                LEFT JOIN lots l ON vi.lot_id = l.id
                WHERE vi.vente_id = ?
                ORDER BY vi.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un article à une vente
     */
    public function addArticle(int $venteId, array $article): int
    {
        $sql = "INSERT INTO ventes_items (
                    vente_id, 
                    produit_id, 
                    lot_id, 
                    quantite, 
                    prix_unitaire, 
                    montant_total, 
                    remise
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $venteId,
            $article['produit_id'],
            $article['lot_id'] ?? null,
            $article['quantite'],
            $article['prix_unitaire'],
            $article['montant_total'],
            $article['remise'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Met à jour les articles d'une vente
     */
    public function updateArticles(int $venteId, array $articles): void
    {
        // Supprimer les anciens articles
        $sql = "DELETE FROM ventes_items WHERE vente_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        // Ajouter les nouveaux articles
        foreach ($articles as $article) {
            $this->addArticle($venteId, $article);
        }
    }

    /**
     * Récupère les ventes d'une période
     */
    public function getVentesPeriode(string $dateDebut, string $dateFin, int $limit = 100): array
    {
        $sql = "SELECT v.*, 
                       c.nom as client_nom,
                       u.username as vendeur_nom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                WHERE v.date_vente BETWEEN ? AND ?
                AND v.deleted_at IS NULL
                ORDER BY v.date_vente DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les ventes d'un utilisateur
     */
    public function getVentesUtilisateur(int $utilisateurId, string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT v.*, 
                       c.nom as client_nom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                WHERE v.utilisateur_id = ?
                AND v.date_vente BETWEEN ? AND ?
                AND v.deleted_at IS NULL
                ORDER BY v.date_vente DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utilisateurId, $dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule les totaux d'une vente
     */
    public function calculerTotaux(int $venteId): array
    {
        $sql = "SELECT 
                    SUM(montant_total) as montant_total,
                    SUM(montant_total * remise / 100) as montant_remise
                FROM ventes_items 
                WHERE vente_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $montantTotal = $result['montant_total'] ?? 0;
        $montantRemise = $result['montant_remise'] ?? 0;
        $montantNet = $montantTotal - $montantRemise;
        
        return [
            'montant_total' => $montantTotal,
            'montant_remise' => $montantRemise,
            'montant_net' => $montantNet
        ];
    }

    /**
     * Met à jour les totaux d'une vente
     */
    public function updateTotaux(int $venteId): void
    {
        $totaux = $this->calculerTotaux($venteId);
        
        $sql = "UPDATE ventes SET 
                    montant_total = ?,
                    montant_remise = ?,
                    montant_net = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $totaux['montant_total'],
            $totaux['montant_remise'],
            $totaux['montant_net'],
            $venteId
        ]);
    }

    /**
     * Récupère les statistiques de ventes
     */
    public function getStatistiques(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_ventes,
                    SUM(montant_net) as chiffre_affaires,
                    SUM(montant_remise) as total_remises,
                    AVG(montant_net) as panier_moyen,
                    COUNT(CASE WHEN is_credit = 1 THEN 1 END) as ventes_credit,
                    COUNT(CASE WHEN type_paiement = 'ESPECE' THEN 1 END) as paiements_espece,
                    COUNT(CASE WHEN type_paiement = 'CARTE' THEN 1 END) as paiements_carte
                FROM ventes 
                WHERE date_vente BETWEEN ? AND ?
                AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les produits les plus vendus
     */
    public function getProduitsPlusVendus(string $dateDebut, string $dateFin, int $limit = 10): array
    {
        $sql = "SELECT 
                    p.id, p.nom, p.code_cip,
                    SUM(vi.quantite) as quantite_vendue,
                    SUM(vi.montant_total) as chiffre_affaires,
                    COUNT(DISTINCT vi.vente_id) as nombre_ventes
                FROM ventes_items vi
                JOIN ventes v ON vi.vente_id = v.id
                JOIN produits p ON vi.produit_id = p.id
                WHERE v.date_vente BETWEEN ? AND ?
                AND v.deleted_at IS NULL
                GROUP BY p.id, p.nom, p.code_cip
                ORDER BY quantite_vendue DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si une vente peut être annulée
     */
    public function peutAnnuler(int $venteId): bool
    {
        $sql = "SELECT statut_vente, date_vente 
                FROM ventes 
                WHERE id = ? AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vente) {
            return false;
        }
        
        // Une vente ne peut être annulée si elle est déjà annulée
        if ($vente['statut_vente'] === 'ANNULEE') {
            return false;
        }
        
        // Optionnel: limiter l'annulation dans le temps (ex: 24h)
        $dateVente = new \DateTime($vente['date_vente']);
        $now = new \DateTime();
        $interval = $dateVente->diff($now);
        
        // Autoriser l'annulation dans les 24 heures
        return $interval->days <= 1;
    }

    /**
     * Génère un numéro de facture unique
     */
    public function genererNumeroFacture(): string
    {
        $prefix = 'FAC' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM ventes WHERE DATE(date_vente) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $prefix . str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
    }
}
