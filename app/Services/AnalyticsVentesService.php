<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class AnalyticsVentesService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les analytics complets des ventes
     */
    public function getVentesAnalytics(): array
    {
        $analytics = [];

        try {
            // Ventes par produit
            $analytics['ventes_par_produit'] = $this->getVentesParProduit();
            
            // Ventes par client
            $analytics['ventes_par_client'] = $this->getVentesParClient();
            
            // Ventes par utilisateur (vendeur)
            $analytics['ventes_par_utilisateur'] = $this->getVentesParUtilisateur();
            
            // Performance caisse par session
            $analytics['performance_caisse'] = $this->getPerformanceCaisse();
            
            // Analyse temporelle
            $analytics['analyse_temporelle'] = $this->getAnalyseTemporelle();
            
            // Analyse des produits
            $analytics['analyse_produits'] = $this->getAnalyseProduits();
            
            // Analyse des clients
            $analytics['analyse_clients'] = $this->getAnalyseClients();

            return [
                'success' => true,
                'data' => $analytics,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des analytics ventes',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les ventes par produit
     */
    private function getVentesParProduit(): array
    {
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    p.categorie_id,
                    c.nom as categorie_nom,
                    SUM(va.quantite) as quantite_totale,
                    SUM(va.montant_total) as chiffre_affaires,
                    COUNT(DISTINCT v.id) as nombre_ventes,
                    AVG(va.prix_unitaire) as prix_moyen,
                    MAX(v.date_vente) as derniere_vente,
                    MIN(v.date_vente) as premiere_vente
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id, p.nom, p.reference, p.categorie_id, c.nom
                ORDER BY chiffre_affaires DESC
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les ventes par client
     */
    private function getVentesParClient(): array
    {
        $sql = "SELECT 
                    c.id,
                    c.nom,
                    c.telephone,
                    c.email,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    MAX(v.date_vente) as derniere_vente,
                    MIN(v.date_vente) as premiere_vente,
                    c.solde,
                    c.plafond_credit,
                    DATEDIFF(CURDATE(), MAX(v.date_vente)) as jours_derniere_vente
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY c.id, c.nom, c.telephone, c.email, c.solde, c.plafond_credit
                ORDER BY chiffre_affaires DESC
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les ventes par utilisateur (vendeur)
     */
    private function getVentesParUtilisateur(): array
    {
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.nom,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    COUNT(DISTINCT DATE(v.date_vente)) as jours_actifs,
                    MAX(v.date_vente) as derniere_vente,
                    MIN(v.date_vente) as premiere_vente,
                    r.code as role_code
                FROM utilisateurs u
                JOIN ventes v ON u.id = v.utilisateur_id
                JOIN roles r ON u.role_id = r.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY u.id, u.username, u.nom, r.code
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la performance de la caisse par session
     */
    private function getPerformanceCaisse(): array
    {
        $sql = "SELECT 
                    cs.id,
                    cs.numero_session,
                    cs.date_ouverture,
                    cs.date_fermeture,
                    cs.montant_ouverture,
                    cs.montant_fermeture,
                    cs.solde_theorique,
                    cs.solde_reel,
                    cs.ecart,
                    u.username as caissier,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    SUM(CASE WHEN mc.type_mouvement = 'VERSEMENT' THEN mc.montant ELSE 0 END) as total_versements,
                    SUM(CASE WHEN mc.type_mouvement = 'RETRAIT' THEN mc.montant ELSE 0 END) as total_retraits
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                LEFT JOIN ventes v ON cs.id = v.caisse_session_id
                LEFT JOIN mouvements_caisse mc ON cs.id = mc.caisse_session_id
                WHERE cs.date_ouverture >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY cs.id, cs.numero_session, cs.date_ouverture, cs.date_fermeture, 
                         cs.montant_ouverture, cs.montant_fermeture, cs.solde_theorique, 
                         cs.solde_reel, cs.ecart, u.username
                ORDER BY cs.date_ouverture DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Analyse temporelle des ventes
     */
    private function getAnalyseTemporelle(): array
    {
        $analyses = [];

        // Ventes par jour de la semaine
        $sql = "SELECT 
                    DAYNAME(v.date_vente) as jour_semaine,
                    DAYOFWEEK(v.date_vente) as jour_numero,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY DAYNAME(v.date_vente), DAYOFWEEK(v.date_vente)
                ORDER BY jour_numero";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['par_jour_semaine'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ventes par heure
        $sql = "SELECT 
                    HOUR(v.date_vente) as heure,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY HOUR(v.date_vente)
                ORDER BY heure";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['par_heure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ventes par mois
        $sql = "SELECT 
                    MONTHNAME(v.date_vente) as mois,
                    MONTH(v.date_vente) as mois_numero,
                    YEAR(v.date_vente) as annee,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY YEAR(v.date_vente), MONTH(v.date_vente), MONTHNAME(v.date_vente), MONTH(v.date_vente)
                ORDER BY annee DESC, mois_numero DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['par_mois'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $analyses;
    }

    /**
     * Analyse des produits
     */
    private function getAnalyseProduits(): array
    {
        $analyses = [];

        // Produits les plus rentables
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.prix_vente,
                    p.prix_achat,
                    (p.prix_vente - p.prix_achat) as marge_unitaire,
                    SUM(va.quantite) as quantite_vendue,
                    SUM((p.prix_vente - p.prix_achat) * va.quantite) as marge_totale,
                    ROUND(((p.prix_vente - p.prix_achat) / p.prix_vente) * 100, 2) as taux_marge
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id, p.nom, p.prix_vente, p.prix_achat
                HAVING quantite_vendue > 0
                ORDER BY marge_totale DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['produits_rentables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Produits en croissance
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END) as quantite_semaine,
                    SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END) as quantite_precedente,
                    CASE 
                        WHEN SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END) = 0 
                        THEN 100 
                        ELSE ROUND(((SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END) - 
                                     SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END)) / 
                                     SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN va.quantite ELSE 0 END)) * 100, 2)
                    END as croissance
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id, p.nom
                HAVING quantite_semaine > 0
                ORDER BY croissance DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['produits_croissance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Produits en déclin
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) as quantite_mois,
                    SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) as quantite_mois_precedent,
                    CASE 
                        WHEN SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) = 0 
                        THEN -100 
                        ELSE ROUND(((SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) - 
                                     SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END)) / 
                                     SUM(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND v.date_vente < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END)) * 100, 2)
                    END as variation
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                GROUP BY p.id, p.nom
                HAVING quantite_mois > 0
                ORDER BY variation ASC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['produits_declin'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $analyses;
    }

    /**
     * Analyse des clients
     */
    private function getAnalyseClients(): array
    {
        $analyses = [];

        // Segmentation des clients par CA
        $sql = "SELECT 
                    CASE 
                        WHEN SUM(v.montant_ttc) >= 100000 THEN 'VIP'
                        WHEN SUM(v.montant_ttc) >= 50000 THEN 'Premium'
                        WHEN SUM(v.montant_ttc) >= 20000 THEN 'Standard'
                        ELSE 'Occasionnel'
                    END as segment,
                    COUNT(DISTINCT c.id) as nombre_clients,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY 
                    CASE 
                        WHEN SUM(v.montant_ttc) >= 100000 THEN 'VIP'
                        WHEN SUM(v.montant_ttc) >= 50000 THEN 'Premium'
                        WHEN SUM(v.montant_ttc) >= 20000 THEN 'Standard'
                        ELSE 'Occasionnel'
                    END
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['segmentation_ca'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clients les plus fidèles
        $sql = "SELECT 
                    c.id,
                    c.nom,
                    c.telephone,
                    COUNT(v.id) as frequence_achats,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    MAX(v.date_vente) as derniere_vente,
                    DATEDIFF(CURDATE(), MAX(v.date_vente)) as jours_inactivite
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY c.id, c.nom, c.telephone
                HAVING frequence_achats >= 3
                ORDER BY frequence_achats DESC, chiffre_affaires DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['clients_fideles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clients en risque (inactivité)
        $sql = "SELECT 
                    c.id,
                    c.nom,
                    c.telephone,
                    COUNT(v.id) as nombre_achats_historique,
                    SUM(v.montant_ttc) as chiffre_affaires_historique,
                    MAX(v.date_vente) as derniere_vente,
                    DATEDIFF(CURDATE(), MAX(v.date_vente)) as jours_inactivite
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND c.is_actif = 1
                GROUP BY c.id, c.nom, c.telephone
                HAVING MAX(v.date_vente) < DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                AND COUNT(v.id) >= 2
                ORDER BY jours_inactivite DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analyses['clients_risque'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $analyses;
    }

    /**
     * Récupère les statistiques de performance
     */
    public function getPerformanceStats(): array
    {
        $sql = "SELECT 
                    -- Statistiques générales
                    COUNT(DISTINCT v.id) as total_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    
                    -- Performance par vendeur
                    COUNT(DISTINCT CASE WHEN r.code = 'VENDEUR' THEN v.utilisateur_id END) as vendeurs_actifs,
                    
                    -- Performance par jour
                    COUNT(DISTINCT DATE(v.date_vente)) as jours_avec_ventes,
                    
                    -- Moyennes
                    AVG(CASE WHEN r.code = 'VENDEUR' THEN v.montant_ttc END) as panier_moyen_vendeur,
                    AVG(CASE WHEN r.code = 'ADMIN' THEN v.montant_ttc END) as panier_moyen_admin,
                    
                    -- Taux de conversion (clients vs ventes)
                    (SELECT COUNT(*) FROM clients WHERE is_actif = 1) as total_clients,
                    COUNT(DISTINCT v.client_id) as clients_actifs
                FROM ventes v
                JOIN utilisateurs u ON v.utilisateur_id = u.id
                JOIN roles r ON u.role_id = r.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Exporte les données analytics
     */
    public function exportAnalytics(string $type, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        try {
            switch ($type) {
                case 'ventes_produit':
                    $data = $this->exportVentesParProduit($dateDebut, $dateFin);
                    break;
                case 'ventes_client':
                    $data = $this->exportVentesParClient($dateDebut, $dateFin);
                    break;
                case 'performance_vendeur':
                    $data = $this->exportPerformanceVendeur($dateDebut, $dateFin);
                    break;
                case 'performance_caisse':
                    $data = $this->exportPerformanceCaisse($dateDebut, $dateFin);
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
     * Export des ventes par produit
     */
    private function exportVentesParProduit(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    p.reference,
                    p.nom,
                    c.nom as categorie,
                    SUM(va.quantite) as quantite_totale,
                    SUM(va.montant_total) as chiffre_affaires,
                    COUNT(DISTINCT v.id) as nombre_ventes,
                    AVG(va.prix_unitaire) as prix_moyen
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY p.id, p.reference, p.nom, c.nom
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export des ventes par client
     */
    private function exportVentesParClient(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    c.nom,
                    c.telephone,
                    c.email,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    MAX(v.date_vente) as derniere_vente
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY c.id, c.nom, c.telephone, c.email
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export de la performance des vendeurs
     */
    private function exportPerformanceVendeur(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    u.username,
                    u.nom,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    COUNT(DISTINCT DATE(v.date_vente)) as jours_actifs
                FROM utilisateurs u
                JOIN ventes v ON u.id = v.utilisateur_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY u.id, u.username, u.nom
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export de la performance de la caisse
     */
    private function exportPerformanceCaisse(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    cs.numero_session,
                    u.username as caissier,
                    cs.date_ouverture,
                    cs.date_fermeture,
                    cs.montant_ouverture,
                    cs.montant_fermeture,
                    cs.solde_theorique,
                    cs.solde_reel,
                    cs.ecart,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                LEFT JOIN ventes v ON cs.id = v.caisse_session_id
                WHERE cs.date_ouverture BETWEEN ? AND ?
                GROUP BY cs.id, cs.numero_session, u.username, cs.date_ouverture, cs.date_fermeture,
                         cs.montant_ouverture, cs.montant_fermeture, cs.solde_theorique, cs.solde_reel, cs.ecart
                ORDER BY cs.date_ouverture DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
