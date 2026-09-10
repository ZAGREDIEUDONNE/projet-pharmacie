<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Facade comptable : delegue au moteur SYSCOHADA (EcritureComptableService).
 * Conserve l'API historique pour VenteService et les controleurs existants.
 */
class ComptabiliteService
{
    private PDO $db;
    private AuditService $auditService;
    private EcritureComptableService $ecritureService;
    private JournalComptableService $journalService;

    public function __construct(PDO $db, AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
        $journalService = new JournalComptableService($db, $auditService);
        $this->ecritureService = new EcritureComptableService($db, $auditService, $journalService);
        $this->journalService = $journalService;
    }

    /**
     * Initialise plan + journaux avant une transaction metier.
     */
    public function prepareEcritures(): void
    {
        $this->journalService->ensureComptabiliteReady();
    }

    public function genererEcritureVente(int $venteId): array
    {
        return $this->ecritureService->genererEcritureVenteParId($venteId);
    }

    public function genererEcritureAchat(int $commandeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, f.nom AS fournisseur_nom
             FROM commandes c
             LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
             WHERE c.id = ?"
        );
        $stmt->execute([$commandeId]);
        $achat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$achat) {
            throw new Exception('Commande introuvable pour ecriture comptable');
        }

        return $this->ecritureService->genererEcrituresAchat($achat);
    }

    public function annulerEcritureVente(int $venteId, int $utilisateurId): void
    {
        $this->ecritureService->annulerEcritureVente($venteId, $utilisateurId);
    }

    public function ajusterEcritureVente(int $venteId, int $utilisateurId): array
    {
        return $this->ecritureService->ajusterEcritureVente($venteId, $utilisateurId);
    }

    public function genererBilan(string $dateFin): array
    {
        $service = new EtatsFinanciersService($this->db, $this->auditService);
        $bilan = $service->genererBilan($dateFin);

        return $bilan['lignes_bilan'] ?? [];
    }

    public function genererCompteResultat(string $dateDebut, string $dateFin): array
    {
        $service = new EtatsFinanciersService($this->db, $this->auditService);
        $resultat = $service->genererCompteResultat($dateDebut, $dateFin);

        return $resultat['lignes_resultat'] ?? [];
    }
}
