<?php

namespace App\Services;

use Exception;
use PDO;

class AssistantDashboardService
{
    public function __construct(private PDO $db) {}

    /** Données strictement nécessaires au dashboard Assistant. */
    public function getOverview(int $userId): array
    {
        $sales = $this->getTodaySalesSummary();
        $alerts = $this->getActionAlerts();

        return [
            'widgets' => [
                'ca_jour' => $sales['total'],
                'ventes_jour' => $sales['count'],
                'montant_encaisse' => $sales['paid'],
                'alertes' => array_sum(array_column($alerts, 'count')),
            ],
            'cash_register' => $this->getCashRegisterSummary($userId),
            'alerts' => $alerts,
            'recent_sales' => $this->getRecentSales(),
        ];
    }

    private function getTodaySalesSummary(): array
    {
        if (!$this->tableExists('ventes')) return ['count' => 0, 'total' => 0.0, 'paid' => 0.0];
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(montant_net), 0) AS total, COALESCE(SUM(montant_paye), 0) AS paid FROM ventes WHERE deleted_at IS NULL AND statut_vente != 'ANNULEE' AND DATE(date_vente) = CURDATE()");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return ['count' => (int)($row['count'] ?? 0), 'total' => (float)($row['total'] ?? 0), 'paid' => (float)($row['paid'] ?? 0)];
        } catch (Exception $e) {
            error_log('AssistantDashboardService::getTodaySalesSummary - ' . $e->getMessage());
            return ['count' => 0, 'total' => 0.0, 'paid' => 0.0];
        }
    }

    private function getActionAlerts(): array
    {
        $alerts = [];
        $lowStock = $this->countLowStock();
        if ($lowStock > 0) $alerts[] = ['label' => 'Stock faible', 'count' => $lowStock, 'url' => '/stock/alerts?return_to=/assistant/dashboard'];
        $expired = $this->countExpiredProducts();
        if ($expired > 0) $alerts[] = ['label' => 'Produits expirés', 'count' => $expired, 'url' => '/stock/alerts?return_to=/assistant/dashboard'];
        return $alerts;
    }

    private function countLowStock(): int
    {
        if (!$this->tableExists('produits') || !$this->tableExists('stock')) return 0;
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM produits p LEFT JOIN stock s ON s.produit_id = p.id WHERE p.is_actif = 1 AND p.deleted_at IS NULL AND COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0)");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('AssistantDashboardService::countLowStock - ' . $e->getMessage());
            return 0;
        }
    }

    private function countExpiredProducts(): int
    {
        if (!$this->tableExists('lots')) return 0;
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM lots WHERE is_actif = 1 AND quantite_restante > 0 AND date_peremption < CURDATE()");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('AssistantDashboardService::countExpiredProducts - ' . $e->getMessage());
            return 0;
        }
    }

    private function getCashRegisterSummary(int $userId): array
    {
        if ($userId <= 0 || !$this->tableExists('caisse_sessions')) return ['open' => false];
        try {
            $sql = "SELECT 1 FROM caisse_sessions WHERE caissier_id = :user_id AND statut_session = 'OUVERTE' ORDER BY date_ouverture DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['open' => (bool)$session];
        } catch (Exception $e) {
            error_log('AssistantDashboardService::getCashRegisterSummary - ' . $e->getMessage());
            return ['open' => false];
        }
    }

    private function getRecentSales(int $limit = 6): array
    {
        if (!$this->tableExists('ventes')) return [];
        try {
            $stmt = $this->db->prepare("SELECT v.id, v.numero_facture, v.date_vente, v.montant_net, v.statut_vente, COALESCE(CONCAT(NULLIF(c.prenom, ''), ' ', c.nom), c.nom, 'Client comptoir') AS client_nom FROM ventes v LEFT JOIN clients c ON c.id = v.client_id WHERE v.deleted_at IS NULL AND v.statut_vente != 'ANNULEE' ORDER BY v.date_vente DESC LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('AssistantDashboardService::getRecentSales - ' . $e->getMessage());
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));
            return $stmt && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("AssistantDashboardService::tableExists({$table}) - " . $e->getMessage());
            return false;
        }
    }
}
