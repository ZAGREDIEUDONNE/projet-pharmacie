<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use PDOException;
use Exception;

class ComptabiliteAvanceeService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Clôture un exercice comptable
     */
    public function cloturerExercice(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Vérifier si l'exercice peut être clôturé
            $exercice = $this->getExercice($data['exercice']);
            if (!$exercice) {
                return [
                    'success' => false,
                    'message' => 'Exercice introuvable'
                ];
            }

            if ($exercice['statut'] === 'CLOTURE') {
                return [
                    'success' => false,
                    'message' => 'Exercice déjà clôturé'
                ];
            }

            // Calculer les totaux de l'exercice
            $totaux = $this->calculerTotauxExercice($data['exercice']);

            // Mettre à jour l'exercice
            $sql = "UPDATE exercices_comptables 
                    SET statut = 'CLOTURE', 
                        date_cloture = NOW(),
                        utilisateur_cloture_id = ?,
                        notes_cloture = ?,
                        total_debit = ?,
                        total_credit = ?,
                        solde_final = ?
                    WHERE exercice = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['utilisateur_id'],
                $data['notes_cloture'] ?? null,
                $totaux['total_debit'],
                $totaux['total_credit'],
                $totaux['solde_final'],
                $data['exercice']
            ]);

            // Créer l'exercice suivant
            $this->creerExerciceSuivant($data['exercice'], $totaux);

            // Générer le bilan de clôture
            $bilanCloture = $this->genererBilanCloture($data['exercice'], $totaux);

            $this->db->commit();

            // Logger l'opération
            $this->logComptabiliteOperation($data['utilisateur_id'], 'EXERCICE_CLOTURE', [
                'exercice' => $data['exercice'],
                'totaux' => $totaux
            ]);

            return [
                'success' => true,
                'totaux' => $totaux,
                'bilan_cloture' => $bilanCloture,
                'message' => 'Exercice clôturé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la clôture de l\'exercice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Exporte les données comptables en PDF
     */
    public function exporterPDF(array $data): array
    {
        try {
            $typeExport = $data['type_export'];
            $exercice = $data['exercice'] ?? date('Y');

            switch ($typeExport) {
                case 'bilan':
                    $donnees = $this->genererBilan($exercice);
                    break;
                case 'compte_resultat':
                    $donnees = $this->genererCompteResultat($exercice);
                    break;
                case 'journal_general':
                    $donnees = $this->genererJournalGeneral($exercice, $data);
                    break;
                case 'grand_livre':
                    $donnees = $this->genererGrandLivre($exercice, $data);
                    break;
                case 'balance':
                    $donnees = $this->genererBalance($exercice, $data);
                    break;
                default:
                    return [
                        'success' => false,
                        'message' => 'Type d\'export non reconnu'
                    ];
            }

            // Générer le PDF
            $pdfContent = $this->genererPDF($donnees, $typeExport, $exercice);

            // Sauvegarder le fichier
            $filename = $this->sauvegarderPDF($pdfContent, $typeExport, $exercice);

            return [
                'success' => true,
                'filename' => $filename,
                'content' => base64_encode($pdfContent),
                'message' => 'Export PDF généré avec succès'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Exporte les données comptables en Excel
     */
    public function exporterExcel(array $data): array
    {
        try {
            $typeExport = $data['type_export'];
            $exercice = $data['exercice'] ?? date('Y');

            switch ($typeExport) {
                case 'bilan':
                    $donnees = $this->genererBilan($exercice);
                    break;
                case 'compte_resultat':
                    $donnees = $this->genererCompteResultat($exercice);
                    break;
                case 'journal_general':
                    $donnees = $this->genererJournalGeneral($exercice, $data);
                    break;
                case 'grand_livre':
                    $donnees = $this->genererGrandLivre($exercice, $data);
                    break;
                case 'balance':
                    $donnees = $this->genererBalance($exercice, $data);
                    break;
                default:
                    return [
                        'success' => false,
                        'message' => 'Type d\'export non reconnu'
                    ];
            }

            // Générer le fichier Excel
            $excelContent = $this->genererExcel($donnees, $typeExport, $exercice);

            // Sauvegarder le fichier
            $filename = $this->sauvegarderExcel($excelContent, $typeExport, $exercice);

            return [
                'success' => true,
                'filename' => $filename,
                'content' => base64_encode($excelContent),
                'message' => 'Export Excel généré avec succès'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export Excel: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Génère le bilan SYSCOA
     */
    private function genererBilan(string $exercice): array
    {
        $sql = "SELECT
                    pc.classe AS classe_syscoa,
                    pc.nom_compte AS libelle_syscoa,
                    SUM(le.debit) AS total_debit,
                    SUM(le.credit) AS total_credit,
                    SUM(le.debit - le.credit) AS solde
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                WHERE YEAR(ec.date_ecriture) = ?
                  AND pc.classe IN ('1', '2', '3', '4', '5')
                GROUP BY pc.classe, pc.nom_compte
                ORDER BY pc.classe";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$exercice]);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'exercice' => $exercice,
            'type_document' => 'BILAN_SYSCOA',
            'date_generation' => date('Y-m-d H:i:s'),
            'donnees' => $resultats,
            'total_actif' => array_sum(array_column($resultats, 'solde')),
            'total_passif' => array_sum(array_column($resultats, 'solde'))
        ];
    }

    /**
     * Génère le compte de résultat SYSCOA
     */
    private function genererCompteResultat(string $exercice): array
    {
        $sql = "SELECT
                    pc.classe AS classe_syscoa,
                    pc.nom_compte AS libelle_syscoa,
                    SUM(le.debit) AS total_debit,
                    SUM(le.credit) AS total_credit,
                    SUM(le.credit - le.debit) AS solde
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                WHERE YEAR(ec.date_ecriture) = ?
                  AND pc.classe IN ('6', '7', '8')
                GROUP BY pc.classe, pc.nom_compte
                ORDER BY pc.classe";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$exercice]);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'exercice' => $exercice,
            'type_document' => 'COMPTE_RESULTAT_SYSCOA',
            'date_generation' => date('Y-m-d H:i:s'),
            'donnees' => $resultats,
            'total_charges' => array_sum(array_column(array_filter($resultats, fn($r) => in_array($r['classe_syscoa'], ['6', '7'], true)), 'solde')),
            'total_produits' => array_sum(array_column(array_filter($resultats, fn($r) => in_array($r['classe_syscoa'], ['7', '8'], true)), 'solde')),
            'resultat_net' => array_sum(array_column($resultats, 'solde'))
        ];
    }

    /**
     * Génère le journal général
     */
    private function genererJournalGeneral(string $exercice, array $filtres): array
    {
        $sql = "SELECT
                    ec.id,
                    ec.date_ecriture,
                    ec.numero_piece,
                    ec.libelle AS libelle_ecriture,
                    pc.numero_compte,
                    pc.nom_compte AS libelle_compte,
                    le.debit,
                    le.credit,
                    u.username AS utilisateur_nom
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                LEFT JOIN utilisateurs u ON ec.utilisateur_id = u.id
                WHERE YEAR(ec.date_ecriture) = ?";

        $params = [$exercice];

        if (!empty($filtres['date_debut'])) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $filtres['date_debut'];
        }

        if (!empty($filtres['date_fin'])) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $filtres['date_fin'];
        }

        $numeroCompte = $filtres['numero_compte'] ?? null;
        if (!empty($numeroCompte)) {
            $sql .= " AND le.compte_code = ?";
            $params[] = $numeroCompte;
        }

        $sql .= " ORDER BY ec.date_ecriture, ec.id, le.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'exercice' => $exercice,
            'type_document' => 'JOURNAL_GENERAL',
            'date_generation' => date('Y-m-d H:i:s'),
            'filtres' => $filtres,
            'donnees' => $resultats,
            'total_debit' => array_sum(array_column($resultats, 'debit')),
            'total_credit' => array_sum(array_column($resultats, 'credit'))
        ];
    }

    /**
     * Génère le grand livre
     */
    private function genererGrandLivre(string $exercice, array $filtres): array
    {
        $sql = "SELECT
                    pc.numero_compte,
                    pc.nom_compte AS libelle_compte,
                    pc.classe AS classe_syscoa,
                    ec.date_ecriture,
                    ec.numero_piece,
                    ec.libelle AS libelle_ecriture,
                    le.debit,
                    le.credit
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                WHERE YEAR(ec.date_ecriture) = ?";

        $params = [$exercice];

        $numeroCompte = $filtres['numero_compte'] ?? null;
        if (!empty($numeroCompte)) {
            $sql .= " AND le.compte_code = ?";
            $params[] = $numeroCompte;
        }

        $sql .= " ORDER BY pc.numero_compte, ec.date_ecriture, ec.id, le.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedByCompte = [];
        foreach ($resultats as $resultat) {
            $compte = $resultat['numero_compte'];
            if (!isset($groupedByCompte[$compte])) {
                $groupedByCompte[$compte] = [
                    'numero_compte' => $compte,
                    'libelle_compte' => $resultat['libelle_compte'],
                    'classe_syscoa' => $resultat['classe_syscoa'],
                    'mouvements' => [],
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'solde_final' => 0
                ];
            }
            $groupedByCompte[$compte]['mouvements'][] = $resultat;
            $groupedByCompte[$compte]['total_debit'] += (float) $resultat['debit'];
            $groupedByCompte[$compte]['total_credit'] += (float) $resultat['credit'];
        }

        foreach ($groupedByCompte as &$compteData) {
            $compteData['solde_final'] = $compteData['total_debit'] - $compteData['total_credit'];
        }
        unset($compteData);

        return [
            'exercice' => $exercice,
            'type_document' => 'GRAND_LIVRE',
            'date_generation' => date('Y-m-d H:i:s'),
            'filtres' => $filtres,
            'donnees' => array_values($groupedByCompte)
        ];
    }

    /**
     * Génère la balance
     */
    private function genererBalance(string $exercice, array $filtres): array
    {
        $sql = "SELECT
                    pc.numero_compte,
                    pc.nom_compte AS libelle_compte,
                    pc.classe AS classe_syscoa,
                    SUM(le.debit) AS total_debit,
                    SUM(le.credit) AS total_credit,
                    SUM(le.debit - le.credit) AS solde,
                    COUNT(le.id) AS nombre_ecritures
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                WHERE YEAR(ec.date_ecriture) = ?";

        $params = [$exercice];

        if (!empty($filtres['classe_syscoa'])) {
            $sql .= " AND pc.classe = ?";
            $params[] = $filtres['classe_syscoa'];
        }

        $sql .= " GROUP BY pc.numero_compte, pc.nom_compte, pc.classe
                   ORDER BY pc.numero_compte";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'exercice' => $exercice,
            'type_document' => 'BALANCE',
            'date_generation' => date('Y-m-d H:i:s'),
            'filtres' => $filtres,
            'donnees' => $resultats,
            'total_debit_general' => array_sum(array_column($resultats, 'total_debit')),
            'total_credit_general' => array_sum(array_column($resultats, 'total_credit'))
        ];
    }

    /**
     * Vérifie la conformité SYSCOA
     */
    public function verifierConformiteSyscoa(string $exercice): array
    {
        try {
            $verifications = [];

            // Vérification des classes SYSCOA
            $sql = "SELECT
                        pc.classe AS classe_syscoa,
                        COUNT(DISTINCT pc.id) AS nombre_comptes,
                        COUNT(CASE WHEN pc.classe NOT IN ('1','2','3','4','5','6','7','8') THEN 1 END) AS non_conformes
                    FROM lignes_ecritures le
                    JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                    JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                    WHERE YEAR(ec.date_ecriture) = ?
                    GROUP BY pc.classe";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$exercice]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $verifications['classes_syscoa'] = [
                'conformes' => array_sum(array_column($classes, 'nombre_comptes')) - array_sum(array_column($classes, 'non_conformes')),
                'non_conformes' => array_sum(array_column($classes, 'non_conformes')),
                'details' => $classes
            ];

            // Vérification de l'équilibre débit/crédit
            $sql = "SELECT
                        SUM(le.debit) AS total_debit,
                        SUM(le.credit) AS total_credit
                    FROM lignes_ecritures le
                    JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                    WHERE YEAR(ec.date_ecriture) = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$exercice]);
            $equilibre = $stmt->fetch(PDO::FETCH_ASSOC);

            $verifications['equilibre'] = [
                'total_debit' => $equilibre['total_debit'],
                'total_credit' => $equilibre['total_credit'],
                'ecart' => abs($equilibre['total_debit'] - $equilibre['total_credit']),
                'equilibre' => abs($equilibre['total_debit'] - $equilibre['total_credit']) < 0.01
            ];

            // Vérification des comptes de résultat
            $sql = "SELECT
                        COUNT(DISTINCT pc.id) AS nombre_comptes_resultat
                    FROM lignes_ecritures le
                    JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                    JOIN plan_comptable pc ON " . DatabaseSql::joinPlanComptableOnCompteCode() . "
                    WHERE YEAR(ec.date_ecriture) = ?
                      AND pc.classe IN ('6','7','8')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$exercice]);
            $comptesResultat = $stmt->fetch(PDO::FETCH_ASSOC);

            $verifications['comptes_resultat'] = [
                'nombre_comptes' => $comptesResultat['nombre_comptes_resultat'],
                'conforme' => $comptesResultat['nombre_comptes_resultat'] > 0
            ];

            return [
                'success' => true,
                'exercice' => $exercice,
                'date_verification' => date('Y-m-d H:i:s'),
                'verifications' => $verifications,
                'conforme' => $verifications['equilibre']['equilibre'] && $verifications['comptes_resultat']['conforme'] && $verifications['classes_syscoa']['non_conformes'] === 0
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification SYSCOA: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère un exercice
     */
    private function getExercice(string $exercice): ?array
    {
        $sql = "SELECT * FROM exercices_comptables WHERE exercice = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$exercice]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Calcule les totaux d'un exercice
     */
    private function calculerTotauxExercice(string $exercice): array
    {
        $sql = "SELECT 
                    SUM(CASE WHEN sens = 'DEBIT' THEN montant ELSE 0 END) as total_debit,
                    SUM(CASE WHEN sens = 'CREDIT' THEN montant ELSE 0 END) as total_credit
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                WHERE YEAR(date_ecriture) = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$exercice]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_debit' => (float)$result['total_debit'],
            'total_credit' => (float)$result['total_credit'],
            'solde_final' => (float)($result['total_debit'] - $result['total_credit'])
        ];
    }

    /**
     * Crée l'exercice suivant
     */
    private function creerExerciceSuivant(string $exerciceActuel, array $totaux): void
    {
        $exerciceSuivant = (int)$exerciceActuel + 1;
        
        $sql = "INSERT IGNORE INTO exercices_comptables 
                    (exercice, date_debut, date_fin, statut, solde_precedent) 
                    VALUES (?, ?, ?, 'OUVERT', ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $exerciceSuivant,
            $exerciceSuivant . '-01-01',
            $exerciceSuivant . '-12-31',
            $totaux['solde_final']
        ]);
    }

    /**
     * Génère le bilan de clôture
     */
    private function genererBilanCloture(string $exercice, array $totaux): array
    {
        return [
            'exercice' => $exercice,
            'date_cloture' => date('Y-m-d H:i:s'),
            'totaux' => $totaux,
            'type_document' => 'BILAN_CLOTURE_SYSCOA'
        ];
    }

    /**
     * Génère un fichier PDF (simulation)
     */
    private function genererPDF(array $donnees, string $type, string $exercice): string
    {
        // Simulation de génération PDF
        $filename = "export_{$type}_{$exercice}_" . date('YmdHis') . ".pdf";
        
        $content = "Export {$type} - Exercice {$exercice}\n\n";
        $content .= json_encode($donnees, JSON_PRETTY_PRINT);
        
        return $content;
    }

    /**
     * Génère un fichier Excel (simulation)
     */
    private function genererExcel(array $donnees, string $type, string $exercice): string
    {
        // Simulation de génération Excel
        $filename = "export_{$type}_{$exercice}_" . date('YmdHis') . ".xlsx";
        
        $content = "Export {$type} - Exercice {$exercice}\n\n";
        $content .= json_encode($donnees, JSON_PRETTY_PRINT);
        
        return $content;
    }

    /**
     * Sauvegarde un fichier PDF
     */
    private function sauvegarderPDF(string $content, string $type, string $exercice): string
    {
        $filename = "exports/pdf/export_{$type}_{$exercice}_" . date('YmdHis') . ".pdf";
        $filepath = __DIR__ . "/../../{$filename}";
        
        file_put_contents($filepath, $content);
        
        return $filename;
    }

    /**
     * Sauvegarde un fichier Excel
     */
    private function sauvegarderExcel(string $content, string $type, string $exercice): string
    {
        $filename = "exports/excel/export_{$type}_{$exercice}_" . date('YmdHis') . ".xlsx";
        $filepath = __DIR__ . "/../../{$filename}";
        
        file_put_contents($filepath, $content);
        
        return $filename;
    }

    /**
     * Logger les opérations comptables
     */
    private function logComptabiliteOperation(int $utilisateurId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs (
                        utilisateur_id, action, table_name, new_values, 
                        ip_address, user_agent, date_action
                    ) VALUES (?, 'COMPTABILITE_OPERATION', 'exercices_comptables', ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $utilisateurId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log comptabilite operation: " . $e->getMessage());
        }
    }
}
