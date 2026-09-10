<?php

namespace App\Services;

use App\Models\CaisseSession;
use App\Models\MouvementCaisse;
use App\Models\Vente;
use App\Services\AuditService;
use PDO;
use PDOException;
use Exception;

class CaisseService
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
            $this->ecritureService = new EcritureComptableService($this->db, $this->auditService, $this->getJournalComptableService());
        }

        return $this->ecritureService;
    }

    private function getJournalComptableService(): JournalComptableService
    {
        return new JournalComptableService($this->db, $this->auditService);
    }

    /**
     * Ouvre une nouvelle session de caisse
     */
    public function ouvrirSession(array $data): array
    {
        // Complete un plan comptable incomplet avant d'ouvrir la transaction.
        $this->getJournalComptableService()->ensureComptabiliteReady();
        $this->db->beginTransaction();
        
        try {
            // Vérifier qu'il n'y a pas déjà une session ouverte pour ce caissier
            $sessionExistante = $this->getSessionOuverte($data['caissier_id']);
            if ($sessionExistante) {
                throw new Exception("Une session de caisse est déjà ouverte pour ce caissier");
            }
            
            // Générer un numéro de session
            $numeroSession = $this->genererNumeroSession();
            
            // Créer la session
            $sql = "INSERT INTO caisse_sessions (
                        numero_session, 
                        caissier_id, 
                        date_ouverture, 
                        montant_ouverture,
                        statut_session
                    ) VALUES (?, ?, NOW(), ?, 'OUVERTE')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $numeroSession,
                $data['caissier_id'],
                $data['montant_ouverture'] ?? 0
            ]);
            
            $sessionId = $this->db->lastInsertId();
            
            // Enregistrer le mouvement d'ouverture de caisse
            if ($data['montant_ouverture'] > 0) {
                $this->enregistrerMouvement([
                    'caisse_session_id' => $sessionId,
                    'type_mouvement' => 'OUVERTURE_CAISSE',
                    'montant' => $data['montant_ouverture'],
                    'moyen_paiement' => 'ESPECE',
                    'reference' => 'OUVERTURE_SESSION',
                    'description' => 'Ouverture de caisse session ' . $numeroSession,
                    'utilisateur_id' => $data['caissier_id']
                ]);
            }
            
            // Logger l'action
            $this->auditService->logAction(
                $data['caissier_id'],
                'OUVRIR_SESSION_CAISSE',
                'caisse_sessions',
                $sessionId,
                null,
                [
                    'numero_session' => $numeroSession,
                    'montant_ouverture' => $data['montant_ouverture'] ?? 0
                ]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'numero_session' => $numeroSession,
                'message' => 'Session de caisse ouverte avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de l'ouverture de la session: " . $e->getMessage());
        }
    }

    /**
     * Ferme une session de caisse
     */
    public function fermerSession(int $sessionId, array $data): array
    {
        $this->db->beginTransaction();
        
        try {
            // Récupérer les détails de la session
            $session = $this->getSessionDetails($sessionId);
            
            if ($session['statut_session'] !== 'OUVERTE') {
                throw new Exception("Cette session n'est pas ouverte");
            }
            
            // Calculer le montant théorique
            $montantTheorique = $this->calculerMontantTheorique($sessionId);
            
            // Mettre à jour la session
            $sql = "UPDATE caisse_sessions SET 
                        date_fermeture = NOW(),
                        montant_fermeture = ?,
                        montant_theorique = ?,
                        montant_ventes = ?,
                        ecart = ? - ?,
                        statut_session = 'FERMEE',
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['montant_fermeture'],
                $montantTheorique,
                $session['montant_ventes'],
                $data['montant_fermeture'],
                $montantTheorique,
                $sessionId
            ]);
            
            // Enregistrer le mouvement de fermeture de caisse
            $montantRetrait = $data['montant_fermeture'] - $session['montant_ouverture'];
            if ($montantRetrait > 0) {
                $this->enregistrerMouvement([
                    'caisse_session_id' => $sessionId,
                    'type_mouvement' => 'FERMETURE_CAISSE',
                    'montant' => $montantRetrait,
                    'moyen_paiement' => 'ESPECE',
                    'reference' => 'FERMETURE_SESSION',
                    'description' => 'Fermeture de caisse session ' . $session['numero_session'],
                    'utilisateur_id' => $data['utilisateur_id']
                ]);
            }
            
            // Logger l'action
            $this->auditService->logAction(
                $data['utilisateur_id'],
                'FERMER_SESSION_CAISSE',
                'caisse_sessions',
                $sessionId,
                $session,
                [
                    'montant_fermeture' => $data['montant_fermeture'],
                    'montant_theorique' => $montantTheorique,
                    'ecart' => $data['montant_fermeture'] - $montantTheorique
                ]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'montant_theorique' => $montantTheorique,
                'ecart' => $data['montant_fermeture'] - $montantTheorique,
                'message' => 'Session fermée avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la fermeture de la session: " . $e->getMessage());
        }
    }

    /**
     * Enregistre un mouvement de caisse
     */
    public function enregistrerMouvement(array $data): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Calculer le solde avant
            $soldeAvant = $this->calculerSoldeSession($data['caisse_session_id']);
            
            // Déterminer si c'est une entrée ou une sortie
            $typesEntree = ['VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE'];
            $typesSortie = ['REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE'];
            
            $isEntree = in_array($data['type_mouvement'], $typesEntree);
            $isSortie = in_array($data['type_mouvement'], $typesSortie);
            
            // Calculer le solde après
            $soldeApres = $soldeAvant;
            if ($isEntree) {
                $soldeApres += $data['montant'];
            } elseif ($isSortie) {
                $soldeApres -= $data['montant'];
            }
            
        $sql = "INSERT INTO mouvements_caisse (
                    caisse_session_id,
                    type_mouvement,
                    montant,
                    solde_avant,
                    solde_apres,
                    moyen_paiement,
                    reference,
                    description,
                    utilisateur_id" . 
                    (isset($data['vente_id']) ? ", vente_id" : "") . "
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?" . 
                    (isset($data['vente_id']) ? ", ?" : "") . ")";
        
        $params = [
            $data['caisse_session_id'],
            $data['type_mouvement'],
            $data['montant'],
            $soldeAvant,
            $soldeApres,
            $data['moyen_paiement'],
            $data['reference'],
            $data['description'],
            $data['utilisateur_id']
        ];
        
        if (isset($data['vente_id'])) {
            $params[] = $data['vente_id'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $mouvementId = $this->db->lastInsertId();
        
        // Mettre à jour le total des ventes de la session
        if ($data['type_mouvement'] === 'VENTE') {
            $sql = "UPDATE caisse_sessions 
                    SET montant_ventes = montant_ventes + ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['montant'], $data['caisse_session_id']]);
        }

            $this->genererEcritureComptableMouvement((int) $mouvementId, $data);

            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

        return [
            'success' => true,
            'mouvement_id' => $mouvementId,
            'solde_avant' => $soldeAvant,
            'solde_apres' => $soldeApres,
            'message' => 'Mouvement enregistré avec succès'
        ];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('CaisseService::enregistrerMouvement - ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcule le solde actuel d'une session
     */
    private function calculerSoldeSession(int $sessionId): float
    {
        $sql = "SELECT COALESCE(SUM(CASE 
                    WHEN type_mouvement IN ('VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE') 
                    THEN montant 
                    ELSE 0 
                END) - SUM(CASE 
                    WHEN type_mouvement IN ('REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE') 
                    THEN montant 
                    ELSE 0 
                END), 0) as solde
                FROM mouvements_caisse
                WHERE caisse_session_id = ? AND supprime = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        return (float) $stmt->fetchColumn();
    }

    private function genererEcritureComptableMouvement(int $mouvementId, array $data): void
    {
        // La vente et sa contre-passation sont comptabilisées par VenteService.
        // Ne jamais créer une seconde écriture caisse qui doublerait le 571/701.
        if (in_array(strtoupper((string)($data['type_mouvement'] ?? '')), ['VENTE', 'ANNULATION_VENTE'], true)) {
            return;
        }

        $utilisateurId = (int) ($data['utilisateur_id'] ?? 0);
        if ($utilisateurId <= 0) {
            return;
        }

        $mouvementCompta = [
            'id' => $mouvementId,
            'type_mouvement' => strtoupper((string) ($data['type_mouvement'] ?? '')),
            'montant' => (float) ($data['montant'] ?? 0),
            'motif' => (string) ($data['description'] ?? $data['reference'] ?? 'Mouvement caisse'),
            'reference_type' => (string) ($data['type_mouvement'] ?? 'MOUVEMENT_CAISSE'),
            'date_mouvement' => date('Y-m-d H:i:s'),
            'utilisateur_id' => $utilisateurId,
            'client_id' => $data['client_id'] ?? null,
            'fournisseur_id' => $data['fournisseur_id'] ?? null,
            'type_depense' => $data['type_depense'] ?? 'AUTRE',
        ];

        // Toute écriture passe par lignes_ecritures via JournalComptableService.
        $this->getEcritureService()->genererEcrituresCaisse($mouvementCompta);
    }

    /**
     * Annule un mouvement de vente
     */
    public function annulerMouvementVente(int $venteId, int $utilisateurId): void
    {
        // Récupérer le mouvement de caisse correspondant à la vente
        $sql = "SELECT mc.*, cs.numero_session 
                FROM mouvements_caisse mc
                JOIN caisse_sessions cs ON mc.caisse_session_id = cs.id
                WHERE (mc.vente_id = :vente_id OR mc.reference = :reference)
                AND mc.type_mouvement = 'VENTE'
                ORDER BY mc.id DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['vente_id' => $venteId, 'reference' => 'VENTE_' . $venteId]);
        $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mouvement) {
            // Créer un mouvement d'annulation de vente
            $this->enregistrerMouvement([
                'caisse_session_id' => $mouvement['caisse_session_id'],
                'type_mouvement' => 'ANNULATION_VENTE',
                'montant' => $mouvement['montant'],
                'moyen_paiement' => $mouvement['moyen_paiement'],
                'reference' => 'ANNULATION_VENTE_' . $venteId,
                'description' => 'Annulation vente ' . $venteId,
                'utilisateur_id' => $utilisateurId,
                'vente_id' => $venteId
            ]);
            
            // Mettre à jour le total des ventes de la session
            $sql = "UPDATE caisse_sessions 
                    SET montant_ventes = montant_ventes - ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$mouvement['montant'], $mouvement['caisse_session_id']]);
        }
    }

    /**
     * Récupère la session ouverte d'un caissier
     */
    public function getSessionOuverte(int $caissierId): ?array
    {
        $sql = "SELECT * FROM caisse_sessions 
                WHERE caissier_id = ? AND statut_session = 'OUVERTE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$caissierId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère les détails d'une session
     */
    public function getSessionDetails(int $sessionId): array
    {
        $sql = "SELECT cs.*, u.username as caissier_nom 
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.caissier_id = u.id
                WHERE cs.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new Exception("Session non trouvée");
        }
        
        return $session;
    }

    /**
     * Calcule le montant théorique d'une session
     */
    private function calculerMontantTheorique(int $sessionId): float
    {
        $sql = "SELECT 
                    montant_ouverture + 
                    COALESCE(SUM(CASE WHEN type_mouvement = 'VENTE' THEN montant ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN type_mouvement = 'REMBOURSEMENT' THEN montant ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN type_mouvement = 'RETRAIT' THEN montant ELSE 0 END), 0)
                FROM caisse_sessions cs
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        return (float) $stmt->fetchColumn();
    }

    /**
     * Génère un numéro de session unique
     */
    private function genererNumeroSession(): string
    {
        $sql = "SELECT COUNT(*) as count FROM caisse_sessions WHERE DATE(date_ouverture) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return 'CS' . date('Ymd') . str_pad($result['count'] + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère les mouvements d'une session
     */
    public function getMouvementsSession(int $sessionId): array
    {
        $sql = "SELECT * FROM mouvements_caisse 
                WHERE caisse_session_id = ? 
                ORDER BY date_mouvement ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'historique des sessions d'un caissier
     */
    public function getHistoriqueSessions(int $caissierId, int $limit = 50): array
    {
        $sql = "SELECT cs.*, 
                    COUNT(mc.id) as nombre_mouvements,
                    SUM(CASE WHEN mc.type_mouvement = 'VENTE' THEN mc.montant ELSE 0 END) as total_ventes
                FROM caisse_sessions cs
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.caissier_id = ?
                GROUP BY cs.id
                ORDER BY cs.date_ouverture DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$caissierId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère le rapport Z de caisse
     */
    public function genererRapportZ(int $sessionId): array
    {
        $this->db->beginTransaction();
        
        try {
            $session = $this->getSessionDetails($sessionId);
            
            if ($session['statut_session'] !== 'FERMEE') {
                throw new Exception("Impossible de générer un rapport Z pour une session non fermée");
            }
            
            // Récupérer les détails des mouvements
            $mouvements = $this->getMouvementsSession($sessionId);
            
            // Calculer les totaux par type de mouvement
            $totaux = [
                'ventes' => 0,
                'remboursements' => 0,
                'approvisionnements' => 0,
                'retraits' => 0
            ];
            
            foreach ($mouvements as $mouvement) {
                switch ($mouvement['type_mouvement']) {
                    case 'VENTE':
                        $totaux['ventes'] += $mouvement['montant'];
                        break;
                    case 'REMBOURSEMENT':
                        $totaux['remboursements'] += $mouvement['montant'];
                        break;
                    case 'APPROVISIONNEMENT':
                        $totaux['approvisionnements'] += $mouvement['montant'];
                        break;
                    case 'RETRAIT':
                        $totaux['retraits'] += $mouvement['montant'];
                        break;
                }
            }
            
            // Marquer la session comme contrôlée
            $sql = "UPDATE caisse_sessions SET statut_session = 'CONTROLEE', updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionId]);
            
            $this->db->commit();
            
            return [
                'session' => $session,
                'mouvements' => $mouvements,
                'totaux' => $totaux,
                'ecart' => $session['ecart'],
                'montant_theorique' => $session['montant_theorique'],
                'montant_fermeture' => $session['montant_fermeture']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la génération du rapport Z: " . $e->getMessage());
        }
    }

    /**
     * Récupère les statistiques de caisse pour une période
     */
    public function getStatistiquesCaisse(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    DATE(cs.date_ouverture) as date,
                    COUNT(*) as nombre_sessions,
                    SUM(cs.montant_ventes) as total_ventes,
                    AVG(cs.ecart) as ecart_moyen,
                    COUNT(CASE WHEN cs.ecart > 0 THEN 1 END) as sessions_ecart_positif,
                    COUNT(CASE WHEN cs.ecart < 0 THEN 1 END) as sessions_ecart_negatif
                FROM caisse_sessions cs
                WHERE cs.date_ouverture BETWEEN ? AND ?
                AND cs.statut_session IN ('FERMEE', 'CONTROLEE')
                GROUP BY DATE(cs.date_ouverture)
                ORDER BY date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
