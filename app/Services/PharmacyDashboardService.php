<?php

namespace App\Services;

use PDO;

/**
 * Indicateurs métier pour le tableau de bord pharmacie.
 */
class PharmacyDashboardService
{
    private PDO $db;
    private StockFluxService $stockFlux;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->stockFlux = new StockFluxService($db);
    }

    public function getIndicateurs(): array
    {
        return [
            'ca_jour' => $this->getCaPeriode('CURDATE()', 'CURDATE()'),
            'ca_mois' => $this->getCaPeriode('DATE_FORMAT(CURDATE(), "%Y-%m-01")', 'CURDATE()'),
            'ventes_jour' => $this->countVentes('CURDATE()', 'CURDATE()'),
            'ventes_mois' => $this->countVentes('DATE_FORMAT(CURDATE(), "%Y-%m-01")', 'CURDATE()'),
            'produits_rupture' => count($this->stockFlux->getProduitsRupture()),
            'produits_alerte' => count($this->stockFlux->getProduitsAlerte()),
            'valeur_stock' => $this->stockFlux->getValeurGlobale(),
            'valeur_par_categorie' => $this->stockFlux->getValeurParCategorie(),
            'clients_debiteurs' => $this->countClientsDebiteurs(),
            'fournisseurs_a_payer' => $this->countFournisseursAPayer(),
            'ventes_par_periode' => $this->getVentes7Jours(),
            'mouvements_recents' => $this->stockFlux->getHistoriqueMouvements([], 10),
            'ruptures' => array_slice($this->stockFlux->getProduitsRupture(), 0, 8),
            'alertes' => array_slice($this->stockFlux->getProduitsAlerte(), 0, 8),
        ];
    }

    private function getCaPeriode(string $dateDebut, string $dateFin): float
    {
        $sql = "SELECT COALESCE(SUM(montant_net), 0)
                FROM ventes
                WHERE statut_vente != 'ANNULEE'
                AND DATE(date_vente) BETWEEN {$dateDebut} AND {$dateFin}";
        return (float)$this->db->query($sql)->fetchColumn();
    }

    private function countVentes(string $dateDebut, string $dateFin): int
    {
        $sql = "SELECT COUNT(*)
                FROM ventes
                WHERE statut_vente != 'ANNULEE'
                AND DATE(date_vente) BETWEEN {$dateDebut} AND {$dateFin}";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    private function countClientsDebiteurs(): int
    {
        $sql = 'SELECT COUNT(DISTINCT c.id)
                FROM clients c
                LEFT JOIN ventes v ON v.client_id = c.id AND v.is_credit = 1 AND v.statut_vente != "ANNULEE"
                WHERE c.deleted_at IS NULL
                AND (c.solde_credit > 0 OR COALESCE(v.montant_restant, 0) > 0)';
        return (int)$this->db->query($sql)->fetchColumn();
    }

    private function countFournisseursAPayer(): int
    {
        if (!$this->columnExists('receptions', 'montant_facture')) {
            return 0;
        }

        $sql = 'SELECT COUNT(DISTINCT f.id)
                FROM fournisseurs f
                JOIN receptions r ON r.fournisseur_id = f.id
                WHERE f.deleted_at IS NULL AND r.montant_facture > 0';
        return (int)$this->db->query($sql)->fetchColumn();
    }

    private function getVentes7Jours(): array
    {
        $sql = "SELECT DATE(date_vente) AS jour,
                       COUNT(*) AS nb_ventes,
                       COALESCE(SUM(montant_net), 0) AS ca
                FROM ventes
                WHERE statut_vente != 'ANNULEE'
                AND date_vente >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(date_vente)
                ORDER BY jour ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
