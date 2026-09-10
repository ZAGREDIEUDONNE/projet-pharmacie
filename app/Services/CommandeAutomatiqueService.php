<?php

namespace App\Services;

use PDO;
use Exception;

class CommandeAutomatiqueService
{
    private const AUTO_ORDER_MARKER = 'Commande automatique';

    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db, ?AuditService $auditService = null)
    {
        $this->db = $db;
        $this->auditService = $auditService ?? new AuditService($db);
    }

    /**
     * Genere les commandes fournisseurs automatiques a partir du stock.
     */
    public function genererCommandesAutomatiques(int $userId): array
    {
        if ($userId <= 0) {
            throw new Exception('Utilisateur invalide pour la generation automatique.');
        }

        $this->db->beginTransaction();

        try {
            $produits = $this->identifierProduitsACommander();
            $ignores = [
                'sans_fournisseur' => 0,
                'commande_manuelle_active' => 0,
                'deja_dans_commande_auto' => 0,
            ];

            if (empty($produits)) {
                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Aucun produit ne necessite une commande automatique',
                    'data' => [],
                    'ignores' => $ignores,
                ];
            }

            $parFournisseur = $this->regrouperParFournisseur($produits);
            $resume = [];

            foreach ($parFournisseur as $fournisseurId => $groupe) {
                if ($this->hasManualActiveOrder((int)$fournisseurId)) {
                    $ignores['commande_manuelle_active'] += count($groupe['produits']);
                    continue;
                }

                $produitsEligibles = $this->filtrerProduitsEligibles((int)$fournisseurId, $groupe['produits'], $ignores);
                if (empty($produitsEligibles)) {
                    continue;
                }

                $commandeId = $this->upsertAutomaticOrder((int)$fournisseurId, $produitsEligibles, $userId);
                $resume[] = [
                    'fournisseur' => (string)$groupe['fournisseur_nom'],
                    'fournisseur_id' => (int)$fournisseurId,
                    'produits' => count($produitsEligibles),
                    'commande_id' => $commandeId,
                ];
            }

            $this->auditService->logAction(
                $userId,
                'GENERATION_COMMANDES_AUTOMATIQUES',
                'supplier_orders',
                null,
                null,
                [
                    'commandes' => $resume,
                    'ignores' => $ignores,
                ]
            );

            $this->db->commit();

            if (empty($resume)) {
                return [
                    'success' => true,
                    'message' => 'Aucune nouvelle commande automatique a creer',
                    'data' => [],
                    'ignores' => $ignores,
                ];
            }

            return [
                'success' => true,
                'message' => 'Commandes automatiques generees avec succes',
                'data' => $resume,
                'ignores' => $ignores,
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception('Erreur lors de la generation des commandes automatiques: ' . $e->getMessage());
        }
    }

    /**
     * Apercu des produits sous seuil avant generation.
     */
    public function getApercuProduitsACommander(): array
    {
        $produits = $this->identifierProduitsACommander();

        return array_map(function (array $produit): array {
            return [
                'produit_id' => (int)$produit['produit_id'],
                'produit_nom' => (string)$produit['produit_nom'],
                'code_cip' => (string)($produit['code_cip'] ?? ''),
                'fournisseur_nom' => (string)($produit['fournisseur_nom'] ?? ''),
                'quantite_disponible' => (int)$produit['quantite_disponible'],
                'stock_alerte' => (int)$produit['stock_alerte'],
                'quantite' => $this->calculerQuantiteCommande($produit),
                'niveau_stock' => (string)$produit['niveau_stock'],
            ];
        }, $produits);
    }

    public function analyserCommandesAutomatiques(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->db->prepare(
            "SELECT so.id, so.numero_commande, so.date_commande, so.date_livraison_prevue,
                    so.montant_total, so.statut, f.nom AS fournisseur_nom,
                    COUNT(soi.id) AS nombre_produits,
                    COALESCE(SUM(soi.quantite_commandee), 0) AS quantite_totale
             FROM supplier_orders so
             JOIN fournisseurs f ON f.id = so.fournisseur_id
             LEFT JOIN supplier_order_items soi ON soi.supplier_order_id = so.id
             WHERE so.date_commande BETWEEN :debut AND :fin
             AND so.observations LIKE :marker
             GROUP BY so.id, so.numero_commande, so.date_commande, so.date_livraison_prevue,
                      so.montant_total, so.statut, f.nom
             ORDER BY so.date_commande DESC, so.id DESC"
        );
        $stmt->execute([
            'debut' => $dateDebut,
            'fin' => $dateFin,
            'marker' => '%' . self::AUTO_ORDER_MARKER . '%',
        ]);

        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'commandes' => $commandes,
            'produits_a_commander' => $this->getApercuProduitsACommander(),
            'statistiques' => [
                'total_commandes' => count($commandes),
                'valeur_totale' => array_sum(array_map(static fn(array $row): float => (float)($row['montant_total'] ?? 0), $commandes)),
                'total_produits' => array_sum(array_map(static fn(array $row): int => (int)($row['nombre_produits'] ?? 0), $commandes)),
            ],
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
        ];
    }

    private function identifierProduitsACommander(): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                p.id AS produit_id,
                p.nom AS produit_nom,
                p.code_cip,
                p.prix_achat,
                p.stock_securite,
                p.stock_alerte,
                p.fournisseur_id,
                f.nom AS fournisseur_nom,
                f.delai_livraison,
                COALESCE(s.quantite_disponible, 0) AS quantite_disponible,
                CASE
                    WHEN COALESCE(s.quantite_disponible, 0) <= 0 THEN 'RUPTURE'
                    WHEN COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_securite, 0), p.stock_alerte) THEN 'CRITIQUE'
                    ELSE 'ALERTE'
                END AS niveau_stock
             FROM produits p
             LEFT JOIN stock s ON s.produit_id = p.id
             LEFT JOIN fournisseurs f ON f.id = p.fournisseur_id
             WHERE p.is_actif = 1
             AND p.deleted_at IS NULL
             AND p.fournisseur_id IS NOT NULL
             AND COALESCE(f.is_actif, 1) = 1
             AND COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_alerte, 0), 1)
             ORDER BY quantite_disponible ASC, p.nom ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function regrouperParFournisseur(array $produits): array
    {
        $groupes = [];

        foreach ($produits as $produit) {
            $fournisseurId = (int)($produit['fournisseur_id'] ?? 0);
            if ($fournisseurId <= 0) {
                continue;
            }

            if (!isset($groupes[$fournisseurId])) {
                $groupes[$fournisseurId] = [
                    'fournisseur_id' => $fournisseurId,
                    'fournisseur_nom' => (string)($produit['fournisseur_nom'] ?? 'Fournisseur'),
                    'produits' => [],
                ];
            }

            $groupes[$fournisseurId]['produits'][] = $produit;
        }

        return $groupes;
    }

    private function filtrerProduitsEligibles(int $fournisseurId, array $produits, array &$ignores): array
    {
        $eligibles = [];
        $produitsDejaCommandes = $this->getProduitsDansCommandeAutoActive($fournisseurId);

        foreach ($produits as $produit) {
            $produitId = (int)($produit['produit_id'] ?? 0);
            if ($produitId <= 0) {
                continue;
            }

            if (in_array($produitId, $produitsDejaCommandes, true)) {
                $ignores['deja_dans_commande_auto']++;
                continue;
            }

            $eligibles[] = $produit;
        }

        return $eligibles;
    }

    private function upsertAutomaticOrder(int $fournisseurId, array $produits, int $userId): int
    {
        $commande = $this->getAutomaticDraftOrder($fournisseurId);
        $commandeId = $commande ? (int)$commande['id'] : $this->createAutomaticOrderHeader($fournisseurId, $produits, $userId);

        foreach ($produits as $produit) {
            $quantite = $this->calculerQuantiteCommande($produit);
            $prixAchat = (float)($produit['prix_achat'] ?? 0);
            if ($quantite <= 0 || $prixAchat <= 0) {
                continue;
            }

            $montantLigne = round($quantite * $prixAchat, 2);
            $stmt = $this->db->prepare(
                "INSERT INTO supplier_order_items (
                    supplier_order_id, produit_id, quantite_commandee, quantite_recue, prix_achat, montant_total
                ) VALUES (?, ?, ?, 0, ?, ?)"
            );
            $stmt->execute([$commandeId, (int)$produit['produit_id'], $quantite, $prixAchat, $montantLigne]);

            $this->auditService->logAction(
                $userId,
                'AJOUT_PRODUIT_COMMANDE_AUTO',
                'supplier_order_items',
                (int)$this->db->lastInsertId(),
                null,
                [
                    'supplier_order_id' => $commandeId,
                    'produit_id' => (int)$produit['produit_id'],
                    'quantite' => $quantite,
                    'prix_achat' => $prixAchat,
                    'motif' => (string)($produit['niveau_stock'] ?? 'ALERTE'),
                ]
            );
        }

        $this->recalculateOrderTotal($commandeId);

        return $commandeId;
    }

    private function createAutomaticOrderHeader(int $fournisseurId, array $produits, int $userId): int
    {
        $delai = max(1, (int)($produits[0]['delai_livraison'] ?? 7));
        $stmt = $this->db->prepare(
            "INSERT INTO supplier_orders (
                numero_commande, fournisseur_id, utilisateur_id, date_commande,
                date_livraison_prevue, statut, montant_total, observations
            ) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'BROUILLON', 0, ?)"
        );
        $stmt->execute([
            $this->genererNumeroCommande(),
            $fournisseurId,
            $userId,
            $delai,
            self::AUTO_ORDER_MARKER . ' generee le ' . date('d/m/Y H:i'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function calculerQuantiteCommande(array $produit): int
    {
        $stockActuel = (int)($produit['quantite_disponible'] ?? 0);
        $stockMax = (int)($produit['stock_max'] ?? $produit['stock_securite'] ?? 0);
        $seuilAlerte = max(1, (int)($produit['stock_alerte'] ?? 1));

        if ($stockMax > $stockActuel) {
            return max(1, $stockMax - $stockActuel);
        }

        return max(1, $seuilAlerte * 2);
    }

    private function hasManualActiveOrder(int $fournisseurId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM supplier_orders
             WHERE fournisseur_id = ?
             AND statut IN ('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
             AND (observations IS NULL OR observations NOT LIKE ?)
             LIMIT 1"
        );
        $stmt->execute([$fournisseurId, '%' . self::AUTO_ORDER_MARKER . '%']);

        return (bool)$stmt->fetchColumn();
    }

    private function getAutomaticDraftOrder(int $fournisseurId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM supplier_orders
             WHERE fournisseur_id = ?
             AND statut = 'BROUILLON'
             AND observations LIKE ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$fournisseurId, '%' . self::AUTO_ORDER_MARKER . '%']);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        return $order ?: null;
    }

    private function getProduitsDansCommandeAutoActive(int $fournisseurId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT soi.produit_id
             FROM supplier_orders so
             JOIN supplier_order_items soi ON soi.supplier_order_id = so.id
             WHERE so.fournisseur_id = ?
             AND so.statut IN ('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
             AND so.observations LIKE ?"
        );
        $stmt->execute([$fournisseurId, '%' . self::AUTO_ORDER_MARKER . '%']);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function recalculateOrderTotal(int $commandeId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE supplier_orders
             SET montant_total = (
                 SELECT COALESCE(SUM(montant_total), 0)
                 FROM supplier_order_items
                 WHERE supplier_order_id = ?
             ),
             updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$commandeId, $commandeId]);
    }

    private function genererNumeroCommande(): string
    {
        $prefix = 'CA' . date('Ymd');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM supplier_orders WHERE numero_commande LIKE ?"
        );
        $stmt->execute([$prefix . '%']);
        $count = (int)$stmt->fetchColumn();

        return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
