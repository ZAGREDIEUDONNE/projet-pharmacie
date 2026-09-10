<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class AnalyticsStockService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les analytics complets du stock
     */
    public function getStockAnalytics(): array
    {
        $analytics = [];

        try {
            // Rotation des produits
            $analytics['rotation_produits'] = $this->getRotationProduits();
            
            // Produits morts (jamais vendus)
            $analytics['produits_morts'] = $this->getProduitsMorts();
            
            // Prévision de rupture de stock
            $analytics['prevision_rupture'] = $this->getPrevisionRupture();
            
            // Consommation moyenne par produit
            $analytics['consommation_moyenne'] = $this->getConsommationMoyenne();
            
            // Analyse des mouvements
            $analytics['analyse_mouvements'] = $this->getAnalyseMouvements();
            
            // Analyse des péremptions
            $analytics['analyse_peremption'] = $this->getAnalysePeremption();
            
            // Analyse des fournisseurs
            $analytics['analyse_fournisseurs'] = $this->getAnalyseFournisseurs();

            return [
                'success' => true,
                'data' => $analytics,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des analytics stock',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calcule la rotation des produits
     */
    private function getRotationProduits(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    COALESCE(s.stock_minimum, 0) as stock_minimum,
                    COALESCE(s.stock_alerte, 0) as stock_alerte,
                    SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) as ventes_30j,
                    SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) as ventes_90j,
                    AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) as ventes_moyennes_mensuelles,
                    CASE 
                        WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) = 0 THEN NULL
                        ELSE ROUND(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END), 2)
                    END as jours_stock,
                    CASE 
                        WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) = 0 THEN 'INCONNU'
                        WHEN COALESCE(s.quantite_actuelle, 0) = 0 THEN 'RUPTURE'
                        WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_minimum, 0) THEN 'CRITIQUE'
                        WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_alerte, 0) THEN 'ALERTE'
                        WHEN ROUND(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END), 2) <= 30 THEN 'FAIBLE'
                        WHEN ROUND(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END), 2) <= 90 THEN 'NORMAL'
                        ELSE 'ELEVE'
                    END as niveau_rotation
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN vente_articles va ON p.id = va.produit_id
                LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                WHERE p.is_actif = 1
                GROUP BY p.id, p.nom, p.reference, s.quantite_actuelle, s.stock_minimum, s.stock_alerte
                HAVING ventes_90j > 0 OR stock_actuel > 0
                ORDER BY 
                    CASE 
                        WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) = 0 THEN 0
                        ELSE ROUND(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END), 2)
                    END ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Identifie les produits morts (jamais vendus)
     */
    private function getProduitsMorts(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    p.prix_vente,
                    p.prix_achat,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    p.date_creation,
                    DATEDIFF(CURDATE(), p.date_creation) as jours_existance,
                    c.nom as categorie_nom,
                    f.nom as fournisseur_nom
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                WHERE p.is_actif = 1
                AND NOT EXISTS (
                    SELECT 1 FROM vente_articles va 
                    WHERE va.produit_id = p.id
                )
                AND p.date_creation <= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY jours_existance DESC, stock_actuel DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prédit les ruptures de stock
     */
    private function getPrevisionRupture(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    COALESCE(s.stock_minimum, 0) as stock_minimum,
                    AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) as consommation_moyenne_journaliere,
                    CASE 
                        WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) = 0 THEN NULL
                        ELSE FLOOR(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END))
                    END as jours_avant_rupture,
                    CASE 
                        WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) = 0 THEN NULL
                        ELSE DATE_ADD(CURDATE(), INTERVAL FLOOR(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END)) DAY)
                    END as date_rupture_prevue,
                    CASE 
                        WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) = 0 THEN 'INCONNU'
                        WHEN FLOOR(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END)) <= 7 THEN 'URGENT'
                        WHEN FLOOR(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END)) <= 15 THEN 'CRITIQUE'
                        WHEN FLOOR(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END)) <= 30 THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END as niveau_urgence
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN vente_articles va ON p.id = va.produit_id
                LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                WHERE p.is_actif = 1
                AND COALESCE(s.quantite_actuelle, 0) > 0
                AND AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) > 0
                GROUP BY p.id, p.nom, p.reference, s.quantite_actuel, s.stock_minimum
                HAVING jours_avant_rupture IS NOT NULL AND jours_avant_rupture <= 60
                ORDER BY jours_avant_rupture ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule la consommation moyenne par produit
     */
    private function getConsommationMoyenne(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    c.nom as categorie,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END) as consommation_hebdomadaire,
                    AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) as consommation_mensuelle,
                    AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) as consommation_trimestrielle,
                    ROUND(AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) * 30, 2) as consommation_journaliere_moyenne,
                    ROUND(STDDEV(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END), 2) as ecart_type_consommation,
                    MAX(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) as pic_consommation,
                    MIN(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) as creux_consommation
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN vente_articles va ON p.id = va.produit_id
                LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                WHERE p.is_actif = 1
                AND EXISTS (SELECT 1 FROM vente_articles va2 WHERE va2.produit_id = p.id)
                GROUP BY p.id, p.nom, p.reference, c.nom, s.quantite_actuel
                HAVING consommation_mensuelle > 0
                ORDER BY consommation_mensuelle DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Analyse des mouvements de stock
     */
    private function getAnalyseMouvements(): array
    {
        $analyses = [];

        // Mouvements par type
        $sql = "SELECT 
                    m.type_mouvement,
                    COUNT(*) as nombre_mouvements,
                    SUM(m.quantite) as quantite_totale,
                    AVG(m.quantite) as quantite_moyenne,
                    MAX(m.quantite) as quantite_max,
                    MIN(m.quantite) as quantite_min
                FROM mouvements_stock m
                WHERE m.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY m.type_mouvement
                ORDER BY nombre_mouvements DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['par_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mouvements par produit
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COUNT(*) as nombre_mouvements,
                    SUM(CASE WHEN m.type_mouvement = 'ENTREE' THEN m.quantite ELSE 0 END) as total_entrees,
                    SUM(CASE WHEN m.type_mouvement = 'SORTIE' THEN m.quantite ELSE 0 END) as total_sorties,
                    AVG(m.quantite) as quantite_moyenne
                FROM produits p
                JOIN mouvements_stock m ON p.id = m.produit_id
                WHERE m.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id, p.nom, p.reference
                HAVING nombre_mouvements >= 5
                ORDER BY nombre_mouvements DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['par_produit'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tendance des mouvements
        $sql = "SELECT 
                    DATE(m.date_mouvement) as date,
                    SUM(CASE WHEN m.type_mouvement = 'ENTREE' THEN m.quantite ELSE 0 END) as entrees,
                    SUM(CASE WHEN m.type_mouvement = 'SORTIE' THEN m.quantite ELSE 0 END) as sorties,
                    COUNT(*) as total_mouvements
                FROM mouvements_stock m
                WHERE m.date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(m.date_mouvement)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['tendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $analyses;
    }

    /**
     * Analyse des péremptions
     */
    private function getAnalysePeremption(): array
    {
        $analyses = [];

        // Produits proches de péremption
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    l.numero_lot,
                    l.date_peremption,
                    l.quantite,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                    CASE 
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 7 THEN 'URGENT'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'ALERTE'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ATTENTION'
                        ELSE 'NORMAL'
                    END as niveau_risque,
                    (l.quantite * p.prix_vente) as valeur_perimee
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['produits_risque'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Statistiques de péremption
        $sql = "SELECT 
                    COUNT(*) as total_lots_risque,
                    SUM(l.quantite) as quantite_totale_risque,
                    SUM(l.quantite * p.prix_vente) as valeur_totale_risque,
                    COUNT(CASE WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 1 END) as lots_perimes,
                    COUNT(CASE WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 7 THEN 1 END) as lots_urgence,
                    COUNT(CASE WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 1 END) as lots_alerte
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['statistiques'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Taux de perte par péremption
        $sql = "SELECT 
                    COUNT(*) as total_lots_perimes_30j,
                    SUM(l.quantite) as quantite_perimee_30j,
                    SUM(l.quantite * p.prix_vente) as valeur_perimee_30j,
                    ROUND((SUM(l.quantite * p.prix_vente) / 
                          (SELECT SUM(l2.quantite * p2.prix_vente) 
                           FROM lots l2 JOIN produits p2 ON l2.produit_id = p2.id 
                           WHERE l2.is_actif = 1 AND l2.quantite > 0) * 100), 2) as taux_perte_global
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite > 0
                AND l.date_peremption <= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND l.date_peremption >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['taux_perte'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $analyses;
    }

    /**
     * Analyse des fournisseurs
     */
    private function getAnalyseFournisseurs(): array
    {
        $sql = "SELECT 
                    f.id,
                    f.nom,
                    f.telephone,
                    COUNT(DISTINCT cf.id) as nombre_commandes,
                    COUNT(DISTINCT p.id) as nombre_produits_fournis,
                    SUM(cf.montant_total) as montant_total_commandes,
                    AVG(cf.montant_total) as montant_moyen_commande,
                    MAX(cf.date_commande) as derniere_commande,
                    AVG(DATEDIFF(cf.date_livraison, cf.date_commande)) as delai_moyen_livraison,
                    COUNT(CASE WHEN cf.statut_commande = 'LIVREE' THEN 1 END) as commandes_livrees,
                    ROUND((COUNT(CASE WHEN cf.statut_commande = 'LIVREE' THEN 1 END) / COUNT(*)) * 100, 2) as taux_livraison
                FROM fournisseurs f
                LEFT JOIN commandes_fournisseurs cf ON f.id = cf.fournisseur_id
                LEFT JOIN produits p ON f.id = p.fournisseur_id
                WHERE f.is_actif = 1
                GROUP BY f.id, f.nom, f.telephone
                HAVING nombre_commandes > 0
                ORDER BY montant_total_commandes DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les indicateurs clés du stock
     */
    public function getStockKPIs(): array
    {
        $sql = "SELECT 
                    -- Valeur du stock
                    (SELECT COALESCE(SUM(s.quantite_actuelle * p.prix_achat), 0) 
                     FROM stock s JOIN produits p ON s.produit_id = p.id) as valeur_stock_achat,
                    (SELECT COALESCE(SUM(s.quantite_actuelle * p.prix_vente), 0) 
                     FROM stock s JOIN produits p ON s.produit_id = p.id) as valeur_stock_vente,
                    
                    -- Produits en stock
                    (SELECT COUNT(*) FROM produits WHERE is_actif = 1) as total_produits,
                    (SELECT COUNT(*) FROM stock WHERE quantite_actuelle > 0) as produits_en_stock,
                    (SELECT COUNT(*) FROM stock WHERE quantite_actuelle <= 0) as produits_en_rupture,
                    (SELECT COUNT(*) FROM stock WHERE quantite_actuelle <= stock_minimum) as produits_stock_critique,
                    
                    -- Valeur des produits en risque
                    (SELECT COALESCE(SUM(l.quantite * p.prix_vente), 0) 
                     FROM lots l JOIN produits p ON l.produit_id = p.id 
                     WHERE l.is_actif = 1 AND l.quantite > 0 
                     AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as valeur_peremption_risque,
                    
                    -- Mouvements récents
                    (SELECT COUNT(*) FROM mouvements_stock 
                     WHERE date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as mouvements_semaine,
                    (SELECT COUNT(*) FROM mouvements_stock 
                     WHERE date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as mouvements_mois,
                    
                    -- Taux de rotation moyen
                    (SELECT AVG(jours_stock) FROM (
                        SELECT 
                            CASE 
                                WHEN AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END) = 0 THEN NULL
                                ELSE ROUND(COALESCE(s.quantite_actuelle, 0) / AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN va.quantite ELSE 0 END), 2)
                            END as jours_stock
                        FROM produits p
                        LEFT JOIN stock s ON p.id = s.produit_id
                        LEFT JOIN vente_articles va ON p.id = va.produit_id
                        LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                        WHERE p.is_actif = 1
                        GROUP BY p.id, s.quantite_actuelle
                        HAVING jours_stock IS NOT NULL
                    ) AS rotation_avg) as rotation_moyenne";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte les analytics du stock
     */
    public function exportStockAnalytics(string $type, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-90 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        try {
            switch ($type) {
                case 'rotation':
                    $data = $this->exportRotationProduits($dateDebut, $dateFin);
                    break;
                case 'rupture':
                    $data = $this->exportPrevisionRupture($dateDebut, $dateFin);
                    break;
                case 'peremption':
                    $data = $this->exportAnalysePeremption($dateDebut, $dateFin);
                    break;
                case 'mouvements':
                    $data = $this->exportAnalyseMouvements($dateDebut, $dateFin);
                    break;
                default:
                    throw new Exception("Type d'export non valide: $type");
            }

            return [
                'success' => true,
                'data' => $data,
                'period' => [
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin
                ],
                'export_date' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export de la rotation des produits
     */
    private function exportRotationProduits(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    p.reference,
                    p.nom,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    COALESCE(s.stock_minimum, 0) as stock_minimum,
                    SUM(CASE WHEN v.date_vente BETWEEN ? AND ? THEN va.quantite ELSE 0 END) as ventes_periode,
                    ROUND(COALESCE(s.quantite_actuelle, 0) / 
                          NULLIF(SUM(CASE WHEN v.date_vente BETWEEN ? AND ? THEN va.quantite ELSE 0 END), 0), 2) as jours_stock
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN vente_articles va ON p.id = va.produit_id
                LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                WHERE p.is_actif = 1
                GROUP BY p.id, p.reference, p.nom, s.quantite_actuel, s.stock_minimum
                HAVING ventes_periode > 0 OR stock_actuel > 0
                ORDER BY jours_stock ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin, $dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export des prévisions de rupture
     */
    private function exportPrevisionRupture(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    p.reference,
                    p.nom,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    AVG(CASE WHEN v.date_vente BETWEEN ? AND ? THEN va.quantite ELSE 0 END) as consommation_moyenne,
                    FLOOR(COALESCE(s.quantite_actuelle, 0) / 
                         NULLIF(AVG(CASE WHEN v.date_vente BETWEEN ? AND ? THEN va.quantite ELSE 0 END), 0)) as jours_avant_rupture,
                    DATE_ADD(CURDATE(), INTERVAL FLOOR(COALESCE(s.quantite_actuelle, 0) / 
                         NULLIF(AVG(CASE WHEN v.date_vente BETWEEN ? AND ? THEN va.quantite ELSE 0 END), 0)) DAY) as date_rupture_prevue
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN vente_articles va ON p.id = va.produit_id
                LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                WHERE p.is_actif = 1
                AND COALESCE(s.quantite_actuelle, 0) > 0
                GROUP BY p.id, p.reference, p.nom, s.quantite_actuel
                HAVING consommation_moyenne > 0 AND jours_avant_rupture IS NOT NULL
                ORDER BY jours_avant_rupture ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin, $dateDebut, $dateFin, $dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export de l'analyse des péremptions
     */
    private function exportAnalysePeremption(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    p.reference,
                    p.nom,
                    l.numero_lot,
                    l.date_peremption,
                    l.quantite,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                    (l.quantite * p.prix_vente) as valeur_perimee,
                    CASE 
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 7 THEN 'URGENT'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'ALERTE'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ATTENTION'
                        ELSE 'NORMAL'
                    END as niveau_risque
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export de l'analyse des mouvements
     */
    private function exportAnalyseMouvements(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    p.reference,
                    p.nom,
                    m.type_mouvement,
                    m.quantite,
                    m.date_mouvement,
                    m.motif,
                    u.username as operateur
                FROM mouvements_stock m
                JOIN produits p ON m.produit_id = p.id
                LEFT JOIN utilisateurs u ON m.utilisateur_id = u.id
                WHERE m.date_mouvement BETWEEN ? AND ?
                ORDER BY m.date_mouvement DESC, p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
