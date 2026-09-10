<?php

namespace App\Services;

use App\Core\DatabaseSql;
use PDO;
use PDOException;
use Exception;

class GrandLivreService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    private function joinPlanComptableOnCompteCode(): string
    {
        return DatabaseSql::equals('le.compte_code', 'pc.numero_compte');
    }

    private function whereCompteCodeEqualsParam(): string
    {
        return DatabaseSql::collate('le.compte_code') . ' = ?';
    }

    /**
     * Récupère le grand livre pour un compte
     */
    public function getGrandLivreCompte(string $compteCode, string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT 
                    ec.id as ecriture_id,
                    ec.date_ecriture,
                    ec.numero_piece,
                    ec.libelle as ecriture_libelle,
                    ec.reference_type,
                    ec.reference_id,
                    le.debit,
                    le.credit,
                    le.libelle as ligne_libelle,
                    j.code as journal_code,
                    j.libelle as journal_libelle,
                    u.username as utilisateur_nom,
                    c.nom as client_nom,
                    f.nom as fournisseur_nom,
                    le.tiers_id
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN journaux_comptables j ON ec.journal_id = j.id
                LEFT JOIN utilisateurs u ON ec.utilisateur_id = u.id
                LEFT JOIN clients c ON le.tiers_id = c.id AND le.reference_type = 'VENTE'
                LEFT JOIN fournisseurs f ON le.tiers_id = f.id AND le.reference_type = 'ACHAT'
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                AND " . $this->whereCompteCodeEqualsParam();
        
        $params = [$compteCode];
        
        if ($dateDebut) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " ORDER BY ec.date_ecriture ASC, ec.numero_piece ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les soldes cumulés
        $soldePrecedent = $this->getSoldePrecedent($compteCode, $dateDebut);
        $soldeCumule = $soldePrecedent;
        
        foreach ($lignes as &$ligne) {
            $soldeCumule += $ligne['debit'] - $ligne['credit'];
            $ligne['solde_cumule'] = $soldeCumule;
            $ligne['solde_precedent'] = $soldePrecedent;
        }
        
        return [
            'compte_code' => $compteCode,
            'solde_precedent' => $soldePrecedent,
            'solde_final' => $soldeCumule,
            'lignes' => $lignes,
            'total_debit' => array_sum(array_column($lignes, 'debit')),
            'total_credit' => array_sum(array_column($lignes, 'credit')),
            'nombre_ecritures' => count($lignes)
        ];
    }

    /**
     * Récupère le grand livre pour une période
     */
    public function getGrandLivrePeriode(string $dateDebut, string $dateFin, array $comptes = []): array
    {
        if (empty($comptes)) {
            // Récupérer tous les comptes avec des mouvements
            $sql = "SELECT DISTINCT le.compte_code, pc.libelle as compte_libelle
                    FROM lignes_ecritures le
                    JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                    JOIN plan_comptable pc ON " . $this->joinPlanComptableOnCompteCode() . "
                    WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                    AND ec.date_ecriture BETWEEN ? AND ?
                    ORDER BY le.compte_code";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dateDebut, $dateFin]);
            $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $resultats = [];
        $totalGeneral = [
            'total_debit' => 0,
            'total_credit' => 0,
            'total_ecritures' => 0
        ];
        
        foreach ($comptes as $compte) {
            $compteCode = $compte['compte_code'] ?? $compte;
            $grandLivre = $this->getGrandLivreCompte($compteCode, $dateDebut, $dateFin);
            
            $resultats[] = $grandLivre;
            
            $totalGeneral['total_debit'] += $grandLivre['total_debit'];
            $totalGeneral['total_credit'] += $grandLivre['total_credit'];
            $totalGeneral['total_ecritures'] += $grandLivre['nombre_ecritures'];
        }
        
        return [
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ],
            'comptes' => $resultats,
            'total_general' => $totalGeneral
        ];
    }

    /**
     * Récupère le solde précédent d'un compte
     */
    private function getSoldePrecedent(string $compteCode, string $dateDebut = null): float
    {
        if (!$dateDebut) {
            return 0;
        }
        
        $sql = "SELECT 
                    SUM(le.debit) - SUM(le.credit) as solde
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                WHERE " . ComptabiliteEcritureScope::valide('ec') . "
                AND " . $this->whereCompteCodeEqualsParam() . "
                AND ec.date_ecriture < ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$compteCode, $dateDebut]);
        
        return (float) $stmt->fetchColumn() ?: 0;
    }

    /**
     * Récupère les soldes de tous les comptes
     */
    public function getSoldesComptes(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    le.compte_code,
                    pc.libelle as compte_libelle,
                    pc.type as compte_type,
                    pc.classe_id,
                    cc.libelle as classe_libelle,
                    SUM(le.debit) as total_debit,
                    SUM(le.credit) as total_credit,
                    SUM(le.debit) - SUM(le.credit) as solde
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . $this->joinPlanComptableOnCompteCode() . "
                JOIN classes_comptes cc ON " . DatabaseSql::equals('CAST(pc.classe AS CHAR)', 'cc.code') . "
                WHERE ec.date_ecriture <= ?
                GROUP BY le.compte_code, pc.libelle, pc.type, pc.classe_id, cc.libelle
                HAVING solde != 0
                ORDER BY le.compte_code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le grand livre par classe de comptes
     */
    public function getGrandLivreParClasse(string $classeId, string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT 
                    le.compte_code,
                    pc.libelle as compte_libelle,
                    ec.date_ecriture,
                    ec.numero_piece,
                    ec.libelle as ecriture_libelle,
                    le.debit,
                    le.credit,
                    j.code as journal_code,
                    j.libelle as journal_libelle
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . $this->joinPlanComptableOnCompteCode() . "
                JOIN journaux_comptables j ON ec.journal_id = j.id
                WHERE pc.classe_id = ?";
        
        $params = [$classeId];
        
        if ($dateDebut) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " ORDER BY le.compte_code, ec.date_ecriture ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les écritures par type de référence
     */
    public function getEcrituresParReference(string $referenceType, string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT 
                    ec.id as ecriture_id,
                    ec.date_ecriture,
                    ec.numero_piece,
                    ec.libelle as ecriture_libelle,
                    ec.reference_id,
                    le.compte_code,
                    pc.libelle as compte_libelle,
                    le.debit,
                    le.credit,
                    j.code as journal_code,
                    j.libelle as journal_libelle,
                    u.username as utilisateur_nom
                FROM ecritures_comptables ec
                JOIN lignes_ecritures le ON ec.id = le.ecriture_id
                JOIN plan_comptable pc ON " . $this->joinPlanComptableOnCompteCode() . "
                JOIN journaux_comptables j ON ec.journal_id = j.id
                LEFT JOIN utilisateurs u ON ec.utilisateur_id = u.id
                WHERE ec.reference_type = ?";
        
        $params = [$referenceType];
        
        if ($dateDebut) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " ORDER BY ec.date_ecriture DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte le grand livre
     */
    public function exporterGrandLivre(string $compteCode, string $dateDebut, string $dateFin): array
    {
        $grandLivre = $this->getGrandLivreCompte($compteCode, $dateDebut, $dateFin);
        
        $exportData = [];
        
        // En-tête
        $exportData[] = [
            'Date' => 'Date',
            'Pièce' => 'N° Pièce',
            'Libellé' => 'Libellé',
            'Débit' => 'Débit',
            'Crédit' => 'Crédit',
            'Solde' => 'Solde',
            'Journal' => 'Journal',
            'Référence' => 'Référence'
        ];
        
        // Solde précédent
        $exportData[] = [
            'Date' => $dateDebut,
            'Pièce' => '',
            'Libellé' => 'SOLDE PRÉCÉDENT',
            'Débit' => '',
            'Crédit' => '',
            'Solde' => number_format($grandLivre['solde_precedent'], 2, ',', ' '),
            'Journal' => '',
            'Référence' => ''
        ];
        
        // Lignes du grand livre
        foreach ($grandLivre['lignes'] as $ligne) {
            $exportData[] = [
                'Date' => date('d/m/Y H:i', strtotime($ligne['date_ecriture'])),
                'Pièce' => $ligne['numero_piece'],
                'Libellé' => $ligne['ligne_libelle'],
                'Débit' => $ligne['debit'] > 0 ? number_format($ligne['debit'], 2, ',', ' ') : '',
                'Crédit' => $ligne['credit'] > 0 ? number_format($ligne['credit'], 2, ',', ' ') : '',
                'Solde' => number_format($ligne['solde_cumule'], 2, ',', ' '),
                'Journal' => $ligne['journal_libelle'],
                'Référence' => $ligne['reference_type'] . ' #' . $ligne['reference_id']
            ];
        }
        
        // Totaux
        $exportData[] = [
            'Date' => $dateFin,
            'Pièce' => '',
            'Libellé' => 'TOTAUX',
            'Débit' => number_format($grandLivre['total_debit'], 2, ',', ' '),
            'Crédit' => number_format($grandLivre['total_credit'], 2, ',', ' '),
            'Solde' => number_format($grandLivre['solde_final'], 2, ',', ' '),
            'Journal' => '',
            'Référence' => ''
        ];
        
        return [
            'compte_code' => $compteCode,
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ],
            'donnees' => $exportData,
            'statistiques' => [
                'solde_precedent' => $grandLivre['solde_precedent'],
                'solde_final' => $grandLivre['solde_final'],
                'total_debit' => $grandLivre['total_debit'],
                'total_credit' => $grandLivre['total_credit'],
                'nombre_ecritures' => $grandLivre['nombre_ecritures']
            ]
        ];
    }

    /**
     * Recherche dans le grand livre
     */
    public function rechercherGrandLivre(array $criteres): array
    {
        $sql = "SELECT 
                    ec.id as ecriture_id,
                    ec.date_ecriture,
                    ec.numero_piece,
                    ec.libelle as ecriture_libelle,
                    ec.reference_type,
                    ec.reference_id,
                    le.compte_code,
                    pc.libelle as compte_libelle,
                    le.debit,
                    le.credit,
                    j.code as journal_code,
                    j.libelle as journal_libelle,
                    u.username as utilisateur_nom
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                JOIN plan_comptable pc ON " . $this->joinPlanComptableOnCompteCode() . "
                JOIN journaux_comptables j ON ec.journal_id = j.id
                LEFT JOIN utilisateurs u ON ec.utilisateur_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Filtre par compte
        if (!empty($criteres['compte_code'])) {
            $sql .= ' AND ' . $this->whereCompteCodeEqualsParam();
            $params[] = $criteres['compte_code'];
        }
        
        // Filtre par classe
        if (!empty($criteres['classe_id'])) {
            $sql .= " AND pc.classe_id = ?";
            $params[] = $criteres['classe_id'];
        }
        
        // Filtre par type de compte
        if (!empty($criteres['type_compte'])) {
            $sql .= " AND pc.type LIKE ?";
            $params[] = "%" . $criteres['type_compte'] . "%";
        }
        
        // Filtre par journal
        if (!empty($criteres['journal_code'])) {
            $sql .= " AND j.code = ?";
            $params[] = $criteres['journal_code'];
        }
        
        // Filtre par type de référence
        if (!empty($criteres['reference_type'])) {
            $sql .= " AND ec.reference_type = ?";
            $params[] = $criteres['reference_type'];
        }
        
        // Filtre par période
        if (!empty($criteres['date_debut'])) {
            $sql .= " AND ec.date_ecriture >= ?";
            $params[] = $criteres['date_debut'];
        }
        
        if (!empty($criteres['date_fin'])) {
            $sql .= " AND ec.date_ecriture <= ?";
            $params[] = $criteres['date_fin'];
        }
        
        // Filtre par montant
        if (!empty($criteres['montant_min'])) {
            $sql .= " AND (le.debit >= ? OR le.credit >= ?)";
            $params[] = $criteres['montant_min'];
            $params[] = $criteres['montant_min'];
        }
        
        if (!empty($criteres['montant_max'])) {
            $sql .= " AND (le.debit <= ? OR le.credit <= ?)";
            $params[] = $criteres['montant_max'];
            $params[] = $criteres['montant_max'];
        }
        
        // Recherche textuelle
        if (!empty($criteres['recherche'])) {
            $sql .= " AND (ec.libelle LIKE ? OR le.libelle LIKE ? OR ec.numero_piece LIKE ?)";
            $recherche = "%" . $criteres['recherche'] . "%";
            $params[] = $recherche;
            $params[] = $recherche;
            $params[] = $recherche;
        }
        
        $sql .= " ORDER BY ec.date_ecriture DESC";
        
        if (!empty($criteres['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $criteres['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques du grand livre
     */
    public function getStatistiquesGrandLivre(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    COUNT(DISTINCT le.compte_code) as nombre_comptes,
                    COUNT(DISTINCT ec.id) as nombre_ecritures,
                    SUM(le.debit) as total_debit,
                    SUM(le.credit) as total_credit,
                    COUNT(DISTINCT ec.journal_id) as nombre_journaux,
                    COUNT(DISTINCT ec.utilisateur_id) as nombre_utilisateurs
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                WHERE ec.date_ecriture BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ajouter les statistiques par classe
        $sqlClasses = "SELECT 
                           pc.classe_id,
                           cc.libelle as classe_libelle,
                           COUNT(DISTINCT le.compte_code) as nombre_comptes,
                           SUM(le.debit) as total_debit,
                           SUM(le.credit) as total_credit,
                           SUM(le.debit) - SUM(le.credit) as solde_classe
                        FROM lignes_ecritures le
                        JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                        JOIN plan_comptable pc ON " . $this->joinPlanComptableOnCompteCode() . "
                        JOIN classes_comptes cc ON pc.classe_id = cc.code
                        WHERE ec.date_ecriture BETWEEN ? AND ?
                        GROUP BY pc.classe_id, cc.libelle
                        ORDER BY pc.classe_id";
        
        $stmt = $this->db->prepare($sqlClasses);
        $stmt->execute([$dateDebut, $dateFin]);
        
        $stats['par_classe'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    /**
     * Valide l'équilibre du grand livre
     */
    public function validerEquilibreGrandLivre(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    SUM(le.debit) as total_debit,
                    SUM(le.credit) as total_credit,
                    SUM(le.debit) - SUM(le.credit) as solde_general
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON le.ecriture_id = ec.id
                WHERE ec.date_ecriture <= ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFin]);
        
        $resultats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $equilibre = abs($resultats['total_debit'] - $resultats['total_credit']) < 0.01;
        
        return [
            'equilibre' => $equilibre,
            'total_debit' => $resultats['total_debit'],
            'total_credit' => $resultats['total_credit'],
            'solde_general' => $resultats['solde_general'],
            'ecart' => abs($resultats['total_debit'] - $resultats['total_credit']),
            'date_controle' => $dateFin,
            'message' => $equilibre ? 'Le grand livre est équilibré' : 'Le grand livre présente un déséquilibre'
        ];
    }
}
