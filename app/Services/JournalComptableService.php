<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use Exception;

class JournalComptableService
{
    private PDO $db;
    private AuditService $auditService;
    private ?PlanComptableService $planService = null;

    public function __construct(PDO $db, AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    private function getPlanService(): PlanComptableService
    {
        if ($this->planService === null) {
            $this->planService = new PlanComptableService($this->db);
        }

        return $this->planService;
    }

    /**
     * Plan comptable + journaux requis avant toute écriture.
     */
    public function ensureComptabiliteReady(): void
    {
        $this->ensureSchema();
        $this->getPlanService()->ensureInitialized();

        $journalCount = (int) $this->db->query('SELECT COUNT(*) FROM journaux_comptables WHERE is_actif = 1')->fetchColumn();
        if ($journalCount === 0) {
            $this->creerJournauxPrincipaux();
        }

    }

    /**
     * Résout un compte SYSCOHADA vers id + code canonique.
     */
    public function resoudreCompte(string $compteCode): array
    {
        $compte = $this->getPlanService()->resoudreCompteActif($compteCode);

        return [
            'numero_compte' => $compte['numero_compte'],
        ];
    }

    public function getCompteTresoreriePourVente(array $vente): string
    {
        return $this->getPlanService()->getCompteTresoreriePourVente($vente);
    }

    public function getCompteCreditPourAchat(array $achat): string
    {
        return $this->getPlanService()->getCompteCreditPourAchat($achat);
    }

    public function getCompteDepense(string $typeDepense): string
    {
        return $this->getPlanService()->getCompteDepense($typeDepense);
    }

    public function creerJournauxPrincipaux(): array
    {
        $this->ensureSchema();

        $journaux = [
            ['AC', 'Journal des Achats', 'Achats et factures fournisseurs', 'ACHATS', '#ef4444'],
            ['VT', 'Journal des Ventes', 'Ventes au comptoir et ventes a credit', 'VENTES', '#10b981'],
            ['CA', 'Journal de Caisse', 'Encaissements et decaissements en especes', 'CAISSE', '#3b82f6'],
            ['BQ', 'Journal de Banque', 'Operations bancaires', 'BANQUE', '#f59e0b'],
            ['OD', 'Journal des Operations Diverses', 'Regularisations, inventaires et operations diverses', 'DIVERS', '#8b5cf6']
        ];

        $stmt = $this->db->prepare("INSERT INTO journaux_comptables (code, libelle, description, type, couleur, is_actif, is_systeme)
            VALUES (?, ?, ?, ?, ?, 1, 1)
            ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), description = VALUES(description), type = VALUES(type), couleur = VALUES(couleur), is_actif = 1");

        foreach ($journaux as $journal) {
            $stmt->execute($journal);
        }

        return [
            'success' => true,
            'message' => 'Journaux comptables SYSCOHADA crees avec succes',
            'nombre_journaux' => count($journaux)
        ];
    }

    public function enregistrerEcriture(array $data): int
    {
        if (!$this->db->inTransaction()) {
            $this->ensureComptabiliteReady();
        } else {
            $this->ensureSchema();
        }

        try {
            $this->validerEcriture($data);
        } catch (Exception $e) {
            $this->journaliserErreurComptable($e, $data);
            throw $e;
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $dateEcriture = $data['date_ecriture'] ?? date('Y-m-d H:i:s');
            (new ExerciceComptableService($this->db))->assertEcritureAllowed($dateEcriture);

            $journal = $this->getJournalByCode($data['journal_code']);
            if (!$journal) {
                $ex = new Exception("Journal {$data['journal_code']} non trouve");
                $this->journaliserErreurComptable($ex, $data);
                throw $ex;
            }

            $numeroPiece = $data['numero_piece'] ?? $this->genererNumeroPiece($data['journal_code']);
            $stmt = $this->db->prepare("INSERT INTO ecritures_comptables (
                    journal_id, numero_piece, date_ecriture, libelle, reference_type,
                    reference_id, utilisateur_id, periode_comptable
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $journal['id'],
                $numeroPiece,
                $dateEcriture,
                $data['libelle'],
                $data['reference_type'],
                $data['reference_id'],
                $data['utilisateur_id'],
                $this->getPeriodeComptable($dateEcriture)
            ]);

            $ecritureId = (int) $this->db->lastInsertId();
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            $stmtLigne = $this->db->prepare("INSERT INTO lignes_ecritures (
                    ecriture_id, compte_code, libelle, debit, credit, reference_type, reference_id, tiers_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['lignes'] as $ligne) {
                $debit = round((float) ($ligne['debit'] ?? 0), 2);
                $credit = round((float) ($ligne['credit'] ?? 0), 2);
                $compte = $this->resoudreCompte((string) $ligne['compte_code']);

                $stmtLigne->execute([
                    $ecritureId,
                    $compte['numero_compte'],
                    $ligne['libelle'] ?? $data['libelle'],
                    $debit,
                    $credit,
                    $data['reference_type'],
                    $data['reference_id'],
                    $ligne['tiers_id'] ?? null,
                ]);

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                $ex = new Exception("Ecriture desequilibree apres insertion: Debit={$totalDebit}, Credit={$totalCredit}");
                $this->journaliserErreurComptable($ex, $data, $ecritureId);
                throw $ex;
            }

            $this->verifierLignesNumeroCompte($ecritureId);

            $stmt = $this->db->prepare('UPDATE ecritures_comptables SET total_debit = ?, total_credit = ?, is_equilibree = 1 WHERE id = ?');
            $stmt->execute([$totalDebit, $totalCredit, $ecritureId]);

            $this->auditService->logAction(
                (int) $data['utilisateur_id'],
                'ENREGISTREMENT_ECRITURE_SYSCOHADA',
                'ecritures_comptables',
                $ecritureId,
                null,
                [
                    'journal_code' => $data['journal_code'],
                    'numero_piece' => $numeroPiece,
                    'reference_type' => $data['reference_type'],
                    'reference_id' => $data['reference_id'],
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit
                ]
            );

            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $ecritureId;
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->journaliserErreurComptable($e, $data ?? [], $ecritureId ?? null);
            throw new Exception("Erreur lors de l'enregistrement de l'ecriture: " . $e->getMessage());
        }
    }

    public function genererEcrituresVente(array $vente): int
    {
        $montantTtc = round((float) $vente['montant_net'], 2);
        $montantHt = round((float) ($vente['montant_ht'] ?? 0), 2);
        $montantTva = round((float) ($vente['montant_tva'] ?? 0), 2);

        if ($montantHt <= 0 && $montantTva <= 0) {
            $montantHt = $montantTtc;
        } elseif ($montantHt <= 0) {
            $montantHt = max(0, round($montantTtc - $montantTva, 2));
        } elseif ($montantTva <= 0 && $montantHt < $montantTtc) {
            $montantTva = round($montantTtc - $montantHt, 2);
        }

        $compteDebit = $this->getCompteTresoreriePourVente($vente);
        $ref = $this->referenceVente($vente);

        $lignes = [[
            'compte_code' => $compteDebit,
            'libelle' => "Vente pharmacie - {$ref}",
            'debit' => $montantTtc,
            'credit' => 0,
            'tiers_id' => !empty($vente['is_credit']) ? ($vente['client_id'] ?? null) : null,
        ], [
            'compte_code' => PlanComptableService::COMPTE_VENTES,
            'libelle' => "Ventes de marchandises - {$ref}",
            'debit' => 0,
            'credit' => $montantHt,
            'tiers_id' => null,
        ]];

        if ($montantTva > 0) {
            $lignes[] = [
                'compte_code' => PlanComptableService::COMPTE_TVA_COLLECTEE,
                'libelle' => "TVA collectee - {$ref}",
                'debit' => 0,
                'credit' => $montantTva,
                'tiers_id' => null,
            ];
        }

        return $this->enregistrerEcriture([
            'journal_code' => 'VT',
            'numero_piece' => 'VT' . date('Ymd') . str_pad((string) $vente['id'], 6, '0', STR_PAD_LEFT),
            'date_ecriture' => $vente['date_vente'],
            'libelle' => "Vente pharmacie - {$ref}",
            'reference_type' => 'VENTE',
            'reference_id' => $vente['id'],
            'utilisateur_id' => $vente['utilisateur_id'],
            'lignes' => $lignes,
        ]);
    }

    public function genererEcrituresAchat(array $achat): int
    {
        $montantTtc = round((float) $achat['montant_total'], 2);
        $montantHt = round((float) ($achat['montant_ht'] ?? $montantTtc), 2);
        $montantTva = round((float) ($achat['montant_tva'] ?? max(0, $montantTtc - $montantHt)), 2);

        if ($montantHt <= 0) {
            $montantHt = max(0, round($montantTtc - $montantTva, 2));
        }

        $lignes = [[
            'compte_code' => PlanComptableService::COMPTE_STOCK_MEDICAMENTS,
            'libelle' => "Achat medicaments - {$achat['reference']}",
            'debit' => $montantHt,
            'credit' => 0,
            'tiers_id' => null,
        ]];

        if ($montantTva > 0) {
            $lignes[] = [
                'compte_code' => PlanComptableService::COMPTE_TVA_DEDUCTIBLE,
                'libelle' => "TVA deductible - {$achat['reference']}",
                'debit' => $montantTva,
                'credit' => 0,
                'tiers_id' => null,
            ];
        }

        $compteCredit = $this->getCompteCreditPourAchat($achat);
        $lignes[] = [
            'compte_code' => $compteCredit,
            'libelle' => $compteCredit === PlanComptableService::COMPTE_BANQUE
                ? "Paiement banque achat - {$achat['reference']}"
                : "Dette fournisseur - {$achat['reference']}",
            'debit' => 0,
            'credit' => $montantTtc,
            'tiers_id' => $compteCredit === PlanComptableService::COMPTE_FOURNISSEURS
                ? ($achat['fournisseur_id'] ?? null)
                : null,
        ];

        return $this->enregistrerEcriture([
            'journal_code' => 'AC',
            'numero_piece' => 'AC' . date('Ymd') . str_pad((string) $achat['id'], 6, '0', STR_PAD_LEFT),
            'date_ecriture' => $achat['date_commande'],
            'libelle' => "Achat pharmacie - {$achat['reference']}",
            'reference_type' => 'ACHAT',
            'reference_id' => $achat['id'],
            'utilisateur_id' => $achat['utilisateur_id'],
            'lignes' => $lignes,
        ]);
    }

    public function genererEcrituresCaisse(array $mouvement): int
    {
        $montant = round((float) $mouvement['montant'], 2);
        $lignes = [];

        if ($mouvement['type_mouvement'] === 'ENTREE') {
            $lignes[] = [
                'compte_code' => PlanComptableService::COMPTE_CAISSE,
                'libelle' => $mouvement['motif'],
                'debit' => $montant,
                'credit' => 0,
                'tiers_id' => $mouvement['client_id'] ?? null,
            ];
            $lignes[] = [
                'compte_code' => ($mouvement['reference_type'] ?? null) === 'VENTE'
                    ? PlanComptableService::COMPTE_CLIENTS
                    : PlanComptableService::COMPTE_AUTRES_PRODUITS,
                'libelle' => $mouvement['motif'],
                'debit' => 0,
                'credit' => $montant,
                'tiers_id' => $mouvement['client_id'] ?? null,
            ];
        } else {
            $lignes[] = [
                'compte_code' => $this->getCompteDepense($mouvement['type_depense'] ?? 'AUTRE'),
                'libelle' => $mouvement['motif'],
                'debit' => $montant,
                'credit' => 0,
                'tiers_id' => $mouvement['fournisseur_id'] ?? null,
            ];
            $lignes[] = [
                'compte_code' => PlanComptableService::COMPTE_CAISSE,
                'libelle' => $mouvement['motif'],
                'debit' => 0,
                'credit' => $montant,
                'tiers_id' => null,
            ];
        }

        return $this->enregistrerEcriture([
            'journal_code' => 'CA',
            'numero_piece' => 'CA' . date('Ymd') . str_pad((string) $mouvement['id'], 6, '0', STR_PAD_LEFT),
            'date_ecriture' => $mouvement['date_mouvement'],
            'libelle' => $mouvement['motif'],
            'reference_type' => 'MOUVEMENT_CAISSE',
            'reference_id' => $mouvement['id'],
            'utilisateur_id' => $mouvement['utilisateur_id'],
            'lignes' => $lignes,
        ]);
    }

    public function getJournalByCode(string $code): ?array
    {
        $this->ensureSchemaIfSafe();

        $stmt = $this->db->prepare("SELECT * FROM journaux_comptables WHERE code = ? AND is_actif = 1");
        $stmt->execute([$code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function prepareSchema(): void
    {
        $this->ensureSchema();
    }

    public function getJournaux(): array
    {
        $this->ensureSchemaIfSafe();

        $stmt = $this->db->query("SELECT * FROM journaux_comptables WHERE is_actif = 1 ORDER BY code");
        $journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($journaux)) {
            $this->creerJournauxPrincipaux();
            $stmt = $this->db->query("SELECT * FROM journaux_comptables WHERE is_actif = 1 ORDER BY code");
            $journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $journaux;
    }

    public function getEcrituresJournal(string $journalCode, string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT ec.*,
                       COALESCE(ec.date_ecriture, ec.created_at) AS date_ecriture,
                       j.code AS journal_code,
                       j.libelle AS journal_libelle,
                       u.username AS utilisateur_nom
                FROM ecritures_comptables ec
                JOIN journaux_comptables j ON ec.journal_id = j.id
                LEFT JOIN utilisateurs u ON ec.utilisateur_id = u.id
                WHERE j.code = ?";
        $params = [$journalCode];

        if ($dateDebut) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $dateDebut;
        }

        if ($dateFin) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $dateFin;
        }

        $sql .= " ORDER BY ec.date_ecriture DESC, ec.numero_piece DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLignesEcriture(int $ecritureId): array
    {
        $stmt = $this->db->prepare("SELECT le.*,
                le.compte_code AS compte_code,
                pc.nom_compte AS compte_libelle,
                c.nom AS client_nom,
                f.nom AS fournisseur_nom
            FROM lignes_ecritures le
            LEFT JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
            LEFT JOIN clients c ON le.tiers_id = c.id
            LEFT JOIN fournisseurs f ON le.tiers_id = f.id
            WHERE le.ecriture_id = ?
            ORDER BY le.id");
        $stmt->execute([$ecritureId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exporterJournal(string $journalCode, string $dateDebut, string $dateFin): array
    {
        $exportData = [];
        foreach ($this->getEcrituresJournal($journalCode, $dateDebut, $dateFin) as $ecriture) {
            foreach ($this->getLignesEcriture((int)$ecriture['id']) as $ligne) {
                $exportData[] = [
                    'date_ecriture' => $ecriture['date_ecriture'],
                    'journal' => $ecriture['journal_libelle'],
                    'numero_piece' => $ecriture['numero_piece'],
                    'compte' => $ligne['compte_code'] . ' - ' . $ligne['compte_libelle'],
                    'libelle' => $ligne['libelle'],
                    'debit' => $ligne['debit'],
                    'credit' => $ligne['credit'],
                    'tiers' => $ligne['client_nom'] ?? $ligne['fournisseur_nom'] ?? '',
                    'reference' => $ecriture['reference_type'] . ' #' . $ecriture['reference_id']
                ];
            }
        }

        return $exportData;
    }

    private function ensureSchemaIfSafe(): void
    {
        if (!$this->db->inTransaction()) {
            $this->ensureSchema();
        }
    }

    private function ensureSchema(): void
    {
        $this->ensureJournauxTable();
        $this->ensureEcrituresTable();
        $this->ensureLignesEcrituresTable();
    }

    private function tableExists(string $table): bool
    {
        $safeTable = str_replace('`', '``', $table);
        $stmt = $this->db->query("SHOW TABLES LIKE '{$safeTable}'");

        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $safeTable = str_replace('`', '``', $table);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$safeTable, $column]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
        }
    }

    private function ensureJournauxTable(): void
    {
        if (!$this->tableExists('journaux_comptables')) {
            $this->db->exec("CREATE TABLE journaux_comptables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(10) UNIQUE NOT NULL,
                libelle VARCHAR(120) NOT NULL,
                description TEXT NULL,
                type VARCHAR(30) NULL,
                couleur VARCHAR(20) NULL,
                is_actif BOOLEAN DEFAULT TRUE,
                is_systeme BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            return;
        }

        $this->addColumnIfMissing('journaux_comptables', 'type', 'type VARCHAR(30) NULL');
        $this->addColumnIfMissing('journaux_comptables', 'couleur', 'couleur VARCHAR(20) NULL');
        $this->addColumnIfMissing('journaux_comptables', 'is_systeme', 'is_systeme BOOLEAN DEFAULT FALSE');
        $this->addColumnIfMissing('journaux_comptables', 'description', 'description TEXT NULL');
    }

    private function ensureEcrituresTable(): void
    {
        if (!$this->tableExists('ecritures_comptables')) {
            $this->db->exec("CREATE TABLE ecritures_comptables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                journal_id INT NOT NULL,
                numero_piece VARCHAR(50) NULL,
                date_ecriture DATETIME NULL,
                libelle VARCHAR(255) NULL,
                reference_type VARCHAR(50) NULL,
                reference_id INT NULL,
                utilisateur_id INT NULL,
                periode_comptable VARCHAR(7) NULL,
                total_debit DECIMAL(15,2) DEFAULT 0,
                total_credit DECIMAL(15,2) DEFAULT 0,
                is_equilibree BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ecritures_journal (journal_id),
                INDEX idx_ecritures_date (date_ecriture)
            )");
            return;
        }

        $columns = [
            'numero_piece' => 'numero_piece VARCHAR(50) NULL',
            'date_ecriture' => 'date_ecriture DATETIME NULL',
            'reference_type' => 'reference_type VARCHAR(50) NULL',
            'reference_id' => 'reference_id INT NULL',
            'utilisateur_id' => 'utilisateur_id INT NULL',
            'periode_comptable' => 'periode_comptable VARCHAR(7) NULL',
            'total_debit' => 'total_debit DECIMAL(15,2) DEFAULT 0',
            'total_credit' => 'total_credit DECIMAL(15,2) DEFAULT 0',
            'is_equilibree' => 'is_equilibree BOOLEAN DEFAULT FALSE',
            'updated_at' => 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($columns as $column => $definition) {
            $this->addColumnIfMissing('ecritures_comptables', $column, $definition);
        }
    }

    private function ensureLignesEcrituresTable(): void
    {
        if (!$this->tableExists('lignes_ecritures')) {
            $this->db->exec("CREATE TABLE lignes_ecritures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ecriture_id INT NOT NULL,
                compte_code VARCHAR(20) NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                debit DECIMAL(15,2) DEFAULT 0,
                credit DECIMAL(15,2) DEFAULT 0,
                reference_type VARCHAR(50) NULL,
                reference_id INT NULL,
                tiers_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lignes_ecriture (ecriture_id),
                INDEX idx_lignes_compte (compte_code)
            )");
            return;
        }

        $columns = [
            'compte_code' => 'compte_code VARCHAR(20) NULL',
            'reference_type' => 'reference_type VARCHAR(50) NULL',
            'reference_id' => 'reference_id INT NULL',
            'tiers_id' => 'tiers_id INT NULL',
        ];

        foreach ($columns as $column => $definition) {
            $this->addColumnIfMissing('lignes_ecritures', $column, $definition);
        }

    }

    private function validerEcriture(array $data): void
    {
        foreach (['journal_code', 'libelle', 'reference_type', 'reference_id', 'utilisateur_id', 'lignes'] as $champ) {
            if (!isset($data[$champ]) || $data[$champ] === '' || $data[$champ] === []) {
                throw new Exception("Le champ '$champ' est obligatoire");
            }
        }

        if (count($data['lignes']) < 2) {
            throw new Exception("Une ecriture comptable doit avoir au moins deux lignes");
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($data['lignes'] as $ligne) {
            $debit = round((float) ($ligne['debit'] ?? 0), 2);
            $credit = round((float) ($ligne['credit'] ?? 0), 2);

            if (empty($ligne['compte_code'])) {
                throw new Exception('Chaque ligne doit avoir un compte SYSCOHADA (numero_compte)');
            }

            if (!$this->compteExiste($ligne['compte_code'])) {
                throw new Exception("Compte SYSCOHADA {$ligne['compte_code']} inexistant ou inactif dans le plan comptable");
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new Exception('Chaque ligne doit porter un debit ou un credit strictement positif');
            }

            if ($debit > 0 && $credit > 0) {
                throw new Exception('Une meme ligne ne peut pas porter debit et credit');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            error_log("JournalComptableService::validerEcriture desequilibre Debit={$totalDebit} Credit={$totalCredit}");
            throw new Exception("Ecriture desequilibree: Debit={$totalDebit}, Credit={$totalCredit}");
        }
    }

    private function verifierLignesNumeroCompte(int $ecritureId): void
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM lignes_ecritures
             WHERE ecriture_id = ? AND (compte_code IS NULL OR compte_code = '')"
        );
        $stmt->execute([$ecritureId]);
        $invalides = (int) $stmt->fetchColumn();

        if ($invalides > 0) {
            $message = "Ecriture #{$ecritureId} invalide: {$invalides} ligne(s) sans numero_compte";
            error_log('JournalComptableService::verifierLignesNumeroCompte - ' . $message);
            $ex = new Exception($message);
            $this->journaliserErreurComptable($ex, ['ecriture_id' => $ecritureId], $ecritureId);
            throw $ex;
        }
    }

    private function compteExiste(string $compteCode): bool
    {
        return $this->getPlanService()->compteActifExiste($compteCode);
    }

    private function journaliserErreurComptable(Exception $e, array $data, ?int $ecritureId = null): void
    {
        try {
            $context = [
                'journal_code' => $data['journal_code'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'utilisateur_id' => $data['utilisateur_id'] ?? null,
                'ecriture_id' => $ecritureId,
            ];

            // Older installations have a different system_errors schema.
            // Audit remains available, but do not mask the original accounting error.
            if (!$this->columnExists('system_errors', 'error_type')) {
                $this->auditService->logAction(
                    isset($data['utilisateur_id']) ? (int) $data['utilisateur_id'] : null,
                    'ECRITURE_COMPTABLE_INVALIDE',
                    'ecritures_comptables',
                    $ecritureId,
                    null,
                    $context
                );
                return;
            }

            // Enregistrer dans system_errors via une entrée standardisée
            $sql = "INSERT INTO system_errors (
                        error_type, error_message, file, line, context, ip_address, user_agent, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'COMPTABILITE',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                json_encode($context),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            $this->auditService->logAction(
                isset($data['utilisateur_id']) ? (int) $data['utilisateur_id'] : null,
                'ECRITURE_COMPTABLE_INVALIDE',
                'ecritures_comptables',
                $ecritureId,
                null,
                $context
            );
        } catch (Exception $inner) {
            error_log('JournalComptableService::journaliserErreurComptable - ' . $inner->getMessage());
        }
    }

    private function genererNumeroPiece(string $journalCode): string
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ecritures_comptables ec
            JOIN journaux_comptables j ON ec.journal_id = j.id
            WHERE j.code = ? AND DATE(ec.date_ecriture) = CURDATE()");
        $stmt->execute([$journalCode]);

        return $journalCode . date('Ymd') . str_pad((string)((int)$stmt->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function getPeriodeComptable(string $date): string
    {
        return (new \DateTime($date))->format('Y-m');
    }

    private function referenceVente(array $vente): string
    {
        return $vente['numero_ticket'] ?? $vente['numero_facture'] ?? ('#' . $vente['id']);
    }
}
