<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use PDOException;
use Exception;

class TVAService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Initialise les taux de TVA
     */
    public function initialiserTVA(): array
    {
        $this->db->beginTransaction();
        
        try {
            $tauxTVA = [
                ['code' => 'TVA_0', 'taux' => 0, 'libelle' => 'TVA 0%', 'description' => 'Exonération TVA'],
                ['code' => 'TVA_5_5', 'taux' => 0.055, 'libelle' => 'TVA 5.5%', 'description' => 'Taux réduit pour médicaments'],
                ['code' => 'TVA_10', 'taux' => 0.10, 'libelle' => 'TVA 10%', 'description' => 'Taux intermédiaire'],
                ['code' => 'TVA_18', 'taux' => 0.18, 'libelle' => 'TVA 18%', 'description' => 'Taux normal'],
                ['code' => 'TVA_19_25', 'taux' => 0.1925, 'libelle' => 'TVA 19.25%', 'description' => 'Taux majoré']
            ];

            $sql = "INSERT IGNORE INTO tva_taux (code, taux, libelle, description, is_actif) VALUES (?, ?, ?, ?, 1)";
            $stmt = $this->db->prepare($sql);
            
            foreach ($tauxTVA as $taux) {
                $stmt->execute([
                    $taux['code'],
                    $taux['taux'],
                    $taux['libelle'],
                    $taux['description']
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Taux de TVA initialisés avec succès',
                'nombre_taux' => count($tauxTVA)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de l'initialisation des taux de TVA: " . $e->getMessage());
        }
    }

    /**
     * Calcule la TVA pour un montant
     */
    public function calculerTVA(float $montantHT, string $codeTVA): array
    {
        $taux = $this->getTauxTVA($codeTVA);
        if (!$taux) {
            throw new Exception("Taux de TVA '$codeTVA' non trouvé");
        }

        $montantTVA = $montantHT * $taux['taux'];
        $montantTTC = $montantHT + $montantTVA;

        return [
            'montant_ht' => round($montantHT, 2),
            'montant_tva' => round($montantTVA, 2),
            'montant_ttc' => round($montantTTC, 2),
            'taux_tva' => $taux['taux'],
            'code_tva' => $codeTVA,
            'libelle_tva' => $taux['libelle']
        ];
    }

    /**
     * Calcule le montant HT à partir du TTC
     */
    public function calculerHT(float $montantTTC, string $codeTVA): array
    {
        $taux = $this->getTauxTVA($codeTVA);
        if (!$taux) {
            throw new Exception("Taux de TVA '$codeTVA' non trouvé");
        }

        if ($taux['taux'] == 0) {
            $montantHT = $montantTTC;
        } else {
            $montantHT = $montantTTC / (1 + $taux['taux']);
        }

        $montantTVA = $montantHT * $taux['taux'];

        return [
            'montant_ht' => round($montantHT, 2),
            'montant_tva' => round($montantTVA, 2),
            'montant_ttc' => round($montantTTC, 2),
            'taux_tva' => $taux['taux'],
            'code_tva' => $codeTVA,
            'libelle_tva' => $taux['libelle']
        ];
    }

    /**
     * Applique la TVA sur une vente
     */
    public function appliquerTVAVente(array $vente): array
    {
        $lignesAvecTVA = [];
        $totalTVA = 0;
        $totalHT = 0;
        $totalTTC = 0;

        foreach ($vente['articles'] as $article) {
            $codeTVA = $this->determinerCodeTVAProduit($article['produit_id']);
            
            if ($article['prix_unitaire_ht']) {
                // Prix HT déjà fourni
                $montantHT = $article['quantite'] * $article['prix_unitaire_ht'];
                $calculTVA = $this->calculerTVA($montantHT, $codeTVA);
            } else {
                // Prix TTC fourni, calculer HT
                $montantTTC = $article['quantite'] * $article['prix_unitaire'];
                $calculTVA = $this->calculerHT($montantTTC, $codeTVA);
                $montantHT = $calculTVA['montant_ht'];
            }

            $lignesAvecTVA[] = array_merge($article, [
                'code_tva' => $codeTVA,
                'taux_tva' => $calculTVA['taux_tva'],
                'montant_ht_ligne' => $montantHT,
                'montant_tva_ligne' => $calculTVA['montant_tva'],
                'montant_ttc_ligne' => $calculTVA['montant_ttc']
            ]);

            $totalTVA += $calculTVA['montant_tva'];
            $totalHT += $montantHT;
            $totalTTC += $calculTVA['montant_ttc'];
        }

        return [
            'articles' => $lignesAvecTVA,
            'totaux' => [
                'total_ht' => round($totalHT, 2),
                'total_tva' => round($totalTVA, 2),
                'total_ttc' => round($totalTTC, 2),
                'taux_moyen_tva' => $totalHT > 0 ? round(($totalTVA / $totalHT) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Génère la déclaration TVA
     */
    public function genererDeclarationTVA(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    pc.numero_compte as compte_code,
                    COALESCE(pc.libelle, pc.nom_compte) as compte_libelle,
                    SUM(CASE WHEN le.credit > 0 THEN le.credit ELSE 0 END) as tva_collectee,
                    SUM(CASE WHEN le.debit > 0 THEN le.debit ELSE 0 END) as tva_deductible,
                    COUNT(DISTINCT le.ecriture_id) as nombre_ecritures
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                AND ec.date_ecriture BETWEEN ? AND ?
                AND le.compte_code LIKE '445%'
                GROUP BY pc.numero_compte, pc.libelle, pc.nom_compte
                ORDER BY pc.numero_compte";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);

        $lignesDeclaration = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les totaux
        $totalTVACollectee = array_sum(array_column($lignesDeclaration, 'tva_collectee'));
        $totalTVADeductible = array_sum(array_column($lignesDeclaration, 'tva_deductible'));
        $tvaDue = $totalTVACollectee - $totalTVADeductible;

        return [
            'success' => true,
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'lignes_declaration' => $lignesDeclaration,
            'totaux' => [
                'tva_collectee' => round($totalTVACollectee, 2),
                'tva_deductible' => round($totalTVADeductible, 2),
                'tva_due' => round($tvaDue, 2),
                'tva_a_decaisser' => max(0, $tvaDue),
                'credit_tva' => max(0, -$tvaDue)
            ],
            'message' => $tvaDue >= 0 ? 'TVA à décaisser' : 'Crédit de TVA'
        ];
    }

    /**
     * Récupère les taux de TVA actifs
     */
    public function getTauxTVAActifs(): array
    {
        $sql = "SELECT * FROM tva_taux WHERE is_actif = 1 ORDER BY taux";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un taux de TVA par son code
     */
    public function getTauxTVA(string $code): ?array
    {
        $sql = "SELECT * FROM tva_taux WHERE code = ? AND is_actif = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour un taux de TVA
     */
    public function mettreAJourTauxTVA(string $code, array $donnees): bool
    {
        $fields = [];
        $values = [];

        foreach ($donnees as $key => $value) {
            if (in_array($key, ['code', 'created_at'])) {
                continue;
            }
            $fields[] = "$key = ?";
            $values[] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $code;
        $sql = "UPDATE tva_taux SET " . implode(', ', $fields) . " WHERE code = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Désactive un taux de TVA
     */
    public function desactiverTauxTVA(string $code): bool
    {
        $sql = "UPDATE tva_taux SET is_actif = 0 WHERE code = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$code]);
    }

    /**
     * Récupère le code TVA par produit
     */
    private function determinerCodeTVAProduit(int $produitId): string
    {
        $sql = "SELECT p.code_tva_defaut 
                FROM produits p 
                WHERE p.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);

        $codeTVA = $stmt->fetchColumn();

        // Si pas de code par défaut, utiliser le taux normal
        return $codeTVA ?: 'TVA_18';
    }

    /**
     * Calcule les taxes parafiscales
     */
    public function calculerTaxesParafiscales(float $montantHT): array
    {
        // Taxes parafiscales applicables (à adapter selon la législation locale)
        $taxes = [
            'droit_accise' => $montantHT * 0.01, // 1% pour certains produits
            'contribution_cns' => $montantHT * 0.005, // 0.5% contribution au CNS
            'taxe_fodec' => $montantHT * 0.002 // 0.2% taxe FODEC
        ];

        $totalTaxes = array_sum($taxes);

        return [
            'montant_ht' => $montantHT,
            'taxes' => $taxes,
            'total_taxes' => round($totalTaxes, 2),
            'montant_ttc_taxes_incluses' => round($montantHT + $totalTaxes, 2)
        ];
    }

    /**
     * Génère le rapport de TVA
     */
    public function genererRapportTVA(string $dateDebut, string $dateFin): array
    {
        $declaration = $this->genererDeclarationTVA($dateDebut, $dateFin);

        // Calculer les statistiques supplémentaires
        $sql = "SELECT 
                    COUNT(DISTINCT ec.reference_id) as nombre_operations,
                    COUNT(DISTINCT ec.utilisateur_id) as nombre_utilisateurs,
                    MIN(ec.date_ecriture) as premiere_ecriture,
                    MAX(ec.date_ecriture) as derniere_ecriture
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                AND ec.date_ecriture BETWEEN ? AND ?
                AND le.compte_code LIKE '445%'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'periode' => $declaration['periode'],
            'declaration' => $declaration['lignes_declaration'],
            'totaux' => $declaration['totaux'],
            'statistiques' => $stats,
            'graphique' => [
                'tva_collectee' => array_sum(array_column($declaration['lignes_declaration'], 'tva_collectee')),
                'tva_deductible' => array_sum(array_column($declaration['lignes_declaration'], 'tva_deductible')),
                'tva_due' => $declaration['totaux']['tva_due']
            ],
            'recommandations' => $this->genererRecommandationsTVA($declaration['totaux'])
        ];
    }

    /**
     * Génère des recommandations TVA
     */
    private function genererRecommandationsTVA(array $totaux): array
    {
        $recommandations = [];

        if ($totaux['tva_due'] > 1000000) { // > 1M FCFA
            $recommandations[] = [
                'type' => 'PAIEMENT_URGENT',
                'priorite' => 'ÉLEVÉE',
                'message' => 'TVA à décaisser importante, procéder au paiement dans les délais légaux',
                'montant' => $totaux['tva_due']
            ];
        }

        if ($totaux['tva_due'] > 5000000) { // > 5M FCFA
            $recommandations[] = [
                'type' => 'OPTIMISATION',
                'priorite' => 'MOYENNE',
                'message' => 'Envisager l\'optimisation de la déclaration TVA',
                'suggestions' => [
                    'Vérifier les déductions',
                    'Optimiser la collecte',
                    'Revoir les taux applicables'
                ]
            ];
        }

        return $recommandations;
    }

    /**
     * Exporte la déclaration TVA
     */
    public function exporterDeclarationTVA(string $dateDebut, string $dateFin): array
    {
        $declaration = $this->genererDeclarationTVA($dateDebut, $dateFin);

        $exportData = [];
        
        // En-tête
        $exportData[] = [
            'Compte' => 'Compte',
            'Libellé' => 'Libellé Compte',
            'TVA Collectée' => 'TVA Collectée',
            'TVA Déductible' => 'TVA Déductible',
            'Solde TVA' => 'Solde TVA',
            'Nombre Écritures' => 'Nombre Écritures'
        ];

        // Lignes
        foreach ($declaration['lignes_declaration'] as $ligne) {
            $soldeTVA = $ligne['tva_collectee'] - $ligne['tva_deductible'];
            
            $exportData[] = [
                'Compte' => $ligne['compte_code'],
                'Libellé' => $ligne['compte_libelle'],
                'TVA Collectée' => number_format($ligne['tva_collectee'], 2, ',', ' '),
                'TVA Déductible' => number_format($ligne['tva_deductible'], 2, ',', ' '),
                'Solde TVA' => number_format($soldeTVA, 2, ',', ' '),
                'Nombre Écritures' => $ligne['nombre_ecritures']
            ];
        }

        // Totaux
        $totaux = $declaration['totaux'];
        $exportData[] = [
            'Compte' => '',
            'Libellé' => 'TOTAL GÉNÉRAL',
            'TVA Collectée' => number_format($totaux['tva_collectee'], 2, ',', ' '),
            'TVA Déductible' => number_format($totaux['tva_deductible'], 2, ',', ' '),
            'Solde TVA' => number_format($totaux['tva_due'], 2, ',', ' '),
            'Nombre Écritures' => ''
        ];

        return [
            'success' => true,
            'periode' => $declaration['periode'],
            'donnees' => $exportData,
            'totaux' => $totaux,
            'type' => 'declaration_tva'
        ];
    }

    /**
     * Valide un code TVA
     */
    public function validerCodeTVA(string $code): bool
    {
        $taux = $this->getTauxTVA($code);
        return $taux !== null;
    }

    /**
     * Récupère l'historique des déclarations TVA
     */
    public function getHistoriqueDeclarations(int $limit = 50): array
    {
        $sql = "SELECT * FROM tva_declarations 
                ORDER BY date_fin DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sauvegarde une déclaration TVA
     */
    public function sauvegarderDeclaration(array $declaration, int $utilisateurId): int
    {
        $sql = "INSERT INTO tva_declarations (
                    date_debut, date_fin, tva_collectee, tva_deductible, 
                    tva_due, statut, utilisateur_id, date_sauvegarde
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $declaration['date_debut'],
            $declaration['date_fin'],
            $declaration['tva_collectee'],
            $declaration['tva_deductible'],
            $declaration['tva_due'],
            'BROUILLON', // Statut initial
            $utilisateurId
        ]);

        return $this->db->lastInsertId();
    }
}
