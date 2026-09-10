<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class InventaireService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Lance un inventaire manuel
     */
    public function lancerInventaireManuel(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Créer l'en-tête d'inventaire
            $sql = "INSERT INTO inventaires (
                        reference, type_inventaire, utilisateur_id, 
                        date_debut, date_fin_prevue, statut, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['reference'],
                $data['type_inventaire'] ?? 'MANUEL',
                $data['utilisateur_id'],
                $data['date_debut'] ?? date('Y-m-d H:i:s'),
                $data['date_fin_prevue'] ?? null,
                'EN_COURS',
                $data['notes'] ?? null
            ]);

            $inventaireId = $this->db->lastInsertId();

            // Sauvegarder l'état du stock avant inventaire
            $this->sauvegarderEtatStockAvant($inventaireId);

            $this->db->commit();

            // Logger l'opération
            $this->logInventaireOperation($data['utilisateur_id'], 'INVENTAIRE_LANCE', [
                'inventaire_id' => $inventaireId,
                'reference' => $data['reference'],
                'type' => $data['type_inventaire'] ?? 'MANUEL'
            ]);

            return [
                'success' => true,
                'inventaire_id' => $inventaireId,
                'reference' => $data['reference'],
                'message' => 'Inventaire manuel lancé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors du lancement de l\'inventaire: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lance un inventaire automatique
     */
    public function lancerInventaireAutomatique(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Créer l'en-tête d'inventaire automatique
            $sql = "INSERT INTO inventaires (
                        reference, type_inventaire, utilisateur_id, 
                        date_debut, date_fin_prevue, statut, notes,
                        methode_comptage, seuil_ecart
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['reference'],
                'AUTOMATIQUE',
                $data['utilisateur_id'],
                date('Y-m-d H:i:s'),
                $data['date_fin_prevue'] ?? null,
                'EN_COURS',
                $data['notes'] ?? null,
                $data['methode_comptage'] ?? 'SYSTEM',
                $data['seuil_ecart'] ?? 5
            ]);

            $inventaireId = $this->db->lastInsertId();

            // Sauvegarder l'état du stock avant inventaire
            $this->sauvegarderEtatStockAvant($inventaireId);

            // Compter automatiquement les produits
            $this->compterProduitsAutomatiquement($inventaireId, $data);

            $this->db->commit();

            // Logger l'opération
            $this->logInventaireOperation($data['utilisateur_id'], 'INVENTAIRE_AUTO_LANCE', [
                'inventaire_id' => $inventaireId,
                'reference' => $data['reference'],
                'methode_comptage' => $data['methode_comptage'] ?? 'SYSTEM'
            ]);

            return [
                'success' => true,
                'inventaire_id' => $inventaireId,
                'reference' => $data['reference'],
                'message' => 'Inventaire automatique lancé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors du lancement de l\'inventaire automatique: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Saisit un article d'inventaire
     */
    public function saisirArticleInventaire(int $inventaireId, array $article): array
    {
        try {
            $this->db->beginTransaction();

            // Vérifier si l'inventaire est en cours
            $inventaire = $this->getInventaire($inventaireId);
            if (!$inventaire || $inventaire['statut'] !== 'EN_COURS') {
                return [
                    'success' => false,
                    'message' => 'Inventaire non trouvé ou terminé'
                ];
            }

            // Insérer ou mettre à jour l'article
            $sql = "INSERT INTO inventaire_articles (
                        inventaire_id, produit_id, lot_id, quantite_theorique,
                        quantite_comptee, ecart, prix_unitaire, valeur_totale,
                        statut_saisie, notes, utilisateur_saisie_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        quantite_comptee = VALUES(quantite_comptee),
                        ecart = VALUES(ecart),
                        valeur_totale = VALUES(valeur_totale),
                        statut_saisie = VALUES(statut_saisie),
                        notes = VALUES(notes),
                        utilisateur_saisie_id = VALUES(utilisateur_saisie_id),
                        date_saisie = NOW()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $inventaireId,
                $article['produit_id'],
                $article['lot_id'] ?? null,
                $article['quantite_theorique'] ?? 0,
                $article['quantite_comptee'],
                $article['ecart'] ?? 0,
                $article['prix_unitaire'] ?? 0,
                $article['valeur_totale'] ?? 0,
                'VALIDE',
                $article['notes'] ?? null,
                $article['utilisateur_saisie_id']
            ]);

            $this->db->commit();

            // Logger l'opération
            $this->logInventaireOperation($article['utilisateur_saisie_id'], 'ARTICLE_SAISI', [
                'inventaire_id' => $inventaireId,
                'produit_id' => $article['produit_id'],
                'quantite_comptee' => $article['quantite_comptee'],
                'ecart' => $article['ecart'] ?? 0
            ]);

            return [
                'success' => true,
                'message' => 'Article saisi avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la saisie de l\'article: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clôt un inventaire
     */
    public function cloturerInventaire(int $inventaireId, int $utilisateurId, array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Calculer les totaux
            $totaux = $this->calculerTotauxInventaire($inventaireId);

            // Mettre à jour l'inventaire
            $sql = "UPDATE inventaires 
                    SET date_fin = ?, statut = ?, notes_cloture = ?, 
                        utilisateur_cloture_id = ?, total_articles = ?, 
                        total_valeur_theorique = ?, total_valeur_comptee = ?, 
                        total_ecart_valeur = ?, total_ecart_quantite = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                date('Y-m-d H:i:s'),
                'CLOTURE',
                $data['notes_cloture'] ?? null,
                $utilisateurId,
                $totaux['total_articles'],
                $totaux['valeur_theorique'],
                $totaux['valeur_comptee'],
                $totaux['ecart_valeur'],
                $totaux['ecart_quantite']
            ]);

            // Générer les régularisations si nécessaire
            $regularisations = $this->genererRegularisations($inventaireId, $totaux);

            $this->db->commit();

            // Logger l'opération
            $this->logInventaireOperation($utilisateurId, 'INVENTAIRE_CLOTURE', [
                'inventaire_id' => $inventaireId,
                'totaux' => $totaux,
                'regularisations' => count($regularisations)
            ]);

            return [
                'success' => true,
                'totaux' => $totaux,
                'regularisations' => $regularisations,
                'message' => 'Inventaire clôturé avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de la clôture de l\'inventaire: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère un inventaire
     */
    public function getInventaire(int $inventaireId): ?array
    {
        $sql = "SELECT i.*, 
                        u1.username as createur_nom, u1.nom as createur_prenom,
                        u2.username as clotureur_nom, u2.nom as clotureur_prenom
                    FROM inventaires i
                    LEFT JOIN utilisateurs u1 ON i.utilisateur_id = u1.id
                    LEFT JOIN utilisateurs u2 ON i.utilisateur_cloture_id = u2.id
                    WHERE i.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inventaireId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère les articles d'un inventaire
     */
    public function getArticlesInventaire(int $inventaireId): array
    {
        $sql = "SELECT ia.*, 
                        p.nom as produit_nom, p.code_cip,
                        l.numero_lot, l.date_peremption
                    FROM inventaire_articles ia
                    JOIN produits p ON ia.produit_id = p.id
                    LEFT JOIN lots l ON ia.lot_id = l.id
                    WHERE ia.inventaire_id = ?
                    ORDER BY p.nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inventaireId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des inventaires
     */
    public function getListeInventaires(array $filtres = []): array
    {
        $sql = "SELECT i.*, 
                        u1.username as createur_nom,
                        COUNT(ia.id) as nombre_articles,
                        SUM(ia.valeur_totale) as valeur_totale
                    FROM inventaires i
                    LEFT JOIN utilisateurs u1 ON i.utilisateur_id = u1.id
                    LEFT JOIN inventaire_articles ia ON i.id = ia.inventaire_id";
        
        $where = [];
        $params = [];

        if (!empty($filtres['statut'])) {
            $where[] = "i.statut = ?";
            $params[] = $filtres['statut'];
        }

        if (!empty($filtres['type_inventaire'])) {
            $where[] = "i.type_inventaire = ?";
            $params[] = $filtres['type_inventaire'];
        }

        if (!empty($filtres['date_debut'])) {
            $where[] = "DATE(i.date_debut) >= ?";
            $params[] = $filtres['date_debut'];
        }

        if (!empty($filtres['date_fin'])) {
            $where[] = "DATE(i.date_debut) <= ?";
            $params[] = $filtres['date_fin'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY i.id ORDER BY i.date_debut DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sauvegarde l'état du stock avant inventaire
     */
    private function sauvegarderEtatStockAvant(int $inventaireId): void
    {
        $sql = "INSERT INTO inventaire_etat_stock (inventaire_id, produit_id, lot_id, quantite_stock, valeur_stock)
                    SELECT ?, p.id, l.id, COALESCE(s.quantite_disponible, 0), 
                           COALESCE(s.quantite_disponible, 0) * p.prix_vente
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    LEFT JOIN lots l ON s.produit_id = l.produit_id AND l.is_actif = 1
                    WHERE p.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inventaireId]);
    }

    /**
     * Compte automatiquement les produits
     */
    private function compterProduitsAutomatiquement(int $inventaireId, array $data): void
    {
        $sql = "INSERT INTO inventaire_articles (
                    inventaire_id, produit_id, lot_id, quantite_theorique,
                    quantite_comptee, ecart, prix_unitaire, valeur_totale,
                    statut_saisie, notes, utilisateur_saisie_id, date_saisie
                )
                SELECT ?, p.id, l.id, COALESCE(s.quantite_disponible, 0), 
                       COALESCE(s.quantite_disponible, 0), 0, p.prix_vente,
                       COALESCE(s.quantite_disponible, 0) * p.prix_vente, 0,
                       'VALIDE', 'Comptage automatique', ?, NOW()
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN lots l ON s.produit_id = l.produit_id AND l.is_actif = 1
                WHERE p.is_actif = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inventaireId, $data['utilisateur_id']]);
    }

    /**
     * Calcule les totaux d'un inventaire
     */
    private function calculerTotauxInventaire(int $inventaireId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_articles,
                    SUM(quantite_theorique) as quantite_theorique,
                    SUM(quantite_comptee) as quantite_comptee,
                    SUM(valeur_totale) as valeur_comptee,
                    SUM(quantite_theorique * prix_unitaire) as valeur_theorique,
                    SUM(ecart) as ecart_quantite
                FROM inventaire_articles
                WHERE inventaire_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inventaireId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ecartValeur = $result['valeur_comptee'] - $result['valeur_theorique'];
        $ecartQuantite = $result['quantite_comptee'] - $result['quantite_theorique'];

        return [
            'total_articles' => (int)$result['total_articles'],
            'quantite_theorique' => (int)$result['quantite_theorique'],
            'quantite_comptee' => (int)$result['quantite_comptee'],
            'valeur_theorique' => (float)$result['valeur_theorique'],
            'valeur_comptee' => (float)$result['valeur_comptee'],
            'ecart_valeur' => (float)$ecartValeur,
            'ecart_quantite' => (int)$ecartQuantite
        ];
    }

    /**
     * Génère les régularisations d'inventaire et met à jour le stock
     */
    private function genererRegularisations(int $inventaireId, array $totaux): array
    {
        $regularisations = [];

        // Articles avec écart significatif
        $sql = "SELECT ia.*, p.nom as produit_nom, p.prix_vente
                    FROM inventaire_articles ia
                    JOIN produits p ON ia.produit_id = p.id
                    WHERE ia.inventaire_id = ? 
                    AND ABS(ia.ecart) > 0
                    ORDER BY ABS(ia.ecart) DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inventaireId]);
        $articlesEcart = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($articlesEcart as $article) {
            $typeRegularisation = $article['ecart'] > 0 ? 'MANQUANT' : 'EXCEDENT';
            
            // Créer la régularisation
            $sql = "INSERT INTO regularisations_stock (
                        inventaire_id, produit_id, lot_id, type_regularisation,
                        quantite_theorique, quantite_comptee, ecart, 
                        valeur_ecart, statut_regularisation, date_regularisation
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $inventaireId,
                $article['produit_id'],
                $article['lot_id'],
                $typeRegularisation,
                $article['quantite_theorique'],
                $article['quantite_comptee'],
                $article['ecart'],
                abs($article['ecart'] * $article['prix_unitaire']),
                'EN_ATTENTE'
            ]);

            // Mettre à jour le stock
            $this->appliquerAjustementStock($article);

            // Créer le mouvement de stock
            $this->creerMouvementAjustement($article, $inventaireId);

            $regularisations[] = [
                'produit_nom' => $article['produit_nom'],
                'type' => $typeRegularisation,
                'ecart' => $article['ecart'],
                'valeur_ecart' => abs($article['ecart'] * $article['prix_unitaire'])
            ];
        }

        return $regularisations;
    }

    /**
     * Applique l'ajustement de stock pour un article d'inventaire
     */
    private function appliquerAjustementStock(array $article): void
    {
        $sql = "UPDATE stock 
                SET quantite_disponible = quantite_disponible + :ecart,
                    quantite_theorique = quantite_theorique + :ecart,
                    valeur_stock = valeur_stock + :valeur_ecart,
                    dernier_mouvement = NOW()
                WHERE produit_id = :produit_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ecart' => $article['ecart'],
            'valeur_ecart' => $article['ecart'] * $article['prix_unitaire'],
            'produit_id' => $article['produit_id']
        ]);
    }

    /**
     * Crée un mouvement de stock pour l'ajustement d'inventaire
     */
    private function creerMouvementAjustement(array $article, int $inventaireId): void
    {
        // Récupérer le stock avant/après
        $stmt = $this->db->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = ?");
        $stmt->execute([$article['produit_id']]);
        $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stockAvant = (int)$article['quantite_theorique'];
        $stockApres = (int)$article['quantite_comptee'];
        
        $typeMouvement = $article['ecart'] > 0 ? 'ENTREE' : 'SORTIE';
        
        $sql = "INSERT INTO mouvements_stock (
                    produit_id, type_mouvement, quantite, quantite_avant, quantite_apres,
                    motif, reference_type, reference_id, utilisateur_id, date_mouvement
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $article['produit_id'],
            $typeMouvement,
            abs($article['ecart']),
            $stockAvant,
            $stockApres,
            'Ajustement inventaire - ' . ($article['ecart'] > 0 ? 'Excédent' : 'Manquant'),
            'INVENTAIRE',
            $inventaireId,
            $article['utilisateur_saisie_id'] ?? 1
        ]);
    }

    /**
     * Logger les opérations d'inventaire
     */
    private function logInventaireOperation(int $utilisateurId, string $action, array $data): void
    {
        try {
            $sql = "INSERT INTO audit_logs (
                        utilisateur_id, action, table_name, new_values, 
                        ip_address, user_agent, date_action
                    ) VALUES (?, 'INVENTAIRE_OPERATION', 'inventaires', ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $utilisateurId,
                json_encode(['action' => $action, 'data' => $data]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log inventaire operation: " . $e->getMessage());
        }
    }

    /**
     * Exporte un inventaire en CSV
     */
    public function exporterInventaire(int $inventaireId): string
    {
        $inventaire = $this->getInventaire($inventaireId);
        $articles = $this->getArticlesInventaire($inventaireId);

        $csv = "Inventaire: " . $inventaire['reference'] . "\n";
        $csv .= "Date: " . $inventaire['date_debut'] . "\n";
        $csv .= "Statut: " . $inventaire['statut'] . "\n\n";
        $csv .= "Produit,Référence,Lot,Quantité Théorique,Quantité Comptée,Écart,Prix Unitaire,Valeur Totale\n";

        foreach ($articles as $article) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s\n",
                $article['produit_nom'],
                $article['reference'],
                $article['numero_lot'] ?? '',
                $article['quantite_theorique'],
                $article['quantite_comptee'],
                $article['ecart'],
                $article['prix_unitaire'],
                $article['valeur_totale']
            );
        }

        return $csv;
    }

    /**
     * Récupère les statistiques d'inventaires
     */
    public function getStatistiquesInventaires(?string $dateDebut = null, ?string $dateFin = null): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as nombre_inventaires,
                        COUNT(CASE WHEN statut = 'CLOTURE' THEN 1 END) as clotures,
                        COUNT(CASE WHEN statut = 'EN_COURS' THEN 1 END) => en_cours,
                        AVG(DATEDIFF(date_fin, date_debut)) as duree_moyenne,
                        SUM(total_ecart_valeur) as ecart_valeur_total
                    FROM inventaires";
            
            $params = [];
            $where = [];
            
            if ($dateDebut) {
                $where[] = "DATE(date_debut) >= ?";
                $params[] = $dateDebut;
            }
            
            if ($dateFin) {
                $where[] = "DATE(date_debut) <= ?";
                $params[] = $dateFin;
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $stmt->fetch(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
}
