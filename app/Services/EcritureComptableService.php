<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class EcritureComptableService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;
    private \App\Services\JournalComptableService $journalService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService, \App\Services\JournalComptableService $journalService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
        $this->journalService = $journalService;
    }

    public function prepareComptabilite(): void
    {
        $this->journalService->ensureComptabiliteReady();
    }

    /**
     * Génère automatiquement les écritures pour une vente
     */
    public function genererEcrituresVente(array $vente, string $referenceType = 'VENTE'): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Vérifier si les écritures existent déjà
            if ($this->ecrituresExistent($referenceType, $vente['id'])) {
                throw new Exception("Les écritures pour cette vente existent déjà");
            }
            
            $lignesEcriture = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            // VenteService persists these values before accounting.  Do not infer a
            // rate here: accounting must post the exact commercial document values.
            $montantTtc = round((float)($vente['montant_ttc'] ?? $vente['montant_net']), 2);
            $montantHt = round((float)($vente['montant_ht'] ?? 0), 2);
            $montantTva = round((float)($vente['montant_tva'] ?? 0), 2);
            if (abs(($montantHt + $montantTva) - $montantTtc) > 0.01) {
                throw new Exception('Montants HT, TVA et TTC incohérents pour la vente');
            }

            $ticket = $vente['numero_ticket'] ?? $vente['numero_facture'] ?? ('V' . $vente['id']);
            $isCredit = !empty($vente['is_credit']) && (int) $vente['is_credit'] === 1;

            // 1. Débit trésorerie / client
            if ($isCredit) {
                $compteClient = PlanComptableService::COMPTE_CLIENTS;
                $lignesEcriture[] = [
                    'compte_code' => $compteClient,
                    'libelle' => "Vente a credit - {$vente['client_nom']} - Ticket #{$ticket}",
                    'debit' => $montantTtc,
                    'credit' => 0,
                    'tiers_id' => $vente['client_id'] ?? null,
                ];
            } else {
                // Regle metier demandee: toute vente CASH au compte 571.
                $compteTresorerie = PlanComptableService::COMPTE_CAISSE;
                $lignesEcriture[] = [
                    'compte_code' => $compteTresorerie,
                    'libelle' => "Vente au comptant - Ticket #{$ticket}",
                    'debit' => $montantTtc,
                    'credit' => 0,
                    'tiers_id' => null,
                ];
            }
            $totalDebit += $montantTtc;

            // 2. Crédit ventes (701)
            $lignesEcriture[] = [
                'compte_code' => PlanComptableService::COMPTE_VENTES,
                'libelle' => "Ventes de marchandises - Ticket #{$ticket}",
                'debit' => 0,
                'credit' => $montantHt,
                'tiers_id' => null
            ];
            $totalCredit += $montantHt;
            
            // 3. TVA collectée (44571) - uniquement si TVA > 0
            if ($montantTva > 0.01) {
                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_TVA_COLLECTEE,
                    'libelle' => "TVA collecte - Ticket #{$ticket}",
                    'debit' => 0,
                    'credit' => $montantTva,
                    'tiers_id' => null
                ];
                $totalCredit += $montantTva;
            }
            
            // Vérifier l'équilibre
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new Exception("Déséquilibre des écritures: Débit=$totalDebit, Crédit=$totalCredit");
            }
            
            // Enregistrer l'écriture
            $ecritureData = [
                'journal_code' => 'VT', // Journal des ventes
                'numero_piece' => 'VT' . date('Ymd') . str_pad((string)$vente['id'], 6, '0', STR_PAD_LEFT),
                'date_ecriture' => $vente['date_vente'],
                'libelle' => "Vente pharmacie - Ticket #{$ticket}",
                'reference_type' => $referenceType,
                'reference_id' => $vente['id'],
                'utilisateur_id' => $vente['utilisateur_id'],
                'lignes' => $lignesEcriture
            ];
            
            $ecritureId = $this->enregistrerEcriture($ecritureData);
            
            // Mettre à jour la vente
            $this->mettreAJourReferenceEcriture('VENTE', $vente['id'], $ecritureId);
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'ecriture_id' => $ecritureId,
                'message' => 'Écritures de vente générées avec succès',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Erreur lors de la génération des écritures de vente: " . $e->getMessage());
        }
    }

    /**
     * Génère automatiquement les écritures pour une réception fournisseur
     */
    public function genererEcrituresReception(array $reception): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Vérifier si les écritures existent déjà
            if ($this->ecrituresExistent('RECEPTION', $reception['id'])) {
                throw new Exception("Les écritures pour cette réception existent déjà");
            }
            
            $lignesEcriture = [];
            $totalDebit = 0;
            $totalCredit = 0;
            
            // Calculer les montants à partir des items de réception
            $montantHT = 0;
            $montantTVA = 0;
            
            if (isset($reception['items']) && is_array($reception['items'])) {
                foreach ($reception['items'] as $item) {
                    $itemHT = (float) $item['quantite_recue'] * (float) $item['prix_achat'];
                    $montantHT += $itemHT;
                    
                    // TVA si applicable (utiliser tva de l'item ou taux par défaut)
                    $tauxTVA = (float) ($item['tva'] ?? $reception['tva_globale'] ?? 0);
                    if ($tauxTVA > 0) {
                        $montantTVA += $itemHT * ($tauxTVA / 100);
                    }
                }
            } else {
                // Fallback: utiliser montant_facture de la réception
                $montantHT = (float) ($reception['montant_facture'] ?? 0);
                $tauxTVA = (float) ($reception['tva_globale'] ?? 0);
                if ($tauxTVA > 0) {
                    $montantTVA = $montantHT * ($tauxTVA / 100);
                }
            }
            
            $montantHT = round($montantHT, 2);
            $montantTVA = round($montantTVA, 2);
            $montantTTC = round($montantHT + $montantTVA, 2);
            
            // 1. Débit compte de stock
            $lignesEcriture[] = [
                'compte_code' => PlanComptableService::COMPTE_STOCK_MEDICAMENTS,
                'libelle' => "Réception médicaments HT - Bon #{$reception['numero_reception']}",
                'debit' => $montantHT,
                'credit' => 0,
                'tiers_id' => null
            ];
            $totalDebit += $montantHT;
            
            // 2. TVA déductible si applicable
            if ($montantTVA > 0) {
                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_TVA_DEDUCTIBLE,
                    'libelle' => "TVA déductible - Bon #{$reception['numero_reception']}",
                    'debit' => $montantTVA,
                    'credit' => 0,
                    'tiers_id' => null
                ];
                $totalDebit += $montantTVA;
            }
            
            // 3. Crédit fournisseur (compte 401)
            $lignesEcriture[] = [
                'compte_code' => PlanComptableService::COMPTE_FOURNISSEURS,
                'libelle' => "Réception chez {$reception['fournisseur_nom']} - Bon #{$reception['numero_reception']}",
                'debit' => 0,
                'credit' => $montantTTC,
                'tiers_id' => $reception['fournisseur_id'] ?? null
            ];
            $totalCredit += $montantTTC;
            
            // Vérifier l'équilibre
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new Exception("Déséquilibre des écritures: Débit=$totalDebit, Crédit=$totalCredit");
            }
            
            // Enregistrer l'écriture
            $ecritureData = [
                'journal_code' => 'AC', // Journal des achats
                'numero_piece' => 'AC' . date('Ymd') . str_pad($reception['id'], 6, '0', STR_PAD_LEFT),
                'date_ecriture' => $reception['date_reception'],
                'libelle' => "Réception médicaments - Bon #{$reception['numero_reception']}",
                'reference_type' => 'RECEPTION',
                'reference_id' => $reception['id'],
                'utilisateur_id' => $reception['utilisateur_id'],
                'lignes' => $lignesEcriture
            ];
            
            $ecritureId = $this->enregistrerEcriture($ecritureData);
            
            // Mettre à jour la réception
            $this->mettreAJourReferenceEcriture('RECEPTION', $reception['id'], $ecritureId);
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'ecriture_id' => $ecritureId,
                'message' => 'Écritures de réception générées avec succès',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'montant_ht' => $montantHT,
                'montant_tva' => $montantTVA,
                'montant_ttc' => $montantTTC
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Erreur lors de la génération des écritures de réception: " . $e->getMessage());
        }
    }

    /**
     * Génère automatiquement les écritures pour un achat/commande
     */
    public function genererEcrituresAchat(array $achat): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Vérifier si les écritures existent déjà
            if ($this->ecrituresExistent('ACHAT', $achat['id'])) {
                throw new Exception("Les écritures pour cet achat existent déjà");
            }
            
            $lignesEcriture = [];
            $totalDebit = 0;
            $totalCredit = 0;
            
            // 1. Débit compte de stock
            $montantHT = round((float) ($achat['montant_ht'] ?? $this->calculerHT((float) $achat['montant_total'])), 2);
            $lignesEcriture[] = [
                'compte_code' => PlanComptableService::COMPTE_STOCK_MEDICAMENTS,
                'libelle' => "Achat médicaments HT - Commande #{$achat['reference']}",
                'debit' => $montantHT,
                'credit' => 0,
                'tiers_id' => null
            ];
            $totalDebit += $montantHT;
            
            // 2. TVA déductible si applicable
            $montantTVA = round((float) ($achat['montant_tva'] ?? $this->calculerTVA((float) $achat['montant_total'], $montantHT)), 2);
            if ($montantTVA > 0) {
                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_TVA_DEDUCTIBLE,
                    'libelle' => "TVA déductible - Commande #{$achat['reference']}",
                    'debit' => $montantTVA,
                    'credit' => 0,
                    'tiers_id' => null
                ];
                $totalDebit += $montantTVA;
            }
            
            // 3. Crédit fournisseur ou banque
            $montantTtc = round((float) $achat['montant_total'], 2);
            $compteCredit = $this->journalService->getCompteCreditPourAchat($achat);
            $lignesEcriture[] = [
                'compte_code' => $compteCredit,
                'libelle' => $compteCredit === PlanComptableService::COMPTE_BANQUE
                    ? "Paiement banque - Commande #{$achat['reference']}"
                    : "Achat chez {$achat['fournisseur_nom']} - Commande #{$achat['reference']}",
                'debit' => 0,
                'credit' => $montantTtc,
                'tiers_id' => $compteCredit === PlanComptableService::COMPTE_FOURNISSEURS
                    ? ($achat['fournisseur_id'] ?? null)
                    : null,
            ];
            $totalCredit += $montantTtc;
            
            // Vérifier l'équilibre
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new Exception("Déséquilibre des écritures: Débit=$totalDebit, Crédit=$totalCredit");
            }
            
            // Enregistrer l'écriture
            $ecritureData = [
                'journal_code' => 'AC', // Journal des achats
                'numero_piece' => 'AC' . date('Ymd') . str_pad($achat['id'], 6, '0', STR_PAD_LEFT),
                'date_ecriture' => $achat['date_commande'],
                'libelle' => "Achat médicaments - Commande #{$achat['reference']}",
                'reference_type' => 'ACHAT',
                'reference_id' => $achat['id'],
                'utilisateur_id' => $achat['utilisateur_id'],
                'lignes' => $lignesEcriture
            ];
            
            $ecritureId = $this->enregistrerEcriture($ecritureData);
            
            // Mettre à jour la commande
            $this->mettreAJourReferenceEcriture('ACHAT', $achat['id'], $ecritureId);
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'ecriture_id' => $ecritureId,
                'message' => 'Écritures d\'achat générées avec succès',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Erreur lors de la génération des écritures d'achat: " . $e->getMessage());
        }
    }

    /**
     * Génère automatiquement les écritures pour un mouvement de caisse
     */
    public function genererEcrituresCaisse(array $mouvement): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Vérifier si les écritures existent déjà
            if ($this->ecrituresExistent('MOUVEMENT_CAISSE', $mouvement['id'])) {
                throw new Exception("Les écritures pour ce mouvement existent déjà");
            }
            
            $lignesEcriture = [];
            $totalDebit = 0;
            $totalCredit = 0;
            
            if ($mouvement['type_mouvement'] === 'OUVERTURE_CAISSE') {
                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_CAISSE,
                    'libelle' => $mouvement['motif'],
                    'debit' => $mouvement['montant'],
                    'credit' => 0,
                    'tiers_id' => null
                ];
                $totalDebit += $mouvement['montant'];

                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_VIREMENTS_INTERNES,
                    'libelle' => $mouvement['motif'],
                    'debit' => 0,
                    'credit' => $mouvement['montant'],
                    'tiers_id' => null
                ];
                $totalCredit += $mouvement['montant'];
            } elseif ($mouvement['type_mouvement'] === 'ENTREE') {
                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_CAISSE,
                    'libelle' => $mouvement['motif'],
                    'debit' => $mouvement['montant'],
                    'credit' => 0,
                    'tiers_id' => $mouvement['client_id'] ?? null
                ];
                $totalDebit += $mouvement['montant'];
                
                // Contrepartie selon le type
                if ($mouvement['reference_type'] === 'VENTE') {
                    $lignesEcriture[] = [
                        'compte_code' => PlanComptableService::COMPTE_CLIENTS,
                        'libelle' => "Règlement vente - {$mouvement['motif']}",
                        'debit' => 0,
                        'credit' => $mouvement['montant'],
                        'tiers_id' => $mouvement['client_id']
                    ];
                    $totalCredit += $mouvement['montant'];
                } else {
                    $lignesEcriture[] = [
                        'compte_code' => PlanComptableService::COMPTE_AUTRES_PRODUITS,
                        'libelle' => $mouvement['motif'],
                        'debit' => 0,
                        'credit' => $mouvement['montant'],
                        'tiers_id' => null
                    ];
                    $totalCredit += $mouvement['montant'];
                }
            } else {
                $compteDepense = $this->journalService->getCompteDepense($mouvement['type_depense'] ?? 'AUTRE');
                $lignesEcriture[] = [
                    'compte_code' => $compteDepense,
                    'libelle' => $mouvement['motif'],
                    'debit' => $mouvement['montant'],
                    'credit' => 0,
                    'tiers_id' => $mouvement['fournisseur_id'] ?? null
                ];
                $totalDebit += $mouvement['montant'];
                
                $lignesEcriture[] = [
                    'compte_code' => PlanComptableService::COMPTE_CAISSE,
                    'libelle' => $mouvement['motif'],
                    'debit' => 0,
                    'credit' => $mouvement['montant'],
                    'tiers_id' => null
                ];
                $totalCredit += $mouvement['montant'];
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new Exception("Desequilibre des ecritures caisse: Debit={$totalDebit}, Credit={$totalCredit}");
            }

            // Enregistrer l'écriture
            $ecritureData = [
                'journal_code' => 'CA', // Journal de caisse
                'numero_piece' => 'CA' . date('Ymd') . str_pad($mouvement['id'], 6, '0', STR_PAD_LEFT),
                'date_ecriture' => $mouvement['date_mouvement'],
                'libelle' => $mouvement['motif'],
                'reference_type' => 'MOUVEMENT_CAISSE',
                'reference_id' => $mouvement['id'],
                'utilisateur_id' => $mouvement['utilisateur_id'],
                'lignes' => $lignesEcriture
            ];
            
            $ecritureId = $this->enregistrerEcriture($ecritureData);
            
            // Mettre à jour le mouvement
            $this->mettreAJourReferenceEcriture('MOUVEMENT_CAISSE', $mouvement['id'], $ecritureId);
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'ecriture_id' => $ecritureId,
                'message' => 'Écritures de caisse générées avec succès',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Erreur lors de la génération des écritures de caisse: " . $e->getMessage());
        }
    }

    /**
     * Génère automatiquement les écritures pour une annulation
     */
    public function genererEcrituresAnnulation(array $operation): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Récupérer l'écriture originale
            $ecritureOriginale = $this->getEcritureByReference($operation['reference_type'], $operation['reference_id']);
            if (!$ecritureOriginale) {
                throw new Exception("Écriture originale non trouvée");
            }
            
            // Créer les lignes inversées
            $lignesEcriture = [];
            foreach ($ecritureOriginale['lignes'] as $ligne) {
                $lignesEcriture[] = [
                    'compte_code' => $ligne['compte_code'],
                    'libelle' => "Annulation - " . $ligne['libelle'],
                    'debit' => $ligne['credit'], // Inversion
                    'credit' => $ligne['debit'], // Inversion
                    'tiers_id' => $ligne['tiers_id']
                ];
            }
            
            // Enregistrer l'écriture d'annulation
            $ecritureData = [
                'journal_code' => 'OD', // Journal des opérations diverses
                'numero_piece' => 'AN' . date('Ymd') . str_pad($operation['id'], 6, '0', STR_PAD_LEFT),
                'date_ecriture' => $operation['date_annulation'],
                'libelle' => "Annulation - {$operation['motif']}",
                'reference_type' => 'ANNULATION',
                'reference_id' => $operation['id'],
                'utilisateur_id' => $operation['utilisateur_id'],
                'lignes' => $lignesEcriture
            ];
            
            $ecritureId = $this->enregistrerEcriture($ecritureData);
            
            // Mettre à jour l'opération
            $this->mettreAJourReferenceEcriture('ANNULATION', $operation['id'], $ecritureId);
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'ecriture_id' => $ecritureId,
                'message' => 'Écritures d\'annulation générées avec succès'
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Erreur lors de la génération des écritures d'annulation: " . $e->getMessage());
        }
    }

    public function enregistrerEcriture(array $data): int
    {
        if (!$this->db->inTransaction()) {
            $this->prepareComptabilite();
        }

        return $this->journalService->enregistrerEcriture($data);
    }

    public function genererEcritureVenteParId(int $venteId): array
    {
        $vente = $this->getVentePourEcriture($venteId);
        return $this->genererEcrituresVente($vente);
    }

    public function genererEcritureAchatParId(int $commandeId): array
    {
        $commande = $this->getCommandePourEcriture($commandeId);
        return $this->genererEcrituresAchat($commande);
    }

    public function annulerEcritureVente(int $venteId, int $utilisateurId): void
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
        if ($this->ecrituresExistent('ANNULATION_VENTE', $venteId)) {
            throw new Exception('Cette vente possède déjà une contre-passation');
        }

        $sql = "SELECT ec.id, j.code AS journal_code, ec.numero_piece, ec.date_ecriture, ec.libelle
                FROM ecritures_comptables ec
                JOIN journaux_comptables j ON j.id = ec.journal_id
                WHERE ec.reference_type = 'VENTE' AND ec.reference_id = ?
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $originale = $stmt->fetch(PDO::FETCH_ASSOC);

        // Les ventes historiques sans écriture restent annulables : il n'y a
        // simplement aucune contre-passation à générer pour elles.
        if (!$originale) {
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return;
        }

        $alreadyReversed = $this->db->prepare('SELECT COUNT(*) FROM ecritures_comptables WHERE ecriture_origine_id = ?');
        $alreadyReversed->execute([(int)$originale['id']]);
        if ((int)$alreadyReversed->fetchColumn() > 0) {
            throw new Exception('L’écriture originale a déjà été contre-passée');
        }

        $stmt = $this->db->prepare(
            'SELECT compte_code, libelle, debit, credit, tiers_id
             FROM lignes_ecritures WHERE ecriture_id = ? ORDER BY id'
        );
        $stmt->execute([(int)$originale['id']]);
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($lignes) < 2) {
            throw new Exception('Ecriture de vente incomplete : contre-passation impossible');
        }

        $lignesInversees = array_map(static function (array $ligne): array {
            return [
                'compte_code' => (string)$ligne['compte_code'],
                'libelle' => 'Contre-passation - ' . (string)$ligne['libelle'],
                'debit' => (float)$ligne['credit'],
                'credit' => (float)$ligne['debit'],
                'tiers_id' => $ligne['tiers_id'] !== null ? (int)$ligne['tiers_id'] : null,
            ];
        }, $lignes);

        $ecritureId = $this->enregistrerEcriture([
            'journal_code' => (string)$originale['journal_code'],
            'numero_piece' => 'ANV' . date('Ymd') . '-' . $venteId . '-' . $originale['id'],
            'date_ecriture' => date('Y-m-d H:i:s'),
            'libelle' => 'Contre-passation vente #' . $venteId . ' — écriture initiale #' . $originale['id'],
            'reference_type' => 'ANNULATION_VENTE',
            'reference_id' => $venteId,
            'utilisateur_id' => $utilisateurId,
            'lignes' => $lignesInversees,
        ]);

        $this->db->prepare('UPDATE ecritures_comptables SET ecriture_origine_id = ? WHERE id = ?')
            ->execute([(int)$originale['id'], $ecritureId]);
        $this->auditService->logAction($utilisateurId, 'CONTRE_PASSATION_VENTE', 'ecritures_comptables', $ecritureId, null, ['ecriture_origine_id' => (int)$originale['id'], 'vente_id' => $venteId]);
        if ($ownsTransaction) {
            $this->db->commit();
        }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function ajusterEcritureVente(int $venteId, int $utilisateurId): array
    {
        $this->annulerEcritureVente($venteId, $utilisateurId);
        return $this->genererEcrituresVente($this->getVentePourEcriture($venteId), 'AJUSTEMENT_VENTE');
    }

    private function getVentePourEcriture(int $venteId): array
    {
        $sql = "SELECT v.*, c.nom AS client_nom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                WHERE v.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vente) {
            throw new Exception('Vente non trouvee');
        }
        if ($vente['statut_vente'] === 'ANNULEE') {
            throw new Exception('Impossible de generer une ecriture pour une vente annulee');
        }

        $vente['numero_ticket'] = $vente['numero_ticket'] ?? $vente['numero_facture'] ?? ('V' . $venteId);

        return $vente;
    }

    private function getCommandePourEcriture(int $commandeId): array
    {
        $sql = "SELECT c.*, f.nom AS fournisseur_nom
                FROM commandes c
                LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$commande) {
            throw new Exception('Commande non trouvee');
        }
        if ($commande['statut_commande'] === 'ANNULEE') {
            throw new Exception('Impossible de generer une ecriture pour une commande annulee');
        }

        $commande['reference'] = $commande['numero_commande'] ?? ('C' . $commandeId);

        return $commande;
    }

    private function getEcritureHeaderByReference(string $referenceType, int $referenceId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ecritures_comptables
             WHERE reference_type = ? AND reference_id = ?
             LIMIT 1"
        );
        $stmt->execute([$referenceType, $referenceId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function supprimerEcriture(int $ecritureId): void
    {
        $this->db->prepare('DELETE FROM lignes_ecritures WHERE ecriture_id = ?')->execute([$ecritureId]);
        $this->db->prepare('DELETE FROM ecritures_comptables WHERE id = ?')->execute([$ecritureId]);
    }

    /**
     * Vérifie si des écritures existent pour une référence
     */
    private function ecrituresExistent(string $referenceType, int $referenceId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM ecritures_comptables
             WHERE reference_type = ? AND reference_id = ?"
        );
        $stmt->execute([$referenceType, $referenceId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Met à jour la référence d'écriture
     */
    private function mettreAJourReferenceEcriture(string $referenceType, int $referenceId, int $ecritureId): void
    {
        $table = $this->getTableByReferenceType($referenceType);
        
        if ($table && $this->columnExistsOnTable($table, 'ecriture_id')) {
            $sql = "UPDATE $table SET ecriture_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$ecritureId, $referenceId]);
        }
    }

    private function columnExistsOnTable(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère la table correspondant au type de référence
     */
    private function getTableByReferenceType(string $referenceType): ?string
    {
        $tables = [
            'VENTE' => 'ventes',
            'ACHAT' => 'commandes',
            'RECEPTION' => 'receptions',
            'MOUVEMENT_CAISSE' => 'mouvements_caisse',
            'MOUVEMENT_STOCK' => 'mouvements_stock',
            'ANNULATION' => 'annulations'
        ];
        
        return $tables[$referenceType] ?? null;
    }

    /**
     * Récupère une écriture par sa référence
     */
    private function getEcritureByReference(string $referenceType, int $referenceId): ?array
    {
        $sql = "SELECT ec.*,
                       le.compte_code,
                       le.debit, le.credit, le.libelle as ligne_libelle, le.tiers_id
                FROM ecritures_comptables ec
                JOIN lignes_ecritures le ON ec.id = le.ecriture_id
                WHERE ec.reference_type = ? AND ec.reference_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$referenceType, $referenceId]);
        
        $ecritures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($ecritures)) {
            return null;
        }
        
        // Grouper par écriture
        $resultat = [
            'id' => $ecritures[0]['id'],
            'journal_code' => $ecritures[0]['journal_code'],
            'numero_piece' => $ecritures[0]['numero_piece'],
            'date_ecriture' => $ecritures[0]['date_ecriture'],
            'libelle' => $ecritures[0]['libelle'],
            'lignes' => []
        ];
        
        foreach ($ecritures as $ecriture) {
            $resultat['lignes'][] = [
                'compte_code' => $ecriture['compte_code'],
                'libelle' => $ecriture['ligne_libelle'],
                'debit' => $ecriture['debit'],
                'credit' => $ecriture['credit'],
                'tiers_id' => $ecriture['tiers_id']
            ];
        }
        
        return $resultat;
    }

    private function calculerHT(float $montantTTC, float $tauxTVA = 0.18): float
    {
        return round($montantTTC / (1 + $tauxTVA), 2);
    }

    /**
     * Calcule le montant de TVA
     */
    private function calculerTVA(float $montantTTC, float $montantHT): float
    {
        return round($montantTTC - $montantHT, 2);
    }

    /**
     * Génère les écritures pour une période donnée
     */
    public function genererEcrituresPeriode(string $dateDebut, string $dateFin): array
    {
        $resultats = [];
        
        // Générer les écritures de ventes
        $ventes = $this->getVentesSansEcritures($dateDebut, $dateFin);
        foreach ($ventes as $vente) {
            try {
                $resultats[] = $this->genererEcrituresVente($vente);
            } catch (Exception $e) {
                $resultats[] = [
                    'success' => false,
                    'type' => 'VENTE',
                    'id' => $vente['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Générer les écritures d'achats
        $achats = $this->getAchatsSansEcritures($dateDebut, $dateFin);
        foreach ($achats as $achat) {
            try {
                $resultats[] = $this->genererEcrituresAchat($achat);
            } catch (Exception $e) {
                $resultats[] = [
                    'success' => false,
                    'type' => 'ACHAT',
                    'id' => $achat['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Générer les écritures de caisse
        $mouvements = $this->getMouvementsCaisseSansEcritures($dateDebut, $dateFin);
        foreach ($mouvements as $mouvement) {
            try {
                $resultats[] = $this->genererEcrituresCaisse($mouvement);
            } catch (Exception $e) {
                $resultats[] = [
                    'success' => false,
                    'type' => 'MOUVEMENT_CAISSE',
                    'id' => $mouvement['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $resultats;
    }

    /**
     * Récupère les ventes sans écritures
     */
    private function getVentesSansEcritures(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT v.*, c.nom as client_nom 
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                WHERE v.date_vente BETWEEN ? AND ?
                AND v.ecriture_id IS NULL
                AND v.statut_vente != 'ANNULEE'
                ORDER BY v.date_vente";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les achats sans écritures
     */
    private function getAchatsSansEcritures(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT c.*, f.nom as fournisseur_nom 
                FROM commandes c
                LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
                WHERE c.date_commande BETWEEN ? AND ?
                AND c.ecriture_id IS NULL
                AND c.statut_commande = 'LIVREE'
                ORDER BY c.date_commande";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les mouvements de caisse sans écritures
     */
    private function getMouvementsCaisseSansEcritures(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT mc.*, c.nom as client_nom, f.nom as fournisseur_nom
                FROM mouvements_caisse mc
                LEFT JOIN clients c ON mc.client_id = c.id
                LEFT JOIN fournisseurs f ON mc.fournisseur_id = f.id
                WHERE mc.date_mouvement BETWEEN ? AND ?
                AND mc.ecriture_id IS NULL
                ORDER BY mc.date_mouvement";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
