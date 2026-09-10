<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class VisualizationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Génère toutes les visualisations graphiques
     */
    public function generateAllVisualizations(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        $visualizations = [];

        try {
            // Graphiques des ventes
            $visualizations['ventes'] = $this->generateVentesCharts($dateDebut, $dateFin);
            
            // Graphiques du stock
            $visualizations['stock'] = $this->generateStockCharts($dateDebut, $dateFin);
            
            // Graphiques de performance
            $visualizations['performance'] = $this->generatePerformanceCharts($dateDebut, $dateFin);
            
            // Graphiques financiers
            $visualizations['financiers'] = $this->generateFinancialCharts($dateDebut, $dateFin);
            
            // Graphiques des clients
            $visualizations['clients'] = $this->generateClientCharts($dateDebut, $dateFin);

            return [
                'success' => true,
                'data' => $visualizations,
                'period' => [
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération des visualisations',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère les graphiques des ventes
     */
    private function generateVentesCharts(string $dateDebut, string $dateFin): array
    {
        $charts = [];

        // Graphique linéaire des ventes par période
        $sql = "SELECT 
                    DATE(v.date_vente) as date,
                    SUM(v.montant_ttc) as montant,
                    COUNT(v.id) as nombre_ventes
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) BETWEEN ? AND ?
                GROUP BY DATE(v.date_vente)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $ventesParDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['ventes_periode'] = [
            'type' => 'line',
            'title' => 'Évolution des ventes',
            'data' => array_map(fn($v) => [
                'x' => $v['date'],
                'y' => floatval($v['montant'])
            ], $ventesParDate),
            'options' => [
                'yAxis' => ['title' => ['text' => 'Chiffre d\'affaires (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Date']]
            ]
        ];

        // Graphique en barres des ventes par mois
        $sql = "SELECT 
                    MONTHNAME(v.date_vente) as mois,
                    YEAR(v.date_vente) as annee,
                    SUM(v.montant_ttc) as montant,
                    COUNT(v.id) as nombre_ventes
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY YEAR(v.date_vente), MONTH(v.date_vente), MONTHNAME(v.date_vente)
                ORDER BY annee, MONTH(v.date_vente)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $ventesParMois = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['ventes_mois'] = [
            'type' => 'bar',
            'title' => 'Ventes par mois',
            'data' => array_map(fn($v) => [
                'x' => $v['mois'] . ' ' . $v['annee'],
                'y' => floatval($v['montant'])
            ], $ventesParMois),
            'options' => [
                'yAxis' => ['title' => ['text' => 'Chiffre d\'affaires (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Mois']]
            ]
        ];

        // Graphique circulaire des ventes par catégorie
        $sql = "SELECT 
                    c.nom as categorie,
                    SUM(v.montant_ttc) as montant
                FROM ventes v
                JOIN vente_articles va ON v.id = va.vente_id
                JOIN produits p ON va.produit_id = p.id
                JOIN categories c ON p.categorie_id = c.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY c.id, c.nom
                ORDER BY montant DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $ventesParCategorie = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['ventes_categorie'] = [
            'type' => 'pie',
            'title' => 'Répartition des ventes par catégorie',
            'data' => array_map(fn($v) => [
                'name' => $v['categorie'],
                'value' => floatval($v['montant'])
            ], $ventesParCategorie),
            'options' => [
                'legend' => ['position' => 'right']
            ]
        ];

        // Top produits (bar chart horizontal)
        $sql = "SELECT 
                    p.nom,
                    SUM(va.quantite) as quantite_vendue
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY p.id, p.nom
                ORDER BY quantite_vendue DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $topProduits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['top_produits'] = [
            'type' => 'bar',
            'title' => 'Top 10 des produits les plus vendus',
            'data' => array_map(fn($p) => [
                'x' => $p['nom'],
                'y' => intval($p['quantite_vendue'])
            ], $topProduits),
            'options' => [
                'plotOptions' => ['bar' => ['horizontal' => true]],
                'yAxis' => ['title' => ['text' => 'Quantité vendue']],
                'xAxis' => ['title' => ['text' => 'Produit']]
            ]
        ];

        return $charts;
    }

    /**
     * Génère les graphiques du stock
     */
    private function generateStockCharts(string $dateDebut, string $dateFin): array
    {
        $charts = [];

        // Évolution du stock
        $sql = "SELECT 
                    DATE(m.date_mouvement) as date,
                    SUM(CASE WHEN m.type_mouvement = 'ENTREE' THEN m.quantite ELSE 0 END) as entrees,
                    SUM(CASE WHEN m.type_mouvement = 'SORTIE' THEN m.quantite ELSE 0 END) as sorties
                FROM mouvements_stock m
                WHERE DATE(m.date_mouvement) BETWEEN ? AND ?
                GROUP BY DATE(m.date_mouvement)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $evolutionStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['evolution_stock'] = [
            'type' => 'line',
            'title' => 'Évolution du stock',
            'data' => [
                'entrees' => array_map(fn($e) => ['x' => $e['date'], 'y' => intval($e['entrees'])], $evolutionStock),
                'sorties' => array_map(fn($e) => ['x' => $e['date'], 'y' => intval($e['sorties'])], $evolutionStock)
            ],
            'options' => [
                'yAxis' => ['title' => ['text' => 'Quantité']],
                'xAxis' => ['title' => ['text' => 'Date']]
            ]
        ];

        // État actuel du stock
        $sql = "SELECT 
                    CASE 
                        WHEN s.quantite_actuelle <= 0 THEN 'En rupture'
                        WHEN s.quantite_actuelle <= s.stock_minimum THEN 'Stock critique'
                        WHEN s.quantite_actuelle <= s.stock_alerte THEN 'Stock faible'
                        ELSE 'Stock normal'
                    END as niveau_stock,
                    COUNT(*) as nombre_produits
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                GROUP BY 
                    CASE 
                        WHEN s.quantite_actuelle <= 0 THEN 'En rupture'
                        WHEN s.quantite_actuelle <= s.stock_minimum THEN 'Stock critique'
                        WHEN s.quantite_actuelle <= s.stock_alerte THEN 'Stock faible'
                        ELSE 'Stock normal'
                    END";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $etatStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['etat_stock'] = [
            'type' => 'doughnut',
            'title' => 'État actuel du stock',
            'data' => array_map(fn($e) => [
                'name' => $e['niveau_stock'],
                'value' => intval($e['nombre_produits'])
            ], $etatStock),
            'options' => [
                'legend' => ['position' => 'bottom']
            ]
        ];

        // Produits en rupture de stock
        $sql = "SELECT 
                    p.nom,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND (s.quantite_actuelle IS NULL OR s.quantite_actuelle <= 0)
                ORDER BY p.nom
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $produitsRupture = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($produitsRupture)) {
            $charts['produits_rupture'] = [
                'type' => 'bar',
                'title' => 'Produits en rupture de stock',
                'data' => array_map(fn($p) => [
                    'x' => $p['nom'],
                    'y' => intval($p['stock_actuel'])
                ], $produitsRupture),
                'options' => [
                    'yAxis' => ['title' => ['text' => 'Stock actuel']],
                    'xAxis' => ['title' => ['text' => 'Produit']]
                ]
            ];
        }

        return $charts;
    }

    /**
     * Génère les graphiques de performance
     */
    private function generatePerformanceCharts(string $dateDebut, string $dateFin): array
    {
        $charts = [];

        // Performance par vendeur
        $sql = "SELECT 
                    u.username,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    COUNT(v.id) as nombre_ventes,
                    AVG(v.montant_ttc) as panier_moyen
                FROM utilisateurs u
                JOIN ventes v ON u.id = v.utilisateur_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY u.id, u.username
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $performanceVendeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['performance_vendeurs'] = [
            'type' => 'bar',
            'title' => 'Performance par vendeur',
            'data' => array_map(fn($v) => [
                'x' => $v['username'],
                'y' => floatval($v['chiffre_affaires'])
            ], $performanceVendeurs),
            'options' => [
                'yAxis' => ['title' => ['text' => 'Chiffre d\'affaires (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Vendeur']]
            ]
        ];

        // Performance par heure de la journée
        $sql = "SELECT 
                    HOUR(v.date_vente) as heure,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    COUNT(v.id) as nombre_ventes
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY HOUR(v.date_vente)
                ORDER BY heure";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $performanceHeures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['performance_heures'] = [
            'type' => 'area',
            'title' => 'Performance par heure de la journée',
            'data' => array_map(fn($h) => [
                'x' => $h['heure'] . 'h',
                'y' => floatval($h['chiffre_affaires'])
            ], $performanceHeures),
            'options' => [
                'yAxis' => ['title' => ['text' => 'Chiffre d\'affaires (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Heure']]
            ]
        ];

        // Performance par jour de la semaine
        $sql = "SELECT 
                    DAYNAME(v.date_vente) as jour,
                    DAYOFWEEK(v.date_vente) as jour_numero,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    COUNT(v.id) as nombre_ventes
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY DAYNAME(v.date_vente), DAYOFWEEK(v.date_vente)
                ORDER BY jour_numero";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $performanceJours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['performance_jours'] = [
            'type' => 'column',
            'title' => 'Performance par jour de la semaine',
            'data' => array_map(fn($j) => [
                'x' => $j['jour'],
                'y' => floatval($j['chiffre_affaires'])
            ], $performanceJours),
            'options' => [
                'yAxis' => ['title' => ['text' => 'Chiffre d\'affaires (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Jour']]
            ]
        ];

        return $charts;
    }

    /**
     * Génère les graphiques financiers
     */
    private function generateFinancialCharts(string $dateDebut, string $dateFin): array
    {
        $charts = [];

        // Évolution du chiffre d'affaires et des coûts
        $sql = "SELECT 
                    DATE(v.date_vente) as date,
                    SUM(v.montant_ttc) as ca_ttc,
                    SUM(v.montant_total) as ca_ht,
                    SUM(va.quantite * p.prix_achat) as cout_achat,
                    SUM(v.montant_ttc) - SUM(va.quantite * p.prix_achat) as marge_brute
                FROM ventes v
                JOIN vente_articles va ON v.id = va.vente_id
                JOIN produits p ON va.produit_id = p.id
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) BETWEEN ? AND ?
                GROUP BY DATE(v.date_vente)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $evolutionFinanciere = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['evolution_financiere'] = [
            'type' => 'line',
            'title' => 'Évolution financière',
            'data' => [
                'ca_ttc' => array_map(fn($e) => ['x' => $e['date'], 'y' => floatval($e['ca_ttc'])], $evolutionFinanciere),
                'cout_achat' => array_map(fn($e) => ['x' => $e['date'], 'y' => floatval($e['cout_achat'])], $evolutionFinanciere),
                'marge_brute' => array_map(fn($e) => ['x' => $e['date'], 'y' => floatval($e['marge_brute'])], $evolutionFinanciere)
            ],
            'options' => [
                'yAxis' => ['title' => ['text' => 'Montant (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Date']]
            ]
        ];

        // Répartition des revenus
        $sql = "SELECT 
                    SUM(v.montant_ttc) as total_ca,
                    SUM(v.montant_total) as total_ht,
                    SUM(v.montant_ttc) - SUM(v.montant_total) as total_tva,
                    SUM(va.quantite * p.prix_achat) as total_cout_achat,
                    SUM(v.montant_ttc) - SUM(va.quantite * p.prix_achat) as total_marge
                FROM ventes v
                JOIN vente_articles va ON v.id = va.vente_id
                JOIN produits p ON va.produit_id = p.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $repartition = $stmt->fetch(PDO::FETCH_ASSOC);

        $charts['repartition_revenus'] = [
            'type' => 'pie',
            'title' => 'Répartition des revenus',
            'data' => [
                ['name' => 'Marge brute', 'value' => floatval($repartition['total_marge'])],
                ['name' => 'Coût d\'achat', 'value' => floatval($repartition['total_cout_achat'])],
                ['name' => 'TVA', 'value' => floatval($repartition['total_tva'])]
            ],
            'options' => [
                'legend' => ['position' => 'right']
            ]
        ];

        // Flux de trésorerie
        $sql = "SELECT 
                    DATE(m.date_mouvement) as date,
                    SUM(CASE WHEN m.type_mouvement IN ('VENTE', 'VERSEMENT') THEN m.montant ELSE 0 END) as entrees,
                    SUM(CASE WHEN m.type_mouvement = 'RETRAIT' THEN m.montant ELSE 0 END) as sorties
                FROM mouvements_caisse m
                WHERE DATE(m.date_mouvement) BETWEEN ? AND ?
                GROUP BY DATE(m.date_mouvement)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $cashFlow = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['cash_flow'] = [
            'type' => 'bar',
            'title' => 'Flux de trésorerie',
            'data' => [
                'entrees' => array_map(fn($c) => ['x' => $c['date'], 'y' => floatval($c['entrees'])], $cashFlow),
                'sorties' => array_map(fn($c) => ['x' => $c['date'], 'y' => floatval($c['sorties'])], $cashFlow)
            ],
            'options' => [
                'yAxis' => ['title' => ['text' => 'Montant (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Date']],
                'plotOptions' => ['bar' => ['stacking' => 'normal']]
            ]
        ];

        return $charts;
    }

    /**
     * Génère les graphiques des clients
     */
    private function generateClientCharts(string $dateDebut, string $dateFin): array
    {
        $charts = [];

        // Top clients par chiffre d'affaires
        $sql = "SELECT 
                    c.nom,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    COUNT(v.id) as nombre_ventes
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY c.id, c.nom
                ORDER BY chiffre_affaires DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $topClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['top_clients'] = [
            'type' => 'bar',
            'title' => 'Top 10 des clients',
            'data' => array_map(fn($c) => [
                'x' => $c['nom'],
                'y' => floatval($c['chiffre_affaires'])
            ], $topClients),
            'options' => [
                'yAxis' => ['title' => ['text' => 'Chiffre d\'affaires (FCFA)']],
                'xAxis' => ['title' => ['text' => 'Client']]
            ]
        ];

        // Répartition des ventes (avec/sans client)
        $sql = "SELECT 
                    CASE WHEN v.client_id IS NULL THEN 'Sans client' ELSE 'Avec client' END as type_client,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY CASE WHEN v.client_id IS NULL THEN 'Sans client' ELSE 'Avec client' END";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $repartitionClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $charts['repartition_clients'] = [
            'type' => 'pie',
            'title' => 'Répartition des ventes par type de client',
            'data' => array_map(fn($r) => [
                'name' => $r['type_client'],
                'value' => intval($r['nombre_ventes'])
            ], $repartitionClients),
            'options' => [
                'legend' => ['position' => 'bottom']
            ]
        ];

        // Nouveaux clients par mois
        $sql = "SELECT 
                    DATE_FORMAT(MIN(v.date_vente), '%Y-%m') as mois_premiere_vente,
                    COUNT(DISTINCT c.id) as nombre_nouveaux_clients
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(MIN(v.date_vente), '%Y-%m')
                ORDER BY mois_premiere_vente";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $nouveauxClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($nouveauxClients)) {
            $charts['nouveaux_clients'] = [
                'type' => 'line',
                'title' => 'Évolution des nouveaux clients',
                'data' => array_map(fn($n) => [
                    'x' => $n['mois_premiere_vente'],
                    'y' => intval($n['nombre_nouveaux_clients'])
                ], $nouveauxClients),
                'options' => [
                    'yAxis' => ['title' => ['text' => 'Nombre de nouveaux clients']],
                    'xAxis' => ['title' => ['text' => 'Mois']]
                ]
            ];
        }

        return $charts;
    }

    /**
     * Génère les données pour les graphiques en temps réel
     */
    public function getRealtimeChartData(): array
    {
        try {
            // Ventes du jour en temps réel
            $sql = "SELECT 
                    HOUR(v.date_vente) as heure,
                    SUM(v.montant_ttc) as montant,
                    COUNT(v.id) as nombre_ventes
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) = CURDATE()
                GROUP BY HOUR(v.date_vente)
                ORDER BY heure";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $ventesJour = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // État du stock en temps réel
            $sql = "SELECT 
                    COUNT(CASE WHEN s.quantite_actuelle <= 0 THEN 1 END) as rupture,
                    COUNT(CASE WHEN s.quantite_actuelle <= s.stock_minimum THEN 1 END) as critique,
                    COUNT(CASE WHEN s.quantite_actuelle > s.stock_minimum THEN 1 END) as normal
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stockEtat = $stmt->fetch(PDO::FETCH_ASSOC);

            // Performance caisse du jour
            $sql = "SELECT 
                    SUM(montant_ttc) as ca_jour,
                    COUNT(*) as nombre_ventes,
                    AVG(montant_ttc) as panier_moyen
                FROM ventes 
                WHERE statut_vente = 'VALIDEE' 
                AND DATE(date_vente) = CURDATE()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $caisseJour = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'ventes_jour' => array_map(fn($v) => [
                        'heure' => $v['heure'] . 'h',
                        'montant' => floatval($v['montant']),
                        'nombre' => intval($v['nombre_ventes'])
                    ], $ventesJour),
                    'stock_etat' => [
                        'rupture' => intval($stockEtat['rupture']),
                        'critique' => intval($stockEtat['critique']),
                        'normal' => intval($stockEtat['normal'])
                    ],
                    'caisse_jour' => [
                        'ca' => floatval($caisseJour['ca_jour']),
                        'nombre_ventes' => intval($caisseJour['nombre_ventes']),
                        'panier_moyen' => floatval($caisseJour['panier_moyen'])
                    ]
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des données temps réel',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Exporte les données de visualisation
     */
    public function exportVisualizationData(string $type, string $format, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        try {
            switch ($type) {
                case 'ventes':
                    $data = $this->generateVentesCharts($dateDebut, $dateFin);
                    break;
                case 'stock':
                    $data = $this->generateStockCharts($dateDebut, $dateFin);
                    break;
                case 'performance':
                    $data = $this->generatePerformanceCharts($dateDebut, $dateFin);
                    break;
                case 'financiers':
                    $data = $this->generateFinancialCharts($dateDebut, $dateFin);
                    break;
                case 'clients':
                    $data = $this->generateClientCharts($dateDebut, $dateFin);
                    break;
                default:
                    throw new Exception("Type de visualisation non valide: $type");
            }

            switch ($format) {
                case 'json':
                    return [
                        'success' => true,
                        'filename' => "visualization_{$type}_" . date('YmdHis') . '.json',
                        'content' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                        'mime_type' => 'application/json'
                    ];
                    
                case 'csv':
                    $csvContent = $this->convertVisualizationToCSV($data);
                    return [
                        'success' => true,
                        'filename' => "visualization_{$type}_" . date('YmdHis') . '.csv',
                        'content' => $csvContent,
                        'mime_type' => 'text/csv'
                    ];
                    
                default:
                    throw new Exception("Format d'export non valide: $format");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export des visualisations',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convertit les données de visualisation en CSV
     */
    private function convertVisualizationToCSV(array $data): string
    {
        $csv = "Type de graphique,Titre,Données\n";
        
        foreach ($data as $chartType => $chart) {
            if (isset($chart['data']) && is_array($chart['data'])) {
                if (isset($chart['data'][0]['x']) && isset($chart['data'][0]['y'])) {
                    // Données formatées x,y
                    foreach ($chart['data'] as $point) {
                        $csv .= sprintf(
                            '"%s","%s","%s","%s"\n',
                            $chartType,
                            $chart['title'],
                            $point['x'],
                            $point['y']
                        );
                    }
                } elseif (is_array($chart['data'])) {
                    // Données multiples (ex: entrees/sorties)
                    foreach ($chart['data'] as $seriesName => $seriesData) {
                        foreach ($seriesData as $point) {
                            $csv .= sprintf(
                                '"%s","%s","%s","%s","%s"\n',
                                $chartType,
                                $chart['title'],
                                $seriesName,
                                $point['x'],
                                $point['y']
                            );
                        }
                    }
                }
            }
        }
        
        return $csv;
    }
}
