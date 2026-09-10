<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class RapportsService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Génère tous les types de rapports exportables
     */
    public function generateAllReports(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        $rapports = [];

        try {
            // Rapport journalier
            $rapports['journalier'] = $this->generateRapportJournalier($dateDebut, $dateFin);
            
            // Rapport mensuel
            $rapports['mensuel'] = $this->generateRapportMensuel($dateDebut, $dateFin);
            
            // Rapport comptable simplifié
            $rapports['comptable'] = $this->generateRapportComptable($dateDebut, $dateFin);
            
            // Rapport de performance
            $rapports['performance'] = $this->generateRapportPerformance($dateDebut, $dateFin);
            
            // Rapport de stock
            $rapports['stock'] = $this->generateRapportStock($dateDebut, $dateFin);
            
            // Rapport des ventes
            $rapports['ventes'] = $this->generateRapportVentes($dateDebut, $dateFin);

            return [
                'success' => true,
                'data' => $rapports,
                'period' => [
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération des rapports',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère un rapport journalier
     */
    private function generateRapportJournalier(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    DATE(v.date_vente) as date,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    COUNT(DISTINCT v.client_id) as nombre_clients,
                    COUNT(DISTINCT v.utilisateur_id) as nombre_vendeurs,
                    SUM(CASE WHEN mc.type_mouvement = 'VERSEMENT' THEN mc.montant ELSE 0 END) as total_versements,
                    SUM(CASE WHEN mc.type_mouvement = 'RETRAIT' THEN mc.montant ELSE 0 END) as total_retraits
                FROM ventes v
                LEFT JOIN mouvements_caisse mc ON DATE(v.date_vente) = DATE(mc.date_mouvement)
                WHERE v.statut_vente = 'VALIDEE'
                AND DATE(v.date_vente) BETWEEN ? AND ?
                GROUP BY DATE(v.date_vente)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        $donneesJournalieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les totaux
        $totaux = [
            'total_ventes' => array_sum(array_column($donneesJournalieres, 'nombre_ventes')),
            'total_ca' => array_sum(array_column($donneesJournalieres, 'chiffre_affaires')),
            'panier_moyen_global' => count($donneesJournalieres) > 0 ? 
                array_sum(array_column($donneesJournalieres, 'chiffre_affaires')) / array_sum(array_column($donneesJournalieres, 'nombre_ventes')) : 0
        ];

        return [
            'titre' => 'Rapport Journalier',
            'type' => 'journalier',
            'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'donnees' => $donneesJournalieres,
            'totaux' => $totaux,
            'graphiques' => [
                'ventes_par_jour' => $donneesJournalieres,
                'ca_par_jour' => array_map(fn($d) => ['date' => $d['date'], 'montant' => $d['chiffre_affaires']], $donneesJournalieres)
            ]
        ];
    }

    /**
     * Génère un rapport mensuel
     */
    private function generateRapportMensuel(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    MONTH(v.date_vente) as mois,
                    YEAR(v.date_vente) as annee,
                    MONTHNAME(v.date_vente) as nom_mois,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    COUNT(DISTINCT v.client_id) as nombre_clients,
                    COUNT(DISTINCT v.utilisateur_id) as nombre_vendeurs,
                    SUM(CASE WHEN v.client_id IS NOT NULL THEN 1 ELSE 0 END) as ventes_avec_client,
                    SUM(CASE WHEN v.client_id IS NULL THEN 1 ELSE 0 END) as ventes_sans_client
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY YEAR(v.date_vente), MONTH(v.date_vente), MONTHNAME(v.date_vente)
                ORDER BY annee, mois";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        $donneesMensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top produits du mois
        $sql = "SELECT 
                    p.nom,
                    SUM(va.quantite) as quantite_vendue,
                    SUM(va.montant_total) as chiffre_affaires
                FROM produits p
                JOIN vente_articles va ON p.id = va.produit_id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY p.id, p.nom
                ORDER BY chiffre_affaires DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $topProduits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'titre' => 'Rapport Mensuel',
            'type' => 'mensuel',
            'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'donnees' => $donneesMensuelles,
            'top_produits' => $topProduits,
            'graphiques' => [
                'ca_par_mois' => array_map(fn($d) => ['mois' => $d['nom_mois'], 'montant' => $d['chiffre_affaires']], $donneesMensuelles),
                'top_produits' => $topProduits
            ]
        ];
    }

    /**
     * Génère un rapport comptable simplifié
     */
    private function generateRapportComptable(string $dateDebut, string $dateFin): array
    {
        // Chiffre d'affaires
        $sql = "SELECT 
                    SUM(v.montant_ttc) as ca_total,
                    SUM(v.montant_total) as ca_ht,
                    SUM(v.montant_ttc) - SUM(v.montant_total) as tva_total
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $ca = $stmt->fetch(PDO::FETCH_ASSOC);

        // Coût des marchandises vendues
        $sql = "SELECT 
                    SUM(va.quantite * p.prix_achat) as cout_achat_total
                FROM vente_articles va
                JOIN produits p ON va.produit_id = p.id
                JOIN ventes v ON va.vente_id = v.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $coutAchat = $stmt->fetch(PDO::FETCH_ASSOC);

        // Marge brute
        $margeBrute = $ca['ca_ht'] - $coutAchat['cout_achat_total'];
        $tauxMarge = $ca['ca_ht'] > 0 ? ($margeBrute / $ca['ca_ht']) * 100 : 0;

        // Mouvements de caisse
        $sql = "SELECT 
                    SUM(CASE WHEN type_mouvement = 'VERSEMENT' THEN montant ELSE 0 END) as total_versements,
                    SUM(CASE WHEN type_mouvement = 'RETRAIT' THEN montant ELSE 0 END) as total_retraits,
                    SUM(CASE WHEN type_mouvement = 'VENTE' THEN montant ELSE 0 END) as total_ventes_caisse
                FROM mouvements_caisse
                WHERE date_mouvement BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $mouvementsCaisse = $stmt->fetch(PDO::FETCH_ASSOC);

        // Dettes clients
        $sql = "SELECT 
                    COUNT(*) as nombre_debiteurs,
                    SUM(ABS(solde)) as total_dettes
                FROM clients 
                WHERE solde < 0
                AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $dettesClients = $stmt->fetch(PDO::FETCH_ASSOC);

        // Dettes fournisseurs
        $sql = "SELECT 
                    COUNT(*) as nombre_creanciers,
                    SUM(montant_total - montant_paye) as total_dettes
                FROM commandes_fournisseurs 
                WHERE statut_commande IN ('VALIDEE', 'PARTIELLEMENT_PAYEE')
                AND montant_total > montant_paye";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $dettesFournisseurs = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'titre' => 'Rapport Comptable Simplifié',
            'type' => 'comptable',
            'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'chiffre_affaires' => [
                'ca_ht' => $ca['ca_ht'],
                'ca_ttc' => $ca['ca_ttc'],
                'tva_total' => $ca['tva_total']
            ],
            'marge_brute' => [
                'montant' => $margeBrute,
                'taux' => round($tauxMarge, 2)
            ],
            'mouvements_caisse' => $mouvementsCaisse,
            'dettes' => [
                'clients' => $dettesClients,
                'fournisseurs' => $dettesFournisseurs
            ],
            'resultat' => [
                'resultat_brut' => $margeBrute,
                'solde_caisse' => $mouvementsCaisse['total_versements'] - $mouvementsCaisse['total_retraits'],
                'creances' => $dettesClients['total_dettes'],
                'dettes' => $dettesFournisseurs['total_dettes']
            ]
        ];
    }

    /**
     * Génère un rapport de performance
     */
    private function generateRapportPerformance(string $dateDebut, string $dateFin): array
    {
        // Performance par vendeur
        $sql = "SELECT 
                    u.username,
                    u.nom,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    COUNT(DISTINCT DATE(v.date_vente)) as jours_actifs,
                    ROUND(SUM(v.montant_ttc) / COUNT(DISTINCT DATE(v.date_vente)), 2) as ca_moyen_journalier
                FROM utilisateurs u
                JOIN ventes v ON u.id = v.utilisateur_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY u.id, u.username, u.nom
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $performanceVendeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Performance par heure
        $sql = "SELECT 
                    HOUR(v.date_vente) as heure,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY HOUR(v.date_vente)
                ORDER BY heure";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $performanceHeures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Performance par jour de la semaine
        $sql = "SELECT 
                    DAYNAME(v.date_vente) as jour_semaine,
                    DAYOFWEEK(v.date_vente) as jour_numero,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY DAYNAME(v.date_vente), DAYOFWEEK(v.date_vente)
                ORDER BY jour_numero";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $performanceJours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'titre' => 'Rapport de Performance',
            'type' => 'performance',
            'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'vendeurs' => $performanceVendeurs,
            'par_heure' => $performanceHeures,
            'par_jour_semaine' => $performanceJours,
            'graphiques' => [
                'performance_vendeurs' => array_map(fn($v) => [
                    'vendeur' => $v['username'],
                    'ca' => $v['chiffre_affaires']
                ], $performanceVendeurs),
                'performance_heures' => array_map(fn($h) => [
                    'heure' => $h['heure'],
                    'ca' => $h['chiffre_affaires']
                ], $performanceHeures),
                'performance_jours' => array_map(fn($j) => [
                    'jour' => $j['jour_semaine'],
                    'ca' => $j['chiffre_affaires']
                ], $performanceJours)
            ]
        ];
    }

    /**
     * Génère un rapport de stock
     */
    private function generateRapportStock(string $dateDebut, string $dateFin): array
    {
        // État du stock actuel
        $sql = "SELECT 
                    COUNT(*) as total_produits,
                    COUNT(CASE WHEN s.quantite_actuelle > 0 THEN 1 END) as produits_en_stock,
                    COUNT(CASE WHEN s.quantite_actuelle <= 0 THEN 1 END) as produits_en_rupture,
                    COUNT(CASE WHEN s.quantite_actuelle <= s.stock_minimum THEN 1 END) as produits_stock_critique,
                    SUM(s.quantite_actuelle) as total_unites,
                    SUM(s.quantite_actuelle * p.prix_achat) as valeur_stock_achat,
                    SUM(s.quantite_actuelle * p.prix_vente) as valeur_stock_vente
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $etatStock = $stmt->fetch(PDO::FETCH_ASSOC);

        // Mouvements de stock sur la période
        $sql = "SELECT 
                    m.type_mouvement,
                    COUNT(*) as nombre_mouvements,
                    SUM(m.quantite) as quantite_totale,
                    COUNT(DISTINCT DATE(m.date_mouvement)) as nombre_jours
                FROM mouvements_stock m
                WHERE m.date_mouvement BETWEEN ? AND ?
                GROUP BY m.type_mouvement";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $mouvementsStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Produits en rupture
        $sql = "SELECT 
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
        $produitsRupture = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Produits proches de péremption
        $sql = "SELECT 
                    p.nom,
                    p.reference,
                    l.numero_lot,
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
        $produitsPeremption = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'titre' => 'Rapport de Stock',
            'type' => 'stock',
            'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'etat_stock' => $etatStock,
            'mouvements' => $mouvementsStock,
            'produits_rupture' => $produitsRupture,
            'produits_peremption' => $produitsPeremption,
            'graphiques' => [
                'etat_stock' => [
                    'en_stock' => $etatStock['produits_en_stock'],
                    'en_rupture' => $etatStock['produits_en_rupture'],
                    'critique' => $etatStock['produits_stock_critique']
                ],
                'mouvements_par_type' => array_map(fn($m) => [
                    'type' => $m['type_mouvement'],
                    'quantite' => $m['quantite_totale']
                ], $mouvementsStock)
            ]
        ];
    }

    /**
     * Génère un rapport des ventes
     */
    private function generateRapportVentes(string $dateDebut, string $dateFin): array
    {
        // Ventes par catégorie
        $sql = "SELECT 
                    c.nom as categorie,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                JOIN vente_articles va ON v.id = va.vente_id
                JOIN produits p ON va.produit_id = p.id
                JOIN categories c ON p.categorie_id = c.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY c.id, c.nom
                ORDER BY chiffre_affaires DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $ventesParCategorie = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top clients
        $sql = "SELECT 
                    c.nom,
                    c.telephone,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    MAX(v.date_vente) as derniere_vente
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY c.id, c.nom, c.telephone
                ORDER BY chiffre_affaires DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $topClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Répartition des ventes (avec/sans client)
        $sql = "SELECT 
                    CASE WHEN v.client_id IS NULL THEN 'Sans client' ELSE 'Avec client' END as type_client,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?
                GROUP BY CASE WHEN v.client_id IS NULL THEN 'Sans client' ELSE 'Avec client' END";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $repartitionVentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'titre' => 'Rapport des Ventes',
            'type' => 'ventes',
            'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
            'par_categorie' => $ventesParCategorie,
            'top_clients' => $topClients,
            'repartition' => $repartitionVentes,
            'graphiques' => [
                'ventes_par_categorie' => array_map(fn($v) => [
                    'categorie' => $v['categorie'],
                    'ca' => $v['chiffre_affaires']
                ], $ventesParCategorie),
                'top_clients' => array_map(fn($c) => [
                    'client' => $c['nom'],
                    'ca' => $c['chiffre_affaires']
                ], $topClients),
                'repartition' => array_map(fn($r) => [
                    'type' => $r['type_client'],
                    'ca' => $r['chiffre_affaires']
                ], $repartitionVentes)
            ]
        ];
    }

    /**
     * Exporte un rapport dans différents formats
     */
    public function exportRapport(string $type, string $format, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        try {
            // Générer le rapport
            switch ($type) {
                case 'journalier':
                    $rapport = $this->generateRapportJournalier($dateDebut, $dateFin);
                    break;
                case 'mensuel':
                    $rapport = $this->generateRapportMensuel($dateDebut, $dateFin);
                    break;
                case 'comptable':
                    $rapport = $this->generateRapportComptable($dateDebut, $dateFin);
                    break;
                case 'performance':
                    $rapport = $this->generateRapportPerformance($dateDebut, $dateFin);
                    break;
                case 'stock':
                    $rapport = $this->generateRapportStock($dateDebut, $dateFin);
                    break;
                case 'ventes':
                    $rapport = $this->generateRapportVentes($dateDebut, $dateFin);
                    break;
                default:
                    throw new Exception("Type de rapport non valide: $type");
            }

            // Convertir selon le format demandé
            switch ($format) {
                case 'json':
                    return [
                        'success' => true,
                        'filename' => "rapport_{$type}_" . date('YmdHis') . '.json',
                        'content' => json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                        'mime_type' => 'application/json'
                    ];
                    
                case 'csv':
                    $csvContent = $this->convertRapportToCSV($rapport);
                    return [
                        'success' => true,
                        'filename' => "rapport_{$type}_" . date('YmdHis') . '.csv',
                        'content' => $csvContent,
                        'mime_type' => 'text/csv'
                    ];
                    
                case 'pdf':
                    $pdfContent = $this->convertRapportToPDF($rapport);
                    return [
                        'success' => true,
                        'filename' => "rapport_{$type}_" . date('YmdHis') . '.pdf',
                        'content' => $pdfContent,
                        'mime_type' => 'application/pdf'
                    ];
                    
                case 'excel':
                    $excelContent = $this->convertRapportToExcel($rapport);
                    return [
                        'success' => true,
                        'filename' => "rapport_{$type}_" . date('YmdHis') . '.xlsx',
                        'content' => $excelContent,
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ];
                    
                default:
                    throw new Exception("Format d'export non valide: $format");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export du rapport',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convertit un rapport en CSV
     */
    private function convertRapportToCSV(array $rapport): string
    {
        $csv = '';
        
        // En-tête du rapport
        $csv .= "Rapport: " . $rapport['titre'] . "\n";
        $csv .= "Type: " . $rapport['type'] . "\n";
        $csv .= "Période: " . $rapport['periode']['date_debut'] . " au " . $rapport['periode']['date_fin'] . "\n";
        $csv .= "Date de génération: " . date('Y-m-d H:i:s') . "\n\n";

        // Données principales
        if (isset($rapport['donnees']) && is_array($rapport['donnees'])) {
            $csv .= "Données principales:\n";
            
            // En-têtes
            if (!empty($rapport['donnees'])) {
                $headers = array_keys($rapport['donnees'][0]);
                $csv .= implode(';', $headers) . "\n";
                
                // Données
                foreach ($rapport['donnees'] as $row) {
                    $csv .= implode(';', array_map(fn($v) => is_numeric($v) ? $v : '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
                }
            }
        }

        return $csv;
    }

    /**
     * Convertit un rapport en PDF (simplifié)
     */
    private function convertRapportToPDF(array $rapport): string
    {
        // Note: Pour une vraie génération PDF, il faudrait utiliser une bibliothèque comme TCPDF ou DomPDF
        // Ici, nous retournons un HTML qui pourrait être converti en PDF
        
        $html = "<html>
        <head>
            <title>" . $rapport['titre'] . "</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .header { background-color: #f8f9fa; padding: 10px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>" . $rapport['titre'] . "</h1>
                <p>Type: " . $rapport['type'] . "</p>
                <p>Période: " . $rapport['periode']['date_debut'] . " au " . $rapport['periode']['date_fin'] . "</p>
                <p>Date de génération: " . date('Y-m-d H:i:s') . "</p>
            </div>";

        // Ajouter les données principales
        if (isset($rapport['donnees']) && is_array($rapport['donnees'])) {
            $html .= "<h2>Données principales</h2>";
            $html .= "<table>";
            
            if (!empty($rapport['donnees'])) {
                // En-têtes
                $html .= "<tr>";
                foreach (array_keys($rapport['donnees'][0]) as $header) {
                    $html .= "<th>" . htmlspecialchars($header) . "</th>";
                }
                $html .= "</tr>";
                
                // Données
                foreach ($rapport['donnees'] as $row) {
                    $html .= "<tr>";
                    foreach ($row as $value) {
                        $html .= "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    $html .= "</tr>";
                }
            }
            
            $html .= "</table>";
        }

        $html .= "</body></html>";

        return $html;
    }

    /**
     * Convertit un rapport en Excel (simplifié)
     */
    private function convertRapportToExcel(array $rapport): string
    {
        // Note: Pour une vraie génération Excel, il faudrait utiliser une bibliothèque comme PhpSpreadsheet
        // Ici, nous retournons un CSV qui peut être ouvert dans Excel
        
        return $this->convertRapportToCSV($rapport);
    }

    /**
     * Génère un rapport de direction
     */
    public function generateRapportDirection(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin = $dateFin ?? date('Y-m-d');

        try {
            // Récupérer les KPIs principaux
            $kpis = $this->getKPIsDirection($dateDebut, $dateFin);
            
            // Récupérer les tendances
            $tendances = $this->getTendancesDirection($dateDebut, $dateFin);
            
            // Récupérer les alertes importantes
            $alertes = $this->getAlertesDirection($dateDebut, $dateFin);

            return [
                'success' => true,
                'data' => [
                    'titre' => 'Rapport de Direction',
                    'periode' => ['date_debut' => $dateDebut, 'date_fin' => $dateFin],
                    'kpis' => $kpis,
                    'tendances' => $tendances,
                    'alertes' => $alertes,
                    'recommandations' => $this->generateRecommandationsDirection($kpis, $tendances, $alertes)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport de direction',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les KPIs pour la direction
     */
    private function getKPIsDirection(string $dateDebut, string $dateFin): array
    {
        $kpis = [];

        // KPIs financiers
        $sql = "SELECT 
                    SUM(v.montant_ttc) as chiffre_affaires,
                    COUNT(v.id) as nombre_ventes,
                    AVG(v.montant_ttc) as panier_moyen,
                    SUM(v.montant_ttc) - SUM(va.quantite * p.prix_achat) as marge_brute
                FROM ventes v
                JOIN vente_articles va ON v.id = va.vente_id
                JOIN produits p ON va.produit_id = p.id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $kpis['financiers'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // KPIs opérationnels
        $sql = "SELECT 
                    COUNT(DISTINCT v.client_id) as nombre_clients_actifs,
                    COUNT(DISTINCT v.utilisateur_id) as nombre_vendeurs_actifs,
                    COUNT(DISTINCT DATE(v.date_vente)) as jours_avec_ventes,
                    (SELECT COUNT(*) FROM produits WHERE is_actif = 1) as total_produits,
                    (SELECT COUNT(*) FROM produits WHERE is_actif = 1 AND EXISTS (SELECT 1 FROM stock s WHERE s.produit_id = produits.id AND s.quantite_actuelle > 0)) as produits_en_stock
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $kpis['operationnels'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // KPIs de stock
        $sql = "SELECT 
                    COUNT(CASE WHEN s.quantite_actuelle <= 0 THEN 1 END) as produits_en_rupture,
                    COUNT(CASE WHEN s.quantite_actuelle <= s.stock_minimum THEN 1 END) as produits_stock_critique,
                    SUM(s.quantite_actuelle) as total_unites_stock
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $kpis['stock'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $kpis;
    }

    /**
     * Récupère les tendances pour la direction
     */
    private function getTendancesDirection(string $dateDebut, string $dateFin): array
    {
        $tendances = [];

        // Tendance des ventes (comparaison avec période précédente)
        $sql = "WITH periode_actuelle AS (
                    SELECT SUM(v.montant_ttc) as ca
                    FROM ventes v
                    WHERE v.statut_vente = 'VALIDEE'
                    AND v.date_vente BETWEEN ? AND ?
                ),
                periode_precedente AS (
                    SELECT SUM(v.montant_ttc) as ca
                    FROM ventes v
                    WHERE v.statut_vente = 'VALIDEE'
                    AND v.date_vente BETWEEN DATE_SUB(?, INTERVAL ?) AND DATE_SUB(?, INTERVAL ?)
                )
                SELECT 
                    pa.ca as ca_actuel,
                    pp.ca as ca_precedent,
                    ROUND(((pa.ca - pp.ca) / pp.ca) * 100, 2) as variation_pourcentage
                FROM periode_actuelle pa, periode_precedente pp";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $dateDebut, $dateFin,
            $dateDebut, $this->getDaysBetween($dateDebut, $dateFin),
            $dateFin, $this->getDaysBetween($dateDebut, $dateFin)
        ]);
        $tendances['ventes'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Tendance du stock
        $sql = "SELECT 
                    COUNT(CASE WHEN s.quantite_actuelle <= 0 THEN 1 END) as rupture_actuelle,
                    COUNT(CASE WHEN s.quantite_actuelle <= 0 THEN 1 END) - COUNT(CASE WHEN s.quantite_actuelle <= 0 THEN 1 END) as variation_rupture
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $tendances['stock'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $tendances;
    }

    /**
     * Récupère les alertes importantes pour la direction
     */
    private function getAlertesDirection(string $dateDebut, string $dateFin): array
    {
        $alertes = [];

        // Alertes financières
        $sql = "SELECT 
                    'CA_BAISSE' as type,
                    COUNT(*) as nombre,
                    'Baisse significative du chiffre d\'affaires' as description
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
                HAVING SUM(v.montant_ttc) < (
                    SELECT AVG(ca_jour) * 0.7
                    FROM (
                        SELECT SUM(montant_ttc) as ca_jour
                        FROM ventes
                        WHERE statut_vente = 'VALIDEE'
                        AND date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(date_vente)
                    ) as avg_daily
                )
                GROUP BY 'CA_BAISSE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $alertes['financieres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Alertes de stock
        $sql = "SELECT 
                    'STOCK_CRITIQUE' as type,
                    COUNT(*) as nombre,
                    'Produits en rupture de stock critique' as description
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND (s.quantite_actuelle IS NULL OR s.quantite_actuelle <= 0)
                GROUP BY 'STOCK_CRITIQUE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $alertes['stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $alertes;
    }

    /**
     * Génère des recommandations pour la direction
     */
    private function generateRecommandationsDirection(array $kpis, array $tendances, array $alertes): array
    {
        $recommandations = [];

        // Recommandations basées sur les tendances
        if (isset($tendances['ventes']['variation_pourcentage'])) {
            $variation = $tendances['ventes']['variation_pourcentage'];
            if ($variation < -10) {
                $recommandations[] = [
                    'type' => 'VENTES',
                    'priorite' => 'HIGH',
                    'message' => 'Baisse significative des ventes',
                    'actions' => ['Analyser les causes', 'Lancer des promotions', 'Former les vendeurs']
                ];
            } elseif ($variation > 20) {
                $recommandations[] = [
                    'type' => 'VENTES',
                    'priorite' => 'MEDIUM',
                    'message' => 'Hausse significative des ventes',
                    'actions' => ['Maintenir la dynamique', 'Anticiper les besoins', 'Optimiser les ressources']
                ];
            }
        }

        // Recommandations basées sur les KPIs
        if (isset($kpis['stock']['produits_en_rupture']) && $kpis['stock']['produits_en_rupture'] > 10) {
            $recommandations[] = [
                'type' => 'STOCK',
                'priorite' => 'HIGH',
                'message' => 'Nombre important de produits en rupture',
                'actions' => ['Reconstituer le stock', 'Optimiser les commandes', 'Analyser les prévisions']
            ];
        }

        // Recommandations basées sur les alertes
        if (!empty($alertes['financieres'])) {
            $recommandations[] = [
                'type' => 'FINANCIER',
                'priorite' => 'HIGH',
                'message' => 'Alertes financières détectées',
                'actions' => ['Analyser la rentabilité', 'Optimiser les coûts', 'Revoir la stratégie']
            ];
        }

        return $recommandations;
    }

    /**
     * Calcule le nombre de jours entre deux dates
     */
    private function getDaysBetween(string $dateDebut, string $dateFin): int
    {
        $datetime1 = new DateTime($dateDebut);
        $datetime2 = new DateTime($dateFin);
        $interval = $datetime1->diff($datetime2);
        return $interval->days;
    }
}
