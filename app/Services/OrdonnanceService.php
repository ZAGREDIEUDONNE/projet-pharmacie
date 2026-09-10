<?php

namespace App\Services;

use App\Models\Ordonnance;
use PDO;

class OrdonnanceService
{
    private Ordonnance $ordonnance;

    public function __construct(PDO $db, private StockService $stockService, private AuditService $auditService)
    {
        $this->ordonnance = new Ordonnance($db);
    }

    public function lister(array $filtres): array
    {
        return array_map(fn(array $o) => $this->ajouterStatut($o), $this->ordonnance->rechercher($filtres));
    }

    public function consulter(int $id): ?array
    {
        $ordonnance = $this->ordonnance->trouver($id);
        if (!$ordonnance) {
            return null;
        }
        $ordonnance = $this->ajouterStatut($ordonnance);
        $ordonnance['produits'] = $this->ordonnance->produitsEtVentes($id);
        foreach ($ordonnance['produits'] as &$produit) {
            $produit['stock'] = $this->stockService->verifierDisponibilite((int)$produit['produit_id'], 1);
        }
        unset($produit);
        return $ordonnance;
    }

    public function tracerConsultation(int $utilisateurId, int $ordonnanceId): void
    {
        $this->auditService->logAction($utilisateurId, 'VIEW_ORDONNANCE', 'ordonnances', $ordonnanceId, null, null);
    }

    private function ajouterStatut(array $ordonnance): array
    {
        // Le schéma ne stocke aucun statut d'ordonnance : il est donc déduit des ventes liées.
        $nombreVentes = (int)($ordonnance['nombre_ventes'] ?? 0);
        $nombreVentesActives = (int)($ordonnance['nombre_ventes_actives'] ?? 0);
        $ordonnance['statut'] = $nombreVentesActives > 0 ? 'DISPENSEE' : ($nombreVentes > 0 ? 'ANNULEE' : 'EN_ATTENTE');
        return $ordonnance;
    }
}
