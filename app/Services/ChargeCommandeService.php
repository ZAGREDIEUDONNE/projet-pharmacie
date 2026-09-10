<?php

namespace App\Services;

use PDO;
use Exception;

class ChargeCommandeService
{
    private PDO $db;
    private AuditService $auditService;
    private ?EcritureComptableService $ecritureService;

    public function __construct(PDO $db, ?AuditService $auditService = null, ?EcritureComptableService $ecritureService = null)
    {
        $this->db = $db;
        $this->auditService = $auditService ?? new AuditService($db);
        $this->ecritureService = $ecritureService;
    }

    public function getDashboardData(): array
    {
        $stockSummary = $this->getStockSummary();

        return [
            'widgets' => [
                'stock_total' => $stockSummary['total_produits'],
                'ruptures' => $this->countOutOfStock(),
                'stock_critique' => $this->countCriticalStock(),
                'stock_sous_seuil' => $this->countLowStock(),
                'commandes_en_cours' => $this->countPendingOrders(),
                'receptions_recentes' => $this->countRecentReceptions(),
            ],
            'produits_a_commander' => $this->getProductsToOrder(20),
            'commandes_attente' => $this->getPendingOrders(12),
            'receptions_recentes' => $this->getRecentReceptions(12),
            'mouvements_recents' => $this->getStockMovements([], 15),
            'alertes' => [
                'ruptures' => $this->countOutOfStock(),
                'stock_critique' => $this->countCriticalStock(),
                'stock_sous_seuil' => $this->countLowStock(),
                'peremptions_30j' => $this->countExpiringSoon(30),
                'peremptions_60j' => $this->countExpiringSoon(60),
                'commandes_retard' => $this->countLateOrders(),
            ],
            'charts' => [
                'stock_entries_monthly' => $this->getMonthlyStockMovements('ENTREE'),
                'stock_outputs_monthly' => $this->getMonthlyStockMovements('SORTIE'),
            ],
            'stock_by_forme' => $this->getStockByForme(),
            'top_used_products' => $this->getTopUsedProducts(),
        ];
    }

    public function addStock(array $data, int $userId): array
    {
        $produitId = (int)($data['produit_id'] ?? 0);
        $quantite = (int)($data['quantite'] ?? 0);
        $prixAchat = (float)($data['prix_achat'] ?? 0);
        $fournisseurId = (int)($data['fournisseur_id'] ?? 0);
        $dateReception = (string)($data['date_reception'] ?? date('Y-m-d'));

        if ($produitId <= 0 || $quantite <= 0 || $prixAchat <= 0) {
            throw new Exception('Produit, quantite et prix achat sont obligatoires.');
        }

        if (!$this->isValidDate($dateReception)) {
            throw new Exception('Date de reception invalide.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stock = $this->getStockForUpdate($produitId);
            $stockAvant = (int)$stock['quantite_disponible'];
            $stockApres = $stockAvant + $quantite;

            $stmt = $this->db->prepare(
                "UPDATE stock
                 SET quantite_disponible = :quantite,
                     quantite_theorique = quantite_theorique + :delta,
                     valeur_stock = valeur_stock + :valeur,
                     dernier_mouvement = NOW(),
                     updated_at = NOW()
                 WHERE produit_id = :produit_id"
            );
            $stmt->execute([
                'quantite' => $stockApres,
                'delta' => $quantite,
                'valeur' => $quantite * $prixAchat,
                'produit_id' => $produitId,
            ]);

            $entryId = $this->insertStockEntry($produitId, $fournisseurId, $userId, null, $quantite, $prixAchat, $dateReception, $data, $stockAvant, $stockApres);
            $movementId = $this->insertStockMovement($produitId, $quantite, $stockAvant, $stockApres, $userId, 'COMMAND', $entryId, $data['observations'] ?? 'Ajout stock');

            $this->auditService->logAction($userId, 'ADD_STOCK', 'stock_entries', $entryId, [
                'stock_avant' => $stockAvant,
            ], [
                'produit_id' => $produitId,
                'quantite' => $quantite,
                'prix_achat' => $prixAchat,
                'fournisseur_id' => $fournisseurId ?: null,
                'stock_apres' => $stockApres,
                'mouvement_id' => $movementId,
                'machine' => gethostname() ?: null,
            ], null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'message' => 'Stock ajoute avec succes.', 'stock_apres' => $stockApres];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createSupplierOrder(array $data, int $userId): array
    {
        $fournisseurId = (int)($data['fournisseur_id'] ?? 0);
        $items = $data['items'] ?? [];
        if ($fournisseurId <= 0 || empty($items)) {
            throw new Exception('Fournisseur et produits sont obligatoires.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $numero = $this->generateNumber('CC');
            $reference = trim((string)($data['reference_commande'] ?? '')) ?: null;
            $remiseGlobale = (float)($data['remise_globale'] ?? 0);
            $tvaGlobale = (float)($data['tva_globale'] ?? 0);
            
            $montantHT = 0.0;

            $stmt = $this->db->prepare(
                "INSERT INTO supplier_orders (numero_commande, reference_commande, fournisseur_id, utilisateur_id, date_commande, date_livraison_prevue, statut, observations, remise_globale, tva_globale, montant_total, montant_ht, montant_ttc)
                 VALUES (:numero, :reference, :fournisseur_id, :utilisateur_id, :date_commande, :date_livraison_prevue, :statut, :observations, :remise_globale, :tva_globale, :montant_total, :montant_ht, :montant_ttc)"
            );
            $stmt->execute([
                'numero' => $numero,
                'reference' => $reference,
                'fournisseur_id' => $fournisseurId,
                'utilisateur_id' => $userId,
                'date_commande' => $data['date_commande'] ?? date('Y-m-d'),
                'date_livraison_prevue' => ($data['date_livraison_prevue'] ?? null) ?: null,
                'statut' => $data['statut'] ?? 'BROUILLON',
                'observations' => trim((string)($data['observations'] ?? '')) ?: null,
                'remise_globale' => $remiseGlobale,
                'tva_globale' => $tvaGlobale,
                'montant_total' => 0,
                'montant_ht' => 0,
                'montant_ttc' => 0,
            ]);
            $orderId = (int)$this->db->lastInsertId();

            foreach ($items as $item) {
                $produitId = (int)($item['produit_id'] ?? 0);
                $quantite = (int)($item['quantite'] ?? 0);
                $prix = (float)($item['prix_achat'] ?? 0);
                $remise = (float)($item['remise'] ?? 0);
                $tva = (float)($item['tva'] ?? 0);
                
                if ($produitId <= 0 || $quantite <= 0 || $prix <= 0) {
                    throw new Exception('Lignes commande invalides.');
                }
                
                $lineHT = $quantite * $prix;
                $lineRemise = $lineHT * ($remise / 100);
                $lineApresRemise = $lineHT - $lineRemise;
                $lineTVA = $lineApresRemise * ($tva / 100);
                $lineTTC = $lineApresRemise + $lineTVA;
                
                $montantHT += $lineHT;

                $stmt = $this->db->prepare(
                    "INSERT INTO supplier_order_items (supplier_order_id, produit_id, quantite_commandee, prix_achat, montant_total, remise, tva, montant_ht, montant_ttc)
                     VALUES (:order_id, :produit_id, :quantite, :prix, :total, :remise, :tva, :montant_ht, :montant_ttc)"
                );
                $stmt->execute([
                    'order_id' => $orderId,
                    'produit_id' => $produitId,
                    'quantite' => $quantite,
                    'prix' => $prix,
                    'total' => $lineTTC,
                    'remise' => $remise,
                    'tva' => $tva,
                    'montant_ht' => $lineHT,
                    'montant_ttc' => $lineTTC,
                ]);
            }

            // Calculer les totaux avec remise et TVA globales
            $remiseMontant = $montantHT * ($remiseGlobale / 100);
            $montantApresRemise = $montantHT - $remiseMontant;
            $tvaMontant = $montantApresRemise * ($tvaGlobale / 100);
            $montantTTC = $montantApresRemise + $tvaMontant;

            $stmt = $this->db->prepare("UPDATE supplier_orders SET montant_ht = :montant_ht, montant_ttc = :montant_ttc, montant_total = :montant_ttc WHERE id = :id");
            $stmt->execute([
                'montant_ht' => $montantHT,
                'montant_ttc' => $montantTTC,
                'montant_ttc' => $montantTTC,
                'id' => $orderId,
            ]);

            $this->auditService->logAction($userId, 'CREATE_SUPPLIER_ORDER', 'supplier_orders', $orderId, null, [
                'numero_commande' => $numero,
                'reference_commande' => $reference,
                'fournisseur_id' => $fournisseurId,
                'montant_ht' => $montantHT,
                'montant_ttc' => $montantTTC,
                'remise_globale' => $remiseGlobale,
                'tva_globale' => $tvaGlobale,
                'machine' => gethostname() ?: null,
            ], null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'message' => 'Commande fournisseur creee.', 'order_id' => $orderId, 'numero' => $numero];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function receiveOrder(array $data, int $userId): array
    {
        $orderId = (int)($data['supplier_order_id'] ?? 0);
        $items = $data['items'] ?? [];
        if ($orderId <= 0 || empty($items)) {
            throw new Exception('Commande et quantites recues obligatoires.');
        }

        $dateReception = (string)($data['date_reception'] ?? date('Y-m-d'));
        if (!$this->isValidDate($dateReception)) {
            throw new Exception('Date de reception invalide.');
        }

        // Agréger avant toute écriture : une même ligne ne peut pas contourner
        // le reliquat en étant envoyée plusieurs fois dans le même formulaire.
        $requestedQuantities = [];
        foreach ($items as $item) {
            $itemId = (int)($item['supplier_order_item_id'] ?? 0);
            $quantiteRecue = (int)($item['quantite_recue'] ?? 0);
            if ($itemId <= 0 || $quantiteRecue < 0) {
                throw new Exception('Ligne reception invalide.');
            }
            if ($quantiteRecue > 0) {
                $requestedQuantities[$itemId] = ($requestedQuantities[$itemId] ?? 0) + $quantiteRecue;
            }
        }
        if (!$requestedQuantities) {
            throw new Exception('Au moins une quantite recue doit etre superieure a zero.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $order = $this->getOrderForUpdate($orderId);
            $validatedLines = [];
            $hasGap = false;

            // Tous les verrous et contrôles critiques sont acquis avant le
            // premier INSERT de réception.
            foreach ($requestedQuantities as $itemId => $quantiteRecue) {
                $orderItem = $this->getOrderItemForUpdate($itemId, $orderId);
                $reste = (int)$orderItem['quantite_commandee'] - (int)$orderItem['quantite_recue'];
                if ($quantiteRecue > $reste) {
                    throw new Exception('Quantite recue superieure au reste attendu.');
                }

                $newReceived = (int)$orderItem['quantite_recue'] + $quantiteRecue;
                $gap = (int)$orderItem['quantite_commandee'] - $newReceived;
                $hasGap = $hasGap || $gap !== 0;
                $validatedLines[] = [
                    'item_id' => $itemId,
                    'order_item' => $orderItem,
                    'quantite_recue' => $quantiteRecue,
                    'reste' => $reste,
                    'new_received' => $newReceived,
                ];
            }

            $numeroReception = $this->generateNumber('BR');

            $stmt = $this->db->prepare(
                "INSERT INTO receptions (supplier_order_id, fournisseur_id, utilisateur_id, numero_reception, date_reception, numero_facture, date_facture, reference_facture, montant_facture, statut, observations)
                 VALUES (:order_id, :fournisseur_id, :utilisateur_id, :numero, :date_reception, :numero_facture, :date_facture, :reference_facture, :montant_facture, 'EN_ATTENTE', :observations)"
            );
            $stmt->execute([
                'order_id' => $orderId,
                'fournisseur_id' => $order['fournisseur_id'],
                'utilisateur_id' => $userId,
                'numero' => $numeroReception,
                'date_reception' => $dateReception,
                'numero_facture' => trim((string)($data['numero_facture'] ?? '')) ?: null,
                'date_facture' => !empty($data['date_facture']) ? $data['date_facture'] : $dateReception,
                'reference_facture' => trim((string)($data['reference_facture'] ?? $data['numero_facture'] ?? '')) ?: null,
                'montant_facture' => max(0, (float)($data['montant_facture'] ?? 0)),
                'observations' => trim((string)($data['observations'] ?? '')) ?: null,
            ]);
            $receptionId = (int)$this->db->lastInsertId();

            foreach ($validatedLines as $line) {
                $orderItem = $line['order_item'];

                $stmt = $this->db->prepare(
                    "INSERT INTO reception_items (reception_id, produit_id, quantite_attendue, quantite_recue, prix_achat, ecart)
                     VALUES (:reception_id, :produit_id, :attendue, :recue, :prix, :ecart)"
                );
                $stmt->execute([
                    'reception_id' => $receptionId,
                    'produit_id' => $orderItem['produit_id'],
                    'attendue' => $line['reste'],
                    'recue' => $line['quantite_recue'],
                    'prix' => $orderItem['prix_achat'],
                    'ecart' => $line['reste'] - $line['quantite_recue'],
                ]);

                $this->addStock([
                    'produit_id' => $orderItem['produit_id'],
                    'quantite' => $line['quantite_recue'],
                    'prix_achat' => $orderItem['prix_achat'],
                    'fournisseur_id' => $order['fournisseur_id'],
                    'date_reception' => $dateReception,
                    'numero_facture' => $data['numero_facture'] ?? null,
                    'observations' => 'Reception ' . $numeroReception,
                    'reception_id' => $receptionId,
                ], $userId);

                $stmt = $this->db->prepare("UPDATE supplier_order_items SET quantite_recue = :qte, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['qte' => $line['new_received'], 'id' => $line['item_id']]);
            }

            $allComplete = $this->isOrderFullyReceived($orderId);
            $status = $allComplete ? 'RECEPTION_COMPLETE' : 'RECEPTION_PARTIELLE';
            $receptionStatus = $allComplete ? 'RECU_COMPLET' : 'PARTIELLEMENT_RECU';

            $stmt = $this->db->prepare("UPDATE supplier_orders SET statut = :statut, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['statut' => $status, 'id' => $orderId]);
            $stmt = $this->db->prepare("UPDATE receptions SET statut = :statut, ecart_detecte = :ecart WHERE id = :id");
            $stmt->execute(['statut' => $receptionStatus, 'ecart' => $hasGap ? 1 : 0, 'id' => $receptionId]);

            $montantFacture = max(0, (float)($data['montant_facture'] ?? 0));
            if ($montantFacture > 0) {
                $this->enregistrerDetteFournisseurReception($receptionId, (int)$order['fournisseur_id'], $montantFacture, $userId, $data);
            }

            // Générer les écritures comptables pour la réception
            if ($this->ecritureService !== null) {
                try {
                    // La requête HTTP ne contient que les identifiants de lignes et
                    // quantités reçues. La comptabilité doit recevoir les montants
                    // réels, issus des lignes de commande verrouillées ci-dessus.
                    $comptabiliteItems = array_map(static function (array $line) use ($order): array {
                        return [
                            'quantite_recue' => $line['quantite_recue'],
                            'prix_achat' => $line['order_item']['prix_achat'],
                            'tva' => (float)($line['order_item']['tva'] ?? 0) > 0
                                ? $line['order_item']['tva']
                                : ($order['tva_globale'] ?? 0),
                        ];
                    }, $validatedLines);
                    $receptionData = [
                        'id' => $receptionId,
                        'numero_reception' => $numeroReception,
                        'date_reception' => $data['date_reception'] ?? date('Y-m-d'),
                        'fournisseur_id' => (int)$order['fournisseur_id'],
                        'fournisseur_nom' => $order['fournisseur_nom'] ?? '',
                        'utilisateur_id' => $userId,
                        'montant_facture' => $montantFacture,
                        'tva_globale' => $order['tva_globale'] ?? 0,
                        'items' => $comptabiliteItems,
                    ];
                    
                    $ecritureResult = $this->ecritureService->genererEcrituresReception($receptionData);
                    
                    // Log de la génération d'écriture
                    $this->auditService->logAction($userId, 'GENERATE_ECRITURE_RECEPTION', 'receptions', $receptionId, null, [
                        'ecriture_id' => $ecritureResult['ecriture_id'],
                        'montant_ht' => $ecritureResult['montant_ht'],
                        'montant_tva' => $ecritureResult['montant_tva'],
                        'montant_ttc' => $ecritureResult['montant_ttc']
                    ]);
                } catch (Exception $e) {
                    // L'échec de la génération d'écriture ne doit pas bloquer la réception
                    // On log l'erreur mais on continue
                    $this->auditService->logAction($userId, 'ECRITURE_RECEPTION_ERROR', 'receptions', $receptionId, null, [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->auditService->logAction($userId, 'RECEIVE_PRODUCTS', 'receptions', $receptionId, null, [
                'supplier_order_id' => $orderId,
                'numero_reception' => $numeroReception,
                'statut' => $receptionStatus,
                'machine' => gethostname() ?: null,
            ], null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'message' => 'Reception enregistree.', 'reception_id' => $receptionId, 'numero' => $numeroReception];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getProducts(): array
    {
        return $this->db->query("SELECT id, nom, code_cip, prix_achat FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSuppliers(): array
    {
        return $this->db->query("SELECT id, nom FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReceivableOrders(): array
    {
        $sql = "SELECT so.*, f.nom AS fournisseur_nom
                FROM supplier_orders so
                JOIN fournisseurs f ON f.id = so.fournisseur_id
                WHERE so.statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
                ORDER BY so.date_commande DESC
                LIMIT 100";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderHistory(int $limit = 100, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(so.numero_commande LIKE :search OR f.nom LIKE :search)';
            $params['search'] = '%' . trim((string)$filters['search']) . '%';
        }
        if (!empty($filters['statut'])) {
            $where[] = 'so.statut = :statut';
            $params['statut'] = strtoupper(trim((string)$filters['statut']));
        }
        if (!empty($filters['date_debut'])) {
            $where[] = 'so.date_commande >= :date_debut';
            $params['date_debut'] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $where[] = 'so.date_commande <= :date_fin';
            $params['date_fin'] = $filters['date_fin'];
        }
        if (($filters['retard'] ?? null) === '1') {
            $where[] = "so.statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')";
            $where[] = 'so.date_livraison_prevue < CURDATE()';
        }

        $sql = "SELECT so.id, so.numero_commande, so.statut, so.date_commande, so.date_livraison_prevue,
                    so.montant_total, so.observations, so.created_at, so.updated_at,
                    f.nom AS fournisseur_nom, u.username AS utilisateur_nom,
                    CASE WHEN so.date_livraison_prevue < CURDATE()
                        THEN DATEDIFF(CURDATE(), so.date_livraison_prevue) ELSE 0 END AS jours_retard
             FROM supplier_orders so
             JOIN fournisseurs f ON f.id = so.fournisseur_id
             LEFT JOIN utilisateurs u ON u.id = so.utilisateur_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY so.date_livraison_prevue ASC, so.date_commande DESC, so.id DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSupplierOrderStatus(int $orderId, string $status, int $userId): array
    {
        $allowedStatuses = ['BROUILLON', 'ENVOYEE', 'VALIDEE', 'ANNULEE'];
        $status = strtoupper(trim($status));

        if ($orderId <= 0 || !in_array($status, $allowedStatuses, true)) {
            throw new Exception('Commande ou statut invalide.');
        }

        $this->db->beginTransaction();

        try {
            $order = $this->getOrderForUpdate($orderId);
            if (!in_array($order['statut'], ['BROUILLON', 'ENVOYEE'], true)) {
                throw new Exception('Cette commande ne peut plus etre modifiee.');
            }

            $stmt = $this->db->prepare("UPDATE supplier_orders SET statut = :statut, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['statut' => $status, 'id' => $orderId]);

            $this->auditService->logAction($userId, 'UPDATE_SUPPLIER_ORDER_STATUS', 'supplier_orders', $orderId, [
                'statut' => $order['statut'],
            ], [
                'statut' => $status,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Statut de commande mis a jour.',
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getOrderItems(int $orderId): array
    {
        $stmt = $this->db->prepare(
            "SELECT soi.*, p.nom AS produit_nom, p.code_cip
             FROM supplier_order_items soi
             JOIN produits p ON p.id = soi.produit_id
             WHERE soi.supplier_order_id = :id
             ORDER BY p.nom"
        );
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStockMovements(array $filters = [], int $limit = 100): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['date_debut'])) {
            $where[] = 'DATE(ms.date_mouvement) >= :date_debut';
            $params['date_debut'] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $where[] = 'DATE(ms.date_mouvement) <= :date_fin';
            $params['date_fin'] = $filters['date_fin'];
        }
        if (!empty($filters['produit_id'])) {
            $where[] = 'ms.produit_id = :produit_id';
            $params['produit_id'] = (int)$filters['produit_id'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'ms.type_mouvement = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['utilisateur_id'])) {
            $where[] = 'ms.utilisateur_id = :utilisateur_id';
            $params['utilisateur_id'] = (int)$filters['utilisateur_id'];
        }

        $sql = "SELECT ms.*, p.nom AS produit_nom, p.code_cip, u.username AS utilisateur_nom
                FROM mouvements_stock ms
                JOIN produits p ON p.id = ms.produit_id
                LEFT JOIN utilisateurs u ON u.id = ms.utilisateur_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ms.date_mouvement DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countLowStock(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND COALESCE(s.quantite_disponible, 0) <= p.stock_alerte AND COALESCE(s.quantite_disponible, 0) > 0")->fetchColumn();
    }

    private function countOutOfStock(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND COALESCE(s.quantite_disponible, 0) <= 0")->fetchColumn();
    }

    private function countCriticalStock(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND p.stock_securite > 0 AND COALESCE(s.quantite_disponible, 0) <= p.stock_securite AND COALESCE(s.quantite_disponible, 0) > 0")->fetchColumn();
    }

    private function countPendingOrders(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM supplier_orders WHERE statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')")->fetchColumn();
    }

    private function countSuppliers(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL")->fetchColumn();
    }

    private function countTodayReceptions(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM receptions WHERE date_reception = CURDATE()")->fetchColumn();
    }

    private function countRecentReceptions(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM receptions WHERE date_reception >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    }

    private function getStockSummary(): array
    {
        $sql = "SELECT
                    COUNT(p.id) AS total_produits,
                    SUM(CASE WHEN COALESCE(s.quantite_disponible, 0) > 0 THEN 1 ELSE 0 END) AS produits_en_stock,
                    COALESCE(SUM(COALESCE(s.valeur_stock, COALESCE(s.quantite_disponible, 0) * p.prix_achat)), 0) AS valeur_stock
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL";
        $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_produits' => (int)($row['total_produits'] ?? 0),
            'produits_en_stock' => (int)($row['produits_en_stock'] ?? 0),
            'valeur_stock' => (float)($row['valeur_stock'] ?? 0),
        ];
    }

    private function getLowStockProducts(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT p.nom, p.code_cip, p.forme_pharmaceutique, p.stock_alerte, p.stock_securite, COALESCE(s.quantite_disponible, 0) AS quantite_disponible, COALESCE(s.valeur_stock, COALESCE(s.quantite_disponible, 0) * p.prix_achat, 0) AS valeur_stock, c.nom AS categorie
            FROM produits p
            LEFT JOIN stock s ON s.produit_id = p.id
            LEFT JOIN categories c ON c.id = p.categorie_id
            WHERE p.is_actif = 1 AND p.deleted_at IS NULL
            AND COALESCE(s.quantite_disponible, 0) <= p.stock_alerte
            AND COALESCE(s.quantite_disponible, 0) > 0
            ORDER BY COALESCE(s.quantite_disponible, 0) ASC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getOutOfStockProducts(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT p.nom, p.code_cip, p.forme_pharmaceutique, p.stock_alerte, COALESCE(s.quantite_disponible, 0) AS quantite_disponible, COALESCE(s.valeur_stock, COALESCE(s.quantite_disponible, 0) * p.prix_achat, 0) AS valeur_stock, c.nom AS categorie
            FROM produits p
            LEFT JOIN stock s ON s.produit_id = p.id
            LEFT JOIN categories c ON c.id = p.categorie_id
            WHERE p.is_actif = 1 AND p.deleted_at IS NULL
            AND COALESCE(s.quantite_disponible, 0) <= 0
            ORDER BY p.nom
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStockProducts(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT p.nom, p.code_cip, p.prix_achat, p.stock_alerte,
                COALESCE(s.quantite_disponible, 0) AS quantite_disponible,
                COALESCE(s.valeur_stock, COALESCE(s.quantite_disponible, 0) * p.prix_achat, 0) AS valeur_stock,
                COALESCE(c.nom, 'Non classe') AS categorie,
                CASE
                    WHEN COALESCE(s.quantite_disponible, 0) <= 0 THEN 'RUPTURE'
                    WHEN COALESCE(s.quantite_disponible, 0) <= p.stock_alerte THEN 'FAIBLE'
                    ELSE 'OK'
                END AS niveau
            FROM produits p
            LEFT JOIN stock s ON s.produit_id = p.id
            LEFT JOIN categories c ON c.id = p.categorie_id
            WHERE p.is_actif = 1 AND p.deleted_at IS NULL
            ORDER BY
                CASE
                    WHEN COALESCE(s.quantite_disponible, 0) <= 0 THEN 0
                    WHEN COALESCE(s.quantite_disponible, 0) <= p.stock_alerte THEN 1
                    ELSE 2
                END,
                p.nom
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPendingOrders(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT so.id, so.numero_commande, so.statut, so.date_commande, so.date_livraison_prevue, so.montant_total, f.nom AS fournisseur_nom,
                COALESCE(SUM(soi.quantite_commandee - soi.quantite_recue), 0) AS quantite_attendue
            FROM supplier_orders so
            JOIN fournisseurs f ON f.id = so.fournisseur_id
            LEFT JOIN supplier_order_items soi ON soi.supplier_order_id = so.id
            WHERE so.statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
            GROUP BY so.id, so.numero_commande, so.statut, so.date_commande, so.date_livraison_prevue, so.montant_total, f.nom
            ORDER BY so.date_commande DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRecentReceptions(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT r.numero_reception, r.date_reception, r.numero_facture, r.statut, r.ecart_detecte, r.montant_facture, f.nom AS fournisseur_nom
            FROM receptions r
            JOIN fournisseurs f ON f.id = r.fournisseur_id
            WHERE r.date_reception >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY r.date_reception DESC, r.id DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNearAlertThresholdProducts(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT p.nom, p.code_cip, p.stock_alerte, COALESCE(s.quantite_disponible, 0) AS quantite_disponible,
                c.nom AS categorie,
                (COALESCE(s.quantite_disponible, 0) - p.stock_alerte) AS marge
            FROM produits p
            LEFT JOIN stock s ON s.produit_id = p.id
            LEFT JOIN categories c ON c.id = p.categorie_id
            WHERE p.is_actif = 1 AND p.deleted_at IS NULL
            AND p.stock_alerte > 0
            AND COALESCE(s.quantite_disponible, 0) > p.stock_alerte
            AND COALESCE(s.quantite_disponible, 0) <= p.stock_alerte * 1.5
            ORDER BY marge ASC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSupplierSnapshot(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT f.id, f.nom, f.telephone, f.email, f.delai_livraison,
                COUNT(DISTINCT p.id) AS produits,
                COUNT(DISTINCT so.id) AS commandes_attente
            FROM fournisseurs f
            LEFT JOIN produits p ON p.fournisseur_id = f.id AND p.is_actif = 1 AND p.deleted_at IS NULL
            LEFT JOIN supplier_orders so ON so.fournisseur_id = f.id AND so.statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
            WHERE f.is_actif = 1 AND f.deleted_at IS NULL
            GROUP BY f.id, f.nom, f.telephone, f.email, f.delai_livraison
            ORDER BY commandes_attente DESC, f.nom
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getMonthlyStockMovements(string $type): array
    {
        $stmt = $this->db->prepare("SELECT DATE_FORMAT(date_mouvement, '%Y-%m') AS mois, COALESCE(SUM(quantite), 0) AS total
            FROM mouvements_stock
            WHERE type_mouvement = :type
            AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
            GROUP BY DATE_FORMAT(date_mouvement, '%Y-%m')
            ORDER BY mois");
        $stmt->execute(['type' => $type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStockByForme(): array
    {
        $stmt = $this->db->query("SELECT COALESCE(NULLIF(p.forme_pharmaceutique, ''), NULLIF(p.forme, ''), 'Non renseignée') AS forme,
                COALESCE(SUM(s.quantite_disponible), 0) AS total
            FROM produits p
            LEFT JOIN stock s ON s.produit_id = p.id
            WHERE p.is_actif = 1 AND p.deleted_at IS NULL
            GROUP BY COALESCE(NULLIF(p.forme_pharmaceutique, ''), NULLIF(p.forme, ''), 'Non renseignée')
            ORDER BY total DESC
            LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopUsedProducts(): array
    {
        $stmt = $this->db->query("SELECT p.nom, p.code_cip, COALESCE(SUM(ms.quantite), 0) AS total
            FROM mouvements_stock ms
            JOIN produits p ON p.id = ms.produit_id
            WHERE ms.type_mouvement = 'SORTIE'
            AND ms.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY p.id, p.nom, p.code_cip
            ORDER BY total DESC
            LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStockForUpdate(int $produitId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM stock WHERE produit_id = :produit_id FOR UPDATE");
        $stmt->execute(['produit_id' => $produitId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stock) {
            return $stock;
        }

        $stmt = $this->db->prepare("INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, dernier_mouvement) VALUES (:produit_id, 0, 0, 0, NOW())");
        $stmt->execute(['produit_id' => $produitId]);
        return ['quantite_disponible' => 0, 'quantite_theorique' => 0, 'valeur_stock' => 0];
    }

    private function insertStockEntry(int $produitId, int $fournisseurId, int $userId, ?int $receptionId, int $quantite, float $prixAchat, string $dateReception, array $data, int $stockAvant, int $stockApres): int
    {
        $receptionId = isset($data['reception_id']) ? (int)$data['reception_id'] : $receptionId;
        $stmt = $this->db->prepare(
            "INSERT INTO stock_entries (produit_id, fournisseur_id, utilisateur_id, reception_id, quantite, prix_achat, date_reception, numero_facture, observations, stock_avant, stock_apres)
             VALUES (:produit_id, :fournisseur_id, :utilisateur_id, :reception_id, :quantite, :prix_achat, :date_reception, :numero_facture, :observations, :stock_avant, :stock_apres)"
        );
        $stmt->execute([
            'produit_id' => $produitId,
            'fournisseur_id' => $fournisseurId ?: null,
            'utilisateur_id' => $userId,
            'reception_id' => $receptionId ?: null,
            'quantite' => $quantite,
            'prix_achat' => $prixAchat,
            'date_reception' => $dateReception,
            'numero_facture' => trim((string)($data['numero_facture'] ?? '')) ?: null,
            'observations' => trim((string)($data['observations'] ?? '')) ?: null,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres,
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function insertStockMovement(int $produitId, int $quantite, int $avant, int $apres, int $userId, string $referenceType, int $referenceId, string $motif): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_type, reference_id, utilisateur_id, date_mouvement)
             VALUES (:produit_id, 'ENTREE', :quantite, :avant, :apres, :motif, :reference_type, :reference_id, :utilisateur_id, NOW())"
        );
        $stmt->execute([
            'produit_id' => $produitId,
            'quantite' => $quantite,
            'avant' => $avant,
            'apres' => $apres,
            'motif' => $motif,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'utilisateur_id' => $userId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function getOrderForUpdate(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM supplier_orders WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new Exception('Commande fournisseur introuvable.');
        }
        return $order;
    }

    private function getOrderItemForUpdate(int $itemId, int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM supplier_order_items WHERE id = :id AND supplier_order_id = :order_id FOR UPDATE");
        $stmt->execute(['id' => $itemId, 'order_id' => $orderId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new Exception('Ligne commande introuvable.');
        }
        return $item;
    }

    private function isOrderFullyReceived(int $orderId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM supplier_order_items
             WHERE supplier_order_id = :order_id
             AND quantite_recue < quantite_commandee"
        );
        $stmt->execute(['order_id' => $orderId]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public function getSupplierOrder(int $orderId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT so.*, f.nom as fournisseur_nom, u.username as utilisateur_nom
             FROM supplier_orders so
             JOIN fournisseurs f ON f.id = so.fournisseur_id
             LEFT JOIN utilisateurs u ON u.id = so.utilisateur_id
             WHERE so.id = :id"
        );
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateSupplierOrder(int $orderId, array $data, int $userId): array
    {
        $this->db->beginTransaction();

        try {
            $order = $this->getOrderForUpdate($orderId);
            if (!in_array($order['statut'], ['BROUILLON', 'EN_ATTENTE'], true)) {
                throw new Exception('Cette commande ne peut plus etre modifiee.');
            }

            $items = $data['items'] ?? [];
            $remiseGlobale = (float)($data['remise_globale'] ?? $order['remise_globale']);
            $tvaGlobale = (float)($data['tva_globale'] ?? $order['tva_globale']);
            $reference = trim((string)($data['reference_commande'] ?? '')) ?: $order['reference_commande'];

            // Supprimer les anciens items
            $stmt = $this->db->prepare("DELETE FROM supplier_order_items WHERE supplier_order_id = :order_id");
            $stmt->execute(['order_id' => $orderId]);

            // Ajouter les nouveaux items
            $montantHT = 0.0;
            foreach ($items as $item) {
                $produitId = (int)($item['produit_id'] ?? 0);
                $quantite = (int)($item['quantite'] ?? 0);
                $prix = (float)($item['prix_achat'] ?? 0);
                $remise = (float)($item['remise'] ?? 0);
                $tva = (float)($item['tva'] ?? 0);
                
                if ($produitId <= 0 || $quantite <= 0 || $prix <= 0) {
                    throw new Exception('Lignes commande invalides.');
                }
                
                $lineHT = $quantite * $prix;
                $lineRemise = $lineHT * ($remise / 100);
                $lineApresRemise = $lineHT - $lineRemise;
                $lineTVA = $lineApresRemise * ($tva / 100);
                $lineTTC = $lineApresRemise + $lineTVA;
                
                $montantHT += $lineHT;

                $stmt = $this->db->prepare(
                    "INSERT INTO supplier_order_items (supplier_order_id, produit_id, quantite_commandee, prix_achat, montant_total, remise, tva, montant_ht, montant_ttc)
                     VALUES (:order_id, :produit_id, :quantite, :prix, :total, :remise, :tva, :montant_ht, :montant_ttc)"
                );
                $stmt->execute([
                    'order_id' => $orderId,
                    'produit_id' => $produitId,
                    'quantite' => $quantite,
                    'prix' => $prix,
                    'total' => $lineTTC,
                    'remise' => $remise,
                    'tva' => $tva,
                    'montant_ht' => $lineHT,
                    'montant_ttc' => $lineTTC,
                ]);
            }

            // Recalculer les totaux
            $remiseMontant = $montantHT * ($remiseGlobale / 100);
            $montantApresRemise = $montantHT - $remiseMontant;
            $tvaMontant = $montantApresRemise * ($tvaGlobale / 100);
            $montantTTC = $montantApresRemise + $tvaMontant;

            $stmt = $this->db->prepare(
                "UPDATE supplier_orders 
                 SET reference_commande = :reference, remise_globale = :remise_globale, tva_globale = :tva_globale, 
                     montant_ht = :montant_ht, montant_ttc = :montant_ttc, montant_total = :montant_ttc, 
                     date_livraison_prevue = :date_livraison, observations = :observations, updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                'reference' => $reference,
                'remise_globale' => $remiseGlobale,
                'tva_globale' => $tvaGlobale,
                'montant_ht' => $montantHT,
                'montant_ttc' => $montantTTC,
                'montant_ttc' => $montantTTC,
                'date_livraison' => $data['date_livraison_prevue'] ?? $order['date_livraison_prevue'],
                'observations' => trim((string)($data['observations'] ?? '')) ?: null,
                'id' => $orderId,
            ]);

            $this->auditService->logAction($userId, 'UPDATE_SUPPLIER_ORDER', 'supplier_orders', $orderId, [
                'remise_globale' => $order['remise_globale'],
                'tva_globale' => $order['tva_globale'],
            ], [
                'remise_globale' => $remiseGlobale,
                'tva_globale' => $tvaGlobale,
                'montant_ht' => $montantHT,
                'montant_ttc' => $montantTTC,
            ]);

            $this->db->commit();

            return ['success' => true, 'message' => 'Commande mise a jour avec succes.'];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function duplicateSupplierOrder(int $orderId, int $userId): array
    {
        $this->db->beginTransaction();

        try {
            $order = $this->getSupplierOrder($orderId);
            if (!$order) {
                throw new Exception('Commande introuvable.');
            }

            $items = $this->getOrderItems($orderId);
            
            $newNumero = $this->generateNumber('CC');
            $newReference = trim((string)($order['reference_commande'] ?? '')) ? $order['reference_commande'] . '-COPIE' : null;

            $stmt = $this->db->prepare(
                "INSERT INTO supplier_orders (numero_commande, reference_commande, fournisseur_id, utilisateur_id, date_commande, date_livraison_prevue, statut, observations, remise_globale, tva_globale, montant_ht, montant_ttc)
                 VALUES (:numero, :reference, :fournisseur_id, :utilisateur_id, :date_commande, :date_livraison_prevue, 'BROUILLON', :observations, :remise_globale, :tva_globale, :montant_ht, :montant_ttc)"
            );
            $stmt->execute([
                'numero' => $newNumero,
                'reference' => $newReference,
                'fournisseur_id' => $order['fournisseur_id'],
                'utilisateur_id' => $userId,
                'date_commande' => date('Y-m-d'),
                'date_livraison_prevue' => null,
                'observations' => 'Copie de la commande ' . $order['numero_commande'],
                'remise_globale' => $order['remise_globale'],
                'tva_globale' => $order['tva_globale'],
                'montant_ht' => $order['montant_ht'],
                'montant_ttc' => $order['montant_ttc'],
            ]);
            $newOrderId = (int)$this->db->lastInsertId();

            foreach ($items as $item) {
                $stmt = $this->db->prepare(
                    "INSERT INTO supplier_order_items (supplier_order_id, produit_id, quantite_commandee, prix_achat, montant_total, remise, tva, montant_ht, montant_ttc)
                     VALUES (:order_id, :produit_id, :quantite, :prix, :total, :remise, :tva, :montant_ht, :montant_ttc)"
                );
                $stmt->execute([
                    'order_id' => $newOrderId,
                    'produit_id' => $item['produit_id'],
                    'quantite' => $item['quantite_commandee'],
                    'prix' => $item['prix_achat'],
                    'total' => $item['montant_total'],
                    'remise' => $item['remise'],
                    'tva' => $item['tva'],
                    'montant_ht' => $item['montant_ht'],
                    'montant_ttc' => $item['montant_ttc'],
                ]);
            }

            $this->auditService->logAction($userId, 'DUPLICATE_SUPPLIER_ORDER', 'supplier_orders', $newOrderId, null, [
                'original_order_id' => $orderId,
                'original_numero' => $order['numero_commande'],
                'new_numero' => $newNumero,
            ]);

            $this->db->commit();

            return ['success' => true, 'message' => 'Commande dupliquee avec succes.', 'order_id' => $newOrderId, 'numero' => $newNumero];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function cancelSupplierOrder(int $orderId, string $motif, int $userId): array
    {
        $this->db->beginTransaction();

        try {
            $order = $this->getOrderForUpdate($orderId);
            if (!in_array($order['statut'], ['BROUILLON', 'EN_ATTENTE', 'ENVOYEE'], true)) {
                throw new Exception('Cette commande ne peut plus etre annulee.');
            }

            $stmt = $this->db->prepare("UPDATE supplier_orders SET statut = 'ANNULEE', updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $orderId]);

            $this->auditService->logAction($userId, 'CANCEL_SUPPLIER_ORDER', 'supplier_orders', $orderId, [
                'statut' => $order['statut'],
            ], [
                'statut' => 'ANNULEE',
                'motif' => $motif,
            ]);

            $this->db->commit();

            return ['success' => true, 'message' => 'Commande annulee avec succes.'];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix . date('YmdHis') . random_int(100, 999);
    }

    private function isValidDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function enregistrerDetteFournisseurReception(int $receptionId, int $fournisseurId, float $montant, int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute(['fournisseur_reglements']);
        if ((int)$stmt->fetchColumn() === 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO fournisseur_reglements
             (fournisseur_id, reception_id, type_mouvement, montant, reference, notes, utilisateur_id, date_mouvement)
             VALUES (:fournisseur_id, :reception_id, "DEBIT", :montant, :reference, :notes, :user_id, NOW())'
        );
        $stmt->execute([
            'fournisseur_id' => $fournisseurId,
            'reception_id' => $receptionId,
            'montant' => $montant,
            'reference' => $data['numero_facture'] ?? null,
            'notes' => 'Facture fournisseur liée à la réception',
            'user_id' => $userId,
        ]);
    }

    private function getProductsToOrder(int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.nom,
                p.code_cip,
                p.stock_alerte,
                p.stock_securite,
                p.stock_minimum,
                COALESCE(s.quantite_disponible, 0) AS stock_actuel,
                COALESCE(s.quantite_disponible, 0) AS stock_disponible,
                f.nom AS fournisseur_nom,
                f.id AS fournisseur_id,
                COALESCE(SUM(CASE WHEN so.id IS NOT NULL THEN soi.quantite_commandee - soi.quantite_recue ELSE 0 END), 0) AS quantite_commandee
            FROM produits p
            LEFT JOIN stock s ON s.produit_id = p.id
            LEFT JOIN fournisseurs f ON f.id = p.fournisseur_id
            LEFT JOIN supplier_order_items soi ON soi.produit_id = p.id
            LEFT JOIN supplier_orders so ON so.id = soi.supplier_order_id 
                AND so.statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
            WHERE p.is_actif = 1 
                AND p.deleted_at IS NULL
                AND p.stock_alerte > 0
            GROUP BY p.id, p.nom, p.code_cip, p.stock_alerte, p.stock_securite, p.stock_minimum, 
                     s.quantite_disponible, f.nom, f.id
            HAVING (stock_disponible + quantite_commandee) < stock_alerte
            ORDER BY (stock_disponible + quantite_commandee) ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$product) {
            $stockDisponible = (int)$product['stock_disponible'];
            $stockAlerte = (int)$product['stock_alerte'];
            $quantiteCommandee = (int)$product['quantite_commandee'];
            $product['besoin_estime'] = max(0, $stockAlerte - $stockDisponible - $quantiteCommandee);
        }

        return $products;
    }

    private function countExpiringSoon(int $days): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM lots l
            JOIN produits p ON p.id = l.produit_id
            WHERE p.is_actif = 1
                AND p.deleted_at IS NULL
                AND l.is_actif = 1
                AND l.quantite_restante > 0
                AND l.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function countLateOrders(): int
    {
        return (int)$this->db->query("
            SELECT COUNT(*)
            FROM supplier_orders
            WHERE statut IN ('EN_ATTENTE', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
                AND date_livraison_prevue < CURDATE()
        ")->fetchColumn();
    }
}
