<?php

namespace App\Services;

use App\Services\StockService;
use App\Services\CaisseService;
use App\Services\ComptabiliteService;
use App\Services\AuditService;
use App\Services\DiscountLimitService;
use App\Models\PaiementDetails;
use PDO;
use PDOException;
use Exception;

class VenteService
{
    private const COEFFICIENT_MARGE_VENTE = 1.48;

    private PDO $db;
    private StockService $stockService;
    private CaisseService $caisseService;
    private ComptabiliteService $comptabiliteService;
    private AuditService $auditService;
    private DiscountLimitService $discountLimitService;
    private PaiementDetails $paiementDetails;

    public function __construct(
        PDO $db,
        StockService $stockService,
        CaisseService $caisseService,
        ComptabiliteService $comptabiliteService,
        AuditService $auditService
    ) {
        $this->db = $db;
        $this->stockService = $stockService;
        $this->caisseService = $caisseService;
        $this->comptabiliteService = $comptabiliteService;
        $this->auditService = $auditService;
        $this->discountLimitService = new DiscountLimitService($db);
        $this->paiementDetails = new PaiementDetails($db);
    }

    /**
     * Crée une nouvelle vente avec transaction ACID complète
     */
    public function creerVente(array $data): array
    {
        // Vérifier si une transaction est déjà active
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            $isSuspended = !empty($data['suspendre']);
            $existingSale = null;
            if (!$isSuspended) {
                $this->comptabiliteService->prepareEcritures();
            }

            // 1. Create a new draft or update the exact suspended sale being resumed.
            $this->validatePrescriptionRequirements($data);
            if (!empty($data['vente_id'])) {
                $existingSale = $this->getVenteSuspendueForUpdate((int)$data['vente_id'], (int)$data['utilisateur_id']);
                if (!$existingSale) {
                    throw new Exception('Ticket suspendu introuvable ou non autorisé');
                }
                // Historical EN_COURS tickets may already carry irreversible entries created
                // by the former workflow.  They are outside the draft lifecycle and must
                // never have their header or lines rewritten by a resume request.
                if (!empty($existingSale['ecriture_id'])) {
                    throw new Exception('Ce ticket historique déjà comptabilisé ne peut pas être repris. Il a été conservé intact.');
                }
                $vente = $this->mettreAJourEnteteVenteSuspendue($existingSale, $data, $isSuspended);
                $this->db->prepare('DELETE FROM ventes_items WHERE vente_id = ?')->execute([$vente['id']]);
            } else {
                $vente = $this->creerEnteteVente($data);
            }
            
            // 2. Ajouter les articles et mettre à jour le stock
            $this->ajouterArticlesVente($vente['id'], $data['articles'], $data['utilisateur'] ?? $_SESSION['user'] ?? [], !$isSuspended);
            
            // 3. Calculer les montants
            $this->calculerMontantsVente($vente['id']);
            if (!$isSuspended && !empty($data['is_credit'])) {
                $this->enregistrerVenteCredit($vente['id'], $data);
            }
            if (!$isSuspended) {
                $this->enregistrerOrdonnanceVente($vente['id'], $data);
            }
            
            // 4. Enregistrer les détails de paiement si fournis
            if (!$isSuspended && isset($data['paiement_details']) && !empty($data['paiement_details'])) {
                $this->enregistrerPaiementDetails($vente['id'], $data['paiement_details'], $data['utilisateur_id']);
            }
            
            // 5. Enregistrer le mouvement de caisse si paiement immédiat
            if (!$isSuspended && isset($data['montant_paye']) && $data['montant_paye'] > 0) {
                $this->encaisserVente($vente['id'], $data);
            }
            
            // 6. Générer l'écriture comptable
            if (!$isSuspended) {
                $this->comptabiliteService->genererEcritureVente($vente['id']);
            }
            
            // 6. Logger l'action
            $this->auditService->logAction(
                $data['utilisateur_id'],
                'CREATE_VENTE',
                'ventes',
                $vente['id'],
                null,
                $vente
            );
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'vente_id' => (int)$vente['id'],
                'numero_facture' => $vente['numero_facture'],
                'numero_ticket' => $vente['numero_ticket'],
                'message' => $isSuspended ? 'Ticket suspendu sans impact stock, caisse ou comptabilité' : 'Vente créée avec succès',
                'suspended' => $isSuspended,
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            $this->auditService->logAction(
                $data['utilisateur_id'],
                'ERROR_CREATE_VENTE',
                'ventes',
                null,
                null,
                ['error' => $e->getMessage()]
            );
            
            throw new Exception("Erreur lors de la création de la vente: " . $e->getMessage());
        }
    }

    /**
     * Crée l'entête de la vente
     */
    private function creerEnteteVente(array $data): array
    {
        $numeroFacture = $this->genererNumeroFacture();
        $numeroTicket = $this->genererNumeroTicket();
        
        $sql = "INSERT INTO ventes (
            numero_facture, 
            client_id, 
            utilisateur_id, 
            caisse_session_id,
            date_vente, 
            montant_total,
            montant_net,
            montant_paye,
            montant_restant,
            type_paiement, 
            is_credit,
            statut_vente,
            notes
        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $montantTotal = (float)($data['montant_total'] ?? 0);
        $isCredit = !empty($data['is_credit']);
        $montantPaye = (!empty($data['suspendre']) || $isCredit) ? 0.0 : (float)($data['montant_paye'] ?? 0);
        if (empty($data['suspendre']) && !$isCredit && $montantPaye <= 0) {
            $montantPaye = $montantTotal;
        }
        $montantRestant = $montantTotal - $montantPaye;
        
        // A suspended ticket is not final.  A credit sale is finalised but has
        // an outstanding balance, therefore it must not be marked as paid.
        $statutVente = !empty($data['suspendre'])
            ? 'EN_COURS'
            : ($isCredit ? 'PARTIELLEMENT_PAYEE' : 'PAYEE');
        $notes = !empty($data['suspendre']) ? 'Vente suspendue' : null;
        
        $stmt->execute([
            $numeroFacture,
            $data['client_id'] ?? null,
            $data['utilisateur_id'],
            $data['caisse_session_id'] ?? null,
            $montantTotal,
            $montantTotal,
            $montantPaye,
            max(0, $montantTotal - $montantPaye),
            $isCredit ? 'CREDIT' : ($data['type_paiement'] ?? 'ESPECE'),
            $isCredit ? 1 : 0,
            $statutVente,
            $notes
        ]);
        
        $stmt = $this->db->prepare(
            "SELECT id
             FROM ventes
             WHERE numero_facture = ?
             AND utilisateur_id = ?
             AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$numeroFacture, $data['utilisateur_id']]);
        $venteId = (int)$stmt->fetchColumn();

        if ($venteId <= 0) {
            throw new Exception("Impossible de récupérer l'ID de la vente créée");
        }

        return [
            'id' => $venteId,
            'numero_facture' => $numeroFacture,
            'numero_ticket' => $numeroTicket,
        ];
    }

    /**
     * Ajoute les articles à la vente et met à jour le stock
     */
    private function ajouterArticlesVente(int $venteId, array $articles, array $utilisateur, bool $deduireStock = true): void
    {
        foreach ($articles as $article) {
            $prixUnitaire = $this->getPrixVenteProduit((int)$article['produit_id']);
            // Vérifier la disponibilité du stock
            if ($deduireStock) {
                $stockDisponible = $this->stockService->verifierDisponibilite($article['produit_id'], $article['quantite']);
                if (!$stockDisponible['disponible']) {
                    throw new Exception("Stock insuffisant pour le produit ID: {$article['produit_id']}");
                }
            }
            
            // Valider la remise selon le rôle de l'utilisateur
            $remise = (float)($article['remise'] ?? 0);
            if ($remise > 0) {
                try {
                    $remise = $this->discountLimitService->validateDiscount($remise, $utilisateur);
                } catch (Exception $e) {
                    throw new Exception("Remise non autorisée: " . $e->getMessage());
                }
            }
            
            // Note: Le système fonctionne maintenant sans lots
            // lot_id est null car la gestion des lots a été supprimée de l'interface
            
            // Insérer l'article de vente
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
                null, // lot_id null car le système fonctionne sans lots
                $article['quantite'],
                $prixUnitaire,
                $article['quantite'] * $prixUnitaire,
                $remise
            ]);
            
            // Mettre à jour le stock immédiatement
            if ($deduireStock) {
                $this->stockService->deduireStock($article['produit_id'], $article['quantite'], null, 'VENTE', $venteId);
            }
        }
    }

    private function validatePrescriptionRequirements(array $data): void
    {
        $productIds = [];
        foreach (($data['articles'] ?? []) as $article) {
            $produitId = (int)($article['produit_id'] ?? 0);
            if ($produitId > 0) {
                $productIds[] = $produitId;
            }
        }

        $requiredIds = $this->getPrescriptionRequiredProductIds($productIds);
        if (empty($requiredIds)) {
            return;
        }

        if ((int)($data['ordonnance_id'] ?? 0) > 0 && $this->ordonnanceExiste((int)$data['ordonnance_id'])) {
            return;
        }

        $ordonnance = $data['ordonnance'] ?? [];
        $requiredFields = [
            'numero_ordonnance' => 'Numéro ordonnance',
            'date_ordonnance' => 'Date ordonnance',
            'nom_medecin' => 'Nom du médecin',
            'structure_sanitaire' => 'Structure sanitaire',
            'nom_patient' => 'Nom du patient',
            'telephone_patient' => 'Téléphone du patient',
        ];

        $missing = [];
        foreach ($requiredFields as $field => $label) {
            if (trim((string)($ordonnance[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            throw new Exception(
                'Ordonnance obligatoire pour ce produit (ordonnancier, psychotrope ou anticancéreux). Champs manquants : '
                . implode(', ', $missing)
            );
        }
    }

    private function getPrescriptionRequiredProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (empty($productIds)) {
            return [];
        }

        $requiredTypes = PharmacyProductService::PRESCRIPTION_REQUIRED_TYPES;
        $typePlaceholders = implode(',', array_fill(0, count($requiredTypes), '?'));
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $sql = "SELECT id FROM produits
                WHERE id IN ($placeholders)
                AND is_actif = 1
                AND deleted_at IS NULL
                AND (
                    type_delivrance IN ($typePlaceholders)
                    OR requires_prescription = 1
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($productIds, $requiredTypes));

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function enregistrerOrdonnanceVente(int $venteId, array $data): void
    {
        $productIds = [];
        foreach (($data['articles'] ?? []) as $article) {
            $produitId = (int)($article['produit_id'] ?? 0);
            if ($produitId > 0) {
                $productIds[] = $produitId;
            }
        }

        $requiredProductIds = $this->getPrescriptionRequiredProductIds($productIds);
        $ordonnanceId = (int)($data['ordonnance_id'] ?? 0);
        if ($ordonnanceId > 0 && $this->ordonnanceExiste($ordonnanceId)) {
            $this->lierOrdonnanceExistante($venteId, $ordonnanceId, $productIds, (int)($data['utilisateur_id'] ?? 0));
            return;
        }
        if (empty($requiredProductIds)) {
            return;
        }

        $ordonnance = $data['ordonnance'] ?? [];
        $stmt = $this->db->prepare(
            "INSERT INTO ordonnances (
                numero_ordonnance, date_ordonnance, nom_medecin,
                structure_sanitaire, nom_patient, telephone_patient, observation,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            trim((string)$ordonnance['numero_ordonnance']),
            trim((string)$ordonnance['date_ordonnance']),
            trim((string)$ordonnance['nom_medecin']),
            trim((string)$ordonnance['structure_sanitaire']),
            trim((string)$ordonnance['nom_patient']),
            trim((string)$ordonnance['telephone_patient']),
            trim((string)($ordonnance['observation'] ?? '')) ?: null,
        ]);

        $ordonnanceId = (int)$this->db->lastInsertId();

        $stmtLink = $this->db->prepare(
            "INSERT INTO vente_ordonnances (vente_id, ordonnance_id) VALUES (?, ?)"
        );
        $stmtLink->execute([$venteId, $ordonnanceId]);

        $stmtItem = $this->db->prepare(
            "INSERT INTO vente_ordonnance_items (ordonnance_id, vente_id, produit_id, created_at)
             VALUES (?, ?, ?, NOW())"
        );

        foreach ($requiredProductIds as $produitId) {
            $stmtItem->execute([$ordonnanceId, $venteId, $produitId]);
        }

        $this->auditService->logAction(
            (int)($data['utilisateur_id'] ?? 0),
            'CREATE_VENTE_ORDONNANCE',
            'ordonnances',
            $ordonnanceId,
            null,
            [
                'vente_id' => $venteId,
                'produits' => $requiredProductIds,
                'numero_ordonnance' => $ordonnance['numero_ordonnance'] ?? null
            ]
        );
    }

    private function ordonnanceExiste(int $ordonnanceId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM ordonnances WHERE id = ?');
        $stmt->execute([$ordonnanceId]);
        return (bool)$stmt->fetchColumn();
    }

    private function lierOrdonnanceExistante(int $venteId, int $ordonnanceId, array $productIds, int $utilisateurId): void
    {
        $this->db->prepare('INSERT INTO vente_ordonnances (vente_id, ordonnance_id) VALUES (?, ?)')->execute([$venteId, $ordonnanceId]);
        $item = $this->db->prepare('INSERT INTO vente_ordonnance_items (ordonnance_id, vente_id, produit_id, created_at) VALUES (?, ?, ?, NOW())');
        foreach (array_unique(array_filter(array_map('intval', $productIds))) as $produitId) {
            $item->execute([$ordonnanceId, $venteId, $produitId]);
        }
        $this->auditService->logAction($utilisateurId, 'LINK_ORDONNANCE_VENTE', 'ordonnances', $ordonnanceId, null, ['vente_id' => $venteId, 'produits' => $productIds]);
    }

    /**
     * Calcule les montants totaux de la vente
     */
    private function calculerMontantsVente(int $venteId): void
    {
        $sql = "SELECT 
                    SUM(montant_total) as montant_total,
                    SUM(montant_total * remise / 100) as montant_remise
                FROM ventes_items 
                WHERE vente_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $montantTotal = round((float)($result['montant_total'] ?? 0), 2);
        $montantRemise = round((float)($result['montant_remise'] ?? 0), 2);
        $montantNet = round($montantTotal - $montantRemise, 2); // configured product prices are TTC
        $tauxTva = $this->getTauxTvaVente();
        $montantHt = round($montantNet / (1 + $tauxTva), 2);
        $montantTva = round($montantNet - $montantHt, 2);
        
        $sql = "UPDATE ventes SET 
                    montant_total = ?,
                    montant_ht = ?,
                    montant_tva = ?,
                    montant_ttc = ?,
                    montant_remise = ?,
                    montant_net = ?,
                    montant_restant = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$montantTotal, $montantHt, $montantTva, $montantNet, $montantRemise, $montantNet, $montantNet, $venteId]);
    }

    private function getPrixVenteProduit(int $produitId): float
    {
        $stmt = $this->db->prepare("SELECT prix_vente FROM produits WHERE id = ? AND is_actif = 1 AND deleted_at IS NULL");
        $stmt->execute([$produitId]);

        $prixVente = $stmt->fetchColumn();
        if ($prixVente === false || $prixVente === null) {
            throw new Exception("Produit ID: $produitId non trouve ou inactif");
        }

        return (float)$prixVente;
    }

    private function calculerPrixVenteDepuisAchat(float $prixAchat): float
    {
        return self::calculerPrixVenteStandard($prixAchat);
    }

    public static function calculerPrixVenteStandard(float $prixAchat): float
    {
        return round($prixAchat * self::COEFFICIENT_MARGE_VENTE, 2);
    }

    /**
     * Enregistre les détails de paiement
     */
    private function enregistrerPaiementDetails(int $venteId, array $paiementDetails, int $utilisateurId): void
    {
        $paiementDetails['vente_id'] = $venteId;
        $paiementDetails['utilisateur_id'] = $utilisateurId;
        
        // Créer une nouvelle instance avec la même connexion pour éviter les problèmes de base de données
        $paiementDetailsModel = new PaiementDetails($this->db);
        $paiementDetailsModel->create($paiementDetails);
        
        $this->auditService->logAction(
            $utilisateurId,
            'CREATE_PAIEMENT_DETAILS',
            'paiement_details',
            $venteId,
            null,
            $paiementDetails
        );
    }

    /**
     * Enregistre l'encaissement de la vente
     */
    private function encaisserVente(int $venteId, array $data): void
    {
        $caisseSessionId = (int)($data['caisse_session_id'] ?? 0);
        if ($caisseSessionId > 0) {
            $this->caisseService->enregistrerMouvement([
                'caisse_session_id' => $caisseSessionId,
                'type_mouvement' => 'VENTE',
                'montant' => $data['montant_paye'],
                'moyen_paiement' => $data['type_paiement'] ?? 'ESPECE',
                'reference' => 'VENTE_' . $venteId,
                'description' => 'Encaissement vente ' . $venteId,
                'utilisateur_id' => $data['utilisateur_id'],
                'vente_id' => $venteId
            ]);
        }
        
        // Mettre à jour le statut de la vente
        $sql = "UPDATE ventes SET 
                    montant_paye = ?,
                    montant_restant = montant_net - ?,
                    statut_vente = CASE 
                        WHEN montant_net - ? <= 0 THEN 'PAYEE'
                        WHEN ? < montant_net THEN 'PARTIELLEMENT_PAYEE'
                        ELSE 'EN_COURS'
                    END
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['montant_paye'],
            $data['montant_paye'],
            $data['montant_paye'],
            $data['montant_paye'],
            $venteId
        ]);
    }

    /**
     * Persists the customer receivable only after the sale totals are known.
     * Both the credit ceiling and the balance are locked in the same database
     * transaction as the sale, stock, accounting entry and customer balance.
     */
    private function enregistrerVenteCredit(int $venteId, array $data): void
    {
        $clientId = (int)($data['client_id'] ?? 0);
        if ($clientId <= 0) {
            throw new Exception('Un client est obligatoire pour une vente à crédit');
        }

        $venteStmt = $this->db->prepare('SELECT montant_net FROM ventes WHERE id = ? FOR UPDATE');
        $venteStmt->execute([$venteId]);
        $montant = round((float)$venteStmt->fetchColumn(), 2);
        if ($montant <= 0) {
            throw new Exception('Le montant de la vente à crédit doit être positif');
        }

        $clientStmt = $this->db->prepare(
            'SELECT plafond_credit, solde_credit FROM clients WHERE id = ? AND is_actif = 1 AND deleted_at IS NULL FOR UPDATE'
        );
        $clientStmt->execute([$clientId]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            throw new Exception('Client introuvable ou inactif pour la vente à crédit');
        }

        $plafond = round((float)$client['plafond_credit'], 2);
        $solde = round((float)$client['solde_credit'], 2);
        if ($plafond <= 0 || $solde + $montant > $plafond + 0.01) {
            throw new Exception('Plafond de crédit insuffisant pour ce client');
        }

        $credit = $this->db->prepare(
            "INSERT INTO ventes_credit (vente_id, client_id, montant, montant_paye, montant_restant, date_credit, statut, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, NOW(), 'en_cours', NOW(), NOW())"
        );
        $credit->execute([$venteId, $clientId, $montant, $montant]);
        $this->db->prepare('UPDATE clients SET solde_credit = solde_credit + ?, updated_at = NOW() WHERE id = ?')
            ->execute([$montant, $clientId]);
        $this->db->prepare(
            "UPDATE ventes SET montant_paye = 0, montant_restant = ?, statut_vente = 'PARTIELLEMENT_PAYEE', statut_paiement = 'impaye' WHERE id = ?"
        )->execute([$montant, $venteId]);
    }

    private function getTauxTvaVente(): float
    {
        $stmt = $this->db->query("SELECT taux FROM tva_taux WHERE code = 'TVA_18' AND is_actif = 1 LIMIT 1");
        $taux = $stmt->fetchColumn();
        return $taux === false ? 0.0 : ((float)$taux / 100);
    }

    private function getVenteSuspendueForUpdate(int $venteId, int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ventes WHERE id = ? AND utilisateur_id = ? AND statut_vente = 'EN_COURS' AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$venteId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function mettreAJourEnteteVenteSuspendue(array $vente, array $data, bool $suspendre): array
    {
        $stmt = $this->db->prepare("UPDATE ventes SET client_id = ?, caisse_session_id = ?, type_paiement = ?, is_credit = ?, statut_vente = ?, montant_paye = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $data['client_id'] ?? null,
            $data['caisse_session_id'] ?? null,
            $data['is_credit'] ? 'CREDIT' : ($data['type_paiement'] ?? 'ESPECE'),
            !empty($data['is_credit']) ? 1 : 0,
            $suspendre ? 'EN_COURS' : (!empty($data['is_credit']) ? 'PARTIELLEMENT_PAYEE' : 'PAYEE'),
            $suspendre ? 0 : (float)($data['montant_paye'] ?? 0),
            $suspendre ? 'Vente suspendue' : null,
            $vente['id'],
        ]);
        return ['id' => (int)$vente['id'], 'numero_facture' => $vente['numero_facture'], 'numero_ticket' => $vente['numero_facture']];
    }

    /**
     * Annule une vente et restaure le stock
     */
    public function annulerVente(int $venteId, int $utilisateurId): array
    {
        // Vérifier si une transaction est déjà active
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Récupérer les détails de la vente
            $vente = $this->getVenteDetails($venteId);
            
            if ($vente['statut_vente'] === 'ANNULEE') {
                throw new Exception("La vente est déjà annulée");
            }
            
            // Restaurer le stock pour chaque article
            $articles = $this->getVenteArticles($venteId);
            foreach ($articles as $article) {
                $this->stockService->restaurerStock(
                    $article['produit_id'],
                    $article['quantite'],
                    $article['lot_id'],
                    'ANNULATION_VENTE',
                    $venteId
                );
            }
            
            // Annuler le mouvement de caisse si déjà encaissé
            if ($vente['montant_paye'] > 0) {
                $this->caisseService->annulerMouvementVente($venteId, $utilisateurId);
            }
            
            // Annuler l'écriture comptable
            $this->comptabiliteService->annulerEcritureVente($venteId, $utilisateurId);
            
            // Marquer la vente comme annulée
            $sql = "UPDATE ventes SET 
                        statut_vente = 'ANNULEE',
                        deleted_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$venteId]);
            
            // Logger l'action
            $this->auditService->logAction(
                $utilisateurId,
                'ANNULER_VENTE',
                'ventes',
                $venteId,
                $vente,
                ['statut' => 'ANNULEE']
            );
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'message' => 'Vente annulée avec succès, stock restauré'
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw new Exception("Erreur lors de l'annulation de la vente: " . $e->getMessage());
        }
    }

    /**
     * Corrige un ticket de vente
     */
    public function corrigerTicket(int $venteId, array $corrections, int $utilisateurId): array
    {
        // Vérifier si une transaction est déjà active
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            $vente = $this->getVenteDetails($venteId);
            
            if ($vente['statut_vente'] === 'ANNULEE') {
                throw new Exception("Impossible de corriger une vente annulée");
            }
            
            // Logger la correction avant modification
            $this->auditService->logAction(
                $utilisateurId,
                'CORRECTION_TICKET',
                'ventes',
                $venteId,
                $vente,
                $corrections
            );
            
            // Appliquer les corrections
            foreach ($corrections as $correction) {
                switch ($correction['type']) {
                    case 'MODIFIER_QUANTITE':
                        $this->corrigerQuantiteArticle($venteId, $correction);
                        break;
                    case 'MODIFIER_PRIX':
                        $this->corrigerPrixArticle($venteId, $correction);
                        break;
                    case 'AJOUT_REMISE':
                        $this->ajouterRemiseArticle($venteId, $correction);
                        break;
                }
            }
            
            // Recalculer les montants
            $this->calculerMontantsVente($venteId);
            
            // Ajuster l'écriture comptable si nécessaire
            $this->comptabiliteService->ajusterEcritureVente($venteId, $utilisateurId);
            
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            
            return [
                'success' => true,
                'message' => 'Ticket corrigé avec succès'
            ];
            
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw new Exception("Erreur lors de la correction du ticket: " . $e->getMessage());
        }
    }

    /**
     * Recherche rapide de produits
     */
    public function rechercherProduits(string $query): array
    {
        $sql = "SELECT p.*,
                       p.prix_vente as prix_vente_catalogue,
                       p.prix_vente,
                       c.nom as categorie,
                       COALESCE(s.quantite_disponible, 0) as quantite_disponible,
                       COALESCE(s.quantite_theorique, 0) as quantite_theorique
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE (p.code_cip LIKE ? OR p.code_barre LIKE ? OR p.nom LIKE ?)
                AND p.is_actif = 1 AND p.deleted_at IS NULL
                ORDER BY p.nom
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une vente complète avec ses articles
     */
    public function getVenteComplete(int $venteId): ?array
    {
        $sql = "SELECT v.*,
                       c.nom as client_nom,
                       c.prenom as client_prenom,
                       c.telephone as client_telephone,
                       c.email as client_email,
                       u.username as vendeur_nom,
                       CONCAT(u.prenom, ' ', u.nom) as vendeur_nom_complet,
                       cs.numero_session
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                LEFT JOIN caisse_sessions cs ON v.caisse_session_id = cs.id
                WHERE v.id = ? AND v.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vente) {
            return null;
        }
        
        // Récupérer les articles de la vente
        $sqlArticles = "SELECT vi.*,
                            p.nom as produit_nom,
                            p.code_cip,
                            vi.prix_unitaire as prix,
                            vi.montant_total as total_ligne,
                            l.numero_lot,
                            l.date_peremption
                     FROM ventes_items vi
                     JOIN produits p ON vi.produit_id = p.id
                     LEFT JOIN lots l ON vi.lot_id = l.id
                     WHERE vi.vente_id = ?
                     ORDER BY vi.id";
        
        $stmtArticles = $this->db->prepare($sqlArticles);
        $stmtArticles->execute([$venteId]);
        $articles = $stmtArticles->fetchAll(PDO::FETCH_ASSOC);
        
        $vente['articles'] = $articles;
        $vente['ordonnances'] = $this->getOrdonnancesVente($venteId);
        $vente['paiement_details'] = $this->paiementDetails->findByVenteId($venteId);
        return $vente;
    }

    /**
     * Liste des tickets recents pour la page d'impression.
     */
    public function getTicketsPourImpression(int $userId = 0, bool $onlyUser = false, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $params = [];
        $userFilter = '';

        if ($onlyUser && $userId > 0) {
            $userFilter = ' AND v.utilisateur_id = ?';
            $params[] = $userId;
        }

        $sql = "SELECT v.id
                FROM ventes v
                WHERE v.deleted_at IS NULL
                {$userFilter}
                ORDER BY v.date_vente DESC, v.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $tickets = [];
        foreach ($ids as $venteId) {
            $vente = $this->getVenteComplete($venteId);
            if ($vente) {
                $tickets[] = $vente;
            }
        }

        return $tickets;
    }

    /**
     * Tickets encore annulables (ventes non annulees).
     */
    public function getTicketsAnnulables(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));

        $sql = "SELECT v.id,
                       v.numero_facture,
                       v.date_vente,
                       v.montant_net,
                       v.montant_total,
                       v.statut_vente,
                       v.type_paiement,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(c.prenom, ''), ' ', COALESCE(c.nom, ''))), ''), 'Client comptoir') AS client_label,
                       u.username AS vendeur_nom
                FROM ventes v
                LEFT JOIN clients c ON c.id = v.client_id
                LEFT JOIN utilisateurs u ON u.id = v.utilisateur_id
                WHERE v.deleted_at IS NULL
                AND v.statut_vente <> 'ANNULEE'
                ORDER BY v.date_vente DESC, v.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getOrdonnancesVente(int $venteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.id, o.numero_ordonnance, o.date_ordonnance, o.nom_medecin,
                    o.structure_sanitaire, o.nom_patient, o.telephone_patient,
                    o.observation, o.created_at, o.updated_at,
                    vo.vente_id
             FROM vente_ordonnances vo
             INNER JOIN ordonnances o ON o.id = vo.ordonnance_id
             WHERE vo.vente_id = ?
             ORDER BY o.created_at"
        );
        $stmt->execute([$venteId]);
        $ordonnances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($ordonnances)) {
            return [];
        }

        $stmtItems = $this->db->prepare(
            "SELECT voi.ordonnance_id, p.id as produit_id, p.nom as produit_nom, p.code_cip
             FROM vente_ordonnance_items voi
             INNER JOIN produits p ON p.id = voi.produit_id
             WHERE voi.vente_id = ?
             ORDER BY p.nom"
        );
        $stmtItems->execute([$venteId]);

        $itemsByOrdonnance = [];
        foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByOrdonnance[(int)$item['ordonnance_id']][] = $item;
        }

        foreach ($ordonnances as &$ordonnance) {
            $ordonnanceId = (int)$ordonnance['id'];
            $ordonnance['produits'] = $itemsByOrdonnance[$ordonnanceId] ?? $this->getPrescriptionProductsForVente($venteId, $ordonnanceId);
        }

        return $ordonnances;
    }

    private function getPrescriptionProductsForVente(int $venteId, int $ordonnanceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ? AS ordonnance_id, p.id AS produit_id, p.nom AS produit_nom, p.code_cip
             FROM ventes_items vi
             INNER JOIN produits p ON p.id = vi.produit_id
             WHERE vi.vente_id = ?
             AND p.requires_prescription = 1
             ORDER BY p.nom"
        );
        $stmt->execute([$ordonnanceId, $venteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère un numéro de facture unique
     */
    public function getVenteStats(): array
    {
        $sql = "SELECT
                    COUNT(*) as total_ventes,
                    COALESCE(SUM(montant_net), 0) as chiffre_affaires,
                    COALESCE(SUM(montant_paye), 0) as montant_encaisse,
                    COALESCE(SUM(montant_restant), 0) as montant_restant,
                    SUM(CASE WHEN is_credit = 1 THEN 1 ELSE 0 END) as ventes_credit
                FROM ventes
                WHERE DATE(date_vente) = CURDATE()
                AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_ventes' => 0,
            'chiffre_affaires' => 0,
            'montant_encaisse' => 0,
            'montant_restant' => 0,
            'ventes_credit' => 0
        ];

        // Calculer le panier moyen
        $result['panier_moyen'] = $result['total_ventes'] > 0 
            ? $result['chiffre_affaires'] / $result['total_ventes'] 
            : 0;

        // Calculer le nombre d'articles vendus aujourd'hui
        $sqlArticles = "SELECT COALESCE(SUM(vi.quantite), 0) as total_articles
                        FROM ventes_items vi
                        INNER JOIN ventes v ON vi.vente_id = v.id
                        WHERE DATE(v.date_vente) = CURDATE()
                        AND v.deleted_at IS NULL";

        $stmtArticles = $this->db->prepare($sqlArticles);
        $stmtArticles->execute();
        $result['articles_vendus'] = (int) $stmtArticles->fetchColumn();

        return $result;
    }

    public function getProduitsDisponibles(): array
    {
        $sql = "SELECT
                    p.id,
                    p.code_cip,
                    p.code_barre,
                    p.nom,
                    p.prix_achat,
                    p.prix_vente as prix_vente_catalogue,
                    p.prix_vente,
                    p.requires_prescription,
                    COALESCE(p.type_delivrance, 'MEDICAMENT_CONSEIL') as type_delivrance,
                    p.rayon,
                    COALESCE(s.quantite_disponible, 0) as quantite_disponible,
                    COALESCE(s.quantite_theorique, 0) as quantite_theorique,
                    s.date_peremption
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND p.deleted_at IS NULL
                AND (s.date_peremption IS NULL OR s.date_peremption >= CURDATE())
                ORDER BY p.nom";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientsDisponibles(): array
    {
        $sql = "SELECT
                    id,
                    code,
                    nom,
                    prenom,
                    telephone,
                    email,
                    type_client,
                    solde_credit
                FROM clients
                WHERE is_actif = 1
                AND deleted_at IS NULL
                ORDER BY nom, prenom";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function genererNumeroFacture(): string
    {
        $prefix = 'FAC' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM ventes WHERE DATE(date_vente) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $prefix . str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
    }

    private function genererNumeroTicket(): string
    {
        $prefix = 'VT' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM ventes WHERE DATE(date_vente) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prefix . str_pad((string) (((int) ($result['count'] ?? 0)) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère les détails d'une vente
     */
    private function getVenteDetails(int $venteId): array
    {
        $sql = "SELECT * FROM ventes WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            throw new Exception("Vente non trouvée");
        }
        
        return $result;
    }

    /**
     * Récupère les dernières ventes d'un utilisateur
     */
    public function getDernieresVentes(int $userId, int $limit = 10): array
    {
        $sql = "SELECT 
                    v.id,
                    v.numero_facture,
                    v.date_vente,
                    v.montant_net,
                    v.montant_paye,
                    v.montant_restant,
                    v.type_paiement,
                    v.statut_vente as statut,
                    c.nom as client_nom,
                    c.prenom as client_prenom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                WHERE v.utilisateur_id = ?
                AND v.deleted_at IS NULL
                ORDER BY v.date_vente DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le nombre de clients servis aujourd'hui
     */
    public function getClientsServisAujourdhui(): int
    {
        $sql = "SELECT COUNT(DISTINCT client_id) as count
                FROM ventes
                WHERE DATE(date_vente) = CURDATE()
                AND client_id IS NOT NULL
                AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Récupère les notifications pour le dashboard vendeur
     */
    public function getNotifications(): array
    {
        $notifications = [];
        
        // Produits en rupture de stock
        $sql = "SELECT COUNT(*) as count
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND COALESCE(s.quantite_disponible, 0) = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rupture = (int) $stmt->fetchColumn();
        
        if ($rupture > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'fa-exclamation-circle',
                'message' => "{$rupture} produits en rupture",
                'count' => $rupture
            ];
        }
        
        // Produits bientôt en rupture (stock < 5)
        $sql = "SELECT COUNT(*) as count
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND COALESCE(s.quantite_disponible, 0) < 5
                AND COALESCE(s.quantite_disponible, 0) > 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stockBas = (int) $stmt->fetchColumn();
        
        if ($stockBas > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'fa-exclamation-triangle',
                'message' => "{$stockBas} produits en stock critique",
                'count' => $stockBas
            ];
        }
        
        // Produits proches de la péremption (30 jours)
        $sql = "SELECT COUNT(*) as count
                FROM lots l
                INNER JOIN produits p ON l.produit_id = p.id
                WHERE l.quantite_restante > 0
                AND l.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $peremption = (int) $stmt->fetchColumn();
        
        if ($peremption > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'fa-clock',
                'message' => "{$peremption} lots proches péremption",
                'count' => $peremption
            ];
        }
        
        // Ventes en attente de paiement
        $sql = "SELECT COUNT(*) as count
                FROM ventes
                WHERE montant_restant > 0
                AND statut_vente != 'ANNULEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $ventesEnAttente = (int) $stmt->fetchColumn();
        
        if ($ventesEnAttente > 0) {
            $notifications[] = [
                'type' => 'info',
                'icon' => 'fa-clock',
                'message' => "{$ventesEnAttente} ventes en attente de paiement",
                'count' => $ventesEnAttente
            ];
        }
        
        // Tickets suspendus (statut = 'EN_COURS' depuis plus de 30 min)
        $sql = "SELECT COUNT(*) as count
                FROM ventes
                WHERE statut_vente = 'EN_COURS'
                AND date_vente < DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $ticketsSuspendus = (int) $stmt->fetchColumn();
        
        if ($ticketsSuspendus > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'fa-pause-circle',
                'message' => "{$ticketsSuspendus} tickets suspendus depuis plus de 30 min",
                'count' => $ticketsSuspendus
            ];
        }
        
        return $notifications;
    }

    /**
     * Récupère les articles d'une vente
     */
    private function getVenteArticles(int $venteId): array
    {
        $sql = "SELECT * FROM ventes_items WHERE vente_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les tickets en attente (ventes avec statut EN_COURS)
     */
    public function getTicketsEnAttente(?int $userId = null, ?string $dateDebut = null, ?string $dateFin = null, ?int $clientId = null): array
    {
        $sql = "SELECT 
                    v.id,
                    v.numero_facture,
                    v.date_vente,
                    v.montant_net,
                    v.montant_paye,
                    v.montant_restant,
                    v.type_paiement,
                    v.statut_vente,
                    v.updated_at as derniere_modification,
                    c.nom as client_nom,
                    c.prenom as client_prenom,
                    u.nom as utilisateur,
                    COUNT(vi.id) as nombre_articles
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                LEFT JOIN ventes_items vi ON v.id = vi.vente_id
                WHERE v.statut_vente = 'EN_COURS'
                AND v.deleted_at IS NULL";
        
        $params = [];
        
        if ($userId !== null) {
            $sql .= " AND v.utilisateur_id = ?";
            $params[] = $userId;
        }
        
        if ($clientId !== null) {
            $sql .= " AND v.client_id = ?";
            $params[] = $clientId;
        }
        
        if ($dateDebut !== null) {
            $sql .= " AND DATE(v.date_vente) >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin !== null) {
            $sql .= " AND DATE(v.date_vente) <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY v.id ORDER BY v.date_vente DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une vente complète avec ses articles pour reprise
     */
    public function getVentePourReprise(int $venteId): ?array
    {
        $sql = "SELECT 
                    v.*,
                    c.nom as client_nom,
                    c.prenom as client_prenom,
                    c.telephone as client_telephone
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                WHERE v.id = ?
                AND v.statut_vente = 'EN_COURS'
                AND v.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vente) {
            return null;
        }
        
        // Récupérer les articles
        $sqlItems = "SELECT 
                        vi.*,
                        p.nom as produit_nom,
                        p.code_cip,
                        p.prix_vente
                     FROM ventes_items vi
                     LEFT JOIN produits p ON vi.produit_id = p.id
                     WHERE vi.vente_id = ?";
        
        $stmtItems = $this->db->prepare($sqlItems);
        $stmtItems->execute([$venteId]);
        
        $vente['articles'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        return $vente;
    }

    /**
     * Annule un ticket en attente (met le statut à ANNULEE)
     */
    public function annulerTicketEnAttente(int $venteId, int $userId, string $motif): array
    {
        try {
            $this->db->beginTransaction();
            
            // Vérifier que la vente existe et est en cours
            $sql = "SELECT * FROM ventes WHERE id = ? AND statut_vente = 'EN_COURS' AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$venteId]);
            $vente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vente) {
                throw new Exception("Ticket non trouvé ou déjà traité");
            }
            
            // Mettre à jour le statut
            $sql = "UPDATE ventes SET statut_vente = 'ANNULEE', notes = CONCAT(IFNULL(notes, ''), ' | Annulé: ', ?) WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$motif, $venteId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Ticket annulé avec succès'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Récupère l'historique des ventes avec filtres
     */
    public function getHistoriqueVentes(?int $userId = null, ?string $periode = null, ?string $dateDebut = null, ?string $dateFin = null, ?string $recherche = null, ?int $filtreUserId = null): array
    {
        $sql = "SELECT 
                    v.id,
                    v.numero_facture,
                    v.date_vente,
                    v.montant_net,
                    v.montant_paye,
                    v.montant_restant,
                    v.type_paiement,
                    v.statut_vente,
                    c.nom as client_nom,
                    c.prenom as client_prenom,
                    u.nom as utilisateur,
                    COUNT(vi.id) as nombre_articles
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                LEFT JOIN ventes_items vi ON v.id = vi.vente_id
                WHERE v.deleted_at IS NULL";
        
        $params = [];
        
        // Filtre par utilisateur (vendeur voit seulement ses ventes)
        if ($userId !== null) {
            $sql .= " AND v.utilisateur_id = ?";
            $params[] = $userId;
        }
        
        // Filtre par utilisateur spécifique (pour admin)
        if ($filtreUserId !== null) {
            $sql .= " AND v.utilisateur_id = ?";
            $params[] = $filtreUserId;
        }
        
        // Filtre par période
        if ($periode === 'aujourd\'hui') {
            $sql .= " AND DATE(v.date_vente) = CURDATE()";
        } elseif ($periode === 'semaine') {
            $sql .= " AND YEARWEEK(v.date_vente, 1) = YEARWEEK(CURDATE(), 1)";
        } elseif ($periode === 'mois') {
            $sql .= " AND MONTH(v.date_vente) = MONTH(CURDATE()) AND YEAR(v.date_vente) = YEAR(CURDATE())";
        }
        
        // Filtre par dates personnalisées
        if ($dateDebut !== null) {
            $sql .= " AND DATE(v.date_vente) >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin !== null) {
            $sql .= " AND DATE(v.date_vente) <= ?";
            $params[] = $dateFin;
        }
        
        // Recherche par facture ou client
        if ($recherche !== null && trim($recherche) !== '') {
            $sql .= " AND (v.numero_facture LIKE ? OR c.nom LIKE ? OR c.prenom LIKE ?)";
            $term = '%' . trim($recherche) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        
        $sql .= " GROUP BY v.id ORDER BY v.date_vente DESC, v.id DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthodes de correction privées
    private function corrigerQuantiteArticle(int $venteId, array $correction): void
    {
        // Implémentation de la correction de quantité
        // avec ajustement du stock correspondant
    }

    private function corrigerPrixArticle(int $venteId, array $correction): void
    {
        // Implémentation de la correction de prix
    }

    private function ajouterRemiseArticle(int $venteId, array $correction): void
    {
        // Implémentation de l'ajout de remise
    }
}
