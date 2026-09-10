<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Module d'alertes stock
 */
class StockAlertController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('stock.view');
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());

        try {
            // Produits en rupture de stock
            $sqlRupture = "SELECT p.id, p.nom, p.code_cip, p.stock_alerte,
                                 COALESCE(s.quantite_disponible, 0) as stock_actuel,
                                 'RUPTURE' as type_alerte, 
                                 'CRITIQUE' as niveau_urgence,
                                 NULL as date_expiration,
                                 NULL as lot_numero
                          FROM produits p
                          LEFT JOIN stock s ON p.id = s.produit_id
                          WHERE p.is_actif = 1 
                          AND p.deleted_at IS NULL
                          AND COALESCE(s.quantite_disponible, 0) = 0
                          ORDER BY p.nom";
            
            $stmt = $this->db->prepare($sqlRupture);
            $stmt->execute();
            $ruptures = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Produits sous le stock minimum (au seuil ou en dessous)
            $sqlMinimum = "SELECT p.id, p.nom, p.code_cip, p.stock_alerte,
                                 COALESCE(s.quantite_disponible, 0) as stock_actuel,
                                 'STOCK_MINIMUM' as type_alerte,
                                 'MOYEN' as niveau_urgence,
                                 NULL as date_expiration,
                                 NULL as lot_numero
                          FROM produits p
                          LEFT JOIN stock s ON p.id = s.produit_id
                          WHERE p.is_actif = 1 
                          AND p.deleted_at IS NULL
                          AND COALESCE(s.quantite_disponible, 0) > 0
                          AND COALESCE(s.quantite_disponible, 0) <= p.stock_alerte
                          ORDER BY p.nom";
            
            $stmt = $this->db->prepare($sqlMinimum);
            $stmt->execute();
            $minimums = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Produits expirés
            $sqlExpires = "SELECT p.id, p.nom, p.code_cip, p.stock_alerte,
                                 l.quantite_restante as stock_actuel,
                                 'EXPIRE' as type_alerte,
                                 'CRITIQUE' as niveau_urgence,
                                 l.date_peremption as date_expiration,
                                 l.numero_lot as lot_numero
                          FROM lots l
                          JOIN produits p ON l.produit_id = p.id
                          WHERE l.is_actif = 1
                          AND l.date_peremption < CURDATE()
                          AND l.quantite_restante > 0
                          ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sqlExpires);
            $stmt->execute();
            $expires = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Produits expirant dans 30 jours
            $sqlExpiring30 = "SELECT p.id, p.nom, p.code_cip, p.stock_alerte,
                                     l.quantite_restante as stock_actuel,
                                     'EXPIRATION_30J' as type_alerte,
                                     'HAUT' as niveau_urgence,
                                     l.date_peremption as date_expiration,
                                     l.numero_lot as lot_numero,
                                     DATEDIFF(l.date_peremption, CURDATE()) as jours_restants
                              FROM lots l
                              JOIN produits p ON l.produit_id = p.id
                              WHERE l.is_actif = 1
                              AND l.date_peremption >= CURDATE()
                              AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                              AND l.quantite_restante > 0
                              ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sqlExpiring30);
            $stmt->execute();
            $expiring30 = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Produits expirant dans 60 jours
            $sqlExpiring60 = "SELECT p.id, p.nom, p.code_cip, p.stock_alerte,
                                     l.quantite_restante as stock_actuel,
                                     'EXPIRATION_60J' as type_alerte,
                                     'MOYEN' as niveau_urgence,
                                     l.date_peremption as date_expiration,
                                     l.numero_lot as lot_numero,
                                     DATEDIFF(l.date_peremption, CURDATE()) as jours_restants
                              FROM lots l
                              JOIN produits p ON l.produit_id = p.id
                              WHERE l.is_actif = 1
                              AND l.date_peremption > DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                              AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                              AND l.quantite_restante > 0
                              ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sqlExpiring60);
            $stmt->execute();
            $expiring60 = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Produits expirant dans 90 jours
            $sqlExpiring90 = "SELECT p.id, p.nom, p.code_cip, p.stock_alerte,
                                     l.quantite_restante as stock_actuel,
                                     'EXPIRATION_90J' as type_alerte,
                                     'FAIBLE' as niveau_urgence,
                                     l.date_peremption as date_expiration,
                                     l.numero_lot as lot_numero,
                                     DATEDIFF(l.date_peremption, CURDATE()) as jours_restants
                              FROM lots l
                              JOIN produits p ON l.produit_id = p.id
                              WHERE l.is_actif = 1
                              AND l.date_peremption > DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                              AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                              AND l.quantite_restante > 0
                              ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sqlExpiring90);
            $stmt->execute();
            $expiring90 = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Appliquer les filtres si présents
            $typeAlerte = $_GET['type'] ?? null;
            $niveauUrgence = $_GET['urgence'] ?? null;
            
            $filterAlerts = function($alerts, $typeAlerte, $niveauUrgence) {
                if ($typeAlerte) {
                    $alerts = array_filter($alerts, fn($a) => $a['type_alerte'] === $typeAlerte);
                }
                if ($niveauUrgence) {
                    $alerts = array_filter($alerts, fn($a) => $a['niveau_urgence'] === $niveauUrgence);
                }
                return array_values($alerts);
            };
            
            $ruptures = $filterAlerts($ruptures, $typeAlerte, $niveauUrgence);
            $minimums = $filterAlerts($minimums, $typeAlerte, $niveauUrgence);
            $expires = $filterAlerts($expires, $typeAlerte, $niveauUrgence);
            $expiring30 = $filterAlerts($expiring30, $typeAlerte, $niveauUrgence);
            $expiring60 = $filterAlerts($expiring60, $typeAlerte, $niveauUrgence);
            $expiring90 = $filterAlerts($expiring90, $typeAlerte, $niveauUrgence);
            
            $this->render('stock/alerts', [
                'title' => 'Alertes Stock',
                'ruptures' => $ruptures,
                'minimums' => $minimums,
                'expires' => $expires,
                'expiring30' => $expiring30,
                'expiring60' => $expiring60,
                'expiring90' => $expiring90,
                'type_alerte' => $typeAlerte,
                'niveau_urgence' => $niveauUrgence,
                'user' => $this->currentUser,
                'returnTo' => $returnTo,
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }
}
