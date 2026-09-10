<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class CommandeService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle commande fournisseur
     */
    public function creerCommande(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Générer le numéro de commande
            $numeroCommande = $this->genererNumeroCommande();

            $sql = "INSERT INTO commandes (
                        numero_commande, fournisseur_id, utilisateur_id, date_commande,
                        date_livraison_prevue, montant_total, statut_commande,
                        conditions_paiement, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $numeroCommande,
                $data['fournisseur_id'],
                $data['utilisateur_id'],
                $data['date_commande'] ?? date('Y-m-d'),
                $data['date_livraison_prevue'],
                $data['montant_total'],
                $data['statut_commande'] ?? 'BROUILLON',
                $data['conditions_paiement'] ?? null,
                $data['notes'] ?? null
            ]);

            $commandeId = $this->db->lastInsertId();

            // Ajouter les articles de la commande
            if (!empty($data['articles'])) {
                foreach ($data['articles'] as $article) {
                    $sql = "INSERT INTO commande_items (
                                commande_id, produit_id, quantite_commandee, prix_unitaire,
                                montant_total, notes
                            ) VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $commandeId,
                        $article['produit_id'],
                        $article['quantite_commandee'],
                        $article['prix_unitaire'],
                        $article['montant_total'],
                        $article['notes'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            // Logger l'opération
            $this->logCommandeOperation($data['utilisateur_id'], 'COMMANDE_CREATION', [
                'commande_id' => $commandeId,
                'numero_commande' => $numeroCommande,
                'fournisseur_id' => $data['fournisseur_id'],
                'montant_total' => $data['montant_total']
            ]);

            return [
                'success' => true,
                'commande_id' => $commandeId,
                'numero_commande' => $numeroCommande,
                'message' => 'Commande créée avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valide une commande
     */
    public function validerCommande(int $commandeId, int $utilisateurId): array
    {
        try {
            $this->db->beginTransaction();

            // Vérifier si la commande peut être validée
            $commande = $this->getCommande($commandeId);
            if (!$commande || $commande['statut_commande'] !== 'BROUILLON') {
                return [
                    'success' => false,
                    'message' => 'Commande non valide pour validation'
                ];
            }

            // Mettre à jour le statut
            $sql = "UPDATE commandes 
                    SET statut_commande = 'VALIDEE', 
                        date_validation = NOW(),
                        utilisateur_validation_id = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$utilisateurId, $commandeId]);

            $this->db->commit();

            // Logger l'opération
            $this->logCommandeOperation($utilisateurId, 'COMMANDE_VALIDATION', [
                'commande_id' => $commandeId,
                'numero_commande' => $commande['numero_commande']
            ]);

            return [
                'success' => true,
                'message' => 'Commande validée avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la validation de la commande: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Marque une commande comme reçue
     */
    public function marquerReception(int $commandeId, array $data): array
    {
        try {
            $this->db->beginTransaction();

            $commande = $this->getCommande($commandeId);
            if (!$commande || $commande['statut_commande'] !== 'VALIDEE') {
                return [
                    'success' => false,
                    'message' => 'Commande non valide pour réception'
                ];
            }

            // Mettre à jour les informations de réception
            $sql = "UPDATE commandes 
                    SET statut_commande = 'REÇUE', 
                        date_livraison_reelle = NOW(),
                        utilisateur_reception_id = ?,
                        notes_reception = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['utilisateur_id'],
                $data['notes_reception'] ?? null,
                $commandeId
            ]);

            // Mettre à jour les quantités reçues
            if (!empty($data['articles_reception'])) {
                foreach ($data['articles_reception'] as $article) {
                    $sql = "UPDATE commande_items 
                            SET quantite_livree = ?, 
                                date_reception = NOW(),
                                notes_reception = ?
                            WHERE commande_id = ? AND produit_id = ?";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $article['quantite_livree'],
                        $article['notes_reception'] ?? null,
                        $commandeId,
                        $article['produit_id']
                    ]);
                }
            }

            $this->db->commit();

            // Logger l'opération
            $this->logCommandeOperation($data['utilisateur_id'], 'COMMANDE_RECEPTION', [
                'commande_id' => $commandeId,
                'numero_commande' => $commande['numero_commande']
            ]);

            // Générer automatiquement l'écriture comptable pour l'achat
            try {
                $ecritureService = new EcritureComptableService($this->db, new AuditService($this->db), new JournalComptableService($this->db, new AuditService($this->db)));
                $ecritureService->genererEcritureAchatParId($commandeId);
            } catch (Exception $e) {
                // L'écriture comptable ne doit pas bloquer la réception
                // On log l'erreur mais on continue
                error_log('Erreur lors de la génération de l\'écriture comptable pour la commande ' . $commandeId . ': ' . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => 'Réception enregistrée avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la réception de la commande: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Annule une commande
     */
    public function annulerCommande(int $commandeId, string $motif, int $utilisateurId): array
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE commandes 
                    SET statut_commande = 'ANNULEE', 
                        date_annulation = NOW(),
                        utilisateur_annulation_id = ?,
                        motif_annulation = ?
                    WHERE id = ? AND statut_commande IN ('BROUILLON', 'VALIDEE')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$utilisateurId, $motif, $commandeId]);

            $this->db->commit();

            // Logger l'opération
            $this->logCommandeOperation($utilisateurId, 'COMMANDE_ANNULATION', [
                'commande_id' => $commandeId,
                'motif' => $motif
            ]);

            return [
                'success' => true,
                'message' => 'Commande annulée avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la commande: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère une commande par son ID
     */
    public function getCommande(int $commandeId): ?array
    {
        $sql = "SELECT c.*, 
                        u1.username as createur_nom, u1.nom as createur_prenom,
                        u2.username as validateur_nom, u2.nom as validateur_prenom,
                        u3.username as receptionneur_nom, u3.nom as receptionneur_prenom,
                        f.nom as fournisseur_nom
                    FROM commandes c
                    LEFT JOIN utilisateurs u1 ON c.utilisateur_id = u1.id
                    LEFT JOIN utilisateurs u2 ON c.utilisateur_validation_id = u2.id
                    LEFT JOIN utilisateurs u3 ON c.utilisateur_reception_id = u3.id
                    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                    WHERE c.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère les articles d'une commande
     */
    public function getArticlesCommande(int $commandeId): array
    {
        $sql = "SELECT ci.*, 
                        p.nom as produit_nom, p.reference
                    FROM commande_items ci
                    JOIN produits p ON ci.produit_id = p.id
                    WHERE ci.commande_id = ?
                    ORDER BY ci.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des commandes avec filtres
     */
    public function getListeCommandes(array $filtres = []): array
    {
        $sql = "SELECT c.*, 
                        u1.username as createur_nom,
                        f.nom as fournisseur_nom,
                        COUNT(ci.id) as nombre_articles,
                        SUM(ci.montant_total) as total_articles
                    FROM commandes c
                    LEFT JOIN utilisateurs u1 ON c.utilisateur_id = u1.id
                    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                    LEFT JOIN commande_items ci ON c.id = ci.commande_id";
        
        $where = [];
        $params = [];

        if (!empty($filtres['statut_commande'])) {
            $where[] = "c.statut_commande = ?";
            $params[] = $filtres['statut_commande'];
        }

        if (!empty($filtres['fournisseur_id'])) {
            $where[] = "c.fournisseur_id = ?";
            $params[] = $filtres['fournisseur_id'];
        }

        if (!empty($filtres['date_debut'])) {
            $where[] = "DATE(c.date_commande) >= ?";
            $params[] = $filtres['date_debut'];
        }

        if (!empty($filtres['date_fin'])) {
            $where[] = "DATE(c.date_commande) <= ?";
            $params[] = $filtres['date_fin'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY c.id ORDER BY c.date_commande DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Alias utilise par le dashboard des commandes.
     */
    public function getCommandeStats(): array
    {
        return $this->getStatistiquesCommandes();
    }

    /**
     * Recupere les dernieres commandes pour le dashboard.
     */
    public function getRecentesCommandes(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "SELECT c.*, 
                        u.username as createur_nom,
                        f.nom as fournisseur_nom,
                        COUNT(ci.id) as nombre_articles,
                        SUM(ci.montant_total) as total_articles
                    FROM commandes c
                    LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
                    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                    LEFT JOIN commande_items ci ON c.id = ci.commande_id
                    GROUP BY c.id
                    ORDER BY c.date_commande DESC
                    LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recupere les fournisseurs actifs pour les formulaires de commande.
     */
    public function getFournisseursActifs(): array
    {
        $sql = "SELECT id, nom
                    FROM fournisseurs
                    WHERE is_actif = 1
                    ORDER BY nom";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commandes en attente
     */
    public function getCommandesEnAttente(): array
    {
        $sql = "SELECT c.*, 
                        f.nom as fournisseur_nom,
                        u.username as createur_nom,
                        DATEDIFF(c.date_livraison_prevue, CURDATE()) as jours_restants
                    FROM commandes c
                    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                    LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
                    WHERE c.statut_commande = 'BROUILLON'
                    ORDER BY c.date_livraison_prevue ASC";
        
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des commandes
     */
    public function getStatistiquesCommandes(?string $dateDebut = null, ?string $dateFin = null): array
    {
        try {
            $sql = "SELECT 
                        statut_commande,
                        COUNT(*) as nombre,
                        SUM(montant_total) as montant_total,
                        AVG(montant_total) as montant_moyen,
                        COUNT(CASE WHEN statut_commande = 'REÇUE' THEN 1 END) as recues,
                        COUNT(CASE WHEN statut_commande = 'ANNULEE' THEN 1 END) as annulees,
                        AVG(DATEDIFF(date_livraison_reelle, date_commande)) as delai_moyen_livraison
                    FROM commandes";
            
            $params = [];
            $where = [];
            
            if ($dateDebut) {
                $where[] = "DATE(date_commande) >= ?";
                $params[] = $dateDebut;
            }
            
            if ($dateFin) {
                $where[] = "DATE(date_commande) <= ?";
                $params[] = $dateFin;
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $sql .= " GROUP BY statut_commande ORDER BY statut_commande";
            
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

    /**
     * Génère un numéro de commande unique
     */
    private function genererNumeroCommande(): string
    {
        $prefix = 'CMD';
        $date = date('Ymd');
        $sequence = $this->getSequenceJour($date);

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère la séquence du jour pour les commandes
     */
    private function getSequenceJour(string $date): int
    {
        $sql = "SELECT COUNT(*) as count 
                    FROM commandes 
                    WHERE DATE(date_commande) = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] + 1;
    }

    /**
     * Logger les opérations sur les commandes
     */
    private function logCommandeOperation(int $utilisateurId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs (
                        utilisateur_id, action, table_name, new_values, 
                        ip_address, user_agent, date_action
                    ) VALUES (?, 'COMMANDE_OPERATION', 'commandes', ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $utilisateurId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log commande operation: " . $e->getMessage());
        }
    }

    /**
     * Exporte les commandes en CSV
     */
    public function exporterCommandes(array $filtres = []): string
    {
        $commandes = $this->getListeCommandes($filtres);
        
        $csv = "Numéro Commande,Date Commande,Fournisseur,Statut,Date Livraison Prévue,Montant Total,Nombre Articles\n";
        
        foreach ($commandes as $commande) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%.2f,%d\n",
                $commande['numero_commande'],
                $commande['date_commande'],
                $commande['fournisseur_nom'],
                $commande['statut_commande'],
                $commande['date_livraison_prevue'] ?? '',
                $commande['montant_total'],
                $commande['nombre_articles']
            );
        }
        
        return $csv;
    }
}
