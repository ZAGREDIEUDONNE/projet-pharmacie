<?php

namespace App\Services;

use PDO;
use Exception;
use TypeError;

class StockAvanceService
{
    private PDO $db;
    private AuditService $auditService;
    private ?EcritureComptableService $ecritureService = null;

    public function __construct(PDO $db, AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    private function getEcritureService(): EcritureComptableService
    {
        if ($this->ecritureService === null) {
            $journal = new JournalComptableService($this->db, $this->auditService);
            $this->ecritureService = new EcritureComptableService($this->db, $this->auditService, $journal);
        }

        return $this->ecritureService;
    }

    /**
     * Met à jour le stock réel et théorique en temps réel.
     */
    public function mettreAJourStockTempsReel(int $produitId, int $quantite, string $typeMouvement, array $details): array
    {
        $produitId = max(0, $produitId);
        $quantite = max(0, $quantite);
        $typeMouvement = strtoupper(trim($typeMouvement));
        $details = $this->normaliserDetailsMouvement($details);

        if ($produitId <= 0) {
            throw new Exception('Produit invalide pour la mise à jour du stock');
        }
        if ($quantite <= 0 && !in_array($typeMouvement, ['RESERVATION', 'DERESERVATION'], true)) {
            throw new Exception('La quantité du mouvement doit être strictement positive');
        }

        return $this->runInTransaction(function () use ($produitId, $quantite, $typeMouvement, $details) {
            $avant = $this->getStockActuel($produitId, true);
            $apres = $this->calculerNouvelEtatStock($avant, $quantite, $typeMouvement);

            $sql = 'UPDATE stock SET
                        quantite_disponible = ?,
                        quantite_theorique = ?,
                        dernier_mouvement = NOW(),
                        updated_at = NOW()
                    WHERE produit_id = ?';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $apres['quantite_disponible'],
                $apres['quantite_theorique'],
                $produitId,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Impossible de mettre à jour le stock du produit #{$produitId}");
            }

            if ($typeMouvement === 'ENTREE' && isset($details['prix_unitaire'])) {
                $this->mettreAJourValeurStock($produitId, $quantite, (float) $details['prix_unitaire']);
            }

            $mouvementId = $this->enregistrerMouvementStock(
                $produitId,
                $typeMouvement,
                $quantite,
                $avant,
                $apres,
                $details
            );

            $this->auditService->logAction(
                $details['utilisateur_id'],
                'UPDATE_STOCK_TEMPS_REEL',
                'stock',
                $produitId,
                $avant,
                array_merge($apres, [
                    'type_mouvement' => $typeMouvement,
                    'quantite' => $quantite,
                    'mouvement_id' => $mouvementId,
                ])
            );

            return [
                'success' => true,
                'stock_reel' => $apres['quantite_disponible'],
                'stock_theorique' => $apres['quantite_theorique'],
                'mouvement_id' => $mouvementId,
                'message' => 'Stock mis à jour en temps réel',
            ];
        }, 'mettreAJourStockTempsReel');
    }

    /**
     * Gestion avancée des lots avec traçabilité.
     */
    public function gererLot(array $donneesLot): array
    {
        return $this->runInTransaction(function () use ($donneesLot) {
            $this->validerDonneesLot($donneesLot);

            $lotExistant = $this->getLotByNumero((int) $donneesLot['produit_id'], (string) $donneesLot['numero_lot']);

            if ($lotExistant) {
                $sql = 'UPDATE lots SET
                            quantite_restante = quantite_restante + ?,
                            date_fabrication = ?,
                            date_peremption = ?,
                            prix_achat_unitaire = ?,
                            updated_at = NOW()
                        WHERE id = ?';

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    (int) $donneesLot['quantite'],
                    $donneesLot['date_fabrication'],
                    $donneesLot['date_peremption'],
                    (float) $donneesLot['prix_achat_unitaire'],
                    (int) $lotExistant['id'],
                ]);

                $lotId = (int) $lotExistant['id'];
                $action = 'UPDATE_LOT';
            } else {
                $sql = 'INSERT INTO lots (
                            produit_id, numero_lot, date_fabrication, date_peremption,
                            quantite_initiale, quantite_restante, prix_achat_unitaire,
                            fournisseur_id, commande_id, is_actif
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)';

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    (int) $donneesLot['produit_id'],
                    (string) $donneesLot['numero_lot'],
                    $donneesLot['date_fabrication'],
                    $donneesLot['date_peremption'],
                    (int) $donneesLot['quantite'],
                    (int) $donneesLot['quantite'],
                    (float) $donneesLot['prix_achat_unitaire'],
                    $donneesLot['fournisseur_id'] ?? null,
                    $donneesLot['commande_id'] ?? null,
                ]);

                $lotId = (int) $this->db->lastInsertId();
                $action = 'CREATE_LOT';
            }

            $this->mettreAJourStockTempsReel(
                (int) $donneesLot['produit_id'],
                (int) $donneesLot['quantite'],
                'ENTREE',
                [
                    'lot_id' => $lotId,
                    'utilisateur_id' => (int) $donneesLot['utilisateur_id'],
                    'prix_unitaire' => (float) $donneesLot['prix_achat_unitaire'],
                    'reference_type' => 'LOT',
                    'reference_id' => $lotId,
                    'motif' => 'Entrée lot ' . $donneesLot['numero_lot'],
                ]
            );

            $this->verifierPeremptionLot($lotId);

            $this->auditService->logAction(
                (int) $donneesLot['utilisateur_id'],
                $action,
                'lots',
                $lotId,
                null,
                $donneesLot
            );

            return [
                'success' => true,
                'lot_id' => $lotId,
                'message' => 'Lot géré avec succès',
            ];
        }, 'gererLot');
    }

    /**
     * Déduit le stock en utilisant la méthode FIFO.
     */
    public function deduireStockFIFO(int $produitId, int $quantite, int $utilisateurId): array
    {
        return $this->runInTransaction(function () use ($produitId, $quantite, $utilisateurId) {
            $quantiteADeduire = max(0, $quantite);
            $lotsUtilises = [];

            if ($produitId <= 0 || $quantiteADeduire <= 0) {
                throw new Exception('Produit ou quantité invalide pour la déduction FIFO');
            }

            $sql = 'SELECT * FROM lots
                    WHERE produit_id = ?
                    AND quantite_restante > 0
                    AND is_actif = 1
                    AND (date_peremption IS NULL OR date_peremption > CURDATE())
                    ORDER BY date_fabrication ASC, date_peremption ASC
                    FOR UPDATE';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$produitId]);
            $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($lots === []) {
                throw new Exception('Aucun lot disponible pour ce produit');
            }

            $quantiteTotaleDisponible = array_sum(array_map(
                static fn(array $lot): int => (int) $lot['quantite_restante'],
                $lots
            ));

            if ($quantiteTotaleDisponible < $quantiteADeduire) {
                throw new Exception("Stock insuffisant: {$quantiteTotaleDisponible} disponible pour {$quantiteADeduire} requis");
            }

            foreach ($lots as $lot) {
                if ($quantiteADeduire <= 0) {
                    break;
                }

                $quantiteLot = min($quantiteADeduire, (int) $lot['quantite_restante']);

                $updateLot = $this->db->prepare(
                    'UPDATE lots SET quantite_restante = quantite_restante - ?, updated_at = NOW() WHERE id = ?'
                );
                $updateLot->execute([$quantiteLot, (int) $lot['id']]);

                $lotsUtilises[] = [
                    'lot_id' => (int) $lot['id'],
                    'numero_lot' => (string) $lot['numero_lot'],
                    'quantite' => $quantiteLot,
                    'date_peremption' => $lot['date_peremption'],
                ];

                $quantiteADeduire -= $quantiteLot;
            }

            $this->mettreAJourStockTempsReel($produitId, $quantite, 'SORTIE', [
                'utilisateur_id' => $utilisateurId,
                'lots_utilises' => $lotsUtilises,
                'reference_type' => 'VENTE',
                'reference_id' => null,
                'motif' => 'Sortie FIFO vente',
            ]);

            return [
                'success' => true,
                'lots_utilises' => $lotsUtilises,
                'message' => 'Stock déduit avec succès (FIFO)',
            ];
        }, 'deduireStockFIFO');
    }

    public function verifierPeremptions(): array
    {
        $sql = "SELECT
                    p.id AS produit_id,
                    p.nom AS produit_nom,
                    p.code_cip,
                    l.id AS lot_id,
                    l.numero_lot,
                    l.date_peremption,
                    l.quantite_restante,
                    DATEDIFF(l.date_peremption, CURDATE()) AS jours_restants,
                    CASE
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END AS niveau_peremption
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite_restante > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY l.date_peremption ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $peremptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'perimes' => array_values(array_filter($peremptions, static fn(array $p): bool => $p['niveau_peremption'] === 'PERIME')),
            'urgents' => array_values(array_filter($peremptions, static fn(array $p): bool => $p['niveau_peremption'] === 'URGENT')),
            'alertes' => array_values(array_filter($peremptions, static fn(array $p): bool => $p['niveau_peremption'] === 'ALERTE')),
            'total' => count($peremptions),
        ];
    }

    public function genererCommandesAutomatiques(): array
    {
        return $this->runInTransaction(function () {
            $commandesGenerees = [];

            $sql = 'SELECT p.*, s.quantite_disponible, s.quantite_theorique,
                           f.id AS fournisseur_id, f.nom AS fournisseur_nom
                    FROM produits p
                    JOIN stock s ON p.id = s.produit_id
                    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                    WHERE p.is_actif = 1
                    AND p.deleted_at IS NULL
                    AND s.quantite_disponible <= p.stock_securite
                    ORDER BY s.quantite_disponible ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produitsARecommander = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($produitsARecommander as $produit) {
                if (empty($produit['fournisseur_id'])) {
                    continue;
                }

                $quantiteACommander = max(1, (int) $produit['stock_alerte'] - (int) $produit['quantite_disponible']);
                $commandeExistante = $this->getCommandeEnCours((int) $produit['id'], (int) $produit['fournisseur_id']);

                if ($commandeExistante) {
                    $this->ajouterProduitCommande((int) $commandeExistante['id'], (int) $produit['id'], $quantiteACommander);
                } else {
                    $commandesGenerees[] = $this->creerCommandeAutomatique($produit, $quantiteACommander);
                }
            }

            return [
                'success' => true,
                'commandes_generees' => $commandesGenerees,
                'produits_traites' => count($produitsARecommander),
                'message' => 'Commandes automatiques générées avec succès',
            ];
        }, 'genererCommandesAutomatiques');
    }

    public function calculerStockTheorique(int $produitId): int
    {
        $sql = 'SELECT
                    COALESCE(SUM(l.quantite_restante), 0) AS stock_reel,
                    COALESCE(SUM(ci.quantite_commandee - ci.quantite_livree), 0) AS stock_reserve
                FROM produits p
                LEFT JOIN lots l ON p.id = l.produit_id AND l.is_actif = 1
                LEFT JOIN commande_items ci ON p.id = ci.produit_id
                LEFT JOIN commandes c ON ci.commande_id = c.id
                WHERE p.id = ?
                AND (c.id IS NULL OR c.statut_commande IN (\'VALIDEE\', \'PARTIELLEMENT_LIVREE\'))
                AND (c.deleted_at IS NULL OR c.id IS NULL)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['stock_reel' => 0, 'stock_reserve' => 0];

        return (int) $result['stock_reel'] - (int) $result['stock_reserve'];
    }

    public function synchroniserStocksTheoriques(): array
    {
        $sql = 'UPDATE stock s
                SET quantite_theorique = (
                    SELECT COALESCE(SUM(l.quantite_restante), 0)
                    FROM lots l
                    WHERE l.produit_id = s.produit_id AND l.is_actif = 1
                ) - (
                    SELECT COALESCE(SUM(ci.quantite_commandee - ci.quantite_livree), 0)
                    FROM commande_items ci
                    JOIN commandes c ON ci.commande_id = c.id
                    WHERE ci.produit_id = s.produit_id
                    AND c.statut_commande IN (\'VALIDEE\', \'PARTIELLEMENT_LIVREE\')
                    AND c.deleted_at IS NULL
                ),
                updated_at = NOW()';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return [
            'success' => true,
            'nombre_mises_a_jour' => $stmt->rowCount(),
            'message' => 'Stocks théoriques synchronisés',
        ];
    }

    /**
     * @return array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int}
     */
    private function normaliserSnapshotStock(array $data, int $produitId): array
    {
        return [
            'produit_id' => $produitId,
            'quantite_disponible' => max(0, (int) ($data['quantite_disponible'] ?? 0)),
            'quantite_theorique' => max(0, (int) ($data['quantite_theorique'] ?? $data['quantite_disponible'] ?? 0)),
            'quantite_reservee' => max(0, (int) ($data['quantite_reservee'] ?? 0)),
            'valeur_stock' => max(0, (float) ($data['valeur_stock'] ?? 0)),
        ];
    }

    /**
     * @param mixed $value
     * @return array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int}
     */
    private function assertStockSnapshot($value, int $produitId, string $label): array
    {
        if (!is_array($value)) {
            $message = "StockAvanceService: {$label} doit être un tableau, " . gettype($value) . ' reçu';
            error_log($message);
            throw new TypeError($message);
        }

        return $this->normaliserSnapshotStock($value, $produitId);
    }

    private function normaliserDetailsMouvement(array $details): array
    {
        $utilisateurId = (int) ($details['utilisateur_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        if ($utilisateurId <= 0) {
            throw new Exception('Utilisateur obligatoire pour enregistrer un mouvement de stock');
        }

        return [
            'utilisateur_id' => $utilisateurId,
            'lot_id' => isset($details['lot_id']) ? (int) $details['lot_id'] : null,
            'motif' => trim((string) ($details['motif'] ?? 'Mouvement stock')),
            'reference_type' => strtoupper(trim((string) ($details['reference_type'] ?? 'MANUEL'))),
            'reference_id' => isset($details['reference_id']) && $details['reference_id'] !== ''
                ? (int) $details['reference_id']
                : null,
            'prix_unitaire' => isset($details['prix_unitaire']) ? (float) $details['prix_unitaire'] : null,
            'lots_utilises' => is_array($details['lots_utilises'] ?? null) ? $details['lots_utilises'] : [],
        ];
    }

    /**
     * @param array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int} $avant
     * @return array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int}
     */
    private function calculerNouvelEtatStock(array $avant, int $quantite, string $typeMouvement): array
    {
        $apres = $avant;
        $quantite = max(0, $quantite);

        switch ($typeMouvement) {
            case 'ENTREE':
                $apres['quantite_disponible'] += $quantite;
                $apres['quantite_theorique'] += $quantite;
                break;
            case 'SORTIE':
                if ($apres['quantite_disponible'] < $quantite) {
                    throw new Exception('Stock insuffisant pour cette sortie');
                }
                $apres['quantite_disponible'] -= $quantite;
                $apres['quantite_theorique'] = max(0, $apres['quantite_theorique'] - $quantite);
                break;
            case 'RESERVATION':
                $apres['quantite_theorique'] = max(0, $apres['quantite_theorique'] - $quantite);
                break;
            case 'DERESERVATION':
                $apres['quantite_theorique'] += $quantite;
                break;
            default:
                throw new Exception("Type de mouvement non valide: {$typeMouvement}");
        }

        return $this->normaliserSnapshotStock($apres, $avant['produit_id']);
    }

    /**
     * @param array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int} $avant
     * @param array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int} $apres
     */
    private function enregistrerMouvementStock(
        int $produitId,
        string $typeMouvement,
        int $quantite,
        array $avant,
        array $apres,
        array $details
    ): int {
        $avant = $this->assertStockSnapshot($avant, $produitId, '$avant');
        $apres = $this->assertStockSnapshot($apres, $produitId, '$apres');
        $details = $this->normaliserDetailsMouvement($details);

        $sql = 'INSERT INTO mouvements_stock (
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $produitId,
            $details['lot_id'],
            strtoupper($typeMouvement),
            max(0, $quantite),
            $avant['quantite_disponible'],
            $apres['quantite_disponible'],
            $details['motif'] !== '' ? $details['motif'] : 'Mouvement stock',
            $details['reference_type'],
            $details['reference_id'],
            $details['utilisateur_id'],
        ]);

        $mouvementId = (int) $this->db->lastInsertId();

        $this->genererEcritureComptableMouvementStock($mouvementId, $produitId, strtoupper($typeMouvement), $quantite, $details);

        $this->auditService->logAction(
            $details['utilisateur_id'],
            'MOUVEMENT_STOCK',
            'mouvements_stock',
            $mouvementId,
            $avant,
            [
                'apres' => $apres,
                'produit_id' => $produitId,
                'type_mouvement' => $typeMouvement,
                'quantite' => $quantite,
                'details' => $details,
            ]
        );

        return $mouvementId;
    }

    private function genererEcritureComptableMouvementStock(
        int $mouvementId,
        int $produitId,
        string $typeMouvement,
        int $quantite,
        array $details
    ): void {
        if ($quantite <= 0) {
            throw new Exception('Quantite de mouvement stock invalide pour comptabilisation');
        }
        $prixUnitaire = $this->resoudrePrixUnitaireComptable($produitId, $details);

        $referenceType = strtoupper((string) ($details['reference_type'] ?? 'MOUVEMENT_STOCK'));
        $ecritureId = $this->getEcritureService()->enregistrerEcriture([
            'journal_code' => 'OD',
            'numero_piece' => 'ST' . date('Ymd') . str_pad((string) $mouvementId, 6, '0', STR_PAD_LEFT),
            'date_ecriture' => date('Y-m-d H:i:s'),
            'libelle' => "Mouvement stock produit #{$produitId}",
            'reference_type' => 'MOUVEMENT_STOCK',
            'reference_id' => $mouvementId,
            'utilisateur_id' => (int) $details['utilisateur_id'],
            'lignes' => $this->buildLignesComptablesMouvementStock($typeMouvement, $quantite, $prixUnitaire, $referenceType, $details),
        ]);

        $this->lierMouvementStockAEcriture($mouvementId, $ecritureId);
    }

    private function buildLignesComptablesMouvementStock(
        string $typeMouvement,
        int $quantite,
        float $prixUnitaire,
        string $referenceType,
        array $details
    ): array {
        $montant = round($quantite * $prixUnitaire, 2);
        if ($montant <= 0) {
            throw new Exception('Montant comptable mouvement stock invalide');
        }

        if ($typeMouvement === 'ENTREE') {
            return [[
                // Regle demandee: entree stock -> debit 31, credit 401.
                'compte_code' => PlanComptableService::COMPTE_MARCHANDISES,
                'libelle' => 'Entree stock',
                'debit' => $montant,
                'credit' => 0,
                'tiers_id' => null,
            ], [
                'compte_code' => PlanComptableService::COMPTE_FOURNISSEURS,
                'libelle' => 'Contrepartie entree stock',
                'debit' => 0,
                'credit' => $montant,
                'tiers_id' => $details['fournisseur_id'] ?? null,
            ]];
        }

        if ($typeMouvement === 'SORTIE') {
            $compteDebitSortie = $this->resoudreCompteDebitSortieVente($referenceType, $details);

            return [[
                // Regle demandee: sortie vente -> debit 701 / 411 / 571, credit 31.
                'compte_code' => $compteDebitSortie,
                'libelle' => 'Sortie stock vente',
                'debit' => $montant,
                'credit' => 0,
                'tiers_id' => $compteDebitSortie === PlanComptableService::COMPTE_CLIENTS
                    ? ($details['client_id'] ?? null)
                    : null,
            ], [
                'compte_code' => PlanComptableService::COMPTE_MARCHANDISES,
                'libelle' => 'Sortie stock',
                'debit' => 0,
                'credit' => $montant,
                'tiers_id' => null,
            ]];
        }

        throw new Exception("Type de mouvement non comptabilisable: {$typeMouvement}");
    }

    private function resoudrePrixUnitaireComptable(int $produitId, array $details): float
    {
        $prix = round((float) ($details['prix_unitaire'] ?? 0), 2);
        if ($prix > 0) {
            return $prix;
        }

        $stmt = $this->db->prepare('SELECT prix_achat FROM produits WHERE id = ?');
        $stmt->execute([$produitId]);
        $prix = round((float) ($stmt->fetchColumn() ?: 0), 2);
        if ($prix <= 0) {
            throw new Exception("Prix unitaire introuvable pour comptabiliser le produit #{$produitId}");
        }

        return $prix;
    }

    private function resoudreCompteDebitSortieVente(string $referenceType, array $details): string
    {
        if ($referenceType !== 'VENTE') {
            return PlanComptableService::COMPTE_VENTES;
        }

        $venteId = (int) ($details['reference_id'] ?? 0);
        if ($venteId > 0) {
            $stmt = $this->db->prepare('SELECT is_credit, type_paiement FROM ventes WHERE id = ?');
            $stmt->execute([$venteId]);
            $vente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (!empty($vente['is_credit'])) {
                return PlanComptableService::COMPTE_CLIENTS;
            }

            $typePaiement = strtoupper((string) ($vente['type_paiement'] ?? 'ESPECE'));
            if ($typePaiement === 'ESPECE') {
                return PlanComptableService::COMPTE_CAISSE;
            }
        }

        return PlanComptableService::COMPTE_VENTES;
    }

    private function lierMouvementStockAEcriture(int $mouvementId, int $ecritureId): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute(['mouvements_stock', 'ecriture_id']);
        if ((int) $stmt->fetchColumn() <= 0) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE mouvements_stock SET ecriture_id = ? WHERE id = ?');
        $stmt->execute([$ecritureId, $mouvementId]);
    }

    /**
     * @return array{quantite_disponible:int,quantite_theorique:int,quantite_reservee:int,valeur_stock:float,produit_id:int}
     */
    private function getStockActuel(int $produitId, bool $forUpdate = false): array
    {
        $lock = $forUpdate && $this->db->inTransaction() ? ' FOR UPDATE' : '';
        $sql = "SELECT * FROM stock WHERE produit_id = ?{$lock}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            $this->initialiserStock($produitId);
            $stmt->execute([$produitId]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$stock) {
            throw new Exception("Stock introuvable pour le produit #{$produitId}");
        }

        return $this->normaliserSnapshotStock($stock, $produitId);
    }

    private function initialiserStock(int $produitId): void
    {
        $sql = 'INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, quantite_reservee, valeur_stock, created_at, updated_at)
                VALUES (?, 0, 0, 0, 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
    }

    private function mettreAJourValeurStock(int $produitId, int $quantite, float $prixUnitaire): void
    {
        if ($prixUnitaire <= 0 || $quantite <= 0) {
            return;
        }

        $valeurAjoutee = round($quantite * $prixUnitaire, 2);
        $sql = 'UPDATE stock SET valeur_stock = GREATEST(0, valeur_stock + ?), updated_at = NOW() WHERE produit_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$valeurAjoutee, $produitId]);
    }

    private function runInTransaction(callable $callback, string $contexte)
    {
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();

            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (Exception | TypeError $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log("StockAvanceService::{$contexte} - " . $e->getMessage());
            throw new Exception("Erreur {$contexte}: " . $e->getMessage(), 0, $e);
        }
    }

    private function validerDonneesLot(array $donnees): void
    {
        foreach (['produit_id', 'numero_lot', 'date_fabrication', 'date_peremption', 'quantite', 'prix_achat_unitaire', 'utilisateur_id'] as $champ) {
            if (!isset($donnees[$champ]) || $donnees[$champ] === '' || $donnees[$champ] === null) {
                throw new Exception("Le champ '{$champ}' est obligatoire pour le lot");
            }
        }

        if ($donnees['date_fabrication'] >= $donnees['date_peremption']) {
            throw new Exception('La date de fabrication doit être antérieure à la date de péremption');
        }

        if (new \DateTime((string) $donnees['date_peremption']) <= new \DateTime()) {
            throw new Exception('La date de péremption doit être dans le futur');
        }

        if ((int) $donnees['quantite'] <= 0) {
            throw new Exception('La quantité doit être positive');
        }

        if ((float) $donnees['prix_achat_unitaire'] <= 0) {
            throw new Exception("Le prix d'achat doit être positif");
        }
    }

    private function getLotByNumero(int $produitId, string $numeroLot): ?array
    {
        $sql = 'SELECT * FROM lots WHERE produit_id = ? AND numero_lot = ? AND is_actif = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId, $numeroLot]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function verifierPeremptionLot(int $lotId): void
    {
        $sql = 'SELECT date_peremption, quantite_restante FROM lots WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lotId]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lot) {
            return;
        }

        $joursRestants = (new \DateTime((string) $lot['date_peremption']))->diff(new \DateTime())->days;

        if ($joursRestants <= 0 && (int) $lot['quantite_restante'] > 0) {
            $sql = 'UPDATE lots SET is_actif = 0, updated_at = NOW() WHERE id = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$lotId]);

            $this->auditService->logAction(
                1,
                'LOT_PERIME',
                'lots',
                $lotId,
                null,
                [
                    'date_peremption' => $lot['date_peremption'],
                    'quantite_perdue' => $lot['quantite_restante'],
                ]
            );
        }
    }

    private function getCommandeEnCours(int $produitId, int $fournisseurId): ?array
    {
        $sql = 'SELECT c.* FROM commandes c
                JOIN commande_items ci ON c.id = ci.commande_id
                WHERE ci.produit_id = ?
                AND c.fournisseur_id = ?
                AND c.statut_commande = \'BROUILLON\'
                AND c.deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId, $fournisseurId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function creerCommandeAutomatique(array $produit, int $quantite): int
    {
        $numeroCommande = $this->genererNumeroCommande();

        $sql = 'INSERT INTO commandes (
                    numero_commande, fournisseur_id, utilisateur_id,
                    date_commande, date_livraison_prevue, montant_total,
                    statut_commande, notes
                ) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), ?, \'BROUILLON\', ?)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $numeroCommande,
            (int) $produit['fournisseur_id'],
            1,
            $quantite * (float) $produit['prix_achat'],
            "Commande automatique - Stock bas pour {$produit['nom']}",
        ]);

        $commandeId = (int) $this->db->lastInsertId();
        $this->ajouterProduitCommande($commandeId, (int) $produit['id'], $quantite);

        return $commandeId;
    }

    private function ajouterProduitCommande(int $commandeId, int $produitId, int $quantite): void
    {
        $stmt = $this->db->prepare('SELECT prix_achat FROM produits WHERE id = ?');
        $stmt->execute([$produitId]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) {
            throw new Exception('Produit non trouvé');
        }

        $prix = (float) $produit['prix_achat'];
        $montantTotal = $quantite * $prix;

        $sql = 'INSERT INTO commande_items (commande_id, produit_id, quantite_commandee, prix_unitaire, montant_total)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                quantite_commandee = quantite_commandee + VALUES(quantite_commandee),
                montant_total = (quantite_commandee + VALUES(quantite_commandee)) * prix_unitaire';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId, $produitId, $quantite, $prix, $montantTotal]);

        $sql = 'UPDATE commandes c
                SET montant_total = (
                    SELECT COALESCE(SUM(montant_total), 0)
                    FROM commande_items
                    WHERE commande_id = ?
                )
                WHERE id = ?';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commandeId, $commandeId]);
    }

    private function genererNumeroCommande(): string
    {
        $prefix = 'CMD' . date('Ymd');
        $stmt = $this->db->prepare('SELECT COUNT(*) AS count FROM commandes WHERE DATE(date_commande) = CURDATE()');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prefix . str_pad((string) (((int) ($result['count'] ?? 0)) + 1), 3, '0', STR_PAD_LEFT);
    }
}
