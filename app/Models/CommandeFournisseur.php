<?php

namespace App\Models;

use PDO;
use PDOException;

class CommandeFournisseur
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle commande fournisseur
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO commandes (
                    numero_commande,
                    fournisseur_id,
                    utilisateur_id,
                    date_commande,
                    date_livraison_prevue,
                    montant_total,
                    statut_commande,
                    conditions_paiement,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['numero_commande'],
            $data['fournisseur_id'],
            $data['utilisateur_id'],
            $data['date_commande'] ?? date('Y-m-d'),
            $data['date_livraison_prevue'],
            $data['montant_total'],
            $data['statut_commande'] ?? 'BROUILLON',
            $data['conditions_paiement'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Récupère une commande par son ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT c.*, 
                       f.nom as fournisseur_nom,
                       u.username as createur_nom
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
                WHERE c.id = ? AND c.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère une commande par son numéro
     */
    public function findByNumero(string $numero): ?array
    {
        $sql = "SELECT * FROM commandes WHERE numero_commande = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numero]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour une commande
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'numero_commande'])) {
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
        
        $sql = "UPDATE commandes SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Met à jour le statut d'une commande
     */
    public function updateStatut(int $id, string $statut, int $utilisateurId = null): bool
    {
        $sql = "UPDATE commandes SET 
                    statut_commande = ?, 
                    updated_at = ?";
        
        $params = [$statut, date('Y-m-d H:i:s')];
        
        if ($utilisateurId) {
            $sql .= ", validated_by = ?";
            $params[] = $utilisateurId;
            
            if ($statut === 'VALIDEE') {
                $sql .= ", date_validation = NOW()";
            }
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Supprime une commande (soft delete)
     */
    public function delete(int $id): bool
    {
        $sql = "UPDATE commandes SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les commandes d'un fournisseur
     */
    public function getByFournisseur(int $fournisseurId, string $statut = null): array
    {
        $sql = "SELECT * FROM commandes 
                WHERE fournisseur_id = ? AND deleted_at IS NULL";
        $params = [$fournisseurId];
        
        if ($statut) {
            $sql .= " AND statut_commande = ?";
            $params[] = $statut;
        }
        
        $sql .= " ORDER BY date_commande DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes par statut
     */
    public function getByStatut(string $statut): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.statut_commande = ? AND c.deleted_at IS NULL
                ORDER BY c.date_commande DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$statut]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes d'une période
     */
    public function getByPeriode(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.date_commande BETWEEN ? AND ?
                AND c.deleted_at IS NULL
                ORDER BY c.date_commande DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes en cours
     */
    public function getEnCours(): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.statut_commande IN ('BROUILLON', 'VALIDEE', 'PARTIELLEMENT_LIVREE')
                AND c.deleted_at IS NULL
                ORDER BY c.date_commande ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes en retard
     */
    public function getEnRetard(): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom,
                       DATEDIFF(CURDATE(), c.date_livraison_prevue) as jours_retard
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.statut_commande IN ('VALIDEE', 'PARTIELLEMENT_LIVREE')
                AND c.date_livraison_prevue < CURDATE()
                AND c.deleted_at IS NULL
                ORDER BY jours_retard DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un article à une commande
     */
    public function ajouterArticle(int $commandeId, array $article): int
    {
        $sql = "INSERT INTO commande_items (
                    commande_id,
                    produit_id,
                    quantite_commandee,
                    quantite_livree,
                    prix_unitaire,
                    montant_total
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $commandeId,
            $article['produit_id'],
            $article['quantite_commandee'],
            $article['quantite_livree'] ?? 0,
            $article['prix_unitaire'],
            $article['montant_total']
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Met à jour un article de commande
     */
    public function updateArticle(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'commande_id'])) {
                continue;
            }
            
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE commande_items SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Récupère les articles d'une commande
     */
    public function getArticles(int $commandeId): array
    {
        $sql = "SELECT ci.*, 
                       p.nom as produit_nom, p.code_cip,
                       u.nom as unite_mesure
                FROM commande_items ci
                JOIN produits p ON ci.produit_id = p.id
                LEFT JOIN unites u ON p.unite_id = u.id
                WHERE ci.commande_id = ?
                ORDER BY p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour la quantité livrée d'un article
     */
    public function updateQuantiteLivree(int $id, int $quantiteLivree): bool
    {
        $sql = "UPDATE commande_items SET 
                    quantite_livree = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$quantiteLivree, $id]);
    }

    /**
     * Recalcule le montant total d'une commande
     */
    public function recalculerTotal(int $commandeId): bool
    {
        $sql = "UPDATE commandes c 
                SET montant_total = (
                    SELECT COALESCE(SUM(montant_total), 0) 
                    FROM commande_items 
                    WHERE commande_id = ?
                ),
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$commandeId, $commandeId]);
    }

    /**
     * Génère un numéro de commande unique
     */
    public function genererNumero(): string
    {
        $prefix = 'CMD' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM commandes WHERE DATE(date_commande) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $prefix . str_pad($result['count'] + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie si une commande peut être validée
     */
    public function peutValider(int $commandeId): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_articles,
                    SUM(montant_total) as montant_total
                FROM commande_items 
                WHERE commande_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'peut_valider' => $result['nombre_articles'] > 0 && $result['montant_total'] > 0,
            'nombre_articles' => (int) $result['nombre_articles'],
            'montant_total' => (float) $result['montant_total']
        ];
    }

    /**
     * Récupère les statistiques des commandes
     */
    public function getStatistiques(string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_commandes,
                    SUM(montant_total) as montant_total,
                    COUNT(CASE WHEN statut_commande = 'BROUILLON' THEN 1 END) as brouillons,
                    COUNT(CASE WHEN statut_commande = 'VALIDEE' THEN 1 END) => validees,
                    COUNT(CASE WHEN statut_commande = 'PARTIELLEMENT_LIVREE' THEN 1 END) => partiellement_livrees,
                    COUNT(CASE WHEN statut_commande = 'LIVREE' THEN 1 END) => livrees,
                    COUNT(CASE WHEN statut_commande = 'ANNULEE' THEN 1 END) => annulees,
                    AVG(montant_total) as montant_moyen
                FROM commandes 
                WHERE deleted_at IS NULL";
        
        $params = [];
        
        if ($dateDebut && $dateFin) {
            $sql .= " AND date_commande BETWEEN ? AND ?";
            $params = [$dateDebut, $dateFin];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes par utilisateur
     */
    public function getByUtilisateur(int $utilisateurId): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.utilisateur_id = ? AND c.deleted_at IS NULL
                ORDER BY c.date_commande DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utilisateurId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte les commandes
     */
    public function exporter(array $filtres = []): array
    {
        $sql = "SELECT c.*, 
                       f.nom as fournisseur_nom,
                       u.username as createur_nom,
                       (SELECT COUNT(*) FROM commande_items ci WHERE ci.commande_id = c.id) as nombre_articles
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
                WHERE c.deleted_at IS NULL";
        
        $params = [];
        $whereClauses = [];
        
        if (!empty($filtres['fournisseur_id'])) {
            $whereClauses[] = "c.fournisseur_id = ?";
            $params[] = $filtres['fournisseur_id'];
        }
        
        if (!empty($filtres['statut'])) {
            $whereClauses[] = "c.statut_commande = ?";
            $params[] = $filtres['statut'];
        }
        
        if (!empty($filtres['utilisateur_id'])) {
            $whereClauses[] = "c.utilisateur_id = ?";
            $params[] = $filtres['utilisateur_id'];
        }
        
        if (!empty($filtres['date_debut']) && !empty($filtres['date_fin'])) {
            $whereClauses[] = "c.date_commande BETWEEN ? AND ?";
            $params[] = $filtres['date_debut'];
            $params[] = $filtres['date_fin'];
        }
        
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $sql .= " ORDER BY c.date_commande DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes automatiques générées
     */
    public function getAutomatiques(string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom
                FROM commandes c
                JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.notes LIKE '%Commande automatique%'
                AND c.deleted_at IS NULL";
        
        $params = [];
        
        if ($dateDebut && $dateFin) {
            $sql .= " AND c.date_commande BETWEEN ? AND ?";
            $params = [$dateDebut, $dateFin];
        }
        
        $sql .= " ORDER BY c.date_commande DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un produit est déjà dans une commande en cours pour un fournisseur
     */
    public function produitDansCommandeEnCours(int $produitId, int $fournisseurId): ?array
    {
        $sql = "SELECT c.id, c.numero_commande, c.statut_commande
                FROM commandes c
                JOIN commande_items ci ON c.id = ci.commande_id
                WHERE ci.produit_id = ? 
                AND c.fournisseur_id = ?
                AND c.statut_commande IN ('BROUILLON', 'VALIDEE')
                AND c.deleted_at IS NULL
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId, $fournisseurId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
