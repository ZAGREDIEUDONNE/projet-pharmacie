<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class AlertesService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Génère toutes les alertes intelligentes
     */
    public function generateAllAlertes(): array
    {
        $alertes = [];

        try {
            // Alertes de stock
            $alertes['stock'] = $this->generateAlertesStock();
            
            // Alertes de péremption
            $alertes['peremption'] = $this->generateAlertesPeremption();
            
            // Alertes de caisse
            $alertes['caisse'] = $this->generateAlertesCaisse();
            
            // Alertes de ventes anormales
            $alertes['ventes'] = $this->generateAlertesVentes();
            
            // Alertes de performance
            $alertes['performance'] = $this->generateAlertesPerformance();
            
            // Alertes de sécurité
            $alertes['securite'] = $this->generateAlertesSecurite();
            
            // Alertes de clients
            $alertes['clients'] = $this->generateAlertesClients();
            
            // Alertes de fournisseurs
            $alertes['fournisseurs'] = $this->generateAlertesFournisseurs();

            return [
                'success' => true,
                'data' => $alertes,
                'timestamp' => date('Y-m-d H:i:s'),
                'total_alertes' => $this->countTotalAlertes($alertes)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération des alertes',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère les alertes de stock
     */
    private function generateAlertesStock(): array
    {
        $alertes = [];

        // Stock bas
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    COALESCE(s.stock_minimum, 0) as stock_minimum,
                    CASE 
                        WHEN COALESCE(s.quantite_actuelle, 0) = 0 THEN 'CRITIQUE'
                        WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_minimum, 0) THEN 'URGENT'
                        ELSE 'ALERTE'
                    END as niveau
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND (s.quantite_actuelle IS NULL OR s.quantite_actuelle <= COALESCE(s.stock_minimum, 0))
                ORDER BY 
                    CASE 
                        WHEN COALESCE(s.quantite_actuelle, 0) = 0 THEN 1
                        WHEN COALESCE(s.quantite_actuelle, 0) <= COALESCE(s.stock_minimum, 0) THEN 2
                        ELSE 3
                    END ASC,
                    p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stockBas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($stockBas)) {
            $alertes['stock_bas'] = [
                'type' => 'STOCK_BAS',
                'titre' => 'Stock critique ou bas',
                'niveau' => 'CRITIQUE',
                'description' => count($stockBas) . ' produit(s) en stock critique ou bas',
                'items' => $stockBas,
                'actions' => ['Reconstituer le stock', 'Vérifier les commandes en cours', 'Contacter les fournisseurs']
            ];
        }

        // Prévision de rupture
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) as consommation_moyenne,
                    FLOOR(COALESCE(s.quantite_actuelle, 0) / NULLIF(AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END), 0)) as jours_avant_rupture
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN vente_articles va ON p.id = va.produit_id
                LEFT JOIN ventes v ON va.vente_id = v.id AND v.statut_vente = 'VALIDEE'
                WHERE p.is_actif = 1
                AND COALESCE(s.quantite_actuelle, 0) > 0
                AND AVG(CASE WHEN v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN va.quantite ELSE 0 END) > 0
                GROUP BY p.id, p.nom, p.reference, s.quantite_actuel
                HAVING jours_avant_rupture IS NOT NULL AND jours_avant_rupture <= 15
                ORDER BY jours_avant_rupture ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $previsionRupture = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($previsionRupture)) {
            $alertes['prevision_rupture'] = [
                'type' => 'PREVISION_RUPTURE',
                'titre' => 'Rupture de stock imminente',
                'niveau' => 'URGENT',
                'description' => count($previsionRupture) . ' produit(s) en rupture dans moins de 15 jours',
                'items' => $previsionRupture,
                'actions' => ['Commander immédiatement', 'Vérifier les délais fournisseurs', 'Prévoir les ventes futures']
            ];
        }

        // Produits morts
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    COALESCE(s.quantite_actuelle, 0) as stock_actuel,
                    p.date_creation,
                    DATEDIFF(CURDATE(), p.date_creation) as jours_existance
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1
                AND NOT EXISTS (SELECT 1 FROM vente_articles va WHERE va.produit_id = p.id)
                AND p.date_creation <= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                AND COALESCE(s.quantite_actuelle, 0) > 0
                ORDER BY jours_existence DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $produitsMorts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($produitsMorts)) {
            $alertes['produits_morts'] = [
                'type' => 'PRODUITS_MORTS',
                'titre' => 'Produits jamais vendus',
                'niveau' => 'WARNING',
                'description' => count($produitsMorts) . ' produit(s) jamais vendus avec stock',
                'items' => $produitsMorts,
                'actions' => ['Analyser la pertinence du produit', 'Promouvoir le produit', 'Retirer du catalogue']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de péremption
     */
    private function generateAlertesPeremption(): array
    {
        $alertes = [];

        // Produits périmés ou proches
        $sql = "SELECT 
                    p.id,
                    p.nom,
                    p.reference,
                    l.numero_lot,
                    l.date_peremption,
                    l.quantite,
                    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                    (l.quantite * p.prix_vente) as valeur_perimee,
                    CASE 
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 7 THEN 'URGENT'
                        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'ALERTE'
                        ELSE 'WARNING'
                    END as niveau
                FROM produits p
                JOIN lots l ON p.id = l.produit_id
                WHERE l.is_actif = 1
                AND l.quantite > 0
                AND l.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY l.date_peremption ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $peremption = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($peremption)) {
            $valeurTotale = array_sum(array_column($peremption, 'valeur_perimee'));
            $perimes = array_filter($permutation, fn($p) => $p['jours_restants'] <= 0);
            
            $alertes['peremption'] = [
                'type' => 'PEREMPTION',
                'titre' => 'Produits périmés ou proches',
                'niveau' => !empty($perimes) ? 'CRITIQUE' : 'URGENT',
                'description' => count($peremption) . ' lot(s) concernés pour une valeur de ' . number_format($valeurTotale, 0, ',', ' ') . ' FCFA',
                'items' => $peremption,
                'actions' => ['Retirer les produits périmés', 'Promouvoir les produits proches', 'Ajuster les commandes futures']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de caisse
     */
    private function generateAlertesCaisse(): array
    {
        $alertes = [];

        // Caisse déséquilibrée
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
                    CASE 
                        WHEN ABS(cs.ecart) > 10000 THEN 'CRITIQUE'
                        WHEN ABS(cs.ecart) > 5000 THEN 'URGENT'
                        WHEN ABS(cs.ecart) > 1000 THEN 'ALERTE'
                        ELSE 'WARNING'
                    END as niveau
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.statut_session = 'FERMEE'
                AND cs.date_fermeture >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND ABS(cs.ecart) > 500
                ORDER BY ABS(cs.ecart) DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $caisseDesequilibree = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($caisseDesequilibree)) {
            $alertes['caisse_desequilibree'] = [
                'type' => 'CAISSE_DESEQUILIBREE',
                'titre' => 'Écarts de caisse importants',
                'niveau' => 'URGENT',
                'description' => count($caisseDesequilibree) . ' session(s) avec écarts significatifs',
                'items' => $caisseDesequilibree,
                'actions' => ['Vérifier les comptages', 'Analyser les transactions', 'Former les caissiers']
            ];
        }

        // Session de caisse ouverte depuis longtemps
        $sql = "SELECT 
                    cs.id,
                    cs.numero_session,
                    cs.date_ouverture,
                    u.username as caissier,
                    DATEDIFF(CURDATE(), cs.date_ouverture) as jours_ouverture
                FROM caisse_sessions cs
                JOIN utilisateurs u ON cs.utilisateur_id = u.id
                WHERE cs.statut_session = 'OUVERTE'
                AND cs.date_ouverture < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                ORDER BY jours_ouverture DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $sessionOuverte = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($sessionOuverte)) {
            $alertes['session_ouverte'] = [
                'type' => 'SESSION_OUVERTE',
                'titre' => 'Session de caisse ouverte depuis longtemps',
                'niveau' => 'WARNING',
                'description' => count($sessionOuverte) . ' session(s) ouvertes depuis plus de 24h',
                'items' => $sessionOuverte,
                'actions' => ['Fermer la session', 'Vérifier les transactions', 'Contacter le caissier']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de ventes anormales
     */
    private function generateAlertesVentes(): array
    {
        $alertes = [];

        // Chute de ventes
        $sql = "WITH ventes_jour AS (
                    SELECT 
                        DATE(v.date_vente) as date,
                        SUM(v.montant_ttc) as ca_jour
                    FROM ventes v
                    WHERE v.statut_vente = 'VALIDEE'
                    AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                    GROUP BY DATE(v.date_vente)
                ),
                moyennes AS (
                    SELECT 
                        AVG(ca_jour) as moyenne_ca,
                        STDDEV(ca_jour) as ecart_type
                    FROM ventes_jour
                    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                )
                SELECT 
                    vj.date,
                    vj.ca_jour,
                    m.moyenne_ca,
                    m.ecart_type,
                    ROUND(((m.moyenne_ca - vj.ca_jour) / m.moyenne_ca) * 100, 2) as variation_pourcentage
                FROM ventes_jour vj
                CROSS JOIN moyennes m
                WHERE vj.date = CURDATE() - INTERVAL 1 DAY
                AND vj.ca_jour < (m.moyenne_ca - (m.ecart_type * 1.5))";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $chuteVentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($chuteVentes)) {
            $alertes['chute_ventes'] = [
                'type' => 'CHUTE_VENTES',
                'titre' => 'Chute anormale des ventes',
                'niveau' => 'WARNING',
                'description' => 'Baisse significative des ventes hier',
                'items' => $chuteVentes,
                'actions' => ['Analyser les causes', 'Vérifier la concurrence', 'Lancer des promotions']
            ];
        }

        // Ventes suspects (fraude possible)
        $sql = "SELECT 
                    u.id,
                    u.username,
                    COUNT(v.id) as nombre_ventes,
                    SUM(v.montant_ttc) as chiffre_affaires,
                    AVG(v.montant_ttc) as panier_moyen,
                    COUNT(CASE WHEN v.montant_ttc > 50000 THEN 1 END) as ventes_elevees,
                    COUNT(CASE WHEN v.client_id IS NULL THEN 1 END) as ventes_sans_client
                FROM utilisateurs u
                JOIN ventes v ON u.id = v.utilisateur_id
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY u.id, u.username
                HAVING (ventes_elevees / nombre_ventes) > 0.3 
                OR (ventes_sans_client / nombre_ventes) > 0.8
                ORDER BY (ventes_elevees / nombre_ventes) DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $ventesSuspects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($ventesSuspects)) {
            $alertes['ventes_suspects'] = [
                'type' => 'VENTES_SUSPECTS',
                'titre' => 'Ventes suspectes détectées',
                'niveau' => 'WARNING',
                'description' => count($ventesSuspects) . ' vendeur(s) avec ventes anormales',
                'items' => $ventesSuspects,
                'actions' => ['Analyser les transactions', 'Vérifier les clients', 'Auditer les ventes']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de performance
     */
    private function generateAlertesPerformance(): array
    {
        $alertes = [];

        // Requêtes lentes (si monitoring activé)
        $sql = "SELECT 
                    COUNT(*) as nombre_erreurs,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as erreurs_1h
                FROM system_errors 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $erreursSysteme = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($erreursSysteme['erreurs_1h'] > 10) {
            $alertes['erreurs_systeme'] = [
                'type' => 'ERREURS_SYSTEME',
                'titre' => 'Pic d\'erreurs système',
                'niveau' => 'CRITIQUE',
                'description' => $erreursSysteme['erreurs_1h'] . ' erreurs dans la dernière heure',
                'items' => [['nombre_erreurs' => $erreursSysteme['erreurs_1h']]],
                'actions' => ['Vérifier les logs', 'Redémarrer les services', 'Contacter l\'administrateur']
            ];
        }

        // Performance des ventes
        $sql = "SELECT 
                    AVG(TIMESTAMPDIFF(MICROSECOND, v.created_at, v.updated_at)) / 1000 as temps_moyen_vente_ms,
                    COUNT(*) as nombre_ventes
                FROM ventes v
                WHERE v.statut_vente = 'VALIDEE'
                AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $performanceVentes = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($performanceVentes['temps_moyen_vente_ms'] > 500) { // > 500ms
            $alertes['performance_ventes'] = [
                'type' => 'PERFORMANCE_VENTES',
                'titre' => 'Performance des ventes dégradée',
                'niveau' => 'WARNING',
                'description' => 'Temps moyen de traitement: ' . round($performanceVentes['temps_moyen_vente_ms']) . 'ms',
                'items' => [['temps_moyen_ms' => round($performanceVentes['temps_moyen_vente_ms'])]],
                'actions' => ['Optimiser les requêtes', 'Vérifier la charge serveur', 'Analyser les goulots']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de sécurité
     */
    private function generateAlertesSecurite(): array
    {
        $alertes = [];

        // Tentatives de connexion multiples
        $sql = "SELECT 
                    COUNT(*) as echecs_connexion,
                    COUNT(DISTINCT username) as utilisateurs_concernes
                FROM login_attempts 
                WHERE success = 0 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $connexionsMultiples = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($connexionsMultiples['echecs_connexion'] > 20) {
            $alertes['connexions_multiples'] = [
                'type' => 'CONNEXIONS_MULTIPLES',
                'titre' => 'Tentatives de connexion multiples',
                'niveau' => 'CRITIQUE',
                'description' => $connexionsMultiples['echecs_connexion'] . ' échecs de connexion détectés',
                'items' => [['echecs_connexion' => $connexionsMultiples['echecs_connexion']]],
                'actions' => ['Bloquer les adresses IP', 'Renforcer la sécurité', 'Notifier l\'administrateur']
            ];
        }

        // Accès non autorisés
        $sql = "SELECT 
                    COUNT(*) as acces_non_autorises,
                    COUNT(DISTINCT user_id) as utilisateurs_concernes
                FROM audit_logs 
                WHERE action = 'UNAUTHORIZED_ACCESS'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $accesNonAutorises = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($accesNonAutorises['acces_non_autorises'] > 10) {
            $alertes['acces_non_autorises'] = [
                'type' => 'ACCES_NON_AUTORISES',
                'titre' => 'Accès non autorisés détectés',
                'niveau' => 'WARNING',
                'description' => $accesNonAutorises['acces_non_autorises'] . ' tentatives d\'accès non autorisés',
                'items' => [['acces_non_autorises' => $accesNonAutorises['acces_non_autorises']]],
                'actions' => ['Vérifier les permissions', 'Analyser les tentatives', 'Former les utilisateurs']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de clients
     */
    private function generateAlertesClients(): array
    {
        $alertes = [];

        // Clients avec dettes importantes
        $sql = "SELECT 
                    COUNT(*) as nombre_debiteurs,
                    COALESCE(SUM(ABS(solde)), 0) as total_dettes,
                    AVG(ABS(solde)) as dette_moyenne
                FROM clients 
                WHERE solde < 0
                AND ABS(solde) > 10000
                AND is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $dettesClients = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dettesClients['nombre_debiteurs'] > 0) {
            $alertes['dettes_clients'] = [
                'type' => 'DETTES_CLIENTS',
                'titre' => 'Dettes clients importantes',
                'niveau' => 'WARNING',
                'description' => $dettesClients['nombre_debiteurs'] . ' clients avec dettes > 10k FCFA',
                'items' => [['total_dettes' => $dettesClients['total_dettes']]],
                'actions' => ['Contacter les débiteurs', 'Mettre en place des relances', 'Bloquer les crédits']
            ];
        }

        // Clients inactifs
        $sql = "SELECT 
                    COUNT(*) as nombre_clients_inactifs
                FROM clients c
                WHERE c.is_actif = 1
                AND NOT EXISTS (
                    SELECT 1 FROM ventes v 
                    WHERE v.client_id = c.id 
                    AND v.statut_vente = 'VALIDEE'
                    AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $clientsInactifs = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clientsInactifs['nombre_clients_inactifs'] > 50) {
            $alertes['clients_inactifs'] = [
                'type' => 'CLIENTS_INACTIFS',
                'titre' => 'Clients inactifs',
                'niveau' => 'INFO',
                'description' => $clientsInactifs['nombre_clients_inactifs'] . ' clients sans achat depuis 60 jours',
                'items' => [['nombre_clients' => $clientsInactifs['nombre_clients_inactifs']]],
                'actions' => ['Lancer une campagne', 'Proposer des promotions', 'Analyser les causes']
            ];
        }

        return $alertes;
    }

    /**
     * Génère les alertes de fournisseurs
     */
    private function generateAlertesFournisseurs(): array
    {
        $alertes = [];

        // Commandes en retard
        $sql = "SELECT 
                    COUNT(*) as commandes_en_retard,
                    COUNT(DISTINCT fournisseur_id) as fournisseurs_concernes,
                    SUM(montant_total) as montant_total
                FROM commandes_fournisseurs 
                WHERE statut_commande = 'VALIDEE'
                AND date_livraison_prevue < CURDATE()
                AND date_livraison IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $commandesRetard = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($commandesRetard['commandes_en_retard'] > 0) {
            $alertes['commandes_retard'] = [
                'type' => 'COMMANDES_RETARD',
                'titre' => 'Commandes fournisseurs en retard',
                'niveau' => 'URGENT',
                'description' => $commandesRetard['commandes_en_retard'] . ' commandes en retard',
                'items' => [['montant_total' => $commandesRetard['montant_total']]],
                'actions' => ['Contacter les fournisseurs', 'Prévoir des alternatives', 'Mettre à jour les délais']
            ];
        }

        return $alertes;
    }

    /**
     * Compte le total des alertes
     */
    private function countTotalAlertes(array $alertes): int
    {
        $total = 0;
        foreach ($alertes as $categorie) {
            $total += count($categorie);
        }
        return $total;
    }

    /**
     * Récupère les alertes par niveau
     */
    public function getAlertesByNiveau(string $niveau): array
    {
        $allAlertes = $this->generateAllAlertes();
        
        if (!$allAlertes['success']) {
            return $allAlertes;
        }

        $alertesFiltrees = [];
        foreach ($allAlertes['data'] as $categorie => $alertes) {
            foreach ($alertes as $alerte) {
                if ($alerte['niveau'] === $niveau) {
                    $alertesFiltrees[] = $alerte;
                }
            }
        }

        return [
            'success' => true,
            'data' => $alertesFiltrees,
            'niveau' => $niveau,
            'total' => count($alertesFiltrees)
        ];
    }

    /**
     * Exporte les alertes
     */
    public function exportAlertes(string $format = 'json'): array
    {
        $allAlertes = $this->generateAllAlertes();
        
        if (!$allAlertes['success']) {
            return $allAlertes;
        }

        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'filename' => 'alertes_' . date('YmdHis') . '.json',
                    'content' => json_encode($allAlertes['data'], JSON_PRETTY_PRINT)
                ];
                
            case 'csv':
                $csvContent = $this->convertAlertesToCSV($allAlertes['data']);
                return [
                    'success' => true,
                    'filename' => 'alertes_' . date('YmdHis') . '.csv',
                    'content' => $csvContent
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Format d\'export non supporté'
                ];
        }
    }

    /**
     * Convertit les alertes en CSV
     */
    private function convertAlertesToCSV(array $alertes): string
    {
        $csv = "Type,Titre,Niveau,Description,Actions\n";
        
        foreach ($alertes as $categorie) {
            foreach ($categorie as $alerte) {
                $actions = implode('; ', $alerte['actions']);
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s"\n',
                    $alerte['type'],
                    $alerte['titre'],
                    $alerte['niveau'],
                    $alerte['description'],
                    $actions
                );
            }
        }
        
        return $csv;
    }
}
