<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class BonService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée un nouveau bon (de livraison, de retour, etc.)
     */
    public function creerBon(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Générer le numéro du bon
            $numeroBon = $this->genererNumeroBon($data['type_bon']);

            $sql = "INSERT INTO bons (
                        type_bon, numero_bon, reference_id, reference_type,
                        fournisseur_id, client_id, utilisateur_id,
                        date_emission, date_echeance, date_livraison_prevue,
                        montant_total, statut_bon, conditions_paiement,
                        notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['type_bon'],
                $numeroBon,
                $data['reference_id'] ?? null,
                $data['reference_type'] ?? null,
                $data['fournisseur_id'] ?? null,
                $data['client_id'] ?? null,
                $data['utilisateur_id'],
                $data['date_emission'] ?? date('Y-m-d H:i:s'),
                $data['date_echeance'] ?? null,
                $data['date_livraison_prevue'] ?? null,
                $data['montant_total'] ?? 0,
                $data['statut_bon'] ?? 'EMIS',
                $data['conditions_paiement'] ?? null,
                $data['notes'] ?? null,
                $data['created_by'] ?? $data['utilisateur_id']
            ]);

            $bonId = $this->db->lastInsertId();

            // Insérer les articles du bon
            if (!empty($data['articles'])) {
                foreach ($data['articles'] as $article) {
                    $sql = "INSERT INTO bons_articles (
                                bon_id, produit_id, quantite, prix_unitaire,
                                montant_total, lot_id, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $bonId,
                        $article['produit_id'],
                        $article['quantite'],
                        $article['prix_unitaire'],
                        $article['montant_total'],
                        $article['lot_id'] ?? null,
                        $article['notes'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            // Logger l'opération
            $this->logBonOperation($data['utilisateur_id'], 'BON_CREATION', [
                'bon_id' => $bonId,
                'type_bon' => $data['type_bon'],
                'numero_bon' => $numeroBon,
                'montant_total' => $data['montant_total']
            ]);

            return [
                'success' => true,
                'bon_id' => $bonId,
                'numero_bon' => $numeroBon,
                'message' => 'Bon créé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du bon: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Met à jour le statut d'un bon
     */
    public function mettreAJourStatutBon(int $bonId, string $statut, int $utilisateurId, ?string $notes = null): array
    {
        try {
            $this->db->beginTransaction();

            // Récupérer l'ancien statut
            $ancienStatut = $this->getBon($bonId)['statut_bon'];

            $sql = "UPDATE bons 
                    SET statut_bon = ?, date_traitement = ?, utilisateur_traitement_id = ?, notes_traitement = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $statut,
                date('Y-m-d H:i:s'),
                $utilisateurId,
                $notes,
                $bonId
            ]);

            $this->db->commit();

            // Logger l'opération
            $this->logBonOperation($utilisateurId, 'BON_STATUT_UPDATE', [
                'bon_id' => $bonId,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $statut,
                'notes' => $notes
            ]);

            return [
                'success' => true,
                'message' => 'Statut du bon mis à jour avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Marque un bon comme utilisé
     */
    public function utiliserBon(int $bonId, int $venteId, int $utilisateurId): array
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE bons 
                    SET statut_bon = 'UTILISE', 
                        date_utilisation = NOW(),
                        vente_id = ?,
                        utilisateur_utilisation_id = ?
                    WHERE id = ? AND statut_bon IN ('EMIS', 'VALIDE')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$venteId, $utilisateurId, $bonId]);

            $this->db->commit();

            // Logger l'opération
            $this->logBonOperation($utilisateurId, 'BON_UTILISATION', [
                'bon_id' => $bonId,
                'vente_id' => $venteId
            ]);

            return [
                'success' => true,
                'message' => 'Bon marqué comme utilisé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'utilisation du bon: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère un bon par son ID
     */
    public function getBon(int $bonId): ?array
    {
        $sql = "SELECT b.*, 
                        u.username as createur_nom, u.nom as createur_prenom,
                        ut.username as traitement_nom, ut.nom as traitement_prenom,
                        f.nom as fournisseur_nom,
                        c.nom as client_nom
                    FROM bons b
                    LEFT JOIN utilisateurs u ON b.created_by = u.id
                    LEFT JOIN utilisateurs ut ON b.utilisateur_traitement_id = ut.id
                    LEFT JOIN fournisseurs f ON b.fournisseur_id = f.id
                    LEFT JOIN clients c ON b.client_id = c.id
                    WHERE b.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bonId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère les articles d'un bon
     */
    public function getArticlesBon(int $bonId): array
    {
        $sql = "SELECT ba.*, p.nom as produit_nom, p.reference, l.numero_lot
                    FROM bons_articles ba
                    JOIN produits p ON ba.produit_id = p.id
                    LEFT JOIN lots l ON ba.lot_id = l.id
                    WHERE ba.bon_id = ?
                    ORDER BY ba.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bonId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des bons avec filtres
     */
    public function getListeBons(array $filtres = []): array
    {
        $sql = "SELECT b.*, 
                        u.username as createur_nom,
                        f.nom as fournisseur_nom,
                        c.nom as client_nom,
                        COUNT(ba.id) as nombre_articles
                    FROM bons b
                    LEFT JOIN utilisateurs u ON b.created_by = u.id
                    LEFT JOIN fournisseurs f ON b.fournisseur_id = f.id
                    LEFT JOIN clients c ON b.client_id = c.id
                    LEFT JOIN bons_articles ba ON b.id = ba.bon_id";
        
        $where = [];
        $params = [];

        // Filtres
        if (!empty($filtres['type_bon'])) {
            $where[] = "b.type_bon = ?";
            $params[] = $filtres['type_bon'];
        }

        if (!empty($filtres['statut_bon'])) {
            $where[] = "b.statut_bon = ?";
            $params[] = $filtres['statut_bon'];
        }

        if (!empty($filtres['fournisseur_id'])) {
            $where[] = "b.fournisseur_id = ?";
            $params[] = $filtres['fournisseur_id'];
        }

        if (!empty($filtres['client_id'])) {
            $where[] = "b.client_id = ?";
            $params[] = $filtres['client_id'];
        }

        if (!empty($filtres['date_debut'])) {
            $where[] = "DATE(b.date_emission) >= ?";
            $params[] = $filtres['date_debut'];
        }

        if (!empty($filtres['date_fin'])) {
            $where[] = "DATE(b.date_emission) <= ?";
            $params[] = $filtres['date_fin'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY b.id ORDER BY b.date_emission DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les bons en attente
     */
    public function getBonsEnAttente(): array
    {
        $sql = "SELECT b.*, 
                        u.username as createur_nom,
                        f.nom as fournisseur_nom,
                        c.nom as client_nom,
                        DATEDIFF(b.date_echeance, CURDATE()) as jours_restants
                    FROM bons b
                    LEFT JOIN utilisateurs u ON b.created_by = u.id
                    LEFT JOIN fournisseurs f ON b.fournisseur_id = f.id
                    LEFT JOIN clients c ON b.client_id = c.id
                    WHERE b.statut_bon IN ('EMIS', 'VALIDE')
                    AND (b.date_echeance IS NULL OR b.date_echeance >= CURDATE())
                    ORDER BY b.date_echeance ASC";
        
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les bons utilisés
     */
    public function getBonsUtilises(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT b.*, 
                        u.username as createur_nom,
                        ut.username as utilisateur_nom,
                        f.nom as fournisseur_nom,
                        c.nom as client_nom,
                        v.numero_facture
                    FROM bons b
                    LEFT JOIN utilisateurs u ON b.created_by = u.id
                    LEFT JOIN utilisateurs ut ON b.utilisateur_utilisation_id = ut.id
                    LEFT JOIN fournisseurs f ON b.fournisseur_id = f.id
                    LEFT JOIN clients c ON b.client_id = c.id
                    LEFT JOIN ventes v ON b.vente_id = v.id
                    WHERE b.statut_bon = 'UTILISE'";
        
        $params = [];
        
        if ($dateDebut) {
            $sql .= " AND DATE(b.date_utilisation) >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND DATE(b.date_utilisation) <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " ORDER BY b.date_utilisation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Annule un bon
     */
    public function annulerBon(int $bonId, string $motif, int $utilisateurId): array
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE bons 
                    SET statut_bon = 'ANNULE', 
                        date_annulation = NOW(),
                        utilisateur_annulation_id = ?,
                        motif_annulation = ?
                    WHERE id = ? AND statut_bon IN ('EMIS', 'VALIDE')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$utilisateurId, $motif, $bonId]);

            $this->db->commit();

            // Logger l'opération
            $this->logBonOperation($utilisateurId, 'BON_ANNULATION', [
                'bon_id' => $bonId,
                'motif' => $motif
            ]);

            return [
                'success' => true,
                'message' => 'Bon annulé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'annulation du bon: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Génère un numéro de bon unique
     */
    private function genererNumeroBon(string $typeBon): string
    {
        $prefixes = [
            'LIVRAISON' => 'BL',
            'RETOUR' => 'BR',
            'AVOIR' => 'BA',
            'REMISE' => 'BREM',
            'GARANTIE' => 'BG'
        ];

        $prefix = $prefixes[$typeBon] ?? 'BON';
        $date = date('Ymd');
        $sequence = $this->getSequenceJour($typeBon, $date);

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère la séquence du jour pour un type de bon
     */
    private function getSequenceJour(string $typeBon, string $date): int
    {
        $sql = "SELECT COUNT(*) as count 
                    FROM bons 
                    WHERE type_bon = ? AND DATE(date_emission) = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$typeBon, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] + 1;
    }

    /**
     * Récupère les statistiques des bons
     */
    public function getStatistiquesBons(?string $dateDebut = null, ?string $dateFin = null): array
    {
        try {
            $sql = "SELECT 
                        type_bon,
                        COUNT(*) as nombre,
                        SUM(montant_total) as montant_total,
                        AVG(montant_total) as montant_moyen,
                        COUNT(CASE WHEN statut_bon = 'UTILISE' THEN 1 END) as utilises,
                        COUNT(CASE WHEN statut_bon = 'ANNULE' THEN 1 END) as annules,
                        COUNT(CASE WHEN statut_bon IN ('EMIS', 'VALIDE') AND date_echeance < CURDATE() THEN 1 END) as expires
                    FROM bons";
            
            $params = [];
            $where = [];
            
            if ($dateDebut) {
                $where[] = "DATE(date_emission) >= ?";
                $params[] = $dateDebut;
            }
            
            if ($dateFin) {
                $where[] = "DATE(date_emission) <= ?";
                $params[] = $dateFin;
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $sql .= " GROUP BY type_bon ORDER BY type_bon";
            
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
     * Vérifie si un bon peut être utilisé
     */
    public function verifierUtilisationBon(int $bonId): array
    {
        $sql = "SELECT statut_bon, date_echeance, montant_total, montant_utilise
                    FROM bons 
                    WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bonId]);
        $bon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bon) {
            return [
                'valide' => false,
                'message' => 'Bon introuvable'
            ];
        }

        // Vérifications
        if ($bon['statut_bon'] !== 'EMIS' && $bon['statut_bon'] !== 'VALIDE') {
            return [
                'valide' => false,
                'message' => 'Bon non valide pour utilisation (statut: ' . $bon['statut_bon'] . ')'
            ];
        }

        if ($bon['date_echeance'] && new DateTime($bon['date_echeance']) < new DateTime()) {
            return [
                'valide' => false,
                'message' => 'Bon expiré depuis le ' . $bon['date_echeance']
            ];
        }

        $resteUtilisable = $bon['montant_total'] - ($bon['montant_utilise'] ?? 0);
        if ($resteUtilisable <= 0) {
            return [
                'valide' => false,
                'message' => 'Bon entièrement utilisé'
            ];
        }

        return [
            'valide' => true,
            'message' => 'Bon valide pour utilisation',
            'montant_restant' => $resteUtilisable
        ];
    }

    /**
     * Logger les opérations sur les bons
     */
    private function logBonOperation(int $utilisateurId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs 
                    (utilisateur_id, action, table_name, new_values, ip_address, user_agent) 
                    VALUES (?, 'BON_OPERATION', 'bons', ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $utilisateurId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log bon operation: " . $e->getMessage());
        }
    }

    /**
     * Exporte les bons en CSV
     */
    public function exporterBons(array $filtres = []): string
    {
        $bons = $this->getListeBons($filtres);
        
        $csv = "Type Bon,Numéro Bon,Date Émission,Date Échéance,Fournisseur,Client,Montant Total,Statut\n";
        
        foreach ($bons as $bon) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s\n",
                $bon['type_bon'],
                $bon['numero_bon'],
                $bon['date_emission'],
                $bon['date_echeance'] ?? '',
                $bon['fournisseur_nom'] ?? '',
                $bon['client_nom'] ?? '',
                $bon['montant_total'],
                $bon['statut_bon']
            );
        }
        
        return $csv;
    }
}
