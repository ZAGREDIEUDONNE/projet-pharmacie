<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use Exception;

class PlanComptableService
{
    public const COMPTE_STOCK_MEDICAMENTS = '311';
    public const COMPTE_MARCHANDISES = '31';
    public const COMPTE_FOURNISSEURS = '401';
    public const COMPTE_CLIENTS = '411';
    public const COMPTE_BANQUE = '521';
    public const COMPTE_CAISSE = '571';
    public const COMPTE_VIREMENTS_INTERNES = '581';
    public const COMPTE_VENTES = '701';
    public const COMPTE_TVA_DEDUCTIBLE = '44561';
    public const COMPTE_TVA_COLLECTEE = '44571';
    public const COMPTE_AUTRES_PRODUITS = '75';
    public const COMPTE_VARIATION_STOCK = '6031';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function initialiserPlanComptable(): array
    {
        $this->ensureSchema();

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->creerClassesComptes();
            $this->creerComptesSyscohadaPharmacie();

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'message' => 'Plan comptable SYSCOHADA revise initialise pour une pharmacie au Burkina Faso'
            ];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Erreur lors de l'initialisation du plan comptable SYSCOHADA: " . $e->getMessage());
        }
    }

    private function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS classes_comptes (
            code VARCHAR(2) PRIMARY KEY,
            libelle VARCHAR(150) NOT NULL,
            description TEXT NULL,
            type_classe VARCHAR(30) NULL,
            is_actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->addColumnIfMissing('classes_comptes', 'type_classe', 'type_classe VARCHAR(30) NULL');
        $this->addColumnIfMissing('classes_comptes', 'etat_financier', 'etat_financier VARCHAR(20) NULL');
        $this->addColumnIfMissing('classes_comptes', 'type', 'type VARCHAR(30) NULL');

        $columns = [
            'description' => 'description TEXT NULL',
            'classe_id' => 'classe_id VARCHAR(2) NULL',
            'parent_code' => 'parent_code VARCHAR(20) NULL',
            'type' => 'type VARCHAR(30) NULL',
            'etat_financier' => 'etat_financier VARCHAR(20) NULL',
            'is_systeme' => 'is_systeme BOOLEAN DEFAULT FALSE',
        ];

        foreach ($columns as $column => $definition) {
            $this->addColumnIfMissing('plan_comptable', $column, $definition);
        }

        $this->db->exec('UPDATE plan_comptable SET classe_id = CAST(classe AS CHAR) WHERE classe_id IS NULL AND id > 0');
        $this->db->exec('UPDATE plan_comptable SET type = type_compte WHERE type IS NULL AND id > 0');
        $this->db->exec("UPDATE plan_comptable SET etat_financier = CASE WHEN classe IN (6, 7, 8) THEN 'RESULTAT' ELSE 'BILAN' END WHERE etat_financier IS NULL AND id > 0");
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

    private function creerClassesComptes(): void
    {
        $classes = [
            ['1', 'Comptes de ressources durables', 'Capitaux propres, emprunts et ressources assimilees', 'PASSIF', 'BILAN'],
            ['2', 'Comptes de l actif immobilise', 'Immobilisations incorporelles, corporelles et financieres', 'ACTIF', 'BILAN'],
            ['3', 'Comptes de stocks', 'Marchandises et autres stocks', 'ACTIF', 'BILAN'],
            ['4', 'Comptes de tiers', 'Clients, fournisseurs, Etat, personnel et autres tiers', 'ACTIF/PASSIF', 'BILAN'],
            ['5', 'Comptes de tresorerie', 'Banques, caisse et mouvements internes', 'ACTIF', 'BILAN'],
            ['6', 'Comptes de charges', 'Charges des activites ordinaires', 'CHARGE', 'RESULTAT'],
            ['7', 'Comptes de produits', 'Produits des activites ordinaires', 'PRODUIT', 'RESULTAT'],
            ['8', 'Autres charges et autres produits', 'Operations hors activites ordinaires', 'CHARGE/PRODUIT', 'RESULTAT']
        ];

        $stmt = $this->db->prepare("INSERT INTO classes_comptes (code, libelle, description, type_classe, etat_financier, is_actif)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), description = VALUES(description),
                type_classe = VALUES(type_classe), etat_financier = VALUES(etat_financier), is_actif = 1");

        foreach ($classes as $classe) {
            $stmt->execute($classe);
        }
    }

    private function creerComptesSyscohadaPharmacie(): void
    {
        $comptes = [
            ['101', 'Capital social', 'Ressources durables apportees par les associes', '1', null, 'PASSIF', 'BILAN'],
            ['103', 'Capital personnel', 'Capital de l exploitant individuel', '1', null, 'PASSIF', 'BILAN'],
            ['106', 'Ecarts de reevaluation', 'Ecarts de reevaluation SYSCOHADA', '1', null, 'PASSIF', 'BILAN'],
            ['11', 'Reserves', 'Reserves legales, statutaires et libres', '1', null, 'PASSIF', 'BILAN'],
            ['12', 'Report a nouveau', 'Resultats anterieurs reportes', '1', null, 'PASSIF', 'BILAN'],
            ['13', 'Resultat net de l exercice', 'Resultat beneficiaire ou deficitaire', '1', null, 'PASSIF', 'BILAN'],
            ['16', 'Emprunts et dettes assimilees', 'Dettes financieres a moyen et long terme', '1', null, 'PASSIF', 'BILAN'],

            ['21', 'Immobilisations incorporelles', 'Logiciels et droits incorporels', '2', null, 'ACTIF', 'BILAN'],
            ['23', 'Batiments, installations techniques et agencements', 'Locaux et installations de pharmacie', '2', null, 'ACTIF', 'BILAN'],
            ['24', 'Materiel', 'Materiel informatique, mobilier et equipements', '2', null, 'ACTIF', 'BILAN'],
            ['28', 'Amortissements', 'Amortissements des immobilisations', '2', null, 'PASSIF', 'BILAN'],

            ['31', 'Marchandises', 'Stocks de marchandises', '3', null, 'ACTIF', 'BILAN'],
            ['311', 'Medicaments en stock', 'Medicaments et produits pharmaceutiques destines a la vente', '3', '31', 'ACTIF', 'BILAN'],
            ['312', 'Produits parapharmaceutiques en stock', 'Parapharmacie, hygiene et cosmetiques', '3', '31', 'ACTIF', 'BILAN'],
            ['313', 'Dispositifs medicaux en stock', 'Dispositifs medicaux et consommables', '3', '31', 'ACTIF', 'BILAN'],
            ['39', 'Depreciations des stocks', 'Depreciations de marchandises', '3', null, 'PASSIF', 'BILAN'],

            ['401', 'Fournisseurs', 'Dettes envers les fournisseurs de marchandises et services', '4', '40', 'PASSIF', 'BILAN'],
            ['411', 'Clients', 'Creances clients et assures', '4', '41', 'ACTIF', 'BILAN'],
            ['421', 'Personnel, avances et acomptes', 'Comptes du personnel', '4', '42', 'ACTIF/PASSIF', 'BILAN'],
            ['431', 'Organismes sociaux', 'Cotisations sociales', '4', '43', 'PASSIF', 'BILAN'],
            ['443', 'Etat, TVA facturee', 'TVA collectee a reverser', '4', '44', 'PASSIF', 'BILAN'],
            ['44561', 'TVA deductible sur biens et services', 'TVA deductible sur achats et charges', '4', '44', 'ACTIF', 'BILAN'],
            ['44571', 'TVA collectee sur ventes', 'TVA facturee aux clients', '4', '44', 'PASSIF', 'BILAN'],

            ['521', 'Banques', 'Comptes bancaires de la pharmacie', '5', null, 'ACTIF', 'BILAN'],
            ['571', 'Caisse', 'Especes en caisse', '5', null, 'ACTIF', 'BILAN'],
            ['581', 'Virements internes', 'Transferts entre caisse et banque', '5', null, 'ACTIF', 'BILAN'],

            ['601', 'Achats de marchandises', 'Achats de medicaments et produits revendus', '6', '60', 'CHARGE', 'RESULTAT'],
            ['6031', 'Variation des stocks de marchandises', 'Consommation ou entree de stock de marchandises', '6', '603', 'CHARGE', 'RESULTAT'],
            ['61', 'Transports', 'Charges de transport', '6', null, 'CHARGE', 'RESULTAT'],
            ['62', 'Services exterieurs A', 'Loyers, entretien, assurances et honoraires', '6', null, 'CHARGE', 'RESULTAT'],
            ['63', 'Services exterieurs B', 'Publicite, telecoms, frais bancaires et services divers', '6', null, 'CHARGE', 'RESULTAT'],
            ['64', 'Impots et taxes', 'Impots, taxes et versements assimiles', '6', null, 'CHARGE', 'RESULTAT'],
            ['65', 'Autres charges', 'Autres charges de gestion courante', '6', null, 'CHARGE', 'RESULTAT'],
            ['66', 'Charges de personnel', 'Salaires et charges sociales', '6', null, 'CHARGE', 'RESULTAT'],
            ['67', 'Frais financiers et charges assimilees', 'Interets et frais financiers', '6', null, 'CHARGE', 'RESULTAT'],
            ['68', 'Dotations aux amortissements', 'Dotations aux amortissements et provisions', '6', null, 'CHARGE', 'RESULTAT'],
            ['69', 'Dotations aux provisions', 'Dotations aux provisions et depreciations', '6', null, 'CHARGE', 'RESULTAT'],
            ['622', 'Locations et charges locatives', 'Loyer de l officine et charges locatives', '6', '62', 'CHARGE', 'RESULTAT'],
            ['624', 'Entretien, reparations et maintenance', 'Maintenance des locaux et equipements', '6', '62', 'CHARGE', 'RESULTAT'],
            ['625', 'Primes d assurances', 'Assurances professionnelles', '6', '62', 'CHARGE', 'RESULTAT'],
            ['631', 'Frais bancaires', 'Frais de tenue de compte et commissions', '6', '63', 'CHARGE', 'RESULTAT'],
            ['661', 'Remunerations directes versees au personnel', 'Salaires et appointements', '6', '66', 'CHARGE', 'RESULTAT'],
            ['664', 'Charges sociales', 'Cotisations sociales employeur', '6', '66', 'CHARGE', 'RESULTAT'],

            ['701', 'Ventes de marchandises', 'Ventes de medicaments et produits pharmaceutiques', '7', '70', 'PRODUIT', 'RESULTAT'],
            ['702', 'Ventes de produits parapharmaceutiques', 'Ventes parapharmacie, hygiene et cosmetiques', '7', '70', 'PRODUIT', 'RESULTAT'],
            ['707', 'Produits accessoires', 'Prestations et produits accessoires de pharmacie', '7', '70', 'PRODUIT', 'RESULTAT'],
            ['75', 'Autres produits', 'Autres produits de gestion courante', '7', null, 'PRODUIT', 'RESULTAT'],
            ['77', 'Revenus financiers et produits assimiles', 'Produits financiers', '7', null, 'PRODUIT', 'RESULTAT'],

            ['83', 'Charges hors activites ordinaires', 'Charges HAO', '8', null, 'CHARGE', 'RESULTAT'],
            ['84', 'Produits hors activites ordinaires', 'Produits HAO', '8', null, 'PRODUIT', 'RESULTAT'],
            ['89', 'Impots sur le resultat', 'Impots sur les benefices', '8', null, 'CHARGE', 'RESULTAT']
        ];

        foreach ($comptes as $compte) {
            $this->upsertCompte($compte);
        }
    }

    private function upsertCompte(array $compte): void
    {
        [$numero, $libelle, $description, $classe, $parent, $type, $etat] = $compte;

        $sql = "INSERT INTO plan_comptable (
                    numero_compte, nom_compte, classe, type_compte,
                    description, classe_id, parent_code, type, etat_financier, is_systeme, is_actif
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                ON DUPLICATE KEY UPDATE
                    nom_compte = VALUES(nom_compte),
                    classe = VALUES(classe),
                    type_compte = VALUES(type_compte),
                    description = VALUES(description),
                    classe_id = VALUES(classe_id),
                    parent_code = VALUES(parent_code),
                    type = VALUES(type),
                    etat_financier = VALUES(etat_financier),
                    is_systeme = 1,
                    is_actif = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numero, $libelle, (int) substr($numero, 0, 1), $this->normalizeTypeCompte($type), $description, $classe, $parent, $type, $etat]);
    }

    private function normalizeTypeCompte(string $type): string
    {
        if ($type === 'ACTIF/PASSIF') {
            return 'ACTIF';
        }

        return in_array($type, ['ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT'], true) ? $type : 'ACTIF';
    }

    public function getPlanComptable(): array
    {
        $this->ensureSchema();

        $sql = "SELECT
                    pc.*,
                    COALESCE(pc.classe_id, CAST(pc.classe AS CHAR)) AS classe_id,
                    COALESCE(pc.type, pc.type_compte) AS type,
                    cc.libelle AS classe_libelle,
                    parent.nom_compte AS parent_libelle
                FROM plan_comptable pc
                LEFT JOIN classes_comptes cc ON " . DatabaseSql::equals('CAST(pc.classe AS CHAR)', 'cc.code') . "
                LEFT JOIN plan_comptable parent ON " . DatabaseSql::equals('pc.parent_code', 'parent.numero_compte') . "
                WHERE pc.is_actif = 1
                ORDER BY pc.numero_compte";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCompteByNumeroCompte(string $numeroCompte): ?array
    {
        if (!$this->db->inTransaction()) {
            $this->ensureSchema();
        }

        $numeroCompte = trim($numeroCompte);
        $stmt = $this->db->prepare('SELECT *, COALESCE(classe_id, CAST(classe AS CHAR)) AS classe_id, COALESCE(type, type_compte) AS type
            FROM plan_comptable
            WHERE ' . DatabaseSql::collate('numero_compte') . ' = ?
            LIMIT 1');
        $stmt->execute([$numeroCompte]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCompteByCode(string $code): ?array
    {
        return $this->getCompteByNumeroCompte($code);
    }

    /**
     * Compte actif uniquement (obligatoire pour toute écriture).
     */
    public function getCompteActifByNumeroCompte(string $numeroCompte): ?array
    {
        if (!$this->db->inTransaction()) {
            $this->ensureSchema();
        }

        $numeroCompte = trim($numeroCompte);
        $stmt = $this->db->prepare('SELECT *, COALESCE(classe_id, CAST(classe AS CHAR)) AS classe_id, COALESCE(type, type_compte) AS type
            FROM plan_comptable
            WHERE is_actif = 1
              AND ' . DatabaseSql::collate('numero_compte') . ' = ?
            LIMIT 1');
        $stmt->execute([$numeroCompte]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCompteActifByCode(string $code): ?array
    {
        return $this->getCompteActifByNumeroCompte($code);
    }

    public function compteActifExiste(string $numeroCompte): bool
    {
        return $this->getCompteActifByNumeroCompte($numeroCompte) !== null;
    }

    /**
     * @return array{id:int,numero_compte:string,libelle:string}
     */
    public function resoudreCompteActif(string $numeroCompte): array
    {
        $compte = $this->getCompteActifByNumeroCompte($numeroCompte);
        if (!$compte || empty($compte['id'])) {
            error_log("PlanComptableService: compte SYSCOHADA introuvable ou inactif [{$numeroCompte}]");
            throw new Exception("Compte SYSCOHADA {$numeroCompte} introuvable ou inactif dans plan_comptable");
        }

        return [
            'id' => (int) $compte['id'],
            'numero_compte' => (string) ($compte['numero_compte'] ?? $numeroCompte),
            'libelle' => (string) ($compte['nom_compte'] ?? ''),
        ];
    }

    public function getCompteTresoreriePourVente(array $vente): string
    {
        if (!empty($vente['is_credit']) && (int) $vente['is_credit'] === 1) {
            return self::COMPTE_CLIENTS;
        }

        $type = strtoupper(trim((string) ($vente['type_paiement'] ?? $vente['mode_paiement'] ?? 'ESPECE')));
        $modesBanque = ['CARTE', 'CHEQUE', 'VIREMENT', 'BANQUE', 'MOBILE', 'TRANSFERT'];

        return in_array($type, $modesBanque, true) ? self::COMPTE_BANQUE : self::COMPTE_CAISSE;
    }

    public function getCompteCreditPourAchat(array $achat): string
    {
        $mode = strtoupper(trim((string) ($achat['mode_paiement'] ?? $achat['type_paiement'] ?? '')));
        $payeImmediate = !empty($achat['paiement_immediat'])
            || !empty($achat['est_paye'])
            || (isset($achat['statut_paiement']) && strtoupper((string) $achat['statut_paiement']) === 'PAYE');
        $modesBanque = ['BANQUE', 'VIREMENT', 'CHEQUE', 'CARTE', 'TRANSFERT', 'MOBILE'];

        if ($payeImmediate || in_array($mode, $modesBanque, true)) {
            return self::COMPTE_BANQUE;
        }

        return self::COMPTE_FOURNISSEURS;
    }

    public function getCompteDepense(string $typeDepense): string
    {
        $comptes = [
            'ACHAT' => '601',
            'LOYER' => '622',
            'ENTRETIEN' => '624',
            'ASSURANCE' => '625',
            'PERSONNEL' => '661',
            'CHARGES_SOCIALES' => '664',
            'SERVICES' => '63',
            'FRAIS_BANCAIRES' => '631',
            'AUTRE' => '65',
        ];

        $code = $comptes[strtoupper(trim($typeDepense))] ?? '65';

        if (!$this->compteActifExiste($code)) {
            error_log("PlanComptableService: compte depense {$code} absent, repli sur 65");
            return '65';
        }

        return $code;
    }

    public function getComptesByClasse(string $classeId): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare("SELECT * FROM plan_comptable WHERE classe = ? AND is_actif = 1 ORDER BY numero_compte");
        $stmt->execute([(int) $classeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getComptesByType(string $type): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare("SELECT * FROM plan_comptable WHERE type_compte = ? AND is_actif = 1 ORDER BY numero_compte");
        $stmt->execute([$this->normalizeTypeCompte($type)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterCompte(array $data): int
    {
        $this->ensureSchema();
        $this->validerDonneesCompte($data);

        $numeroCompte = $data['numero_compte'] ?? null;
        if ($this->compteExiste($numeroCompte)) {
            throw new Exception("Le compte {$numeroCompte} existe deja");
        }

        $this->upsertCompte([
            $numeroCompte,
            $data['nom_compte'] ?? $data['libelle'],
            $data['description'] ?? null,
            $data['classe_id'],
            $data['parent_code'] ?? null,
            $data['type'],
            $data['etat_financier']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function modifierCompte(string $numeroCompte, array $data): bool
    {
        $this->ensureSchema();
        $compte = $this->getCompteByNumeroCompte($numeroCompte);
        if (!$compte) {
            throw new Exception("Le compte $numeroCompte n'existe pas");
        }

        if (!empty($compte['is_systeme'])) {
            throw new Exception("Impossible de modifier un compte systeme SYSCOHADA");
        }

        $allowed = ['nom_compte', 'libelle', 'description', 'type_compte', 'type', 'is_actif'];
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (!$fields) {
            return false;
        }

        $values[] = $numeroCompte;
        $stmt = $this->db->prepare("UPDATE plan_comptable SET " . implode(', ', $fields) . " WHERE numero_compte = ?");
        return $stmt->execute($values);
    }

    public function desactiverCompte(string $numeroCompte): bool
    {
        $this->ensureSchema();
        $compte = $this->getCompteByNumeroCompte($numeroCompte);
        if (!$compte) {
            throw new Exception("Le compte $numeroCompte n'existe pas");
        }

        if (!empty($compte['is_systeme'])) {
            throw new Exception("Impossible de desactiver un compte systeme SYSCOHADA");
        }

        $stmt = $this->db->prepare("UPDATE plan_comptable SET is_actif = 0 WHERE numero_compte = ?");
        return $stmt->execute([$numeroCompte]);
    }

    public function compteExiste(?string $numeroCompte): bool
    {
        $this->ensureSchema();

        if ($numeroCompte === null || trim($numeroCompte) === '') {
            return false;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plan_comptable
            WHERE ' . DatabaseSql::collate('numero_compte') . ' = ?');
        $stmt->execute([trim($numeroCompte)]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getComptesEcrituresAuto(): array
    {
        $this->ensureSchema();

        $codes = ['571', '521', '411', '401', '311', '312', '313', '601', '6031', '701', '702', '707', '44561', '44571'];
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $stmt = $this->db->prepare("SELECT *
            FROM plan_comptable
            WHERE is_actif = 1 AND numero_compte IN ($placeholders)
            ORDER BY numero_compte");
        $stmt->execute($codes);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validerDonneesCompte(array $data): void
    {
        $data['numero_compte'] = $data['numero_compte'] ?? null;
        $data['nom_compte'] = $data['nom_compte'] ?? $data['libelle'] ?? null;

        foreach (['numero_compte', 'nom_compte', 'classe_id', 'type', 'etat_financier'] as $champ) {
            if (empty($data[$champ])) {
                throw new Exception("Le champ '$champ' est obligatoire");
            }
        }

        if (!preg_match('/^[0-9]{1,6}$/', $data['numero_compte'])) {
            throw new Exception("Le numero du compte doit etre numerique (1 a 6 chiffres)");
        }

        if (!in_array((string) $data['classe_id'], ['1', '2', '3', '4', '5', '6', '7', '8'], true)) {
            throw new Exception("La classe de compte doit etre comprise entre 1 et 8");
        }

        if (!in_array($data['type'], ['ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT', 'ACTIF/PASSIF', 'CHARGE/PRODUIT'], true)) {
            throw new Exception("Type de compte invalide");
        }

        if (!in_array($data['etat_financier'], ['BILAN', 'RESULTAT'], true)) {
            throw new Exception("Etat financier invalide");
        }
    }

    public function exporterPlanComptable(): array
    {
        return $this->getPlanComptable();
    }

    public function getStatistiquesPlanComptable(): array
    {
        $this->ensureSchema();

        $stmt = $this->db->query("SELECT
                COUNT(*) AS total_comptes,
                SUM(CASE WHEN is_actif = 1 THEN 1 ELSE 0 END) AS comptes_actifs,
                SUM(CASE WHEN is_systeme = 1 THEN 1 ELSE 0 END) AS comptes_systeme,
                SUM(CASE WHEN type_compte = 'ACTIF' THEN 1 ELSE 0 END) AS comptes_actif,
                SUM(CASE WHEN type_compte = 'PASSIF' THEN 1 ELSE 0 END) AS comptes_passif,
                SUM(CASE WHEN type_compte = 'CHARGE' THEN 1 ELSE 0 END) AS comptes_charge,
                SUM(CASE WHEN type_compte = 'PRODUIT' THEN 1 ELSE 0 END) AS comptes_produit
            FROM plan_comptable");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Initialise le plan SYSCOHADA s'il est vide.
     */
    public function ensureInitialized(): void
    {
        if ($this->db->inTransaction()) {
            $count = (int) $this->db->query('SELECT COUNT(*) FROM plan_comptable WHERE is_actif = 1')->fetchColumn();
            if ($count === 0) {
                throw new Exception('Plan comptable SYSCOHADA non initialise');
            }
            return;
        }

        $this->ensureSchema();
        $requiredAccounts = [
            self::COMPTE_STOCK_MEDICAMENTS, self::COMPTE_FOURNISSEURS,
            self::COMPTE_CLIENTS, self::COMPTE_BANQUE, self::COMPTE_CAISSE,
            self::COMPTE_VIREMENTS_INTERNES, self::COMPTE_VENTES,
            self::COMPTE_TVA_DEDUCTIBLE, self::COMPTE_TVA_COLLECTEE,
            self::COMPTE_AUTRES_PRODUITS, self::COMPTE_VARIATION_STOCK,
            '601', '65',
        ];
        $placeholders = implode(',', array_fill(0, count($requiredAccounts), '?'));
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT numero_compte) FROM plan_comptable WHERE is_actif = 1 AND numero_compte IN ({$placeholders})");
        $stmt->execute($requiredAccounts);

        if ((int) $stmt->fetchColumn() !== count($requiredAccounts)) {
            $this->initialiserPlanComptable();
        }
    }

    /**
     * Retourne l'identifiant plan_comptable pour un numero_compte SYSCOHADA.
     */
    public function getCompteIdByCode(string $numeroCompte): int
    {
        $this->ensureInitialized();

        return $this->resoudreCompteActif($numeroCompte)['id'];
    }
}
