<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Suivi des règlements clients et fournisseurs (acomptes, ristournes, escomptes).
 */
class ReglementTiersService
{
    private PDO $db;
    private ?ComptabiliteService $comptabilite;
    private ?EcritureComptableService $ecritureService;

    public function __construct(PDO $db, ?ComptabiliteService $comptabilite = null, ?EcritureComptableService $ecritureService = null)
    {
        $this->db = $db;
        $this->comptabilite = $comptabilite;
        $this->ecritureService = $ecritureService;
    }

    // ─── Clients ───────────────────────────────────────────────

    public function getSuiviClients(): array
    {
        $sql = 'SELECT
                    c.id, c.code, c.nom, c.prenom, c.telephone,
                    c.plafond_credit, c.solde_credit,
                    COALESCE(SUM(CASE WHEN v.statut_vente != "ANNULEE" AND v.is_credit = 1 THEN v.montant_net ELSE 0 END), 0) AS montant_du_ventes,
                    COALESCE(SUM(CASE WHEN v.statut_vente != "ANNULEE" THEN v.montant_paye ELSE 0 END), 0) AS montant_paye_ventes,
                    COALESCE(SUM(CASE WHEN v.statut_vente != "ANNULEE" AND v.is_credit = 1 THEN v.montant_restant ELSE 0 END), 0) AS reste_a_payer
                FROM clients c
                LEFT JOIN ventes v ON v.client_id = c.id
                WHERE c.deleted_at IS NULL AND c.is_actif = 1
                GROUP BY c.id
                HAVING reste_a_payer > 0 OR c.solde_credit > 0
                ORDER BY reste_a_payer DESC, c.nom';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistoriqueClient(int $clientId): array
    {
        $mouvements = [];

        if ($this->tableExists('client_reglements')) {
            $stmt = $this->db->prepare(
                'SELECT cr.*, u.username AS utilisateur_nom
                 FROM client_reglements cr
                 LEFT JOIN utilisateurs u ON u.id = cr.utilisateur_id
                 WHERE cr.client_id = ?
                 ORDER BY cr.date_mouvement DESC'
            );
            $stmt->execute([$clientId]);
            $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            'SELECT id, numero_facture, date_vente, montant_net, montant_paye, montant_restant,
                    is_credit, echeance_credit, statut_vente
             FROM ventes
             WHERE client_id = ? AND statut_vente != "ANNULEE"
             ORDER BY date_vente DESC LIMIT 50'
        );
        $stmt->execute([$clientId]);
        $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['client' => $client, 'ventes' => $ventes, 'reglements' => $mouvements];
    }

    public function enregistrerReglementClient(array $data, int $userId): array
    {
        if (!$this->tableExists('client_reglements')) {
            throw new Exception('Table client_reglements absente. Exécutez la migration 014.');
        }

        $clientId = (int)($data['client_id'] ?? 0);
        $montant = (float)($data['montant'] ?? 0);
        $type = strtoupper((string)($data['type_mouvement'] ?? 'CREDIT'));
        $venteId = !empty($data['vente_id']) ? (int)$data['vente_id'] : null;

        if ($clientId <= 0 || $montant <= 0) {
            throw new Exception('Client et montant obligatoires.');
        }

        $allowed = ['DEBIT', 'CREDIT', 'RISTOURNE', 'ESCOMPTE'];
        if (!in_array($type, $allowed, true)) {
            throw new Exception('Type de mouvement invalide.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO client_reglements
                 (client_id, vente_id, type_mouvement, montant, mode_paiement, reference, notes, utilisateur_id, date_mouvement)
                 VALUES (:client_id, :vente_id, :type, :montant, :mode, :ref, :notes, :user_id, NOW())'
            );
            $stmt->execute([
                'client_id' => $clientId,
                'vente_id' => $venteId,
                'type' => $type,
                'montant' => $montant,
                'mode' => $data['mode_paiement'] ?? 'ESPECE',
                'ref' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId,
            ]);
            $reglementId = (int)$this->db->lastInsertId();

            if ($venteId && in_array($type, ['CREDIT', 'ESCOMPTE'], true)) {
                $stmt = $this->db->prepare(
                    'UPDATE ventes SET montant_paye = montant_paye + :m,
                     montant_restant = GREATEST(0, montant_restant - :m)
                     WHERE id = :id AND client_id = :client_id'
                );
                $stmt->execute(['m' => $montant, 'id' => $venteId, 'client_id' => $clientId]);
            }

            if (in_array($type, ['CREDIT', 'RISTOURNE', 'ESCOMPTE'], true)) {
                $stmt = $this->db->prepare(
                    'UPDATE clients SET solde_credit = GREATEST(0, solde_credit - :m) WHERE id = :id'
                );
                $stmt->execute(['m' => $montant, 'id' => $clientId]);
            } elseif ($type === 'DEBIT') {
                $stmt = $this->db->prepare(
                    'UPDATE clients SET solde_credit = solde_credit + :m WHERE id = :id'
                );
                $stmt->execute(['m' => $montant, 'id' => $clientId]);
            }

            if ($this->tableExists('remises_commerciales') && in_array($type, ['RISTOURNE', 'ESCOMPTE'], true)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO remises_commerciales
                     (tiers_type, tiers_id, reference_type, reference_id, type_avantage, montant, motif, utilisateur_id)
                     VALUES ("CLIENT", :tiers_id, "VENTE", :ref_id, :type_av, :montant, :motif, :user_id)'
                );
                $stmt->execute([
                    'tiers_id' => $clientId,
                    'ref_id' => $venteId,
                    'type_av' => $type,
                    'montant' => $montant,
                    'motif' => $data['notes'] ?? null,
                    'user_id' => $userId,
                ]);
            }

            $this->db->commit();
            return ['success' => true, 'reglement_id' => $reglementId];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ─── Fournisseurs ──────────────────────────────────────────

    public function getSuiviFournisseurs(): array
    {
        $sql = 'SELECT
                    f.id, f.code, f.nom, f.telephone,
                    COALESCE(SUM(r.montant_facture), 0) AS montant_factures,
                    COALESCE(fr.total_paye, 0) AS montant_paye,
                    COALESCE(SUM(r.montant_facture), 0) - COALESCE(fr.total_paye, 0) AS reste_a_payer
                FROM fournisseurs f
                LEFT JOIN receptions r ON r.fournisseur_id = f.id AND r.statut != "ANNULEE"
                LEFT JOIN (
                    SELECT fournisseur_id,
                           SUM(CASE WHEN type_mouvement IN ("CREDIT","ESCOMPTE") THEN montant
                                    WHEN type_mouvement IN ("DEBIT","RISTOURNE") THEN -montant ELSE 0 END) AS total_paye
                    FROM fournisseur_reglements
                    GROUP BY fournisseur_id
                ) fr ON fr.fournisseur_id = f.id
                WHERE f.deleted_at IS NULL AND f.is_actif = 1
                GROUP BY f.id
                ORDER BY reste_a_payer DESC, f.nom';

        if (!$this->tableExists('fournisseur_reglements')) {
            $sql = 'SELECT f.id, f.code, f.nom, f.telephone,
                           COALESCE(SUM(r.montant_facture), 0) AS montant_factures,
                           0 AS montant_paye,
                           COALESCE(SUM(r.montant_facture), 0) AS reste_a_payer
                    FROM fournisseurs f
                    LEFT JOIN receptions r ON r.fournisseur_id = f.id
                    WHERE f.deleted_at IS NULL AND f.is_actif = 1
                    GROUP BY f.id ORDER BY f.nom';
        }

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistoriqueFournisseur(int $fournisseurId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, so.numero_commande
             FROM receptions r
             LEFT JOIN supplier_orders so ON so.id = r.supplier_order_id
             WHERE r.fournisseur_id = ?
             ORDER BY r.date_reception DESC LIMIT 50'
        );
        $stmt->execute([$fournisseurId]);
        $receptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reglements = [];
        if ($this->tableExists('fournisseur_reglements')) {
            $stmt = $this->db->prepare(
                'SELECT fr.*, u.username AS utilisateur_nom
                 FROM fournisseur_reglements fr
                 LEFT JOIN utilisateurs u ON u.id = fr.utilisateur_id
                 WHERE fr.fournisseur_id = ?
                 ORDER BY fr.date_mouvement DESC'
            );
            $stmt->execute([$fournisseurId]);
            $reglements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare('SELECT * FROM fournisseurs WHERE id = ?');
        $stmt->execute([$fournisseurId]);
        $fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['fournisseur' => $fournisseur, 'receptions' => $receptions, 'reglements' => $reglements];
    }

    public function enregistrerReglementFournisseur(array $data, int $userId): array
    {
        if (!$this->tableExists('fournisseur_reglements')) {
            throw new Exception('Table fournisseur_reglements absente. Exécutez la migration 014.');
        }

        $fournisseurId = (int)($data['fournisseur_id'] ?? 0);
        $montant = (float)($data['montant'] ?? 0);
        $type = strtoupper((string)($data['type_mouvement'] ?? 'CREDIT'));
        $receptionId = !empty($data['reception_id']) ? (int)$data['reception_id'] : null;
        $modePaiement = strtoupper((string)($data['mode_paiement'] ?? 'VIREMENT'));
        $reference = trim((string)($data['reference'] ?? ''));

        if ($fournisseurId <= 0 || $montant <= 0) {
            throw new Exception('Fournisseur et montant obligatoires.');
        }

        $this->db->beginTransaction();
        try {
            if ($reference !== '') {
                $duplicate = $this->db->prepare('SELECT COUNT(*) FROM fournisseur_reglements WHERE fournisseur_id = ? AND reference = ? FOR UPDATE');
                $duplicate->execute([$fournisseurId, $reference]);
                if ((int)$duplicate->fetchColumn() > 0) {
                    throw new Exception('Un règlement fournisseur possède déjà cette référence.');
                }
            }
            $stmt = $this->db->prepare(
                'INSERT INTO fournisseur_reglements
                 (fournisseur_id, reception_id, type_mouvement, montant, mode_paiement, reference, notes, utilisateur_id, date_mouvement)
                 VALUES (:fournisseur_id, :reception_id, :type, :montant, :mode, :ref, :notes, :user_id, NOW())'
            );
            $stmt->execute([
                'fournisseur_id' => $fournisseurId,
                'reception_id' => $receptionId,
                'type' => $type,
                'montant' => $montant,
                'mode' => $modePaiement,
                'ref' => $reference !== '' ? $reference : null,
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId,
            ]);
            $reglementId = (int)$this->db->lastInsertId();

            // Seul CREDIT est un paiement fournisseur dans le modèle actuel.
            // Les remises, escomptes et factures n'ont pas encore de règle de
            // contrepartie comptable explicitement définie : ne pas en inventer.
            if ($type === 'CREDIT') {
                if ($this->ecritureService === null) {
                    throw new Exception('Service d’écriture comptable indisponible pour le règlement fournisseur.');
                }
                $compteTresorerie = $modePaiement === 'ESPECE' ? '571' : '521';
                $journalCode = $modePaiement === 'ESPECE' ? 'CA' : 'BQ';
                $this->ecritureService->enregistrerEcriture([
                    'journal_code' => $journalCode,
                    'numero_piece' => 'RF' . date('Ymd') . '-' . $reglementId,
                    'date_ecriture' => date('Y-m-d H:i:s'),
                    'libelle' => 'Règlement fournisseur #' . $reglementId,
                    'reference_type' => 'REGLEMENT_FOURNISSEUR',
                    'reference_id' => $reglementId,
                    'utilisateur_id' => $userId,
                    'lignes' => [
                        ['compte_code' => '401', 'libelle' => 'Règlement fournisseur', 'debit' => $montant, 'credit' => 0, 'tiers_id' => $fournisseurId],
                        ['compte_code' => $compteTresorerie, 'libelle' => 'Sortie de trésorerie fournisseur', 'debit' => 0, 'credit' => $montant, 'tiers_id' => null],
                    ],
                ]);
            }

            if ($this->tableExists('remises_commerciales') && in_array($type, ['RISTOURNE', 'ESCOMPTE'], true)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO remises_commerciales
                     (tiers_type, tiers_id, reference_type, reference_id, type_avantage, montant, motif, utilisateur_id)
                     VALUES ("FOURNISSEUR", :tiers_id, "RECEPTION", :ref_id, :type_av, :montant, :motif, :user_id)'
                );
                $stmt->execute([
                    'tiers_id' => $fournisseurId,
                    'ref_id' => $receptionId,
                    'type_av' => $type,
                    'montant' => $montant,
                    'motif' => $data['notes'] ?? null,
                    'user_id' => $userId,
                ]);
            }

            $this->db->commit();
            return ['success' => true, 'reglement_id' => $reglementId];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
