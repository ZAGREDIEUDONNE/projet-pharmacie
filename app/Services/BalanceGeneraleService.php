<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use PDOException;
use Exception;

class BalanceGeneraleService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Génère la balance générale pour une période
     */
    public function genererBalance(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    le.compte_code AS compte_code,
                    COALESCE(pc.libelle, pc.nom_compte) as compte_libelle,
                    COALESCE(pc.type, pc.type_compte) as compte_type,
                    COALESCE(pc.classe_id, CAST(pc.classe AS CHAR)) as classe_id,
                    cc.libelle as classe_libelle,
                    SUM(le.debit) as total_debit,
                    SUM(le.credit) as total_credit,
                    SUM(le.debit) - SUM(le.credit) as solde,
                    COUNT(DISTINCT le.ecriture_id) as nombre_ecritures
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . " AND pc.is_actif = 1
                JOIN classes_comptes cc ON " . DatabaseSql::joinClassesComptesOnPlan() . "
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                AND ec.date_ecriture <= ?
                GROUP BY le.compte_code, pc.numero_compte, pc.libelle, pc.nom_compte, pc.type, pc.type_compte, pc.classe_id, pc.classe, cc.libelle
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY pc.numero_compte";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFin]);
        
        $lignesBalance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les totaux généraux
        $totalDebit = array_sum(array_column($lignesBalance, 'total_debit'));
        $totalCredit = array_sum(array_column($lignesBalance, 'total_credit'));
        $ecart = abs($totalDebit - $totalCredit);
        $equilibree = $ecart < 0.01;
        
        // Regrouper par classe
        $balanceParClasse = [];
        foreach ($lignesBalance as $ligne) {
            $classeId = $ligne['classe_id'];
            if (!isset($balanceParClasse[$classeId])) {
                $balanceParClasse[$classeId] = [
                    'classe_id' => $classeId,
                    'classe_libelle' => $ligne['classe_libelle'],
                    'comptes' => [],
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'solde' => 0
                ];
            }
            
            $balanceParClasse[$classeId]['comptes'][] = $ligne;
            $balanceParClasse[$classeId]['total_debit'] += $ligne['total_debit'];
            $balanceParClasse[$classeId]['total_credit'] += $ligne['total_credit'];
            $balanceParClasse[$classeId]['solde'] += $ligne['solde'];
        }
        
        // Regrouper par type
        $balanceParType = [
            'ACTIF' => ['comptes' => [], 'total_debit' => 0, 'total_credit' => 0, 'solde' => 0],
            'PASSIF' => ['comptes' => [], 'total_debit' => 0, 'total_credit' => 0, 'solde' => 0],
            'CHARGE' => ['comptes' => [], 'total_debit' => 0, 'total_credit' => 0, 'solde' => 0],
            'PRODUIT' => ['comptes' => [], 'total_debit' => 0, 'total_credit' => 0, 'solde' => 0]
        ];
        
        foreach ($lignesBalance as $ligne) {
            $type = $ligne['compte_type'];
            if (isset($balanceParType[$type])) {
                $balanceParType[$type]['comptes'][] = $ligne;
                $balanceParType[$type]['total_debit'] += $ligne['total_debit'];
                $balanceParType[$type]['total_credit'] += $ligne['total_credit'];
                $balanceParType[$type]['solde'] += $ligne['solde'];
            }
        }
        
        return [
            'success' => true,
            'periode' => [
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'lignes_balance' => $lignesBalance,
            'balance_par_classe' => $balanceParClasse,
            'balance_par_type' => $balanceParType,
            'totaux_generaux' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'ecart' => $ecart,
                'equilibree' => $equilibree,
                'nombre_comptes' => count($lignesBalance),
                'nombre_ecritures' => array_sum(array_column($lignesBalance, 'nombre_ecritures'))
            ],
            'message' => $equilibree ? 'Balance équilibrée' : 'Balance non équilibrée - Écart: ' . number_format($ecart, 2, ',', ' ')
        ];
    }

    /**
     * Génère la balance pour une période spécifique
     */
    public function genererBalancePeriode(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    le.compte_code,
                    COALESCE(pc.libelle, pc.nom_compte) as compte_libelle,
                    COALESCE(pc.type, pc.type_compte) as compte_type,
                    COALESCE(pc.classe_id, CAST(pc.classe AS CHAR)) as classe_id,
                    cc.libelle as classe_libelle,
                    SUM(le.debit) as total_debit,
                    SUM(le.credit) as total_credit,
                    SUM(le.debit) - SUM(le.credit) as solde,
                    COUNT(DISTINCT le.ecriture_id) as nombre_ecritures
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                JOIN classes_comptes cc ON " . DatabaseSql::joinClassesComptesOnPlan() . "
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                AND ec.date_ecriture BETWEEN ? AND ?
                GROUP BY le.compte_code, pc.libelle, pc.nom_compte, pc.type, pc.type_compte, pc.classe_id, pc.classe, cc.libelle
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY le.compte_code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        $lignesBalance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les soldes précédents pour la période
        $balancePrecedente = $this->genererBalance($dateDebut);
        $soldesPrecedents = [];
        foreach ($balancePrecedente['lignes_balance'] as $ligne) {
            $soldesPrecedents[$ligne['compte_code']] = $ligne['solde'];
        }
        
        // Ajouter les soldes précédents
        foreach ($lignesBalance as &$ligne) {
            $ligne['solde_precedent'] = $soldesPrecedents[$ligne['compte_code']] ?? 0;
            $ligne['solde_final'] = $ligne['solde_precedent'] + $ligne['solde'];
        }
        
        // Recalculer les totaux avec les soldes précédents
        $totalDebit = array_sum(array_column($lignesBalance, 'total_debit'));
        $totalCredit = array_sum(array_column($lignesBalance, 'total_credit'));
        $ecart = abs($totalDebit - $totalCredit);
        $equilibree = $ecart < 0.01;

        $balanceParClasse = [];
        foreach ($lignesBalance as $ligne) {
            $classeId = $ligne['classe_id'];
            if (!isset($balanceParClasse[$classeId])) {
                $balanceParClasse[$classeId] = [
                    'classe_id' => $classeId,
                    'classe_libelle' => $ligne['classe_libelle'],
                    'comptes' => [],
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'solde' => 0,
                ];
            }
            $balanceParClasse[$classeId]['comptes'][] = $ligne;
            $balanceParClasse[$classeId]['total_debit'] += $ligne['total_debit'];
            $balanceParClasse[$classeId]['total_credit'] += $ligne['total_credit'];
            $balanceParClasse[$classeId]['solde'] += $ligne['solde'];
        }
        
        return [
            'success' => true,
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'lignes_balance' => $lignesBalance,
            'balance_par_classe' => $balanceParClasse,
            'soldes_precedents' => $soldesPrecedents,
            'totaux_generaux' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'ecart' => $ecart,
                'equilibree' => $equilibree,
                'nombre_comptes' => count($lignesBalance),
                'nombre_ecritures' => array_sum(array_column($lignesBalance, 'nombre_ecritures'))
            ],
            'message' => $equilibree ? 'Balance équilibrée' : 'Balance non équilibrée - Écart: ' . number_format($ecart, 2, ',', ' ')
        ];
    }

    /**
     * Vérifie l'équilibre de la balance
     */
    public function verifierEquilibre(string $dateFin = null): array
    {
        $balance = $this->genererBalance($dateFin);
        
        if ($balance['totaux_generaux']['equilibree']) {
            return [
                'equilibre' => true,
                'message' => 'La balance est parfaitement équilibrée',
                'ecart' => 0,
                'date_controle' => $dateFin ?? date('Y-m-d H:i:s')
            ];
        } else {
            $ecart = $balance['totaux_generaux']['ecart'];
            
            // Logger le déséquilibre
            $this->auditService->logEvent(
                'DESEQUILIBRE_BALANCE',
                'ERREUR_COMPTABLE',
                'Déséquilibre de la balance générale',
                "Écart de " . number_format($ecart, 2, ',', ' ') . " FCFA détecté",
                [
                    'ecart' => $ecart,
                    'total_debit' => $balance['totaux_generaux']['total_debit'],
                    'total_credit' => $balance['totaux_generaux']['total_credit'],
                    'date_controle' => $dateFin ?? date('Y-m-d H:i:s')
                ]
            );
            
            return [
                'equilibre' => false,
                'message' => 'Déséquilibre détecté dans la balance',
                'ecart' => $ecart,
                'total_debit' => $balance['totaux_generaux']['total_debit'],
                'total_credit' => $balance['totaux_generaux']['total_credit'],
                'date_controle' => $dateFin ?? date('Y-m-d H:i:s'),
                'actions_recommandees' => [
                    'Vérifier les écritures de la période',
                    'Contrôler les reports de soldes',
                    'Valider les écritures automatiques',
                    'Rechercher les erreurs de saisie'
                ]
            ];
        }
    }

    /**
     * Exporte la balance générale
     */
    public function exporterBalance(string $dateDebut = null, string $dateFin = null): array
    {
        $balance = $this->genererBalancePeriode($dateDebut, $dateFin);
        
        $exportData = [];
        
        // En-tête
        $exportData[] = [
            'Compte' => 'Compte',
            'Libellé' => 'Libellé Compte',
            'Type' => 'Type',
            'Classe' => 'Classe',
            'Solde Précédent' => 'Solde Précédent',
            'Débit' => 'Débit Période',
            'Crédit' => 'Crédit Période',
            'Solde Final' => 'Solde Final',
            'Nombre Écritures' => 'Nb Écritures'
        ];
        
        // Lignes de la balance
        foreach ($balance['lignes_balance'] as $ligne) {
            $exportData[] = [
                'Compte' => $ligne['compte_code'],
                'Libellé' => $ligne['compte_libelle'],
                'Type' => $ligne['compte_type'],
                'Classe' => $ligne['classe_libelle'],
                'Solde Précédent' => number_format($ligne['solde_precedent'] ?? 0, 2, ',', ' '),
                'Débit' => number_format($ligne['total_debit'], 2, ',', ' '),
                'Crédit' => number_format($ligne['total_credit'], 2, ',', ' '),
                'Solde Final' => number_format($ligne['solde'], 2, ',', ' '),
                'Nombre Écritures' => $ligne['nombre_ecritures']
            ];
        }
        
        // Totaux par classe
        foreach ($balance['balance_par_classe'] as $classe) {
            $exportData[] = [
                'Compte' => '',
                'Libellé' => 'TOTAL ' . $classe['classe_libelle'],
                'Type' => '',
                'Classe' => $classe['classe_libelle'],
                'Solde Précédent' => '',
                'Débit' => number_format($classe['total_debit'], 2, ',', ' '),
                'Crédit' => number_format($classe['total_credit'], 2, ',', ' '),
                'Solde Final' => number_format($classe['solde'], 2, ',', ' '),
                'Nombre Écritures' => ''
            ];
        }
        
        // Totaux généraux
        $totaux = $balance['totaux_generaux'];
        $exportData[] = [
            'Compte' => '',
            'Libellé' => 'TOTAL GÉNÉRAL',
            'Type' => '',
            'Classe' => '',
            'Solde Précédent' => '',
            'Débit' => number_format($totaux['total_debit'], 2, ',', ' '),
            'Crédit' => number_format($totaux['total_credit'], 2, ',', ' '),
            'Solde Final' => number_format($totaux['total_debit'] - $totaux['total_credit'], 2, ',', ' '),
            'Nombre Écritures' => $totaux['nombre_ecritures']
        ];
        
        return [
            'success' => true,
            'periode' => $balance['periode'],
            'donnees' => $exportData,
            'statistiques' => $totaux,
            'equilibre' => $totaux['equilibree'],
            'message' => $totaux['equilibree'] ? 'Balance équilibrée' : 'Balance non équilibrée'
        ];
    }

    /**
     * Compare deux balances
     */
    public function comparerBalances(string $date1, string $date2): array
    {
        $balance1 = $this->genererBalance($date1);
        $balance2 = $this->genererBalance($date2);
        
        $comparaison = [];
        
        foreach ($balance1['lignes_balance'] as $ligne1) {
            $compteCode = $ligne1['compte_code'];
            $ligne2 = $this->trouverLigneBalance($balance2['lignes_balance'], $compteCode);
            
            if ($ligne2) {
                $variationDebit = $ligne2['total_debit'] - $ligne1['total_debit'];
                $variationCredit = $ligne2['total_credit'] - $ligne1['total_credit'];
                $variationSolde = $ligne2['solde'] - $ligne1['solde'];
                
                if (abs($variationDebit) > 0.01 || abs($variationCredit) > 0.01) {
                    $comparaison[] = [
                        'compte_code' => $compteCode,
                        'compte_libelle' => $ligne1['compte_libelle'],
                        'solde_date1' => $ligne1['solde'],
                        'solde_date2' => $ligne2['solde'],
                        'variation_solde' => $variationSolde,
                        'variation_debit' => $variationDebit,
                        'variation_credit' => $variationCredit,
                        'type_variation' => $variationSolde > 0 ? 'Augmentation' : 'Diminution'
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'periode1' => ['date' => $date1, 'balance' => $balance1],
            'periode2' => ['date' => $date2, 'balance' => $balance2],
            'variations' => $comparaison,
            'statistiques' => [
                'nombre_comptes_varies' => count($comparaison),
                'variation_totale_positive' => array_sum(array_column(array_filter($comparaison, fn($v) => $v['variation_solde'] > 0), 'variation_solde')),
                'variation_totale_negative' => array_sum(array_column(array_filter($comparaison, fn($v) => $v['variation_solde'] < 0), 'variation_solde'))
            ]
        ];
    }

    /**
     * Trouve une ligne dans une balance
     */
    private function trouverLigneBalance(array $balance, string $compteCode): ?array
    {
        foreach ($balance as $ligne) {
            if ($ligne['compte_code'] === $compteCode) {
                return $ligne;
            }
        }
        return null;
    }

    /**
     * Génère un rapport d'analyse de la balance
     */
    public function analyserBalance(string $dateFin = null): array
    {
        $balance = $this->genererBalance($dateFin);
        
        $analyse = [
            'structure_actif_passif' => $this->analyserStructureActifPassif($balance),
            'structure_resultat' => $this->analyserStructureResultat($balance),
            'comptes_sensibles' => $this->identifierComptesSensibles($balance),
            'anomalies' => $this->detecterAnomalies($balance)
        ];
        
        return [
            'success' => true,
            'balance' => $balance,
            'analyse' => $analyse,
            'date_analyse' => $dateFin ?? date('Y-m-d H:i:s'),
            'recommandations' => $this->genererRecommandations($analyse)
        ];
    }

    /**
     * Analyse la structure actif/passif
     */
    private function analyserStructureActifPassif(array $balance): array
    {
        $structure = [
            'actif_total' => 0,
            'passif_total' => 0,
            'capitaux_propres' => 0,
            'dettes_long_terme' => 0,
            'dettes_court_terme' => 0
        ];
        
        foreach ($balance['balance_par_type'] as $type => $donnees) {
            if ($type === 'ACTIF') {
                $structure['actif_total'] += $donnees['solde'];
            } elseif ($type === 'PASSIF') {
                $structure['passif_total'] += $donnees['solde'];
            }
        }
        
        $structure['equilibre_actif_passif'] = abs($structure['actif_total'] - $structure['passif_total']) < 0.01;
        
        return $structure;
    }

    /**
     * Analyse la structure du compte de résultat
     */
    private function analyserStructureResultat(array $balance): array
    {
        $structure = [
            'charges_total' => 0,
            'produits_total' => 0,
            'resultat_exploitation' => 0,
            'resultat_net' => 0
        ];
        
        foreach ($balance['balance_par_type'] as $type => $donnees) {
            if ($type === 'CHARGE') {
                $structure['charges_total'] += $donnees['solde'];
            } elseif ($type === 'PRODUIT') {
                $structure['produits_total'] += $donnees['solde'];
            }
        }
        
        $structure['resultat_exploitation'] = $structure['produits_total'] - $structure['charges_total'];
        $structure['resultat_net'] = $structure['resultat_exploitation']; // Simplifié pour l'instant
        
        return $structure;
    }

    /**
     * Identifie les comptes sensibles
     */
    private function identifierComptesSensibles(array $balance): array
    {
        $comptesSensibles = [];
        
        foreach ($balance['lignes_balance'] as $ligne) {
            // Comptes avec soldes importants
            if (abs($ligne['solde']) > 1000000) { // > 1M FCFA
                $comptesSensibles[] = [
                    'compte_code' => $ligne['compte_code'],
                    'compte_libelle' => $ligne['compte_libelle'],
                    'solde' => $ligne['solde'],
                    'type_sensibilite' => 'MONTANT_ELEVE',
                    'niveau_risque' => abs($ligne['solde']) > 5000000 ? 'ÉLEVÉ' : 'MOYEN'
                ];
            }
            
            // Comptes caisse et banques (SYSCOHADA)
            if (in_array(substr($ligne['compte_code'], 0, 3), ['571', '521', '531'])) {
                $comptesSensibles[] = [
                    'compte_code' => $ligne['compte_code'],
                    'compte_libelle' => $ligne['compte_libelle'],
                    'solde' => $ligne['solde'],
                    'type_sensibilite' => 'TRESORERIE',
                    'niveau_risque' => 'ÉLEVÉ'
                ];
            }
        }
        
        return $comptesSensibles;
    }

    /**
     * Détecte les anomalies dans la balance
     */
    private function detecterAnomalies(array $balance): array
    {
        $anomalies = [];
        
        // Comptes avec soldes négatifs inattendus
        foreach ($balance['lignes_balance'] as $ligne) {
            if ($ligne['compte_type'] === 'ACTIF' && $ligne['solde'] < 0) {
                $anomalies[] = [
                    'type' => 'SOLDE_NEGATIF_ACTIF',
                    'compte_code' => $ligne['compte_code'],
                    'compte_libelle' => $ligne['compte_libelle'],
                    'solde' => $ligne['solde'],
                    'description' => 'Compte d\'actif avec solde négatif'
                ];
            }
            
            if ($ligne['compte_type'] === 'PASSIF' && $ligne['solde'] > 0) {
                $anomalies[] = [
                    'type' => 'SOLDE_POSITIF_PASSIF',
                    'compte_code' => $ligne['compte_code'],
                    'compte_libelle' => $ligne['compte_libelle'],
                    'solde' => $ligne['solde'],
                    'description' => 'Compte de passif avec solde positif'
                ];
            }
        }
        
        return $anomalies;
    }

    /**
     * Génère des recommandations
     */
    private function genererRecommandations(array $analyse): array
    {
        $recommandations = [];
        
        // Recommandations basées sur les anomalies
        if (!empty($analyse['anomalies'])) {
            $recommandations[] = [
                'type' => 'CORRECTION_ANOMALIES',
                'priorite' => 'ÉLEVÉE',
                'description' => 'Corriger les soldes anormaux des comptes',
                'actions' => ['Vérifier les écritures', 'Corriger les erreurs de saisie', 'Valider les reports']
            ];
        }
        
        // Recommandations basées sur les comptes sensibles
        if (!empty($analyse['comptes_sensibles'])) {
            $recommandations[] = [
                'type' => 'CONTROLE_COMPTES_SENSIBLES',
                'priorite' => 'ÉLEVÉE',
                'description' => 'Renforcer le contrôle des comptes sensibles',
                'actions' => ['Rapprochements fréquents', 'Validation manuelle', 'Audit approfondi']
            ];
        }
        
        return $recommandations;
    }
}
