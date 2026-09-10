<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class DashboardService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les KPIs principaux du dashboard
     */
    public function getDashboardKPIs(): array
    {
        $kpis = [];

        try {
            // Chiffre d'affaires journalier
            $kpis['ca_journalier'] = $this->getCAJournalier();
            
            // Chiffre d'affaires mensuel
            $kpis['ca_mensuel'] = $this->getCAMensuel();
            
            // Chiffre d'affaires annuel
            $kpis['ca_annuel'] = $this->getCAAnnuel();
            
            // Bénéfice estimé
            $kpis['benefice_estime'] = $this->getBeneficeEstime();
            
            // Produits les plus vendus
            $kpis['top_produits'] = $this->getTopProduits();
            
            // Produits en rupture de stock
            $kpis['produits_rupture'] = $this->getProduitsRupture();
            
            // Produits proches de péremption
            $kpis['produits_peremption'] = $this->getProduitsPeremption();
            
            // État de la caisse
            $kpis['etat_caisse'] = $this->getEtatCaisse();
            
            // Dettes clients
            $kpis['dettes_clients'] = $this->getDettesClients();
            
            // Dettes fournisseurs
            $kpis['dettes_fournisseurs'] = $this->getDettesFournisseurs();

            return [
                'success' => true,
                'data' => $kpis,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des KPIs',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère le chiffre d'affaires journalier
     */
    private function getCAJournalier(): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(montant_ttc), 0) as ca_jour,
                    COUNT(*) as nombre_ventes,
                    AVG(montant_ttc) as panier_moyen
                FROM ventes 
                WHERE DATE(date_vente) = CURDATE() 
                AND statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'montant' => floatval($result['ca_jour']),
            'nombre_ventes' => intval($result['nombre_ventes']),
            'panier_moyen' => floatval($result['panier_moyen']),
            'variation_jour_precedent' => $this->getVariationJourPrecedent()
        ];
    }

    /**
     * Récupère le chiffre d'affaires mensuel
     */
    private function getCAMensuel(): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(montant_ttc), 0) as ca_mois,
                    COUNT(*) as nombre_ventes,
                    AVG(montant_ttc) as panier_moyen
                FROM ventes 
                WHERE MONTH(date_vente) = MONTH(CURDATE()) 
                AND YEAR(date_vente) = YEAR(CURDATE())
                AND statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'montant' => floatval($result['ca_mois']),
            'nombre_ventes' => intval($result['nombre_ventes']),
            'panier_moyen' => floatval($result['panier_moyen']),
            'variation_mois_precedent' => $this->getVariationMoisPrecedent()
        ];
    }

    /**
     * Récupère le chiffre d'affaires annuel
     */
    private function getCAAnnuel(): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(montant_ttc), 0) as ca_annee,
                    COUNT(*) as nombre_ventes,
                    AVG(montant_ttc) as panier_moyen
                FROM ventes 
                WHERE YEAR(date_vente) = YEAR(CURDATE())
                AND statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'montant' => floatval($result['ca_annee']),
            'nombre_ventes' => intval($result['nombre_ventes']),
            'panier_moyen' => floatval($result['panier_moyen']),
            'variation_annee_precedente' => $this->getVariationAnneePrecedente()
        ];
    }

    /**
     * Calcule la variation par rapport au jour précédent
     */
    private function getVariationJourPrecedent(): float
    {
        $sql = "SELECT 
                    COALESCE(SUM(montant_ttc), 0) as ca_hier
                FROM ventes 
                WHERE DATE(date_vente) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                AND statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $caHier = floatval($result['ca_hier']);
        $caAujourdHui = $this->getCAJournalier()['montant'];

        if ($caHier == 0) return $caAujourdHui > 0 ? 100 : 0;
        
        return round((($caAujourdHui - $caHier) / $caHier) * 100, 2);
    }

    /**
     * Calcule la variation par rapport au mois précédent
     */
    private function getVariationMoisPrecedent(): float
    {
        $sql = "SELECT 
                    COALESCE(SUM(montant_ttc), 0) as ca_mois_precedent
                FROM ventes 
                WHERE MONTH(date_vente) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND YEAR(date_vente) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $caMoisPrecedent = floatval($result['ca_mois_precedent']);
        $caMoisActuel = $this->getCAMensuel()['montant'];

        if ($caMoisPrecedent == 0) return $caMoisActuel > 0 ? 100 : 0;
        
        return round((($caMoisActuel - $caMoisPrecedent) / $caMoisPrecedent) * 100, 2);
    }

    /**
     * Calcule la variation par rapport à l'année précédente
     */
    private function getVariationAnneePrecedente(): float
    {
        $sql = "SELECT 
                    COALESCE(SUM(montant_ttc), 0) as ca_annee_precedente
                FROM ventes 
                WHERE YEAR(date_vente) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
                AND statut_vente = 'VALIDEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $caAnneePrecedente = floatval($result['ca_annee_precedente']);
        $caAnneeActuelle = $this->getCAAnnuel()['montant'];

        if ($caAnneePrecedente == 0) return $caAnneeActuelle > 0 ? 100 : 0;
        
        return round((($caAnneeActuelle - $caAnneePrecedente) / $caAnneePrecedente) * 100, 2);
    }

    /**
     * Calcule le bénéfice estimé
     */
    private function getBeneficeEstime(): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(v.montant_ttc), 0) as ca_total,
                    COALESCE(SUM(v.montant_total), 0) as ca_ht,
                    COALESCE(SUM(va.quantite * p.prix_achat), 0) as cout_achat
                FROM ventes v
                JOIN vente_articles va ON v.id = va.vente_id
                JOIN produits p ON va.produit_id = p.id
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) = CURDATE()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $caTotal = floatval($result['ca_total']);
        $coutAchat = floatval($result['cout_achat']);
        $benefice = $caTotal - $coutAchat;
        $marge = $caTotal > 0 ? round(($benefice / $caTotal) * 100, 2) : 0;

        return [
            'benefice' => $benefice,
            'marge' => $marge,
            'ca_total' => $caTotal,
            'cout_achat' => $coutAchat
        ];
    }

    /**
     * Récupère les produits les plus vendus
     */
    private function getTopProduits(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    SUM(va.quantite) as quantite_vendue,
                    SUM(va.montant_total) as chiffre_affaires,
                    COUNT(DISTINCT v.id) as nombre_ventes
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) = CURDATE()
                GROUP BY p.id, p.nom, p.reference
                ORDER BY quantite_vendue DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les produits en rupture de stock
     */
    private function getProduitsRupture(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    COALESCE(s.stock_minimum, 0) as stock_minimum
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND (s.quantite_actuelle IS NULL OR s.quantite_actuelle <= 0)
                ORDER BY p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les produits proches de péremption
     */
    private function getProduitsPeremption(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    l.date_peremption,
                    l.quantite,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY l.date_peremption ASC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'état de la caisse
     */
    private function getEtatCaisse(): array
    {
        $sql = "SELECT 
                    cs.id,
                    cs.statut_session,
                    cs.date_ouverture,
                    cs.montant_ouverture,
                    cs.montant_fermeture,
                    cs.solde_theorique,
                    cs.solde_reel,
                    u.username as caissier
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.statut_session = 'OUVERTE'
                ORDER BY cs.date_ouverture DESC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return [
                'session_active' => false,
                'message' => 'Aucune session de caisse active'
            ];
        }

        // Calculer les mouvements de la session
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN type_mouvement = 'VENTE' THEN montant ELSE 0 END), 0) as total_ventes,
                    COALESCE(SUM(CASE WHEN type_mouvement = 'VERSEMENT' THEN montant ELSE 0 END), 0) as total_versements,
                    COALESCE(SUM(CASE WHEN type_mouvement = 'RETRAIT' THEN montant ELSE 0 END), 0) as total_retraits
                FROM mouvements_caisse 
                WHERE caisse_session_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$session['id']]);
        $mouvements = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'session_active' => true,
            'session' => $session,
            'mouvements' => $mouvements,
            'solde_actuel' => $session['montant_ouverture'] + $mouvements['total_versements'] - $mouvements['total_retraits']
        ];
    }

    /**
     * Récupère les dettes clients
     */
    private function getDettesClients(): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_debiteurs,
                    COALESCE(SUM(solde), 0) as total_dettes,
                    COALESCE(AVG(solde), 0) dette_moyenne
                FROM clients 
                WHERE solde < 0
                AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Récupérer les détails des principaux débiteurs
        $sql = "SELECT 
                    c.id,
                    c.nom,
                    c.telephone,
                    c.solde,
                    COUNT(v.id) as nombre_ventes_impayees
                FROM clients c
                LEFT JOIN ventes v ON c.id = v.client_id AND v.statut_paiement = 'IMPAYE'
                WHERE c.solde < 0
                AND c.is_actif = 1
                GROUP BY c.id, c.nom, c.telephone, c.solde
                ORDER BY ABS(c.solde) DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'nombre_debiteurs' => intval($result['nombre_debiteurs']),
            'total_dettes' => floatval($result['total_dettes']),
            'dette_moyenne' => floatval($result['dette_moyenne']),
            'top_debiteurs' => $details
        ];
    }

    /**
     * Récupère les dettes fournisseurs
     */
    private function getDettesFournisseurs(): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_creanciers,
                    COALESCE(SUM(montant_total - montant_paye), 0) as total_dettes,
                    COALESCE(AVG(montant_total - montant_paye), 0) dette_moyenne
                FROM commandes_fournisseurs 
                WHERE statut_commande IN ('VALIDEE', 'PARTIELLEMENT_PAYEE')
                AND montant_total > montant_paye";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Récupérer les détails des principaux créanciers
        $sql = "SELECT 
                    cf.id,
                    cf.numero_commande,
                    f.nom as fournisseur_nom,
                    cf.montant_total,
                    cf.montant_paye,
                    (cf.montant_total - cf.montant_paye) as montant_restant,
                    cf.date_commande
                FROM commandes_fournisseurs cf
                JOIN fournisseurs f ON cf.fournisseur_id = f.id
                WHERE cf.statut_commande IN ('VALIDEE', 'PARTIELLEMENT_PAYEE')
                AND cf.montant_total > cf.montant_paye
                ORDER BY (cf.montant_total - cf.montant_paye) DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'nombre_creanciers' => intval($result['nombre_creanciers']),
            'total_dettes' => floatval($result['total_dettes']),
            'dette_moyenne' => floatval($result['dette_moyenne']),
            'top_creanciers' => $details
        ];
    }

    /**
     * Récupère les données pour les graphiques
     */
    public function getChartData(): array
    {
        $charts = [];

        try {
            // Graphique des ventes par période
            $charts['ventes_periode'] = $this->getVentesPeriode();
            
            // Évolution du stock
            $charts['evolution_stock'] = $this->getEvolutionStock();
            
            // Top produits (bar chart)
            $charts['top_produits_chart'] = $this->getTopProduitsChart();
            
            // Flux de trésorerie
            $charts['cash_flow'] = $this->getCashFlow();

            return [
                'success' => true,
                'data' => $charts,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des données graphiques',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Données pour le graphique des ventes par période
     */
    private function getVentesPeriode(): array
    {
        $sql = "SELECT 
                    DATE(date_vente) as date,
                    SUM(montant_ttc) as montant,
                    COUNT(*) as nombre_ventes
                FROM ventes 
                WHERE statut_vente = 'VALIDEE'
                AND date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(date_vente)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Données pour l'évolution du stock
     */
    private function getEvolutionStock(): array
    {
        $sql = "SELECT 
                    DATE(m.date_mouvement) as date,
                    SUM(CASE WHEN m.type_mouvement = 'ENTREE' THEN m.quantite ELSE 0 END) as entrees,
                    SUM(CASE WHEN m.type_mouvement = 'SORTIE' THEN m.quantite ELSE 0 END) as sorties
                FROM mouvements_stock m
                WHERE m.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(m.date_mouvement)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Données pour le top produits chart
     */
    private function getTopProduitsChart(): array
    {
        $sql = "SELECT 
                    p.nom,
                    SUM(va.quantite) as quantite_vendue
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id, p.nom
                ORDER BY quantite_vendue DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Données pour le flux de trésorerie
     */
    private function getCashFlow(): array
    {
        $sql = "SELECT 
                    DATE(m.date_mouvement) as date,
                    SUM(CASE WHEN m.type_mouvement IN ('VENTE', 'VERSEMENT') THEN m.montant ELSE 0 END) as entrees,
                    SUM(CASE WHEN m.type_mouvement = 'RETRAIT' THEN m.montant ELSE 0 END) as sorties
                FROM mouvements_caisse m
                WHERE m.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(m.date_mouvement)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques générales
     */
    public function getGeneralStats(): array
    {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM produits WHERE is_actif = 1) as total_produits,
                    (SELECT COUNT(*) FROM clients WHERE is_actif = 1) as total_clients,
                    (SELECT COUNT(*) FROM fournisseurs WHERE is_actif = 1) as total_fournisseurs,
                    (SELECT COUNT(*) FROM utilisateurs WHERE is_actif = 1) as total_utilisateurs,
                    (SELECT COUNT(*) FROM ventes WHERE statut_vente = 'VALIDEE') as total_ventes,
                    (SELECT COALESCE(SUM(quantite_actuelle), 0) FROM stock) as stock_total";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
