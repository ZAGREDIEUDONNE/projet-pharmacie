<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use Exception;

class EtatFinancierService
{
    private const BILAN_CLASSES = ['1', '2', '3', '4', '5'];
    private const RESULTAT_CLASSES = ['6', '7'];
    private const TRESORERIE_PREFIXES = ['521', '571', '578', '552'];

    private PDO $db;
    private ?AuditService $auditService;

    public function __construct(PDO $db, ?AuditService $auditService = null)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    public function getBilan(?string $dateFin = null): array
    {
        $dateFin = $dateFin ?: date('Y-m-d');
        $lignes = $this->fetchSoldes(null, $dateFin, self::BILAN_CLASSES, [], true);

        $classes = [];
        $actif = [];
        $passif = [];
        $totalActif = 0.0;
        $totalPassif = 0.0;

        foreach ($lignes as &$ligne) {
            $classe = $ligne['classe_id'];
            $solde = (float) $ligne['solde'];
            $montant = abs($solde);
            $section = $this->sectionBilan($classe, $solde);
            $ligne['section'] = $section;
            $ligne['montant'] = $montant;

            if (!isset($classes[$classe])) {
                $classes[$classe] = $this->emptyClasse($classe, $ligne['classe_libelle']);
            }

            $classes[$classe]['comptes'][] = $ligne;
            $classes[$classe]['solde'] += $solde;

            if ($section === 'actif') {
                $actif[] = $ligne;
                $classes[$classe]['total_actif'] += $montant;
                $totalActif += $montant;
            } else {
                $passif[] = $ligne;
                $classes[$classe]['total_passif'] += $montant;
                $totalPassif += $montant;
            }
        }
        unset($ligne);

        ksort($classes);
        $ecart = round(abs($totalActif - $totalPassif), 2);

        return [
            'success' => true,
            'type' => 'bilan',
            'source' => 'lignes_ecritures',
            'date_bilan' => $dateFin,
            'date_generation' => date('Y-m-d H:i:s'),
            'lignes_bilan' => $lignes,
            'actif' => ['comptes' => $actif, 'total' => $totalActif],
            'passif' => ['comptes' => $passif, 'total' => $totalPassif],
            'totaux_par_classe' => $classes,
            'totaux_generaux' => [
                'total_actif' => $totalActif,
                'total_passif' => $totalPassif,
                'ecart' => $ecart,
                'equilibre' => $ecart < 0.01,
                'capital_et_reserves' => $classes['1']['total_passif'] ?? 0,
                'immobilisations' => $classes['2']['total_actif'] ?? 0,
                'stocks' => $classes['3']['total_actif'] ?? 0,
                'creances_clients' => $classes['4']['total_actif'] ?? 0,
                'dettes_fournisseurs' => $classes['4']['total_passif'] ?? 0,
                'tresorerie' => $classes['5']['total_actif'] ?? 0,
            ],
            'message' => $ecart < 0.01 ? 'Bilan equilibre' : 'Bilan non equilibre',
        ];
    }

    public function getCompteResultat(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?: date('Y-m-01');
        $dateFin = $dateFin ?: date('Y-m-d');
        $lignes = $this->fetchSoldes($dateDebut, $dateFin, self::RESULTAT_CLASSES);

        $classes = [];
        $charges = [];
        $produits = [];
        $totalCharges = 0.0;
        $totalProduits = 0.0;

        foreach ($lignes as &$ligne) {
            $classe = $ligne['classe_id'];
            $solde = (float) $ligne['solde'];
            $isCharge = $classe === '6';
            $montant = $isCharge ? max(0, $solde) : max(0, -$solde);

            $ligne['total_charges'] = $isCharge ? $montant : 0.0;
            $ligne['total_produits'] = $isCharge ? 0.0 : $montant;
            $ligne['resultat'] = $ligne['total_produits'] - $ligne['total_charges'];

            if (!isset($classes[$classe])) {
                $classes[$classe] = [
                    'classe_id' => $classe,
                    'classe_libelle' => $ligne['classe_libelle'],
                    'total_charges' => 0.0,
                    'total_produits' => 0.0,
                    'resultat' => 0.0,
                    'comptes' => [],
                ];
            }

            $classes[$classe]['comptes'][] = $ligne;
            $classes[$classe]['total_charges'] += $ligne['total_charges'];
            $classes[$classe]['total_produits'] += $ligne['total_produits'];
            $classes[$classe]['resultat'] += $ligne['resultat'];

            if ($isCharge) {
                $charges[] = $ligne;
                $totalCharges += $montant;
            } else {
                $produits[] = $ligne;
                $totalProduits += $montant;
            }
        }
        unset($ligne);

        ksort($classes);
        $resultat = $totalProduits - $totalCharges;

        return [
            'success' => true,
            'type' => 'compte_resultat',
            'source' => 'lignes_ecritures',
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s'),
            ],
            'lignes_resultat' => $lignes,
            'charges' => ['comptes' => $charges, 'total' => $totalCharges],
            'produits' => ['comptes' => $produits, 'total' => $totalProduits],
            'totaux_par_classe' => $classes,
            'totaux_generaux' => [
                'total_charges' => $totalCharges,
                'total_produits' => $totalProduits,
                'resultat_exploitation' => $resultat,
                'marge_resultat' => $totalProduits > 0 ? ($resultat / $totalProduits) * 100 : 0,
            ],
            'message' => $resultat >= 0 ? 'Resultat positif' : 'Resultat negatif',
        ];
    }

    public function getTresorerie(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?: date('Y-m-01');
        $dateFin = $dateFin ?: date('Y-m-d');
        $lignes = $this->fetchSoldes($dateDebut, $dateFin, [], self::TRESORERIE_PREFIXES);

        $totalEntrees = 0.0;
        $totalSorties = 0.0;
        $parType = [
            'banque' => $this->emptyTresorerieType(),
            'caisse' => $this->emptyTresorerieType(),
            'mobile_money' => $this->emptyTresorerieType(),
            'virements_internes' => $this->emptyTresorerieType(),
        ];

        foreach ($lignes as &$ligne) {
            $ligne['total_entrees'] = (float) $ligne['total_debit'];
            $ligne['total_sorties'] = (float) $ligne['total_credit'];
            $type = $this->typeTresorerie($ligne['compte_code']);
            $ligne['type_tresorerie'] = $type;

            $parType[$type]['comptes'][] = $ligne;
            $parType[$type]['total_entrees'] += $ligne['total_entrees'];
            $parType[$type]['total_sorties'] += $ligne['total_sorties'];
            $parType[$type]['solde'] += (float) $ligne['solde'];

            $totalEntrees += $ligne['total_entrees'];
            $totalSorties += $ligne['total_sorties'];
        }
        unset($ligne);

        $soldeInitial = $this->getSoldeTresorerieAvant($dateDebut);
        $variation = $totalEntrees - $totalSorties;
        $soldeFinal = $soldeInitial + $variation;

        return [
            'success' => true,
            'type' => 'tresorerie',
            'source' => 'lignes_ecritures',
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s'),
            ],
            'comptes_tresorerie' => self::TRESORERIE_PREFIXES,
            'lignes_tresorerie' => $lignes,
            'tresorerie_par_type' => $parType,
            'totaux_generaux' => [
                'solde_initial' => $soldeInitial,
                'total_entrees' => $totalEntrees,
                'total_sorties' => $totalSorties,
                'variation' => $variation,
                'solde_final' => $soldeFinal,
            ],
            'message' => $soldeFinal >= 0 ? 'Tresorerie positive' : 'Tresorerie negative',
        ];
    }

    public function genererBilan(string $dateFin): array
    {
        return $this->getBilan($dateFin);
    }

    public function genererCompteResultat(string $dateDebut, string $dateFin): array
    {
        return $this->getCompteResultat($dateDebut, $dateFin);
    }

    public function genererTableauTresorerie(string $dateDebut, string $dateFin): array
    {
        return $this->getTresorerie($dateDebut, $dateFin);
    }

    public function genererRapportTVA(string $dateDebut, string $dateFin): array
    {
        $service = new TVAService($this->db, $this->auditService ?? new AuditService($this->db));
        return $service->genererRapportTVA($dateDebut, $dateFin);
    }

    public function genererAnalyseFinanciere(string $dateDebut, string $dateFin): array
    {
        $compteResultat = $this->getCompteResultat($dateDebut, $dateFin);
        $bilan = $this->getBilan($dateFin);
        $tresorerie = $this->getTresorerie($dateDebut, $dateFin);

        return [
            'success' => true,
            'type' => 'analyse_financiere',
            'source' => 'lignes_ecritures',
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s'),
            ],
            'compte_resultat' => $compteResultat,
            'bilan' => $bilan,
            'tableau_tresorerie' => $tresorerie,
            'synthese' => [
                'resultat_exploitation' => $compteResultat['totaux_generaux']['resultat_exploitation'],
                'total_actif' => $bilan['totaux_generaux']['total_actif'],
                'total_passif' => $bilan['totaux_generaux']['total_passif'],
                'solde_tresorerie' => $tresorerie['totaux_generaux']['solde_final'],
                'equilibre_bilan' => $bilan['totaux_generaux']['equilibre'],
            ],
        ];
    }

    public function exporterEtatsFinanciers(string $dateDebut, string $dateFin, string $type): array
    {
        switch ($type) {
            case 'compte_resultat':
                $etat = $this->getCompteResultat($dateDebut, $dateFin);
                return [
                    'success' => true,
                    'type' => $type,
                    'periode' => $etat['periode'],
                    'donnees' => $this->formatExportCompteResultat($etat['lignes_resultat']),
                    'totaux' => $etat['totaux_generaux'],
                ];

            case 'bilan':
                $etat = $this->getBilan($dateFin);
                return [
                    'success' => true,
                    'type' => $type,
                    'date_bilan' => $etat['date_bilan'],
                    'donnees' => $this->formatExportBilan($etat['lignes_bilan']),
                    'totaux' => $etat['totaux_generaux'],
                ];

            case 'tresorerie':
                $etat = $this->getTresorerie($dateDebut, $dateFin);
                return [
                    'success' => true,
                    'type' => $type,
                    'periode' => $etat['periode'],
                    'donnees' => $this->formatExportTresorerie($etat['lignes_tresorerie']),
                    'totaux' => $etat['totaux_generaux'],
                ];
        }

        throw new Exception("Type d'etat non valide: {$type}");
    }

    private function fetchSoldes(
        ?string $dateDebut,
        ?string $dateFin,
        array $classes = [],
        array $prefixes = [],
        bool $cumul = false
    ): array {
        $compteExpr = "NULLIF(le.compte_code, '')";
        $classeExpr = "LEFT({$compteExpr}, 1)";

        $sql = "SELECT
                    {$compteExpr} AS compte_code,
                    {$classeExpr} AS classe_id,
                    COALESCE(MAX(NULLIF(pc.libelle, '')), MAX(NULLIF(pc.nom_compte, '')), MAX(le.libelle), {$compteExpr}) AS compte_libelle,
                    COALESCE(MAX(NULLIF(cc.libelle, '')), CONCAT('Classe ', {$classeExpr})) AS classe_libelle,
                    COALESCE(MAX(NULLIF(pc.type, '')), MAX(NULLIF(pc.type_compte, '')), '') AS compte_type,
                    SUM(COALESCE(le.debit, 0)) AS total_debit,
                    SUM(COALESCE(le.credit, 0)) AS total_credit,
                    SUM(COALESCE(le.debit, 0) - COALESCE(le.credit, 0)) AS solde,
                    COUNT(DISTINCT le.ecriture_id) AS nombre_ecritures
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON ec.id = le.ecriture_id
                LEFT JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                LEFT JOIN classes_comptes cc ON " . DatabaseSql::joinClassesComptesOnPlan() . "
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                  AND {$compteExpr} IS NOT NULL
                  AND {$compteExpr} <> ''";

        $params = [];
        if ($dateFin) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $dateFin;
        }
        if (!$cumul && $dateDebut) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $dateDebut;
        }
        if ($classes) {
            $sql .= " AND {$classeExpr} IN (" . implode(',', array_fill(0, count($classes), '?')) . ")";
            array_push($params, ...$classes);
        }
        if ($prefixes) {
            $parts = [];
            foreach ($prefixes as $prefix) {
                $parts[] = "{$compteExpr} LIKE ?";
                $params[] = $prefix . '%';
            }
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }

        $sql .= " GROUP BY compte_code, classe_id
                  HAVING total_debit <> 0 OR total_credit <> 0
                  ORDER BY compte_code";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'normalizeAmounts'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getSoldeTresorerieAvant(string $dateDebut): float
    {
        $veille = date('Y-m-d', strtotime($dateDebut . ' -1 day'));
        $lignes = $this->fetchSoldes(null, $veille, [], self::TRESORERIE_PREFIXES, true);
        return array_sum(array_column($lignes, 'solde'));
    }

    private function normalizeAmounts(array $ligne): array
    {
        foreach (['total_debit', 'total_credit', 'solde'] as $field) {
            $ligne[$field] = round((float) ($ligne[$field] ?? 0), 2);
        }
        $ligne['nombre_ecritures'] = (int) ($ligne['nombre_ecritures'] ?? 0);
        $ligne['compte_code'] = (string) $ligne['compte_code'];
        $ligne['classe_id'] = (string) $ligne['classe_id'];

        return $ligne;
    }

    private function sectionBilan(string $classe, float $solde): string
    {
        if (in_array($classe, ['2', '3', '5'], true)) {
            return $solde >= 0 ? 'actif' : 'passif';
        }
        if ($classe === '1') {
            return $solde <= 0 ? 'passif' : 'actif';
        }

        return $solde >= 0 ? 'actif' : 'passif';
    }

    private function typeTresorerie(string $compteCode): string
    {
        if (str_starts_with($compteCode, '521')) {
            return 'banque';
        }
        if (str_starts_with($compteCode, '571')) {
            return 'caisse';
        }
        if (str_starts_with($compteCode, '578')) {
            return 'mobile_money';
        }

        return 'virements_internes';
    }

    private function emptyClasse(string $classe, string $libelle): array
    {
        return [
            'classe_id' => $classe,
            'classe_libelle' => $libelle,
            'total_actif' => 0.0,
            'total_passif' => 0.0,
            'solde' => 0.0,
            'comptes' => [],
        ];
    }

    private function emptyTresorerieType(): array
    {
        return [
            'comptes' => [],
            'total_entrees' => 0.0,
            'total_sorties' => 0.0,
            'solde' => 0.0,
        ];
    }

    private function formatExportCompteResultat(array $lignes): array
    {
        return array_map(static fn(array $ligne): array => [
            'Classe' => $ligne['classe_id'],
            'Compte' => $ligne['compte_code'],
            'Libelle' => $ligne['compte_libelle'],
            'Charges' => $ligne['total_charges'] ?? 0,
            'Produits' => $ligne['total_produits'] ?? 0,
            'Resultat' => $ligne['resultat'] ?? 0,
        ], $lignes);
    }

    private function formatExportBilan(array $lignes): array
    {
        return array_map(static fn(array $ligne): array => [
            'Classe' => $ligne['classe_id'],
            'Compte' => $ligne['compte_code'],
            'Libelle' => $ligne['compte_libelle'],
            'Section' => $ligne['section'] ?? '',
            'Solde' => $ligne['solde'],
            'Montant' => $ligne['montant'] ?? abs((float) $ligne['solde']),
        ], $lignes);
    }

    private function formatExportTresorerie(array $lignes): array
    {
        return array_map(static fn(array $ligne): array => [
            'Compte' => $ligne['compte_code'],
            'Libelle' => $ligne['compte_libelle'],
            'Type' => $ligne['type_tresorerie'] ?? '',
            'Entrees' => $ligne['total_entrees'] ?? 0,
            'Sorties' => $ligne['total_sorties'] ?? 0,
            'Solde' => $ligne['solde'],
        ], $lignes);
    }
}
