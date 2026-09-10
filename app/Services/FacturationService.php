<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class FacturationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle facture
     */
    public function creerFacture(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Générer le numéro de facture
            $numeroFacture = $this->genererNumeroFacture();

            $sql = "INSERT INTO factures (
                        numero_facture, vente_id, client_id, type_facture,
                        date_emission, date_echeance, montant_ht, montant_tva,
                        montant_ttc, statut_paiement, mode_paiement,
                        conditions_paiement, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $numeroFacture,
                $data['vente_id'] ?? null,
                $data['client_id'] ?? null,
                $data['type_facture'] ?? 'VENTE',
                $data['date_emission'] ?? date('Y-m-d H:i:s'),
                $data['date_echeance'] ?? null,
                $data['montant_ht'] ?? 0,
                $data['montant_tva'] ?? 0,
                $data['montant_ttc'] ?? 0,
                $data['statut_paiement'] ?? 'IMPAYE',
                $data['mode_paiement'] ?? 'COMPTANT',
                $data['conditions_paiement'] ?? null,
                $data['notes'] ?? null,
                $data['created_by']
            ]);

            $factureId = $this->db->lastInsertId();

            // Ajouter les articles de la facture
            if (!empty($data['articles'])) {
                foreach ($data['articles'] as $article) {
                    $sql = "INSERT INTO facture_articles (
                                facture_id, produit_id, quantite, prix_unitaire_ht,
                                montant_ht, tva_taux, montant_tva, montant_ttc
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $factureId,
                        $article['produit_id'],
                        $article['quantite'],
                        $article['prix_unitaire_ht'],
                        $article['montant_ht'],
                        $article['tva_taux'] ?? 0,
                        $article['montant_tva'] ?? 0,
                        $article['montant_ttc']
                    ]);
                }
            }

            $this->db->commit();

            // Logger l'opération
            $this->logFactureOperation($data['created_by'], 'FACTURE_CREATION', [
                'facture_id' => $factureId,
                'numero_facture' => $numeroFacture,
                'montant_ttc' => $data['montant_ttc']
            ]);

            return [
                'success' => true,
                'facture_id' => $factureId,
                'numero_facture' => $numeroFacture,
                'message' => 'Facture créée avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Met à jour le statut de paiement d'une facture
     */
    public function mettreAJourStatutPaiement(int $factureId, string $statutPaiement, int $utilisateurId, ?string $notes = null): array
    {
        try {
            $this->db->beginTransaction();

            $ancienStatut = $this->getFacture($factureId)['statut_paiement'];

            $sql = "UPDATE factures 
                    SET statut_paiement = ?, date_paiement = ?, notes_paiement = ?, 
                        utilisateur_paiement_id = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $statutPaiement,
                ($statutPaiement === 'PAYE') ? date('Y-m-d H:i:s') : null,
                $notes,
                $utilisateurId,
                $factureId
            ]);

            $this->db->commit();

            // Logger l'opération
            $this->logFactureOperation($utilisateurId, 'STATUT_PAIEMENT_UPDATE', [
                'facture_id' => $factureId,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $statutPaiement
            ]);

            return [
                'success' => true,
                'message' => 'Statut de paiement mis à jour avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut de paiement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enregistre un paiement pour une facture
     */
    public function enregistrerPaiement(int $factureId, array $data): array
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO paiements_factures (
                        facture_id, montant_paiement, date_paiement, mode_paiement,
                        reference_paiement, notes, utilisateur_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $factureId,
                $data['montant_paiement'],
                $data['date_paiement'] ?? date('Y-m-d H:i:s'),
                $data['mode_paiement'],
                $data['reference_paiement'] ?? null,
                $data['notes'] ?? null,
                $data['utilisateur_id']
            ]);

            $paiementId = $this->db->lastInsertId();

            // Mettre à jour le statut de la facture
            $facture = $this->getFacture($factureId);
            $totalPaiements = $facture['montant_ttc'] - $facture['montant_paye'];
            
            $nouveauStatut = 'PARTIELLEMENT_PAYE';
            if (($facture['montant_paye'] + $data['montant_paiement']) >= $facture['montant_ttc']) {
                $nouveauStatut = 'PAYE';
            }

            $sql = "UPDATE factures 
                    SET montant_paye = montant_paye + ?, statut_paiement = ?, 
                        date_paiement = ? 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['montant_paiement'],
                $nouveauStatut,
                ($nouveauStatut === 'PAYE') ? date('Y-m-d H:i:s') : $facture['date_paiement'],
                $factureId
            ]);

            $this->db->commit();

            // Logger l'opération
            $this->logFactureOperation($data['utilisateur_id'], 'PAIEMENT_ENREGISTRE', [
                'facture_id' => $factureId,
                'paiement_id' => $paiementId,
                'montant_paiement' => $data['montant_paiement'],
                'nouveau_statut' => $nouveauStatut
            ]);

            return [
                'success' => true,
                'paiement_id' => $paiementId,
                'nouveau_statut' => $nouveauStatut,
                'reste_a_payer' => $totalPaiements - $data['montant_paiement'],
                'message' => 'Paiement enregistré avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du paiement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère une facture par son ID
     */
    public function getFacture(int $factureId): ?array
    {
        $sql = "SELECT f.*, 
                        u.username as createur_nom,
                        c.nom as client_nom,
                        v.numero_facture as vente_facture
                    FROM factures f
                    LEFT JOIN utilisateurs u ON f.created_by = u.id
                    LEFT JOIN clients c ON f.client_id = c.id
                    LEFT JOIN ventes v ON f.vente_id = v.id
                    WHERE f.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$factureId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère les articles d'une facture
     */
    public function getArticlesFacture(int $factureId): array
    {
        $sql = "SELECT fa.*, p.nom as produit_nom, p.reference
                    FROM facture_articles fa
                    JOIN produits p ON fa.produit_id = p.id
                    WHERE fa.facture_id = ?
                    ORDER BY fa.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$factureId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des factures avec filtres
     */
    public function getListeFactures(array $filtres = []): array
    {
        $sql = "SELECT f.*, 
                        u.username as createur_nom,
                        c.nom as client_nom,
                        COUNT(fa.id) as nombre_articles,
                        DATE(f.date_emission) as date_emission_formatee
                    FROM factures f
                    LEFT JOIN utilisateurs u ON f.created_by = u.id
                    LEFT JOIN clients c ON f.client_id = c.id
                    LEFT JOIN facture_articles fa ON f.id = fa.facture_id";
        
        $where = [];
        $params = [];

        // Filtres
        if (!empty($filtres['client_id'])) {
            $where[] = "f.client_id = ?";
            $params[] = $filtres['client_id'];
        }

        if (!empty($filtres['statut_paiement'])) {
            $where[] = "f.statut_paiement = ?";
            $params[] = $filtres['statut_paiement'];
        }

        if (!empty($filtres['date_debut'])) {
            $where[] = "DATE(f.date_emission) >= ?";
            $params[] = $filtres['date_debut'];
        }

        if (!empty($filtres['date_fin'])) {
            $where[] = "DATE(f.date_emission) <= ?";
            $params[] = $filtres['date_fin'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY f.id ORDER BY f.date_emission DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les factures impayées
     */
    public function getFacturesImpayees(): array
    {
        $sql = "SELECT f.*, 
                        c.nom as client_nom,
                        DATEDIFF(f.date_echeance, CURDATE()) as jours_retard,
                        f.montant_ttc - f.montant_paye as reste_a_payer
                    FROM factures f
                    LEFT JOIN clients c ON f.client_id = c.id
                    WHERE f.statut_paiement IN ('IMPAYE', 'PARTIELLEMENT_PAYE')
                    ORDER BY f.date_echeance ASC";
        
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les paiements d'une facture
     */
    public function getPaiementsFacture(int $factureId): array
    {
        $sql = "SELECT pf.*, u.username as utilisateur_nom
                    FROM paiements_factures pf
                    LEFT JOIN utilisateurs u ON pf.utilisateur_id = u.id
                    WHERE pf.facture_id = ?
                    ORDER BY pf.date_paiement DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$factureId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule les statistiques de facturation
     */
    public function getStatistiquesFacturation(?string $dateDebut = null, ?string $dateFin = null): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as nombre_factures,
                        SUM(montant_ttc) as chiffre_affaires,
                        SUM(montant_ht) as ca_ht,
                        SUM(montant_tva) as total_tva,
                        AVG(montant_ttc) as montant_moyen,
                        COUNT(CASE WHEN statut_paiement = 'PAYE' THEN 1 END) as factures_payees,
                        COUNT(CASE WHEN statut_paiement = 'IMPAYE' THEN 1 END) as factures_impayees,
                        SUM(CASE WHEN statut_paiement = 'IMPAYE' THEN montant_ttc - montant_paye ELSE 0 END) as total_impaye
                    FROM factures";
            
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
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Génère un numéro de facture unique
     */
    private function genererNumeroFacture(): string
    {
        $prefix = 'FAC';
        $date = date('Ymd');
        $sequence = $this->getSequenceJour($date);

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère la séquence du jour pour les factures
     */
    private function getSequenceJour(string $date): int
    {
        $sql = "SELECT COUNT(*) as count 
                    FROM factures 
                    WHERE DATE(date_emission) = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] + 1;
    }

    /**
     * Exporte les factures en CSV
     */
    public function exporterFactures(array $filtres = []): string
    {
        $factures = $this->getListeFactures($filtres);
        
        $csv = "Numéro Facture,Date Émission,Client,Statut Paiement,Montant HT,Montant TVA,Montant TTC,Reste à Payer\n";
        
        foreach ($factures as $facture) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%.2f,%.2f,%.2f,%.2f\n",
                $facture['numero_facture'],
                $facture['date_emission'],
                $facture['client_nom'],
                $facture['statut_paiement'],
                $facture['montant_ht'],
                $facture['montant_tva'],
                $facture['montant_ttc'],
                $facture['montant_ttc'] - $facture['montant_paye']
            );
        }
        
        return $csv;
    }

    /**
     * Logger les opérations sur les factures
     */
    private function logFactureOperation(int $utilisateurId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs (
                        utilisateur_id, action, table_name, new_values, 
                        ip_address, user_agent, date_action
                    ) VALUES (?, 'FACTURE_OPERATION', 'factures', ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $utilisateurId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log facture operation: " . $e->getMessage());
        }
    }

    /**
     * Génère un rapport de facturation
     */
    public function genererRapportFacturation(string $dateDebut, string $dateFin): array
    {
        try {
            $statistiques = $this->getStatistiquesFacturation($dateDebut, $dateFin);
            $facturesImpayees = $this->getFacturesImpayees();
            
            // Filtrer les factures impayées pour la période
            $facturesImpayeesPeriode = array_filter($facturesImpayees, function($facture) use ($dateDebut, $dateFin) {
                return $facture['date_emission'] >= $dateDebut && $facture['date_emission'] <= $dateFin;
            });

            $totalImpayePeriode = array_sum(array_column($facturesImpayeesPeriode, 'reste_a_payer'));

            return [
                'success' => true,
                'periode' => [
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin
                ],
                'statistiques' => $statistiques['data'],
                'factures_impayees' => [
                    'total_general' => count($facturesImpayees),
                    'total_periode' => count($facturesImpayeesPeriode),
                    'montant_total_periode' => $totalImpayePeriode
                ],
                'details_factures_impayees' => $facturesImpayeesPeriode
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport: ' . $e->getMessage()
            ];
        }
    }
}
