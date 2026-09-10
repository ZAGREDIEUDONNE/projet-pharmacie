<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Produit;
use App\Services\AuditService;
use PDO;
use PDOException;
use Exception;

class PeremptionService
{
    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db, AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Analyse complète des péremptions avec alertes
     * Modifié pour utiliser stock.date_peremption au lieu de lots
     */
    public function analyserPeremptions(int $horizon = 180, bool $includeExpired = true): array
    {
        $horizon = in_array($horizon, [30, 60, 180], true) ? $horizon : 180;
        $sql = "SELECT 
                    p.id as produit_id,
                    p.nom as produit_nom,
                    p.code_cip,
                    l.id as lot_id,
                    l.numero_lot,
                    l.date_fabrication,
                    l.prix_achat_unitaire,
                    l.fournisseur_id,
                    f.nom as fournisseur_nom,
                    l.date_peremption,
                    l.quantite_restante,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                    CASE 
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 60 THEN 'ALERTE'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 180 THEN 'ATTENTION'
                        ELSE 'NORMAL'
                    END as niveau_peremption,
                    CASE 
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'danger'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'danger'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 60 THEN 'warning'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 180 THEN 'info'
                        ELSE 'success'
                    END as classe_alerte
                FROM produits p
                JOIN lots l ON l.produit_id = p.id
                LEFT JOIN fournisseurs f ON f.id = l.fournisseur_id
                WHERE p.is_actif = 1 
                AND p.deleted_at IS NULL
                AND l.is_actif = 1
                AND l.quantite_restante > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL :horizon DAY)" . ($includeExpired ? '' : "
                AND l.date_peremption >= CURDATE()") . "
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':horizon', $horizon, PDO::PARAM_INT);
        $stmt->execute();
        
        $peremptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Regrouper par niveau
        $resultats = [
            'perimes' => array_filter($peremptions, fn($p) => $p['niveau_peremption'] === 'PERIME'),
            'urgents' => array_filter($peremptions, fn($p) => $p['niveau_peremption'] === 'URGENT'),
            'alertes' => array_filter($peremptions, fn($p) => $p['niveau_peremption'] === 'ALERTE'),
            'attentions' => array_filter($peremptions, fn($p) => $p['niveau_peremption'] === 'ATTENTION'),
            'total_analyse' => count($peremptions),
            'valeur_perimee' => 0,
            'valeur_en_risque' => 0,
            'resume' => [
                'perimes' => 0,
                'urgents' => 0,
                'alertes' => 0,
                'attentions' => 0
            ]
        ];
        
        $resumeKeysByNiveau = [
            'PERIME' => 'perimes',
            'URGENT' => 'urgents',
            'ALERTE' => 'alertes',
            'ATTENTION' => 'attentions',
        ];

        // Calculer les valeurs
        foreach ($peremptions as $peremption) {
            $valeur = $peremption['quantite_restante'] * $peremption['prix_achat_unitaire'];
            $niveau = (string)($peremption['niveau_peremption'] ?? '');
            $resumeKey = $resumeKeysByNiveau[$niveau] ?? null;

            if ($resumeKey !== null) {
                $resultats['resume'][$resumeKey]++;
            }
            
            if ($niveau === 'PERIME') {
                $resultats['valeur_perimee'] += $valeur;
            } elseif (in_array($niveau, ['URGENT', 'ALERTE'], true)) {
                $resultats['valeur_en_risque'] += $valeur;
            }
        }
        
        return $resultats;
    }

    /**
     * Traite les produits périmés
     * Modifié pour utiliser stock.date_peremption au lieu de lots
     */
    public function traiterPerimes(array $produitsPerimes, int $utilisateurId): array
    {
        $this->db->beginTransaction();
        
        try {
            $produitsTraites = [];
            $valeurTotalePerdue = 0;
            
            foreach ($produitsPerimes as $produitId) {
                $stock = $this->getStockByProduitId($produitId);
                if (!$stock || $stock['quantite_disponible'] <= 0) {
                    continue;
                }
                
                // Calculer la valeur perdue
                $valeurPerdue = $stock['quantite_disponible'] * $stock['prix_achat'];
                $valeurTotalePerdue += $valeurPerdue;
                
                // Mettre à jour le stock (mettre à 0)
                $sql = "UPDATE stock SET 
                            quantite_disponible = 0,
                            quantite_theorique = 0,
                            valeur_stock = 0,
                            date_peremption = NULL,
                            updated_at = NOW()
                        WHERE produit_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$produitId]);
                
                // Enregistrer le mouvement de perte
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
                        ) VALUES (?, NULL, 'PERTE', ?, ?, ?, 'Produit périmé', 'PEREMPTION', ?, ?, NOW())";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $produitId,
                    $stock['quantite_disponible'],
                    $stock['quantite_disponible'],
                    0,
                    'Produit périmé - Produit ID ' . $produitId,
                    $produitId,
                    $utilisateurId
                ]);
                
                $produitsTraites[] = [
                    'produit_id' => $produitId,
                    'produit_nom' => $stock['produit_nom'],
                    'quantite_perdue' => $stock['quantite_disponible'],
                    'valeur_perdue' => $valeurPerdue
                ];
                
                // Logger l'action
                $this->auditService->logAction(
                    $utilisateurId,
                    'TRAITER_PRODUIT_PERIME',
                    'stock',
                    $produitId,
                    $stock,
                    [
                        'quantite_perdue' => $stock['quantite_disponible'],
                        'valeur_perdue' => $valeurPerdue,
                        'statut' => 'STOCK_REINITIALISE'
                    ]
                );
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'produits_traites' => $produitsTraites,
                'valeur_totale_perdue' => $valeurTotalePerdue,
                'message' => count($produitsTraites) . ' produits périmés traités'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors du traitement des produits périmés: " . $e->getMessage());
        }
    }

    /**
     * Génère les alertes de péremption automatiques
     */
    public function genererAlertesPeremption(): array
    {
        $analyse = $this->analyserPeremptions();
        $alertes = [];
        
        // Alertes critiques (périmés)
        if (!empty($analyse['perimes'])) {
            $alertes[] = [
                'type' => 'CRITIQUE',
                'titre' => 'Produits périmés détectés',
                'message' => count($analyse['perimes']) . ' produits sont périmés et doivent être traités',
                'valeur' => $analyse['valeur_perimee'],
                'action' => 'TRAITER_PERIMES',
                'produits' => $analyse['perimes']
            ];
        }
        
        // Alertes urgentes (< 30 jours)
        if (!empty($analyse['urgents'])) {
            $valeurUrgent = array_sum(array_map(fn($p) => $p['quantite_restante'] * $p['prix_achat_unitaire'], $analyse['urgents']));
            
            $alertes[] = [
                'type' => 'URGENT',
                'titre' => 'Péremption imminente',
                'message' => count($analyse['urgents']) . ' produits expirent dans moins de 30 jours',
                'valeur' => $valeurUrgent,
                'action' => 'PROMOTION_VENTE',
                'produits' => $analyse['urgents']
            ];
        }
        
        // Alertes préventives (30-90 jours)
        if (!empty($analyse['alertes'])) {
            $valeurAlerte = array_sum(array_map(fn($p) => $p['quantite_restante'] * $p['prix_achat_unitaire'], $analyse['alertes']));
            
            $alertes[] = [
                'type' => 'ALERTE',
                'titre' => 'Péremption à prévoir',
                'message' => count($analyse['alertes']) . ' produits expirent dans 30-90 jours',
                'valeur' => $valeurAlerte,
                'action' => 'SURVEILLANCE',
                'produits' => $analyse['alertes']
            ];
        }
        
        // Enregistrer les alertes dans le système
        foreach ($alertes as $alerte) {
            $this->auditService->logEvent(
                'ALERTE_PEREMPTION',
                $alerte['type'] . '_PEREMPTION',
                $alerte['titre'],
                $alerte['message'],
                [
                    'type' => $alerte['type'],
                    'nombre_lots' => count($alerte['lots']),
                    'valeur' => $alerte['valeur'],
                    'action_recommandee' => $alerte['action']
                ]
            );
        }
        
        return [
            'success' => true,
            'alertes' => $alertes,
            'total_alertes' => count($alertes),
            'resume' => $analyse['resume']
        ];
    }

    /**
     * Suggère des actions pour les lots proches de péremption
     */
    public function getSuggestionsPeremption(): array
    {
        $sql = "SELECT 
                    p.id as produit_id,
                    p.nom as produit_nom,
                    p.code_cip,
                    l.id as lot_id,
                    l.numero_lot,
                    l.date_peremption,
                    l.quantite_restante,
                    l.prix_achat_unitaire,
                    l.prix_vente_suggere,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                    p.prix_vente as prix_vente_normal,
                    (p.prix_vente * 0.7) as prix_vente_promotion,
                    (l.quantite_restante * l.prix_achat_unitaire) as valeur_stock
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1 
                AND l.quantite_restante > 0
                AND l.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $suggestions = [];
        
        foreach ($lots as $lot) {
            $suggestion = [
                'lot' => $lot,
                'suggestions' => []
            ];
            
            // Suggestion 1: Promotion de vente
            if ($lot['jours_restants'] <= 30) {
                $suggestion['suggestions'][] = [
                    'action' => 'PROMOTION_VENTE',
                    'priorite' => 'HAUTE',
                    'description' => 'Lancer une promotion à 30% de remise',
                    'prix_suggere' => $lot['prix_vente_promotion'],
                    'economie_client' => $lot['prix_vente_normal'] - $lot['prix_vente_promotion'],
                    'gain_potentiel' => ($lot['prix_vente_promotion'] - $lot['prix_achat_unitaire']) * $lot['quantite_restante']
                ];
            }
            
            // Suggestion 2: Vente prioritaire
            if ($lot['jours_restants'] <= 60) {
                $suggestion['suggestions'][] = [
                    'action' => 'VENTE_PRIORITAIRE',
                    'priorite' => 'MOYENNE',
                    'description' => 'Mettre en avant dans les ventes',
                    'prix_suggere' => $lot['prix_vente_normal'],
                    'gain_potentiel' => ($lot['prix_vente_normal'] - $lot['prix_achat_unitaire']) * $lot['quantite_restante']
                ];
            }
            
            // Suggestion 3: Contact fournisseur (retour possible)
            if ($lot['jours_restants'] <= 15) {
                $suggestion['suggestions'][] = [
                    'action' => 'CONTACT_FOURNISSEUR',
                    'priorite' => 'URGENTE',
                    'description' => 'Contacter le fournisseur pour un retour éventuel',
                    'perte_evitable' => $lot['valeur_stock']
                ];
            }
            
            // Suggestion 4: Don (si très proche péremption)
            if ($lot['jours_restants'] <= 7) {
                $suggestion['suggestions'][] = [
                    'action' => 'DON',
                    'priorite' => 'MOYENNE',
                    'description' => 'Envisager un don pour éviter la perte totale',
                    'perte_totale' => $lot['valeur_stock'],
                    'economie_fiscale' => $lot['valeur_stock'] * 0.6 // 60% de déduction fiscale
                ];
            }
            
            $suggestions[] = $suggestion;
        }
        
        return [
            'success' => true,
            'suggestions' => $suggestions,
            'total_lots_concernes' => count($lots),
            'message' => count($lots) . ' lots nécessitent une attention particulière'
        ];
    }

    /**
     * Crée un rapport de péremption détaillé
     */
    public function genererRapportPeremption(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    DATE(l.date_peremption) as date_peremption,
                    COUNT(*) as nombre_lots,
                    SUM(l.quantite_restante) as quantite_totale,
                    SUM(l.quantite_restante * l.prix_achat_unitaire) as valeur_totale,
                    COUNT(CASE WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 1 END) as lots_perimes,
                    COUNT(CASE WHEN DATEDIFF(l.date_peremption, CURDATE()) BETWEEN 1 AND 30 THEN 1 END) as lots_urgents,
                    COUNT(CASE WHEN DATEDIFF(l.date_peremption, CURDATE()) BETWEEN 31 AND 90 THEN 1 END) as lots_alertes
                FROM lots l
                WHERE l.is_actif = 1 
                AND l.quantite_restante > 0
                AND l.date_peremption BETWEEN ? AND ?
                GROUP BY DATE(l.date_peremption)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        $rapport = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les totaux
        $totaux = [
            'total_lots' => array_sum(array_column($rapport, 'nombre_lots')),
            'total_quantite' => array_sum(array_column($rapport, 'quantite_totale')),
            'total_valeur' => array_sum(array_column($rapport, 'valeur_totale')),
            'total_perimes' => array_sum(array_column($rapport, 'lots_perimes')),
            'total_urgents' => array_sum(array_column($rapport, 'lots_urgents')),
            'total_alertes' => array_sum(array_column($rapport, 'lots_alertes'))
        ];
        
        return [
            'success' => true,
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ],
            'rapport' => $rapport,
            'totaux' => $totaux,
            'perte_estimee' => $totaux['total_perimes'] > 0 ? 'Perte estimée significative' : 'Perte minimale'
        ];
    }

    /**
     * Met à jour le prix de vente suggéré pour les lots proches de péremption
     */
    public function mettreAJourPrixPromotion(int $lotId, float $nouveauPrix, int $utilisateurId): array
    {
        $this->db->beginTransaction();
        
        try {
            $lot = $this->getLotById($lotId);
            if (!$lot) {
                throw new Exception("Lot non trouvé");
            }
            
            // Valider le prix de promotion
            if ($nouveauPrix <= 0 || $nouveauPrix >= $lot['prix_achat_unitaire']) {
                throw new Exception("Prix de promotion invalide");
            }
            
            // Mettre à jour le prix
            $sql = "UPDATE lots SET 
                        prix_vente_suggere = ?, 
                        updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nouveauPrix, $lotId]);
            
            // Logger l'action
            $this->auditService->logAction(
                $utilisateurId,
                'UPDATE_PRIX_PROMOTION',
                'lots',
                $lotId,
                ['prix_vente_suggere' => $lot['prix_vente_suggere']],
                ['prix_vente_suggere' => $nouveauPrix]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'ancien_prix' => $lot['prix_vente_suggere'],
                'nouveau_prix' => $nouveauPrix,
                'remise' => round((1 - $nouveauPrix / $lot['prix_achat_unitaire']) * 100, 2),
                'message' => 'Prix de promotion mis à jour'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la mise à jour du prix: " . $e->getMessage());
        }
    }

    /**
     * Récupère le stock d'un produit par son ID
     */
    private function getStockByProduitId(int $produitId): ?array
    {
        $sql = "SELECT s.*, p.nom as produit_nom, p.code_cip, p.prix_achat
                FROM stock s
                JOIN produits p ON s.produit_id = p.id
                WHERE s.produit_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Exporte les données de péremption
     */
    public function exporterPeremptions(string $format = 'csv'): array
    {
        $analyse = $this->analyserPeremptions();
        
        $donneesExport = [];
        
        foreach (['perimes', 'urgents', 'alertes', 'attentions'] as $niveau) {
            foreach ($analyse[$niveau] as $peremption) {
                $donneesExport[] = [
                    'produit_nom' => $peremption['produit_nom'],
                    'code_cip' => $peremption['code_cip'],
                    'numero_lot' => $peremption['numero_lot'],
                    'date_fabrication' => $peremption['date_fabrication'],
                    'date_peremption' => $peremption['date_peremption'],
                    'jours_restants' => $peremption['jours_restants'],
                    'quantite_restante' => $peremption['quantite_restante'],
                    'prix_achat_unitaire' => $peremption['prix_achat_unitaire'],
                    'valeur_stock' => $peremption['quantite_restante'] * $peremption['prix_achat_unitaire'],
                    'fournisseur_nom' => $peremption['fournisseur_nom'],
                    'niveau_peremption' => $peremption['niveau_peremption']
                ];
            }
        }
        
        return [
            'success' => true,
            'format' => $format,
            'donnees' => $donneesExport,
            'total_enregistrements' => count($donneesExport),
            'date_export' => date('Y-m-d H:i:s')
        ];
    }
}
