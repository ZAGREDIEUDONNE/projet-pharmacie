<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class IntegrationComptableService
{
    private PDO $db;
    private \App\Services\EcritureComptableService $ecritureService;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\EcritureComptableService $ecritureService, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->ecritureService = $ecritureService;
        $this->auditService = $auditService;
    }

    /**
     * Intègre les ventes → écritures comptables automatiques
     */
    public function integrerVentes(string $dateDebut = null, string $dateFin = null): array
    {
        $this->ecritureService->prepareComptabilite();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            $resultats = [];
            $totalTraitees = 0;
            $totalSucces = 0;
            $totalErreurs = 0;
            
            // Récupérer les ventes sans écritures
            $sql = "SELECT v.*, c.nom as client_nom 
                    FROM ventes v
                    LEFT JOIN clients c ON v.client_id = c.id
                    WHERE v.ecriture_id IS NULL 
                    AND v.statut_vente != 'ANNULEE'";
            
            $params = [];
            if ($dateDebut) {
                $sql .= " AND v.date_vente >= ?";
                $params[] = $dateDebut;
            }
            if ($dateFin) {
                $sql .= " AND v.date_vente <= ?";
                $params[] = $dateFin;
            }
            
            $sql .= " ORDER BY v.date_vente ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($ventes as $vente) {
                try {
                    $resultat = $this->ecritureService->genererEcrituresVente($vente);
                    $resultats[] = [
                        'type' => 'VENTE',
                        'id' => $vente['id'],
                        'reference' => $vente['numero_ticket'],
                        'success' => true,
                        'ecriture_id' => $resultat['ecriture_id'],
                        'message' => $resultat['message']
                    ];
                    $totalSucces++;
                    
                } catch (Exception $e) {
                    $resultats[] = [
                        'type' => 'VENTE',
                        'id' => $vente['id'],
                        'reference' => $vente['numero_ticket'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $totalErreurs++;
                }
                
                $totalTraitees++;
            }
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            // Logger l'opération globale
            $this->auditService->logEvent(
                'INTEGRATION_VENTES',
                'INTEGRATION_COMPTABLE',
                'Intégration des ventes en comptabilité',
                "Traitement de $totalTraitees ventes: $totalSucces succès, $totalErreurs erreurs",
                [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'periode' => [
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => "Intégration des ventes terminée: $totalSucces/$totalTraitees succès",
                'resultats' => $resultats,
                'statistiques' => [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'taux_succes' => $totalTraitees > 0 ? round(($totalSucces / $totalTraitees) * 100, 2) : 0
                ]
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('IntegrationComptableService::integrerVentes - ' . $e->getMessage());
            throw new Exception("Erreur lors de l'intégration des ventes: " . $e->getMessage());
        }
    }

    /**
     * Intègre les commandes → impact fournisseur automatique
     */
    public function integrerCommandes(string $dateDebut = null, string $dateFin = null): array
    {
        $this->ecritureService->prepareComptabilite();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            $resultats = [];
            $totalTraitees = 0;
            $totalSucces = 0;
            $totalErreurs = 0;
            
            // Récupérer les commandes livrées sans écritures
            $sql = "SELECT c.*, f.nom as fournisseur_nom 
                    FROM commandes c
                    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                    WHERE c.ecriture_id IS NULL 
                    AND c.statut_commande = 'LIVREE'";
            
            $params = [];
            if ($dateDebut) {
                $sql .= " AND c.date_commande >= ?";
                $params[] = $dateDebut;
            }
            if ($dateFin) {
                $sql .= " AND c.date_commande <= ?";
                $params[] = $dateFin;
            }
            
            $sql .= " ORDER BY c.date_commande ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($commandes as $commande) {
                try {
                    $resultat = $this->ecritureService->genererEcrituresAchat($commande);
                    $resultats[] = [
                        'type' => 'COMMANDE',
                        'id' => $commande['id'],
                        'reference' => $commande['reference'],
                        'success' => true,
                        'ecriture_id' => $resultat['ecriture_id'],
                        'message' => $resultat['message']
                    ];
                    $totalSucces++;
                    
                } catch (Exception $e) {
                    $resultats[] = [
                        'type' => 'COMMANDE',
                        'id' => $commande['id'],
                        'reference' => $commande['reference'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $totalErreurs++;
                }
                
                $totalTraitees++;
            }
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            // Logger l'opération globale
            $this->auditService->logEvent(
                'INTEGRATION_COMMANDES',
                'INTEGRATION_COMPTABLE',
                'Intégration des commandes en comptabilité',
                "Traitement de $totalTraitees commandes: $totalSucces succès, $totalErreurs erreurs",
                [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'periode' => [
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => "Intégration des commandes terminée: $totalSucces/$totalTraitees succès",
                'resultats' => $resultats,
                'statistiques' => [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'taux_succes' => $totalTraitees > 0 ? round(($totalSucces / $totalTraitees) * 100, 2) : 0
                ]
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('IntegrationComptableService::integrerCommandes - ' . $e->getMessage());
            throw new Exception("Erreur lors de l'intégration des commandes: " . $e->getMessage());
        }
    }

    /**
     * Intègre les mouvements de caisse → flux financier direct
     */
    public function integrerMouvementsCaisse(string $dateDebut = null, string $dateFin = null): array
    {
        $this->ecritureService->prepareComptabilite();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            $resultats = [];
            $totalTraitees = 0;
            $totalSucces = 0;
            $totalErreurs = 0;
            
            // Récupérer les mouvements sans écritures
            $sql = "SELECT mc.*, c.nom as client_nom, f.nom as fournisseur_nom
                    FROM mouvements_caisse mc
                    LEFT JOIN clients c ON mc.client_id = c.id
                    LEFT JOIN fournisseurs f ON mc.fournisseur_id = f.id
                    WHERE mc.ecriture_id IS NULL";
            
            $params = [];
            if ($dateDebut) {
                $sql .= " AND mc.date_mouvement >= ?";
                $params[] = $dateDebut;
            }
            if ($dateFin) {
                $sql .= " AND mc.date_mouvement <= ?";
                $params[] = $dateFin;
            }
            
            $sql .= " ORDER BY mc.date_mouvement ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($mouvements as $mouvement) {
                try {
                    $resultat = $this->ecritureService->genererEcrituresCaisse($mouvement);
                    $resultats[] = [
                        'type' => 'MOUVEMENT_CAISSE',
                        'id' => $mouvement['id'],
                        'reference' => $mouvement['motif'],
                        'success' => true,
                        'ecriture_id' => $resultat['ecriture_id'],
                        'message' => $resultat['message']
                    ];
                    $totalSucces++;
                    
                } catch (Exception $e) {
                    $resultats[] = [
                        'type' => 'MOUVEMENT_CAISSE',
                        'id' => $mouvement['id'],
                        'reference' => $mouvement['motif'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $totalErreurs++;
                }
                
                $totalTraitees++;
            }
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            // Logger l'opération globale
            $this->auditService->logEvent(
                'INTEGRATION_CAISSE',
                'INTEGRATION_COMPTABLE',
                'Intégration des mouvements de caisse en comptabilité',
                "Traitement de $totalTraitees mouvements: $totalSucces succès, $totalErreurs erreurs",
                [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'periode' => [
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => "Intégration des mouvements de caisse terminée: $totalSucces/$totalTraitees succès",
                'resultats' => $resultats,
                'statistiques' => [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'taux_succes' => $totalTraitees > 0 ? round(($totalSucces / $totalTraitees) * 100, 2) : 0
                ]
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('IntegrationComptableService::integrerMouvementsCaisse - ' . $e->getMessage());
            throw new Exception("Erreur lors de l'intégration des mouvements de caisse: " . $e->getMessage());
        }
    }

    /**
     * Intègre le stock → impact comptable automatique
     */
    public function integrerStock(string $dateDebut = null, string $dateFin = null): array
    {
        $this->ecritureService->prepareComptabilite();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            $resultats = [];
            $totalTraitees = 0;
            $totalSucces = 0;
            $totalErreurs = 0;
            
            // Récupérer les mouvements de stock sans écritures
            $sql = "SELECT ms.*, p.nom as produit_nom
                    FROM mouvements_stock ms
                    JOIN produits p ON ms.produit_id = p.id
                    WHERE ms.ecriture_id IS NULL
                    AND (ms.reference_type IS NULL OR ms.reference_type NOT IN ('VENTE', 'vente'))";
            
            $params = [];
            if ($dateDebut) {
                $sql .= " AND ms.date_mouvement >= ?";
                $params[] = $dateDebut;
            }
            if ($dateFin) {
                $sql .= " AND ms.date_mouvement <= ?";
                $params[] = $dateFin;
            }
            
            $sql .= " ORDER BY ms.date_mouvement ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($mouvements as $mouvement) {
                try {
                    // Générer l'écriture comptable pour le mouvement de stock
                    $ecritureData = [
                        'journal_code' => 'OD', // Journal des opérations diverses
                        'numero_piece' => 'ST' . date('Ymd') . str_pad($mouvement['id'], 6, '0', STR_PAD_LEFT),
                        'date_ecriture' => $mouvement['date_mouvement'],
                        'libelle' => "Mouvement stock - {$mouvement['produit_nom']}",
                        'reference_type' => 'MOUVEMENT_STOCK',
                        'reference_id' => $mouvement['id'],
                        'utilisateur_id' => $mouvement['utilisateur_id'] ?? 1,
                        'lignes' => $this->genererLignesEcritureStock($mouvement)
                    ];
                    
                    $ecritureId = $this->ecritureService->enregistrerEcriture($ecritureData);
                    
                    // Mettre à jour la référence
                    $sql = "UPDATE mouvements_stock SET ecriture_id = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$ecritureId, $mouvement['id']]);
                    
                    $resultats[] = [
                        'type' => 'MOUVEMENT_STOCK',
                        'id' => $mouvement['id'],
                        'reference' => $mouvement['produit_nom'],
                        'success' => true,
                        'ecriture_id' => $ecritureId,
                        'message' => 'Mouvement de stock intégré avec succès'
                    ];
                    $totalSucces++;
                    
                } catch (Exception $e) {
                    $resultats[] = [
                        'type' => 'MOUVEMENT_STOCK',
                        'id' => $mouvement['id'],
                        'reference' => $mouvement['produit_nom'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $totalErreurs++;
                }
                
                $totalTraitees++;
            }
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            // Logger l'opération globale
            $this->auditService->logEvent(
                'INTEGRATION_STOCK',
                'INTEGRATION_COMPTABLE',
                'Intégration des mouvements de stock en comptabilité',
                "Traitement de $totalTraitees mouvements: $totalSucces succès, $totalErreurs erreurs",
                [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'periode' => [
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => "Intégration des mouvements de stock terminée: $totalSucces/$totalTraitees succès",
                'resultats' => $resultats,
                'statistiques' => [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'taux_succes' => $totalTraitees > 0 ? round(($totalSucces / $totalTraitees) * 100, 2) : 0
                ]
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('IntegrationComptableService::integrerStock - ' . $e->getMessage());
            throw new Exception("Erreur lors de l'intégration des mouvements de stock: " . $e->getMessage());
        }
    }

    /**
     * Génère les lignes d'écriture pour un mouvement de stock
     */
    private function genererLignesEcritureStock(array $mouvement): array
    {
        $montant = round(((float) ($mouvement['quantite'] ?? 0)) * ((float) ($mouvement['prix_unitaire'] ?? 0)), 2);
        $compteStock = PlanComptableService::COMPTE_STOCK_MEDICAMENTS;
        $referenceType = $mouvement['reference_type'] ?? null;

        if ($montant <= 0) {
            throw new Exception("Montant de mouvement de stock invalide pour {$mouvement['produit_nom']}");
        }

        if ($mouvement['type_mouvement'] === 'ENTREE') {
            return [[
                'compte_code' => $compteStock,
                'libelle' => "Entree stock - {$mouvement['produit_nom']}",
                'debit' => $montant,
                'credit' => 0,
                'tiers_id' => null
            ], [
                'compte_code' => $referenceType === 'ACHAT'
                    ? PlanComptableService::COMPTE_FOURNISSEURS
                    : PlanComptableService::COMPTE_VARIATION_STOCK,
                'libelle' => "Contrepartie entree stock - {$mouvement['produit_nom']}",
                'debit' => 0,
                'credit' => $montant,
                'tiers_id' => $mouvement['fournisseur_id'] ?? null
            ]];
        }

        return [[
            'compte_code' => PlanComptableService::COMPTE_VARIATION_STOCK,
            'libelle' => "Variation stock sortie - {$mouvement['produit_nom']}",
            'debit' => $montant,
            'credit' => 0,
            'tiers_id' => null
        ], [
            'compte_code' => $compteStock,
            'libelle' => "Sortie stock - {$mouvement['produit_nom']}",
            'debit' => 0,
            'credit' => $montant,
            'tiers_id' => null
        ]];
    }

    /**
     * Exécute toutes les intégrations comptables
     */
    public function executerToutesIntegrations(string $dateDebut = null, string $dateFin = null): array
    {
        $resultats = [];
        
        try {
            // Intégration des ventes
            $resultats['ventes'] = $this->integrerVentes($dateDebut, $dateFin);
            
            // Intégration des commandes
            $resultats['commandes'] = $this->integrerCommandes($dateDebut, $dateFin);
            
            // Intégration des mouvements de caisse
            $resultats['mouvements_caisse'] = $this->integrerMouvementsCaisse($dateDebut, $dateFin);
            
            // Intégration des mouvements de stock
            $resultats['mouvements_stock'] = $this->integrerStock($dateDebut, $dateFin);
            
            // Calculer les statistiques globales
            $totalTraitees = 0;
            $totalSucces = 0;
            $totalErreurs = 0;
            
            foreach ($resultats as $type => $resultat) {
                if (isset($resultat['statistiques'])) {
                    $totalTraitees += $resultat['statistiques']['total_traitees'];
                    $totalSucces += $resultat['statistiques']['total_succes'];
                    $totalErreurs += $resultat['statistiques']['total_erreurs'];
                }
            }
            
            // Logger l'opération globale
            $this->auditService->logEvent(
                'INTEGRATION_COMPLETE',
                'INTEGRATION_COMPTABLE',
                'Intégration complète en comptabilité',
                "Traitement global terminé: $totalSucces/$totalTraitees succès",
                [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'taux_succes_global' => $totalTraitees > 0 ? round(($totalSucces / $totalTraitees) * 100, 2) : 0,
                    'periode' => [
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => "Intégration complète terminée: $totalSucces/$totalTraitees succès",
                'resultats' => $resultats,
                'statistiques_globales' => [
                    'total_traitees' => $totalTraitees,
                    'total_succes' => $totalSucces,
                    'total_erreurs' => $totalErreurs,
                    'taux_succes_global' => $totalTraitees > 0 ? round(($totalSucces / $totalTraitees) * 100, 2) : 0
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'intégration complète: " . $e->getMessage());
        }
    }

    /**
     * Vérifie l'état de l'intégration
     */
    public function verifierEtatIntegration(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');

        $ventesSans = (int) $this->db->query(
            "SELECT COUNT(*) FROM ventes
             WHERE statut_vente != 'ANNULEE'
               AND (deleted_at IS NULL)
               AND ecriture_id IS NULL"
        )->fetchColumn();

        $commandesSans = 0;
        if ($this->tableHasColumn('commandes', 'ecriture_id')) {
            $commandesSans = (int) $this->db->query(
                "SELECT COUNT(*) FROM commandes
                 WHERE statut_commande = 'LIVREE' AND ecriture_id IS NULL"
            )->fetchColumn();
        }

        $caisseSans = 0;
        if ($this->tableHasColumn('mouvements_caisse', 'ecriture_id')) {
            $caisseSans = (int) $this->db->query(
                "SELECT COUNT(*) FROM mouvements_caisse WHERE ecriture_id IS NULL"
            )->fetchColumn();
        }

        $stockSans = 0;
        if ($this->tableHasColumn('mouvements_stock', 'ecriture_id')) {
            $stockSans = (int) $this->db->query(
                "SELECT COUNT(*) FROM mouvements_stock WHERE ecriture_id IS NULL"
            )->fetchColumn();
        }

        $etat = [
            'ventes_sans_ecritures' => $ventesSans,
            'commandes_sans_ecritures' => $commandesSans,
            'mouvements_caisse_sans_ecritures' => $caisseSans,
            'mouvements_stock_sans_ecritures' => $stockSans,
        ];

        $totalNonIntegres = array_sum($etat);

        return [
            'success' => true,
            'etat_integration' => $totalNonIntegres === 0 ? 'COMPLÈTE' : 'PARTIELLE',
            'details' => $etat,
            'total_non_integres' => $totalNonIntegres,
            'message' => $totalNonIntegres === 0
                ? 'Toutes les écritures comptables sont à jour'
                : "$totalNonIntegres éléments nécessitent une intégration",
        ];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Génère un rapport d'intégration
     */
    public function genererRapportIntegration(string $dateDebut, string $dateFin): array
    {
        $resultats = $this->executerToutesIntegrations($dateDebut, $dateFin);
        
        $rapport = [
            'periode' => $dateDebut . ' au ' . $dateFin,
            'date_generation' => date('Y-m-d H:i:s'),
            'resume' => $resultats['statistiques_globales'],
            'details' => $resultats['resultats']
        ];
        
        // Logger la génération du rapport
        $this->auditService->logEvent(
            'RAPPORT_INTEGRATION',
            'INTEGRATION_COMPTABLE',
            'Génération du rapport d\'intégration comptable',
            "Rapport généré pour la période: $dateDebut au $dateFin",
            $rapport
        );
        
        return [
            'success' => true,
            'rapport' => $rapport,
            'message' => 'Rapport d\'intégration généré avec succès'
        ];
    }

    /**
     * Force la synchronisation des écritures
     */
    public function forcerSynchronisation(): array
    {
        try {
            $this->executerToutesIntegrations();
            
            return [
                'success' => true,
                'message' => 'Synchronisation forcée des écritures comptables terminée'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage()
            ];
        }
    }
}
