<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class SuiviTiersService
{
    private PDO $db;
    private \App\Services\AuditService $auditService;

    public function __construct(PDO $db, \App\Services\AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Récupère le suivi des clients (créances) - basé sur les écritures comptables
     */
    public function getSuiviClientsComptable(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    c.id,
                    c.code,
                    c.nom,
                    c.prenom,
                    c.matricule,
                    c.telephone,
                    c.email,
                    c.plafond_credit,
                    c.type_client,
                    c.is_actif,
                    c.created_at,
                    COALESCE(SUM(CASE WHEN le.compte_code = '411' THEN le.debit ELSE 0 END), 0) as total_debit_client,
                    COALESCE(SUM(CASE WHEN le.compte_code = '411' THEN le.credit ELSE 0 END), 0) as total_credit_client,
                    COALESCE(SUM(CASE WHEN le.compte_code = '411' THEN le.debit - le.credit ELSE 0 END), 0) as solde_comptable,
                    COUNT(DISTINCT ec.id) as nombre_ecritures,
                    MAX(ec.date_ecriture) as derniere_ecriture
                FROM clients c
                LEFT JOIN lignes_ecritures le ON le.tiers_id = c.id AND le.compte_code = '411'
                LEFT JOIN ecritures_comptables ec ON le.ecriture_id = ec.id AND ec.date_ecriture <= ?
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.code, c.nom, c.prenom, c.matricule, c.telephone, c.email, c.plafond_credit, c.type_client, c.is_actif, c.created_at
                HAVING solde_comptable != 0 OR COUNT(DISTINCT ec.id) > 0
                ORDER BY solde_comptable DESC, c.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFin]);
        
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les statistiques
        $stats = $this->calculerStatistiquesClientsComptable($clients);
        
        return [
            'success' => true,
            'clients' => $clients,
            'statistiques' => $stats,
            'date_analyse' => $dateFin,
            'source' => 'ecritures_comptables',
            'message' => count($clients) . ' clients trouvés (source comptable)'
        ];
    }

    /**
     * Récupère le suivi des fournisseurs (dettes) - basé sur les écritures comptables
     */
    public function getSuiviFournisseursComptable(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    f.id,
                    f.nom,
                    f.telephone,
                    f.email,
                    f.adresse,
                    f.delai_livraison,
                    f.is_actif,
                    f.created_at,
                    COALESCE(SUM(CASE WHEN le.compte_code = '401' THEN le.credit ELSE 0 END), 0) as total_credit_fournisseur,
                    COALESCE(SUM(CASE WHEN le.compte_code = '401' THEN le.debit ELSE 0 END), 0) as total_debit_fournisseur,
                    COALESCE(SUM(CASE WHEN le.compte_code = '401' THEN le.credit - le.debit ELSE 0 END), 0) as solde_comptable,
                    COUNT(DISTINCT ec.id) as nombre_ecritures,
                    MAX(ec.date_ecriture) as derniere_ecriture
                FROM fournisseurs f
                LEFT JOIN lignes_ecritures le ON le.tiers_id = f.id AND le.compte_code = '401'
                LEFT JOIN ecritures_comptables ec ON le.ecriture_id = ec.id AND ec.date_ecriture <= ?
                WHERE f.deleted_at IS NULL
                GROUP BY f.id, f.nom, f.telephone, f.email, f.adresse, f.delai_livraison, f.is_actif, f.created_at
                HAVING solde_comptable != 0 OR COUNT(DISTINCT ec.id) > 0
                ORDER BY solde_comptable DESC, f.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFin]);
        
        $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les statistiques
        $stats = $this->calculerStatistiquesFournisseursComptable($fournisseurs);
        
        return [
            'success' => true,
            'fournisseurs' => $fournisseurs,
            'statistiques' => $stats,
            'date_analyse' => $dateFin,
            'source' => 'ecritures_comptables',
            'message' => count($fournisseurs) . ' fournisseurs trouvés (source comptable)'
        ];
    }

    /**
     * Récupère le suivi des clients (créances)
     */
    public function getSuiviClients(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    c.id,
                    c.code,
                    c.nom,
                    c.prenom,
                    c.matricule,
                    c.telephone,
                    c.email,
                    c.plafond_credit,
                    c.solde_credit,
                    c.type_client,
                    c.is_actif,
                    c.created_at,
                    COUNT(v.id) as nombre_ventes,
                    COALESCE(SUM(v.montant_net), 0) as total_achats,
                    COALESCE(SUM(CASE WHEN v.is_credit = 1 THEN v.montant_net ELSE 0 END), 0) as total_achats_credit,
                    MAX(v.date_vente) as derniere_vente,
                    DATEDIFF(CURDATE(), MAX(v.date_vente)) as jours_derniere_vente,
                    CASE 
                        WHEN c.solde_credit > 0 THEN 'DEBITEUR'
                        WHEN c.solde_credit < 0 THEN 'CREDITEUR'
                        ELSE 'SOLDE'
                    END as statut_client,
                    (c.plafond_credit - c.solde_credit) as credit_disponible,
                    CASE 
                        WHEN c.solde_credit > (c.plafond_credit * 0.8) THEN 'RISQUE ÉLEVÉ'
                        WHEN c.solde_credit > (c.plafond_credit * 0.5) THEN 'RISQUE MOYEN'
                        WHEN c.solde_credit > 0 THEN 'RISQUE FAIBLE'
                        ELSE 'SANS RISQUE'
                    END as niveau_risque,
                    CASE 
                        WHEN COUNT(v.id) = 0 THEN 'INACTIF'
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) > 90 THEN 'INACTIF'
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) > 30 THEN 'DORMANT'
                        ELSE 'ACTIF'
                    END as statut_activite
                FROM clients c
                LEFT JOIN ventes v ON c.id = v.client_id 
                    AND v.statut_vente != 'ANNULEE' 
                    AND v.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.code, c.nom, c.prenom, c.matricule, c.telephone, c.email, 
                         c.plafond_credit, c.solde_credit, c.type_client, c.is_actif, c.created_at
                HAVING c.solde_credit != 0 OR COUNT(v.id) > 0
                ORDER BY c.solde_credit DESC, c.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les statistiques
        $stats = $this->calculerStatistiquesClients($clients);
        
        return [
            'success' => true,
            'clients' => $clients,
            'statistiques' => $stats,
            'date_analyse' => $dateFin,
            'message' => count($clients) . ' clients trouvés'
        ];
    }

    /**
     * Récupère le suivi des fournisseurs (dettes)
     */
    public function getSuiviFournisseurs(string $dateFin = null): array
    {
        $dateFin = $dateFin ?? date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    f.id,
                    f.nom,
                    f.telephone,
                    f.email,
                    f.adresse,
                    f.delai_livraison,
                    f.is_actif,
                    f.created_at,
                    COUNT(c.id) as nombre_commandes,
                    COALESCE(SUM(c.montant_total), 0) as total_achats,
                    COALESCE(SUM(CASE WHEN c.statut_commande = 'LIVREE' THEN c.montant_total ELSE 0 END), 0) as total_livre,
                    COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) as total_en_attente,
                    MAX(c.date_commande) as derniere_commande,
                    DATEDIFF(CURDATE(), MAX(c.date_commande)) as jours_derniere_commande,
                    CASE 
                        WHEN COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) > 0 THEN 'DETTES'
                        ELSE 'SANS DETTES'
                    END as statut_fournisseur,
                    COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) as total_dettes,
                    CASE 
                        WHEN COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) > 1000000 THEN 'DETTE ÉLEVÉE'
                        WHEN COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) > 500000 THEN 'DETTE MOYENNE'
                        WHEN COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) > 0 THEN 'DETTE FAIBLE'
                        ELSE 'SANS DETTE'
                    END as niveau_dette,
                    CASE 
                        WHEN COUNT(c.id) = 0 THEN 'INACTIF'
                        WHEN DATEDIFF(CURDATE(), MAX(c.date_commande)) > 90 THEN 'INACTIF'
                        WHEN DATEDIFF(CURDATE(), MAX(c.date_commande)) > 30 THEN 'DORMANT'
                        ELSE 'ACTIF'
                    END as statut_activite,
                    AVG(DATEDIFF(c.date_livraison_prevue, c.date_commande)) as delai_moyen_livraison
                FROM fournisseurs f
                LEFT JOIN commandes c ON f.id = c.fournisseur_id 
                    AND c.deleted_at IS NULL
                WHERE f.deleted_at IS NULL
                GROUP BY f.id, f.nom, f.telephone, f.email, f.adresse, f.delai_livraison, f.is_actif, f.created_at
                HAVING COALESCE(SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END), 0) > 0 OR COUNT(c.id) > 0
                ORDER BY total_dettes DESC, f.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les statistiques
        $stats = $this->calculerStatistiquesFournisseurs($fournisseurs);
        
        return [
            'success' => true,
            'fournisseurs' => $fournisseurs,
            'statistiques' => $stats,
            'date_analyse' => $dateFin,
            'message' => count($fournisseurs) . ' fournisseurs trouvés'
        ];
    }

    /**
     * Récupère l'âge des créances clients
     */
    public function getAgeCreancesClients(): array
    {
        $sql = "SELECT 
                    c.id,
                    c.code,
                    c.nom,
                    c.prenom,
                    c.solde_credit,
                    DATEDIFF(CURDATE(), MAX(v.date_vente)) as jours_derniere_vente,
                    SUM(CASE WHEN v.is_credit = 1 THEN v.montant_net ELSE 0 END) as total_credit,
                    COUNT(CASE WHEN v.is_credit = 1 THEN 1 ELSE 0 END) as nombre_ventes_credit,
                    CASE 
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) <= 30 THEN '0-30 jours'
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) <= 60 THEN '31-60 jours'
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) <= 90 THEN '61-90 jours'
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) <= 180 THEN '91-180 jours'
                        WHEN DATEDIFF(CURDATE(), MAX(v.date_vente)) <= 365 THEN '181-365 jours'
                        ELSE '> 365 jours'
                    END as tranche_age,
                    SUM(CASE WHEN v.is_credit = 1 AND DATEDIFF(CURDATE(), v.date_vente) <= 30 THEN v.montant_net ELSE 0 END) as creance_0_30,
                    SUM(CASE WHEN v.is_credit = 1 AND DATEDIFF(CURDATE(), v.date_vente) BETWEEN 31 AND 60 THEN v.montant_net ELSE 0 END) as creance_31_60,
                    SUM(CASE WHEN v.is_credit = 1 AND DATEDIFF(CURDATE(), v.date_vente) BETWEEN 61 AND 90 THEN v.montant_net ELSE 0 END) as creance_61_90,
                    SUM(CASE WHEN v.is_credit = 1 AND DATEDIFF(CURDATE(), v.date_vente) BETWEEN 91 AND 180 THEN v.montant_net ELSE 0 END) as creance_91_180,
                    SUM(CASE WHEN v.is_credit = 1 AND DATEDIFF(CURDATE(), v.date_vente) > 180 THEN v.montant_net ELSE 0 END) as creance_plus_180
                FROM clients c
                LEFT JOIN ventes v ON c.id = v.client_id 
                    AND v.is_credit = 1 
                    AND v.statut_vente != 'ANNULEE' 
                    AND v.deleted_at IS NULL
                WHERE c.deleted_at IS NULL 
                AND c.solde_credit > 0
                GROUP BY c.id, c.code, c.nom, c.prenom, c.solde_credit
                ORDER BY c.solde_credit DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'âge des dettes fournisseurs
     */
    public function getAgeDettesFournisseurs(): array
    {
        $sql = "SELECT 
                    f.id,
                    f.nom,
                    SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END) as total_dettes,
                    CASE 
                        WHEN AVG(DATEDIFF(c.date_livraison_prevue, c.date_commande)) <= 15 THEN '0-15 jours'
                        WHEN AVG(DATEDIFF(c.date_livraison_prevue, c.date_commande)) <= 30 THEN '16-30 jours'
                        WHEN AVG(DATEDIFF(c.date_livraison_prevue, c.date_commande)) <= 60 THEN '31-60 jours'
                        WHEN AVG(DATEDIFF(c.date_livraison_prevue, c.date_commande)) <= 90 THEN '61-90 jours'
                        ELSE '> 90 jours'
                    END as tranche_age,
                    SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') AND DATEDIFF(CURDATE(), c.date_commande) <= 15 THEN c.montant_total ELSE 0 END) as dette_0_15,
                    SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') AND DATEDIFF(CURDATE(), c.date_commande) BETWEEN 16 AND 30 THEN c.montant_total ELSE 0 END) as dette_16_30,
                    SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') AND DATEDIFF(CURDATE(), c.date_commande) BETWEEN 31 AND 60 THEN c.montant_total ELSE 0 END) as dette_31_60,
                    SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') AND DATEDIFF(CURDATE(), c.date_commande) BETWEEN 61 AND 90 THEN c.montant_total ELSE 0 END) as dette_61_90,
                    SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') AND DATEDIFF(CURDATE(), c.date_commande) > 90 THEN c.montant_total ELSE 0 END) as dette_plus_90
                FROM fournisseurs f
                LEFT JOIN commandes c ON f.id = c.fournisseur_id 
                    AND c.statut_commande IN ('BROUILLON', 'VALIDEE') 
                    AND c.deleted_at IS NULL
                WHERE f.deleted_at IS NULL
                GROUP BY f.id, f.nom
                HAVING SUM(CASE WHEN c.statut_commande IN ('BROUILLON', 'VALIDEE') THEN c.montant_total ELSE 0 END) > 0
                ORDER BY total_dettes DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère le rapport de suivi des tiers
     */
    public function genererRapportTiers(string $dateDebut = null, string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-01');
        $dateFin = $dateFin ?? date('Y-m-d');
        
        $suiviClients = $this->getSuiviClients($dateFin);
        $suiviFournisseurs = $this->getSuiviFournisseurs($dateFin);
        $ageCreances = $this->getAgeCreancesClients();
        $ageDettes = $this->getAgeDettesFournisseurs();
        
        // Calculer les ratios de performance
        $ratios = $this->calculerRatiosTiers($suiviClients, $suiviFournisseurs);
        
        return [
            'success' => true,
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'date_generation' => date('Y-m-d H:i:s')
            ],
            'suivi_clients' => $suiviClients,
            'suivi_fournisseurs' => $suiviFournisseurs,
            'age_creances' => $ageCreances,
            'age_dettes' => $ageDettes,
            'ratios_performance' => $ratios,
            'synthese' => [
                'total_clients' => count($suiviClients['clients']),
                'total_fournisseurs' => count($suiviFournisseurs['fournisseurs']),
                'total_creances' => $suiviClients['statistiques']['total_creances'],
                'total_dettes' => $suiviFournisseurs['statistiques']['total_dettes'],
                'solde_net_tiers' => $suiviClients['statistiques']['total_creances'] - $suiviFournisseurs['statistiques']['total_dettes']
            ]
        ];
    }

    /**
     * Exporte le suivi des clients
     */
    public function exporterSuiviClients(string $dateFin = null): array
    {
        $suivi = $this->getSuiviClients($dateFin);
        
        $exportData = [];
        
        // En-tête
        $exportData[] = [
            'Code' => 'Code',
            'Nom' => 'Nom',
            'Prénom' => 'Prénom',
            'Matricule' => 'Matricule',
            'Téléphone' => 'Téléphone',
            'Email' => 'Email',
            'Type' => 'Type',
            'Plafond' => 'Plafond',
            'Solde' => 'Solde',
            'Crédit Disponible' => 'Crédit Disponible',
            'Total Achats' => 'Total Achats',
            'Achats Crédit' => 'Achats Crédit',
            'Nombre Ventes' => 'Nombre Ventes',
            'Dernière Vente' => 'Dernière Vente',
            'Statut' => 'Statut',
            'Risque' => 'Niveau Risque',
            'Activité' => 'Statut Activité'
        ];
        
        // Données
        foreach ($suivi['clients'] as $client) {
            $exportData[] = [
                'Code' => $client['code'],
                'Nom' => $client['nom'],
                'Prénom' => $client['prenom'],
                'Matricule' => $client['matricule'],
                'Téléphone' => $client['telephone'],
                'Email' => $client['email'],
                'Type' => $client['type_client'],
                'Plafond' => number_format($client['plafond_credit'], 0, ',', ' '),
                'Solde' => number_format($client['solde_credit'], 0, ',', ' '),
                'Crédit Disponible' => number_format($client['credit_disponible'], 0, ',', ' '),
                'Total Achats' => number_format($client['total_achats'], 0, ',', ' '),
                'Achats Crédit' => number_format($client['total_achats_credit'], 0, ',', ' '),
                'Nombre Ventes' => $client['nombre_ventes'],
                'Dernière Vente' => $client['derniere_vente'],
                'Statut' => $client['statut_client'],
                'Risque' => $client['niveau_risque'],
                'Activité' => $client['statut_activite']
            ];
        }
        
        return [
            'success' => true,
            'type' => 'suivi_clients',
            'periode' => $suivi['date_analyse'],
            'donnees' => $exportData,
            'statistiques' => $suivi['statistiques']
        ];
    }

    /**
     * Exporte le suivi des fournisseurs
     */
    public function exporterSuiviFournisseurs(string $dateFin = null): array
    {
        $suivi = $this->getSuiviFournisseurs($dateFin);
        
        $exportData = [];
        
        // En-tête
        $exportData[] = [
            'Nom' => 'Nom',
            'Téléphone' => 'Téléphone',
            'Email' => 'Email',
            'Délai Livraison' => 'Délai Livraison',
            'Total Achats' => 'Total Achats',
            'Total Livré' => 'Total Livré',
            'Total En Attente' => 'Total En Attente',
            'Total Dettes' => 'Total Dettes',
            'Nombre Commandes' => 'Nombre Commandes',
            'Dernière Commande' => 'Dernière Commande',
            'Statut' => 'Statut',
            'Niveau Dette' => 'Niveau Dette',
            'Activité' => 'Statut Activité',
            'Délai Moyen' => 'Délai Moyen Livraison'
        ];
        
        // Données
        foreach ($suivi['fournisseurs'] as $fournisseur) {
            $exportData[] = [
                'Nom' => $fournisseur['nom'],
                'Téléphone' => $fournisseur['telephone'],
                'Email' => $fournisseur['email'],
                'Délai Livraison' => $fournisseur['delai_livraison'],
                'Total Achats' => number_format($fournisseur['total_achats'], 0, ',', ' '),
                'Total Livré' => number_format($fournisseur['total_livre'], 0, ',', ' '),
                'Total En Attente' => number_format($fournisseur['total_en_attente'], 0, ',', ' '),
                'Total Dettes' => number_format($fournisseur['total_dettes'], 0, ',', ' '),
                'Nombre Commandes' => $fournisseur['nombre_commandes'],
                'Dernière Commande' => $fournisseur['derniere_commande'],
                'Statut' => $fournisseur['statut_fournisseur'],
                'Niveau Dette' => $fournisseur['niveau_dette'],
                'Activité' => $fournisseur['statut_activite'],
                'Délai Moyen' => round($fournisseur['delai_moyen_livraison'], 1) . ' jours'
            ];
        }
        
        return [
            'success' => true,
            'type' => 'suivi_fournisseurs',
            'periode' => $suivi['date_analyse'],
            'donnees' => $exportData,
        ];
    }

    /**
     * Calcule les statistiques clients (source comptable)
     */
    private function calculerStatistiquesClientsComptable(array $clients): array
    {
        $totalClients = count($clients);
        $totalCreances = array_sum(array_column($clients, 'solde_comptable'));
        $clientsAvecCreance = count(array_filter($clients, fn($c) => $c['solde_comptable'] > 0));
        
        return [
            'total_clients' => $totalClients,
            'total_creances' => $totalCreances,
            'clients_avec_creance' => $clientsAvecCreance,
            'creance_moyenne' => $clientsAvecCreance > 0 ? $totalCreances / $clientsAvecCreance : 0,
            'nombre_ecritures_total' => array_sum(array_column($clients, 'nombre_ecritures'))
        ];
    }

    /**
     * Calcule les statistiques fournisseurs (source comptable)
     */
    private function calculerStatistiquesFournisseursComptable(array $fournisseurs): array
    {
        $totalFournisseurs = count($fournisseurs);
        $totalDettes = array_sum(array_column($fournisseurs, 'solde_comptable'));
        $fournisseursAvecDette = count(array_filter($fournisseurs, fn($f) => $f['solde_comptable'] > 0));
        
        return [
            'total_fournisseurs' => $totalFournisseurs,
            'total_dettes' => $totalDettes,
            'fournisseurs_avec_dette' => $fournisseursAvecDette,
            'dette_moyenne' => $fournisseursAvecDette > 0 ? $totalDettes / $fournisseursAvecDette : 0,
            'nombre_ecritures_total' => array_sum(array_column($fournisseurs, 'nombre_ecritures'))
        ];
    }

    /**
     * Calcule les statistiques clients
     */
    private function calculerStatistiquesClients(array $clients): array
    {
        $totalClients = count($clients);
        $totalCreances = array_sum(array_column($clients, 'solde_credit'));
        $totalPlafond = array_sum(array_column($clients, 'plafond_credit'));
        $totalAchats = array_sum(array_column($clients, 'total_achats'));
        
        $debiteurs = array_filter($clients, fn($c) => $c['solde_credit'] > 0);
        $clientsRisqueElevé = array_filter($clients, fn($c) => $c['niveau_risque'] === 'RISQUE ÉLEVÉ');
        $clientsInactifs = array_filter($clients, fn($c) => $c['statut_activite'] === 'INACTIF');
        
        return [
            'total_clients' => $totalClients,
            'total_debiteurs' => count($debiteurs),
            'total_creances' => $totalCreances,
            'total_plafond' => $totalPlafond,
            'total_achats' => $totalAchats,
            'taux_utilisation_credit' => $totalPlafond > 0 ? ($totalCreances / $totalPlafond) * 100 : 0,
            'creance_moyenne' => $totalClients > 0 ? $totalCreances / $totalClients : 0,
            'pourcentage_debiteurs' => $totalClients > 0 ? (count($debiteurs) / $totalClients) * 100 : 0,
            'pourcentage_risque_eleve' => $totalClients > 0 ? (count($clientsRisqueElevé) / $totalClients) * 100 : 0,
            'pourcentage_inactifs' => $totalClients > 0 ? (count($clientsInactifs) / $totalClients) * 100 : 0
        ];
    }

    /**
     * Calcule les statistiques des fournisseurs
     */
    private function calculerStatistiquesFournisseurs(array $fournisseurs): array
    {
        $totalFournisseurs = count($fournisseurs);
        $totalDettes = array_sum(array_column($fournisseurs, 'total_dettes'));
        $totalAchats = array_sum(array_column($fournisseurs, 'total_achats'));
        
        $dettes = array_filter($fournisseurs, fn($f) => $f['total_dettes'] > 0);
        $fournisseursDetteElevée = array_filter($fournisseurs, fn($f) => $f['niveau_dette'] === 'DETTE ÉLEVÉE');
        $fournisseursInactifs = array_filter($fournisseurs, fn($f) => $f['statut_activite'] === 'INACTIF');
        
        return [
            'total_fournisseurs' => $totalFournisseurs,
            'total_dettes' => $totalDettes,
            'total_achats' => $totalAchats,
            'total_en_attente' => array_sum(array_column($fournisseurs, 'total_en_attente')),
            'dette_moyenne' => $totalFournisseurs > 0 ? $totalDettes / $totalFournisseurs : 0,
            'pourcentage_dettes' => $totalFournisseurs > 0 ? (count($dettes) / $totalFournisseurs) * 100 : 0,
            'pourcentage_dette_eleve' => $totalFournisseurs > 0 ? (count($fournisseursDetteElevée) / $totalFournisseurs) * 100 : 0,
            'pourcentage_inactifs' => $totalFournisseurs > 0 ? (count($fournisseursInactifs) / $totalFournisseurs) * 100 : 0,
            'delai_moyen_livraison' => $totalFournisseurs > 0 ? array_sum(array_column($fournisseurs, 'delai_livraison')) / $totalFournisseurs : 0
        ];
    }

    /**
     * Calcule les ratios de performance des tiers
     */
    private function calculerRatiosTiers(array $suiviClients, array $suiviFournisseurs): array
    {
        $statsClients = $suiviClients['statistiques'];
        $statsFournisseurs = $suiviFournisseurs['statistiques'];
        
        return [
            'ratio_dettes_creances' => $statsClients['total_creances'] > 0 ? 
                ($statsFournisseurs['total_dettes'] / $statsClients['total_creances']) : 0,
            'rotation_clients' => $statsClients['total_clients'] > 0 ? 
                ($statsClients['total_achats'] / $statsClients['total_clients']) : 0,
            'rotation_fournisseurs' => $statsFournisseurs['total_fournisseurs'] > 0 ? 
                ($statsFournisseurs['total_achats'] / $statsFournisseurs['total_fournisseurs']) : 0,
            'taux_recouvrement' => $statsClients['total_debiteurs'] > 0 ? 
                (1 - ($statsClients['pourcentage_inactifs'] / 100)) : 0,
            'taux_satisfaction_fournisseurs' => $statsFournisseurs['delai_moyen_livraison'] > 0 ? 
                min(100, (30 / $statsFournisseurs['delai_moyen_livraison']) * 100) : 0
        ];
    }

    /**
     * Met à jour le solde d'un client
     */
    public function mettreAJourSoldeClient(int $clientId, float $nouveauSolde, int $utilisateurId): bool
    {
        $sql = "UPDATE clients SET 
                    solde_credit = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$nouveauSolde, $clientId]);
        
        if ($result) {
            $this->auditService->logAction(
                $utilisateurId,
                'MISE_A_JOUR_SOLDE_CLIENT',
                'clients',
                $clientId,
                ['solde_credit' => $this->getSoldeClient($clientId)],
                ['solde_credit' => $nouveauSolde]
            );
        }
        
        return $result;
    }

    /**
     * Récupère le solde d'un client
     */
    private function getSoldeClient(int $clientId): float
    {
        $sql = "SELECT solde_credit FROM clients WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        
        return (float) $stmt->fetchColumn();
    }
}
