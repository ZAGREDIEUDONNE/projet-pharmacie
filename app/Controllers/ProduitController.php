<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Services\AuditService;
use App\Services\ProduitScannerService;
use PDO;

final class ProduitController extends BaseController
{
    private function requireProduitViewAccess(): void
    {
        $this->requireAuth();
    }

    private function requireProduitManageAccess(): void
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));

        if (in_array($roleId, [1, 3, 4], true)
            || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR', 'ASSISTANT', 'CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true)) {
            return;
        }

        $this->requirePermission('produit.manage');
    }

    private function requireSortieStockAccess(): void
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));

        if (in_array($roleId, [1], true)
            || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true)) {
            return;
        }

        $this->requirePermission('sortie_stock');
    }

    private function requireGestionLotsAccess(): void
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));

        if (in_array($roleId, [1], true)
            || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true)) {
            return;
        }

        $this->requirePermission('gestion_lots');
    }

    public function index(): void
    {
        $this->requireProduitViewAccess();
        $this->render('produits/index', ['title' => 'Dashboard Produits']);
    }

    /**
     * Catalogue des produits pour le vendeur (lecture seule)
     */
    public function catalogueVendeur(): void
    {
        $this->requireAuth();
        $this->requirePermission('stock.view');
        
        $returnTo = $this->getSafeReturnUrl('/vente');
        
        // Récupérer les filtres
        $recherche = trim($_GET['recherche'] ?? '');
        $categorie = $_GET['categorie'] ?? null;
        $forme = $_GET['forme'] ?? null;
        $disponibilite = $_GET['disponibilite'] ?? null;
        $tri = $_GET['tri'] ?? 'nom';
        $ordre = $_GET['ordre'] ?? 'ASC';
        
        // Construire la requête
        $sql = "SELECT 
                    p.id,
                    p.code_cip,
                    p.code_barre,
                    p.nom,
                    p.dci,
                    p.forme_pharmaceutique,
                    p.classe_pharmaceutique,
                    p.prix_vente,
                    p.rayon,
                    p.is_actif,
                    c.nom as categorie,
                    COALESCE(s.quantite_disponible, 0) as stock_disponible,
                    COALESCE(s.quantite_theorique, 0) as stock_theorique
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.is_actif = 1";
        
        $params = [];
        
        // Filtre par recherche
        if ($recherche !== '') {
            $sql .= " AND (p.nom LIKE ? OR p.code_cip LIKE ? OR p.code_barre LIKE ? OR p.dci LIKE ?)";
            $term = '%' . $recherche . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        
        // Filtre par catégorie
        if ($categorie !== null && $categorie !== '') {
            $sql .= " AND p.categorie_id = ?";
            $params[] = $categorie;
        }
        
        // Filtre par forme pharmaceutique
        if ($forme !== null && $forme !== '') {
            $sql .= " AND p.forme_pharmaceutique = ?";
            $params[] = $forme;
        }
        
        // Filtre par disponibilité
        if ($disponibilite === 'disponible') {
            $sql .= " AND COALESCE(s.quantite_disponible, 0) > 0";
        } elseif ($disponibilite === 'rupture') {
            $sql .= " AND COALESCE(s.quantite_disponible, 0) = 0";
        } elseif ($disponibilite === 'faible') {
            $sql .= " AND COALESCE(s.quantite_disponible, 0) > 0 AND COALESCE(s.quantite_disponible, 0) < 10";
        }
        
        // Tri
        $colonnesTri = [
            'nom' => 'p.nom',
            'code' => 'p.code_cip',
            'prix' => 'p.prix_vente',
            'stock' => 's.quantite_disponible'
        ];
        $colonneTri = $colonnesTri[$tri] ?? 'p.nom';
        $ordreSql = $ordre === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$colonneTri} {$ordreSql}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les catégories pour le filtre
        $sqlCategories = "SELECT id, nom FROM categories ORDER BY nom";
        $stmtCategories = $this->db->prepare($sqlCategories);
        $stmtCategories->execute();
        $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les formes pharmaceutiques pour le filtre
        $sqlFormes = "SELECT DISTINCT forme_pharmaceutique FROM produits WHERE forme_pharmaceutique IS NOT NULL AND forme_pharmaceutique != '' ORDER BY forme_pharmaceutique";
        $stmtFormes = $this->db->prepare($sqlFormes);
        $stmtFormes->execute();
        $formes = $stmtFormes->fetchAll(PDO::FETCH_COLUMN);
        
        $this->render('produits/catalogue-vendeur', [
            'title' => 'Catalogue des Produits',
            'produits' => $produits,
            'categories' => $categories,
            'formes' => $formes,
            'recherche' => $recherche,
            'categorie' => $categorie,
            'forme' => $forme,
            'disponibilite' => $disponibilite,
            'tri' => $tri,
            'ordre' => $ordre,
            'returnTo' => $returnTo,
        ]);
    }

    public function inventaire(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $tri = $_GET['tri'] ?? 'nom';
            $ordre = $_GET['ordre'] ?? 'ASC';
            $filtre = $_GET['filtre'] ?? 'tous';
            
            $sql = "SELECT p.id, p.nom, p.code_cip, p.code_barre, p.prix_vente, p.prix_achat,
                           c.nom as categorie,
                           s.quantite_disponible as stock
                    FROM produits p
                    LEFT JOIN categories c ON p.categorie_id = c.id
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if ($filtre === 'avec_stock') {
                $sql .= " AND s.quantite_disponible > 0";
            }
            
            $sql .= " ORDER BY " . match($tri) {
                'nom' => 'p.nom',
                'code' => 'p.code_cip',
                default => 'p.nom'
            } . " " . ($ordre === 'DESC' ? 'DESC' : 'ASC');
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/inventaire', [
                'title' => 'Inventaire Produits',
                'produits' => $produits,
                'tri' => $tri,
                'ordre' => $ordre,
                'filtre' => $filtre
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function etatStocks(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $filtre = $_GET['filtre'] ?? 'tous';
            
            $sql = "SELECT p.id, p.nom, p.code_cip, p.prix_vente,
                           p.stock_securite, p.stock_alerte,
                           s.quantite_disponible as stock,
                           CASE 
                               WHEN s.quantite_disponible <= 0 THEN 'RUPTURE'
                               WHEN s.quantite_disponible < p.stock_securite THEN 'CRITIQUE'
                               WHEN s.quantite_disponible < p.stock_alerte THEN 'ALERTE'
                               WHEN s.quantite_disponible >= p.stock_alerte THEN 'NORMAL'
                           END as statut
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if ($filtre === 'disponible') {
                $sql .= " AND s.quantite_disponible > 0";
            } elseif ($filtre === 'stock_nul') {
                $sql .= " AND s.quantite_disponible = 0";
            } elseif ($filtre === 'stock_negatif') {
                $sql .= " AND s.quantite_disponible < 0";
            } elseif ($filtre === 'sous_minimum') {
                $sql .= " AND s.quantite_disponible < p.stock_securite";
            } elseif ($filtre === 'rupture') {
                $sql .= " AND s.quantite_disponible = 0";
            }
            
            $sql .= " ORDER BY 
                CASE 
                    WHEN s.quantite_disponible <= 0 THEN 1
                    WHEN s.quantite_disponible < p.stock_securite THEN 2
                    WHEN s.quantite_disponible < p.stock_alerte THEN 3
                    ELSE 4
                END, p.nom";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/etat-stocks', [
                'title' => 'État des Stocks',
                'produits' => $produits,
                'filtre' => $filtre
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function listePrix(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $recherche = trim($_GET['recherche'] ?? '');
            $tri = $_GET['tri'] ?? 'nom';
            $ordre = $_GET['ordre'] ?? 'ASC';
            
            $sql = "SELECT p.id, p.nom, p.code_cip, p.prix_achat, p.prix_vente,
                           c.nom as categorie
                    FROM produits p
                    LEFT JOIN categories c ON p.categorie_id = c.id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if (!empty($recherche)) {
                $sql .= " AND (p.nom LIKE :recherche OR p.code_cip LIKE :recherche)";
            }
            
            $sql .= " ORDER BY " . match($tri) {
                'nom' => 'p.nom',
                'prix' => 'p.prix_vente',
                default => 'p.nom'
            } . " " . ($ordre === 'DESC' ? 'DESC' : 'ASC');
            
            $stmt = $this->db->prepare($sql);
            
            if (!empty($recherche)) {
                $stmt->bindValue(':recherche', "%$recherche%");
            }
            
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/liste-prix', [
                'title' => 'Liste des Prix',
                'produits' => $produits,
                'recherche' => $recherche,
                'tri' => $tri,
                'ordre' => $ordre
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function gestionMiniMaxi(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $sql = "SELECT p.id, p.nom, p.code_cip, p.prix_vente,
                           p.stock_securite as stock_minimum,
                           p.stock_alerte as stock_maximum,
                           s.quantite_disponible as stock_actuel,
                           (s.quantite_disponible - p.stock_securite) as ecart,
                           CASE 
                               WHEN s.quantite_disponible < p.stock_securite THEN 'SOUS_MINIMUM'
                               WHEN s.quantite_disponible > p.stock_alerte THEN 'SUR_MAXIMUM'
                               WHEN s.quantite_disponible BETWEEN p.stock_securite AND p.stock_alerte THEN 'NORMAL'
                               ELSE 'ALERTE'
                           END as statut
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1
                    ORDER BY 
                        CASE 
                            WHEN s.quantite_disponible < p.stock_securite THEN 1
                            WHEN s.quantite_disponible > p.stock_alerte THEN 2
                            ELSE 3
                        END, p.nom";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/gestion-mini-maxi', [
                'title' => 'Gestion Mini / Maxi',
                'produits' => $produits
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function produitsSpecifiques(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $filtres = $_GET['filtres'] ?? [];
            if (!is_array($filtres)) {
                $filtres = [];
            }
            
            $conditions = [];
            $params = [];
            
            // Filtres commentés car les champs n'existent pas dans la table produits
            // if (in_array('traceurs', $filtres)) {
            //     $conditions[] = "p.est_traceur = 1";
            // }
            // if (in_array('suspendus', $filtres)) {
            //     $conditions[] = "p.is_suspendu = 1";
            // }
            // if (in_array('hors_extranet', $filtres)) {
            //     $conditions[] = "p.hors_extranet = 1";
            // }
            // if (in_array('hors_etiquette', $filtres)) {
            //     $conditions[] = "p.hors_etiquette = 1";
            // }
            // if (in_array('remise_plafonnee', $filtres)) {
            //     $conditions[] = "p.remise_plafonnee = 1";
            // }
            // if (in_array('tva', $filtres)) {
            //     $conditions[] = "p.soumis_tva = 1";
            // }
            if (in_array('fournisseur', $filtres) && !empty($_GET['fournisseur_id'])) {
                $conditions[] = "p.fournisseur_id = :fournisseur_id";
                $params[':fournisseur_id'] = (int)$_GET['fournisseur_id'];
            }
            
            $sql = "SELECT p.id, p.nom, p.code_cip, p.prix_vente,
                           c.nom as categorie, f.nom as fournisseur,
                           s.quantite_disponible as stock
                    FROM produits p
                    LEFT JOIN categories c ON p.categorie_id = c.id
                    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(' OR ', $conditions) . ")";
            }
            
            $sql .= " ORDER BY p.nom";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtFournisseurs = $this->db->prepare("SELECT id, nom FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmtFournisseurs->execute();
            $fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/produits-specifiques', [
                'title' => 'Produits Spécifiques',
                'produits' => $produits,
                'filtres' => $filtres,
                'fournisseurs' => $fournisseurs
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function coefficientsVente(): void
    {
        $this->requireProduitManageAccess();
        
        try {
            $coefficientMin = floatval($_GET['coefficient_min'] ?? 1.48);
            $coefficientMax = floatval($_GET['coefficient_max'] ?? 2.0);
            
            $sql = "SELECT p.id, p.nom, p.code_cip, p.prix_achat, p.prix_vente,
                           (p.prix_vente / p.prix_achat) as coefficient_actuel
                    FROM produits p
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1
                      AND p.prix_achat > 0
                    ORDER BY p.nom";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $simulation = [];
            foreach ($produits as $produit) {
                $prixSimule = $produit['prix_achat'] * $coefficientMin;
                $simulation[] = [
                    'id' => $produit['id'],
                    'nom' => $produit['nom'],
                    'prix_achat' => $produit['prix_achat'],
                    'prix_vente_actuel' => $produit['prix_vente'],
                    'coefficient_actuel' => $produit['coefficient_actuel'],
                    'prix_vente_simule' => $prixSimule,
                    'coefficient_simule' => $coefficientMin
                ];
            }
            
            $this->render('produits/coefficients-vente', [
                'title' => 'Coefficients de Vente',
                'produits' => $produits,
                'simulation' => $simulation,
                'coefficient_min' => $coefficientMin,
                'coefficient_max' => $coefficientMax
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function sortieStock(): void
    {
        $this->requireSortieStockAccess();
        
        try {
            $stmt = $this->db->prepare("SELECT id, nom, code_cip FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $motifs = [
                'PEREMPTION' => 'Produit périmé',
                'CASSE' => 'Produit cassé',
                'DON' => 'Don',
                'CONSOMMATION_INTERNE' => 'Consommation interne',
                'RETOUR_FOURNISSEUR' => 'Retour fournisseur',
                'DESTRUCTION' => 'Destruction',
                'AUTRE' => 'Autre'
            ];
            
            $this->render('produits/sortie-stock', [
                'title' => 'Sortie de Stock',
                'produits' => $produits,
                'motifs' => $motifs,
                'returnTo' => $this->getSafeReturnUrl('/produits')
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function storeSortieStock(): void
    {
        $this->requireSortieStockAccess();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/produits/sortie-stock');
            return;
        }
        
        try {
            $produitId = (int)($_POST['produit_id'] ?? 0);
            $lotId = !empty($_POST['lot_id']) ? (int)$_POST['lot_id'] : null;
            $quantite = (int)($_POST['quantite'] ?? 0);
            $motif = $_POST['motif'] ?? '';
            $dateSortie = $_POST['date_sortie'] ?? date('Y-m-d');
            $observations = $_POST['observations'] ?? '';
            $utilisateurId = (int)($this->getCurrentUser()['id'] ?? 0);
            
            if ($produitId <= 0 || $quantite <= 0) {
                $_SESSION['error'] = 'Veuillez remplir tous les champs obligatoires';
                $_SESSION['old_data'] = $_POST;
                $this->redirect('/produits/sortie-stock');
                return;
            }
            
            $this->db->beginTransaction();
            
            // Vérifier le stock disponible
            $stmt = $this->db->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stockData) {
                $_SESSION['error'] = 'Produit non trouvé dans le stock';
                $this->db->rollBack();
                $this->redirect('/produits/sortie-stock');
                return;
            }
            
            $stockDisponible = (int)$stockData['quantite_disponible'];
            
            if ($quantite > $stockDisponible) {
                $_SESSION['error'] = "Stock insuffisant. Disponible: $stockDisponible, Demandé: $quantite";
                $this->db->rollBack();
                $this->redirect('/produits/sortie-stock');
                return;
            }
            
            // Vérifier le stock du lot si spécifié
            if ($lotId) {
                $stmtLot = $this->db->prepare("SELECT quantite_restante, statut_lot, date_peremption FROM lots WHERE id = :lot_id AND produit_id = :produit_id");
                $stmtLot->execute(['lot_id' => $lotId, 'produit_id' => $produitId]);
                $lotData = $stmtLot->fetch(PDO::FETCH_ASSOC);
                
                if (!$lotData) {
                    $_SESSION['error'] = 'Lot non trouvé pour ce produit';
                    $this->db->rollBack();
                    $this->redirect('/produits/sortie-stock');
                    return;
                }
                
                if ($lotData['statut_lot'] === 'SUSPENDU') {
                    $_SESSION['error'] = 'Ce lot est suspendu et ne peut pas être utilisé';
                    $this->db->rollBack();
                    $this->redirect('/produits/sortie-stock');
                    return;
                }
                
                if ($lotData['date_peremption'] && strtotime($lotData['date_peremption']) < strtotime(date('Y-m-d'))) {
                    $_SESSION['error'] = 'Ce lot est expiré et ne peut pas être utilisé';
                    $this->db->rollBack();
                    $this->redirect('/produits/sortie-stock');
                    return;
                }
                
                $lotQuantite = (int)$lotData['quantite_restante'];
                
                if ($quantite > $lotQuantite) {
                    $_SESSION['error'] = "Stock insuffisant dans le lot. Disponible: $lotQuantite, Demandé: $quantite";
                    $this->db->rollBack();
                    $this->redirect('/produits/sortie-stock');
                    return;
                }
            }
            
            // Créer le mouvement de stock
            $sqlMouvement = "INSERT INTO mouvements_stock 
                           (produit_id, lot_id, type_mouvement, quantite, quantite_avant, quantite_apres, 
                            motif, reference_type, utilisateur_id, date_mouvement)
                           VALUES (:produit_id, :lot_id, 'SORTIE', :quantite, :quantite_avant, :quantite_apres,
                                   :motif, 'AJUSTEMENT', :utilisateur_id, :date_mouvement)";
            
            $stmtMouvement = $this->db->prepare($sqlMouvement);
            $stmtMouvement->execute([
                'produit_id' => $produitId,
                'lot_id' => $lotId,
                'quantite' => $quantite,
                'quantite_avant' => $stockDisponible,
                'quantite_apres' => $stockDisponible - $quantite,
                'motif' => $motif . ($observations ? ' - ' . $observations : ''),
                'utilisateur_id' => $utilisateurId,
                'date_mouvement' => $dateSortie . ' ' . date('H:i:s')
            ]);
            
            // Mettre à jour le stock
            $sqlUpdateStock = "UPDATE stock SET quantite_disponible = quantite_disponible - :quantite,
                               dernier_mouvement = NOW() WHERE produit_id = :produit_id";
            $stmtUpdateStock = $this->db->prepare($sqlUpdateStock);
            $stmtUpdateStock->execute([
                'quantite' => $quantite,
                'produit_id' => $produitId
            ]);
            
            // Mettre à jour le lot si spécifié
            if ($lotId) {
                $sqlUpdateLot = "UPDATE lots SET quantite_restante = quantite_restante - :quantite
                                 WHERE id = :lot_id";
                $stmtUpdateLot = $this->db->prepare($sqlUpdateLot);
                $stmtUpdateLot->execute([
                    'quantite' => $quantite,
                    'lot_id' => $lotId
                ]);
            }
            
            // Enregistrer dans l'audit
            $auditService = new AuditService($this->db);
            $auditService->log(
                $utilisateurId,
                'SORTIE_STOCK',
                "Sortie de stock: Produit ID $produitId, Quantité: $quantite, Motif: $motif"
            );
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Sortie de stock enregistrée avec succès';
            $this->redirect('/produits/historique-sorties');
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/produits/sortie-stock');
        }
    }

    public function historiqueSorties(): void
    {
        $this->requireSortieStockAccess();
        
        try {
            $filtreProduit = $_GET['produit_id'] ?? '';
            $filtreUtilisateur = $_GET['utilisateur_id'] ?? '';
            $filtreMotif = $_GET['motif'] ?? '';
            $dateDebut = $_GET['date_debut'] ?? '';
            $dateFin = $_GET['date_fin'] ?? '';
            
            $sql = "SELECT ms.*, p.nom as produit_nom, p.code_cip, l.numero_lot, u.username as utilisateur_nom
                    FROM mouvements_stock ms
                    LEFT JOIN produits p ON ms.produit_id = p.id
                    LEFT JOIN lots l ON ms.lot_id = l.id
                    LEFT JOIN utilisateurs u ON ms.utilisateur_id = u.id
                    WHERE ms.type_mouvement = 'SORTIE'";
            
            $params = [];
            
            if (!empty($filtreProduit)) {
                $sql .= " AND ms.produit_id = :produit_id";
                $params[':produit_id'] = (int)$filtreProduit;
            }
            
            if (!empty($filtreUtilisateur)) {
                $sql .= " AND ms.utilisateur_id = :utilisateur_id";
                $params[':utilisateur_id'] = (int)$filtreUtilisateur;
            }
            
            if (!empty($filtreMotif)) {
                $sql .= " AND ms.motif LIKE :motif";
                $params[':motif'] = "%$filtreMotif%";
            }
            
            if (!empty($dateDebut)) {
                $sql .= " AND DATE(ms.date_mouvement) >= :date_debut";
                $params[':date_debut'] = $dateDebut;
            }
            
            if (!empty($dateFin)) {
                $sql .= " AND DATE(ms.date_mouvement) <= :date_fin";
                $params[':date_fin'] = $dateFin;
            }
            
            $sql .= " ORDER BY ms.date_mouvement DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $sorties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtProduits = $this->db->prepare("SELECT id, nom FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmtProduits->execute();
            $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtUtilisateurs = $this->db->prepare("SELECT id, username FROM utilisateurs WHERE is_active = 1 ORDER BY username");
            $stmtUtilisateurs->execute();
            $utilisateurs = $stmtUtilisateurs->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/historique-sorties', [
                'title' => 'Historique des Sorties',
                'sorties' => $sorties,
                'produits' => $produits,
                'utilisateurs' => $utilisateurs,
                'filtre_produit' => $filtreProduit,
                'filtre_utilisateur' => $filtreUtilisateur,
                'filtre_motif' => $filtreMotif,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function ficheLot(): void
    {
        $this->requireGestionLotsAccess();
        
        $lotId = (int)($_GET['id'] ?? 0);
        
        if ($lotId <= 0) {
            $_SESSION['error'] = 'ID lot non fourni';
            $this->redirect('/produits');
            return;
        }
        
        try {
            $sql = "SELECT l.*, p.nom as produit_nom, p.code_cip, f.nom as fournisseur_nom,
                           u.username as utilisateur_suspension_nom
                    FROM lots l
                    LEFT JOIN produits p ON l.produit_id = p.id
                    LEFT JOIN fournisseurs f ON l.fournisseur_id = f.id
                    LEFT JOIN utilisateurs u ON l.utilisateur_suspension_id = u.id
                    WHERE l.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$lotId]);
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lot) {
                $_SESSION['error'] = 'Lot non trouvé';
                $this->redirect('/produits');
                return;
            }
            
            $this->render('produits/fiche-lot', [
                'title' => 'Fiche Lot',
                'lot' => $lot
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function suspendreLot(): void
    {
        $this->requireGestionLotsAccess();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/produits');
            return;
        }
        
        try {
            $lotId = (int)($_POST['lot_id'] ?? 0);
            $motif = $_POST['motif_suspension'] ?? '';
            $utilisateurId = (int)($this->getCurrentUser()['id'] ?? 0);
            
            if ($lotId <= 0 || empty($motif)) {
                $_SESSION['error'] = 'Veuillez remplir tous les champs obligatoires';
                $this->redirect('/produits/fiche-lot?id=' . $lotId);
                return;
            }
            
            $this->db->beginTransaction();
            
            // Récupérer l'ancien statut
            $stmt = $this->db->prepare("SELECT statut_lot FROM lots WHERE id = ?");
            $stmt->execute([$lotId]);
            $lotData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lotData) {
                $_SESSION['error'] = 'Lot non trouvé';
                $this->db->rollBack();
                $this->redirect('/produits');
                return;
            }
            
            $ancienStatut = $lotData['statut_lot'] ?? 'ACTIF';
            
            // Mettre à jour le lot
            $sql = "UPDATE lots SET statut_lot = 'SUSPENDU', 
                    motif_suspension = :motif,
                    date_suspension = CURDATE(),
                    utilisateur_suspension_id = :utilisateur_id
                    WHERE id = :lot_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'motif' => $motif,
                'utilisateur_id' => $utilisateurId,
                'lot_id' => $lotId
            ]);
            
            // Enregistrer dans l'audit
            $auditService = new AuditService($this->db);
            $auditService->log(
                $utilisateurId,
                'SUSPENDRE_LOT',
                "Suspension du lot ID $lotId. Ancien statut: $ancienStatut, Nouveau statut: SUSPENDU, Motif: $motif"
            );
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Lot suspendu avec succès';
            $this->redirect('/produits/fiche-lot?id=' . $lotId);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits/fiche-lot?id=' . ($_POST['lot_id'] ?? 0));
        }
    }

    public function reactiverLot(): void
    {
        $this->requireGestionLotsAccess();
        
        $lotId = (int)($_GET['id'] ?? 0);
        
        if ($lotId <= 0) {
            $_SESSION['error'] = 'ID lot non fourni';
            $this->redirect('/produits');
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Récupérer l'ancien statut
            $stmt = $this->db->prepare("SELECT statut_lot FROM lots WHERE id = ?");
            $stmt->execute([$lotId]);
            $lotData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lotData) {
                $_SESSION['error'] = 'Lot non trouvé';
                $this->db->rollBack();
                $this->redirect('/produits');
                return;
            }
            
            $ancienStatut = $lotData['statut_lot'] ?? 'SUSPENDU';
            
            // Réactiver le lot
            $sql = "UPDATE lots SET statut_lot = 'ACTIF', 
                    motif_suspension = NULL,
                    date_suspension = NULL,
                    utilisateur_suspension_id = NULL
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$lotId]);
            
            // Enregistrer dans l'audit
            $utilisateurId = (int)($this->getCurrentUser()['id'] ?? 0);
            $auditService = new AuditService($this->db);
            $auditService->log(
                $utilisateurId,
                'REACTIVER_LOT',
                "Réactivation du lot ID $lotId. Ancien statut: $ancienStatut, Nouveau statut: ACTIF"
            );
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Lot réactivé avec succès';
            $this->redirect('/produits/fiche-lot?id=' . $lotId);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits/fiche-lot?id=' . $lotId);
        }
    }

    public function apiProduitStock(): void
    {
        $this->requireAuth();
        
        $produitId = (int)($_GET['id'] ?? 0);
        
        if ($produitId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID produit invalide']);
            exit;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = ?");
            $stmt->execute([$produitId]);
            $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['stock' => $stockData ? (int)$stockData['quantite_disponible'] : 0]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public function apiProduitLots(): void
    {
        $this->requireAuth();
        
        $produitId = (int)($_GET['id'] ?? 0);
        
        if ($produitId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID produit invalide']);
            exit;
        }
        
        try {
            $sql = "SELECT l.id, l.numero_lot, l.date_peremption, l.quantite_restante, l.statut_lot
                    FROM lots l
                    WHERE l.produit_id = :produit_id
                      AND l.is_actif = 1
                      AND l.statut_lot = 'ACTIF'
                      AND l.quantite_restante > 0
                      AND (l.date_peremption IS NULL OR l.date_peremption >= CURDATE())
                    ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['produit_id' => $produitId]);
            $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['lots' => $lots, 'has_lots' => count($lots) > 0]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public function entreeStock(): void
    {
        $this->requireProduitManageAccess();
        
        try {
            $stmt = $this->db->prepare("SELECT id, nom, code_cip FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtFournisseurs = $this->db->prepare("SELECT id, nom FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmtFournisseurs->execute();
            $fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/entree-stock', [
                'title' => 'Entrée de Stock',
                'produits' => $produits,
                'fournisseurs' => $fournisseurs
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function storeEntreeStock(): void
    {
        $this->requireProduitManageAccess();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/produits/entree-stock');
            return;
        }
        
        try {
            $produitId = (int)($_POST['produit_id'] ?? 0);
            $lotId = !empty($_POST['lot_id']) ? (int)$_POST['lot_id'] : null;
            $quantite = (int)($_POST['quantite'] ?? 0);
            $prixAchat = (float)($_POST['prix_achat'] ?? 0);
            $motif = $_POST['motif'] ?? '';
            $fournisseurId = (int)($_POST['fournisseur_id'] ?? 0);
            $dateEntree = $_POST['date_entree'] ?? date('Y-m-d');
            $observations = $_POST['observations'] ?? '';
            $utilisateurId = (int)($this->getCurrentUser()['id'] ?? 0);
            
            if ($produitId <= 0 || $quantite <= 0 || $prixAchat <= 0 || empty($motif)) {
                $_SESSION['error'] = 'Veuillez remplir tous les champs obligatoires';
                $_SESSION['old_data'] = $_POST;
                $this->redirect('/produits/entree-stock');
                return;
            }
            
            $this->db->beginTransaction();
            
            // Vérifier si le stock existe pour ce produit
            $stmt = $this->db->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stockAvant = $stockData ? (int)$stockData['quantite_disponible'] : 0;
            
            if (!$stockData) {
                // Créer l'enregistrement de stock
                $stmtCreate = $this->db->prepare(
                    "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                     VALUES (:produit_id, 0, 0, 0, NOW(), NOW())"
                );
                $stmtCreate->execute(['produit_id' => $produitId]);
                $stockAvant = 0;
            }
            
            // Créer le mouvement de stock
            $sqlMouvement = "INSERT INTO mouvements_stock 
                           (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, 
                            motif, reference_type, utilisateur_id, date_mouvement)
                           VALUES (:produit_id, 'ENTREE', :quantite, :quantite_avant, :quantite_apres,
                                   :motif, 'AJUSTEMENT', :utilisateur_id, :date_mouvement)";
            
            $stmtMouvement = $this->db->prepare($sqlMouvement);
            $stmtMouvement->execute([
                'produit_id' => $produitId,
                'quantite' => $quantite,
                'quantite_avant' => $stockAvant,
                'quantite_apres' => $stockAvant + $quantite,
                'motif' => $motif . ($observations ? ' - ' . $observations : ''),
                'utilisateur_id' => $utilisateurId,
                'date_mouvement' => $dateEntree . ' ' . date('H:i:s')
            ]);
            
            // Mettre à jour le stock
            $sqlUpdateStock = "UPDATE stock SET quantite_disponible = quantite_disponible + :quantite,
                               quantite_theorique = quantite_theorique + :quantite,
                               valeur_stock = valeur_stock + :valeur,
                               dernier_mouvement = NOW() WHERE produit_id = :produit_id";
            $stmtUpdateStock = $this->db->prepare($sqlUpdateStock);
            $stmtUpdateStock->execute([
                'quantite' => $quantite,
                'valeur' => $quantite * $prixAchat,
                'produit_id' => $produitId
            ]);
            
            // Enregistrer dans l'audit
            $auditService = new AuditService($this->db);
            $auditService->log(
                $utilisateurId,
                'ENTREE_STOCK_MANUELLE',
                "Entrée de stock manuelle: Produit ID $produitId, Quantité: $quantite, Prix achat: $prixAchat, Motif: $motif"
            );
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Entrée de stock enregistrée avec succès';
            $this->redirect('/produits');
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/produits/entree-stock');
        }
    }

    public function ajustementStock(): void
    {
        $this->requireProduitManageAccess();
        
        try {
            $stmt = $this->db->prepare("SELECT id, nom, code_cip FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/ajustement-stock', [
                'title' => 'Ajustement de Stock',
                'produits' => $produits
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function storeAjustementStock(): void
    {
        $this->requireProduitManageAccess();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/produits/ajustement-stock');
            return;
        }
        
        try {
            $produitId = (int)($_POST['produit_id'] ?? 0);
            $nouveauStock = (int)($_POST['nouveau_stock'] ?? 0);
            $motif = $_POST['motif'] ?? '';
            $observations = $_POST['observations'] ?? '';
            $utilisateurId = (int)($this->getCurrentUser()['id'] ?? 0);
            
            if ($produitId <= 0) {
                $_SESSION['error'] = 'Veuillez sélectionner un produit';
                $_SESSION['old_data'] = $_POST;
                $this->redirect('/produits/ajustement-stock');
                return;
            }
            
            $this->db->beginTransaction();
            
            // Récupérer le stock actuel
            $stmt = $this->db->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = :produit_id");
            $stmt->execute(['produit_id' => $produitId]);
            $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stockAvant = $stockData ? (int)$stockData['quantite_disponible'] : 0;
            
            if (!$stockData) {
                // Créer l'enregistrement de stock
                $stmtCreate = $this->db->prepare(
                    "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                     VALUES (:produit_id, :nouveau_stock, :nouveau_stock, 0, NOW(), NOW())"
                );
                $stmtCreate->execute(['produit_id' => $produitId, 'nouveau_stock' => $nouveauStock]);
                $stockAvant = 0;
            }
            
            $difference = $nouveauStock - $stockAvant;
            $typeMouvement = $difference >= 0 ? 'ENTREE' : 'SORTIE';
            $quantiteMouvement = abs($difference);
            
            // Créer le mouvement de stock
            $sqlMouvement = "INSERT INTO mouvements_stock 
                           (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, 
                            motif, reference_type, utilisateur_id, date_mouvement)
                           VALUES (:produit_id, :type_mouvement, :quantite, :quantite_avant, :quantite_apres,
                                   :motif, 'AJUSTEMENT', :utilisateur_id, NOW())";
            
            $stmtMouvement = $this->db->prepare($sqlMouvement);
            $stmtMouvement->execute([
                'produit_id' => $produitId,
                'type_mouvement' => $typeMouvement,
                'quantite' => $quantiteMouvement,
                'quantite_avant' => $stockAvant,
                'quantite_apres' => $nouveauStock,
                'motif' => 'Ajustement de stock: ' . $motif . ($observations ? ' - ' . $observations : ''),
                'utilisateur_id' => $utilisateurId
            ]);
            
            // Mettre à jour le stock
            $sqlUpdateStock = "UPDATE stock SET quantite_disponible = :nouveau_stock,
                               quantite_theorique = :nouveau_stock,
                               dernier_mouvement = NOW() WHERE produit_id = :produit_id";
            $stmtUpdateStock = $this->db->prepare($sqlUpdateStock);
            $stmtUpdateStock->execute([
                'nouveau_stock' => $nouveauStock,
                'produit_id' => $produitId
            ]);
            
            // Enregistrer dans l'audit
            $auditService = new AuditService($this->db);
            $auditService->log(
                $utilisateurId,
                'AJUSTEMENT_STOCK',
                "Ajustement de stock: Produit ID $produitId, Ancien stock: $stockAvant, Nouveau stock: $nouveauStock, Différence: $difference"
            );
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Ajustement de stock enregistré avec succès';
            $this->redirect('/produits');
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/produits/ajustement-stock');
        }
    }

    public function produitsExpires(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $joursAlerte = (int)($_GET['jours_alerte'] ?? 30);
            $dateLimite = date('Y-m-d', strtotime("+$joursAlerte days"));
            
            $sql = "SELECT p.id, p.nom, p.code_cip, p.prix_vente,
                           l.numero_lot, l.date_peremption, l.quantite_restante, l.statut_lot,
                           DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                           CASE 
                               WHEN l.date_peremption < CURDATE() THEN 'EXPIRE'
                               WHEN l.date_peremption <= :date_limite THEN 'ALERT'
                               ELSE 'OK'
                           END as statut
                    FROM produits p
                    INNER JOIN lots l ON p.id = l.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1
                      AND l.is_actif = 1
                      AND l.quantite_restante > 0
                      AND l.date_peremption IS NOT NULL
                    ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['date_limite' => $dateLimite]);
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('produits/produits-expires', [
                'title' => 'Produits Expirés',
                'produits' => $produits,
                'jours_alerte' => $joursAlerte
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    public function rapports(): void
    {
        $this->requireProduitViewAccess();
        
        try {
            $typeRapport = $_GET['type'] ?? 'general';
            
            $data = [];
            
            switch ($typeRapport) {
                case 'general':
                    $sql = "SELECT COUNT(*) as total_produits,
                                   SUM(CASE WHEN s.quantite_disponible > 0 THEN 1 ELSE 0 END) as produits_en_stock,
                                   SUM(CASE WHEN s.quantite_disponible = 0 THEN 1 ELSE 0 END) as produits_sans_stock,
                                   SUM(s.quantite_disponible) as stock_total,
                                   SUM(s.valeur_stock) as valeur_stock_total
                            FROM produits p
                            LEFT JOIN stock s ON p.id = s.produit_id
                            WHERE p.deleted_at IS NULL AND p.is_actif = 1";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute();
                    $data['general'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
                    
                case 'mouvements':
                    $sql = "SELECT type_mouvement, COUNT(*) as nombre, SUM(quantite) as total_quantite
                            FROM mouvements_stock
                            WHERE date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                            GROUP BY type_mouvement";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute();
                    $data['mouvements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'peremptions':
                    $sql = "SELECT COUNT(*) as total,
                                   SUM(CASE WHEN date_peremption < CURDATE() THEN 1 ELSE 0 END) as expires,
                                   SUM(CASE WHEN date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as alerte
                            FROM lots
                            WHERE is_actif = 1 AND quantite_restante > 0";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute();
                    $data['peremptions'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
            }
            
            $this->render('produits/rapports', [
                'title' => 'Rapports Produits',
                'type_rapport' => $typeRapport,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/produits');
        }
    }

    /**
     * Recherche rapide pour le vendeur
     * Permet de rechercher par nom, code CIP, code-barres, DCI
     */
    public function rechercheVendeur(): void
    {
        $this->requireAuth();
        $this->requirePermission('stock.view');
        
        try {
            $recherche = trim($_GET['recherche'] ?? '');
            $type = $_GET['type'] ?? 'nom'; // nom, code_cip, code_barre, dci
            
            $sql = "SELECT p.id, p.code_cip, p.code_barre, p.nom, p.dci,
                           p.forme_pharmaceutique, p.prix_vente,
                           COALESCE(s.quantite_disponible, 0) as quantite_disponible,
                           CASE 
                               WHEN COALESCE(s.quantite_disponible, 0) > 0 THEN 'Disponible'
                               ELSE 'Rupture'
                           END as statut
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if (!empty($recherche)) {
                $sql .= match($type) {
                    'nom' => " AND p.nom LIKE :recherche",
                    'code_cip' => " AND p.code_cip LIKE :recherche",
                    'code_barre' => " AND p.code_barre LIKE :recherche",
                    'dci' => " AND p.dci LIKE :recherche",
                    default => " AND (p.nom LIKE :recherche OR p.code_cip LIKE :recherche OR p.code_barre LIKE :recherche OR p.dci LIKE :recherche)"
                };
            }
            
            $sql .= " ORDER BY p.nom LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
            
            if (!empty($recherche)) {
                $stmt->bindValue(':recherche', "%$recherche%");
            }
            
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $returnTo = $_GET['return_to'] ?? '/vente';
            
            $this->render('produits/recherche-vendeur', [
                'title' => 'Recherche Produits',
                'produits' => $produits,
                'recherche' => $recherche,
                'type' => $type,
                'returnTo' => $returnTo
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect($_GET['return_to'] ?? '/vente');
        }
    }

    /**
     * Page scanner HID. Avec ?code=, répond en JSON pour la douchette / AJAX.
     */
    public function scanner(): void
    {
        $this->requireAuth();
        $this->requirePermission('vente.create');
        $returnTo = $this->getSafeReturnUrl('/vente');
        $code = trim((string)($_GET['code'] ?? ''));

        if ($code !== '') {
            header('Content-Type: application/json; charset=utf-8');
            if (mb_strlen($code) > 50) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Code-barres invalide']);
                return;
            }
            try {
                $produit = (new ProduitScannerService($this->db))->rechercherParCodeBarres($code);
                if (!$produit) {
                    echo json_encode(['success' => false, 'message' => 'Aucun produit trouvé pour ce code-barres.']);
                    return;
                }
                echo json_encode(['success' => true, 'produit' => $produit]);
            } catch (\Throwable $e) {
                error_log('ProduitController::scanner - ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Recherche indisponible, veuillez réessayer.']);
            }
            return;
        }

        $this->render('produits/scanner', ['title' => 'Scanner un produit', 'returnTo' => $returnTo]);
    }

    /**
     * Consultation des disponibilités pour le vendeur
     * Affiche le stock avec badges d'état
     */
    public function disponibiliteVendeur(): void
    {
        $this->requireAuth();
        $this->requirePermission('stock.view');
        
        try {
            $filtre = $_GET['filtre'] ?? 'tous'; // tous, disponible, faible, rupture
            
            $sql = "SELECT p.id, p.nom, p.code_cip,
                           COALESCE(s.quantite_disponible, 0) as quantite_disponible,
                           p.stock_securite as stock_minimum,
                           CASE 
                               WHEN COALESCE(s.quantite_disponible, 0) = 0 THEN 'rupture'
                               WHEN COALESCE(s.quantite_disponible, 0) < p.stock_securite THEN 'faible'
                               ELSE 'disponible'
                           END as etat
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if ($filtre === 'disponible') {
                $sql .= " AND s.quantite_disponible > 0";
            } elseif ($filtre === 'faible') {
                $sql .= " AND s.quantite_disponible > 0 AND s.quantite_disponible < p.stock_securite";
            } elseif ($filtre === 'rupture') {
                $sql .= " AND s.quantite_disponible = 0";
            }
            
            $sql .= " ORDER BY 
                CASE 
                    WHEN s.quantite_disponible = 0 THEN 1
                    WHEN s.quantite_disponible < p.stock_securite THEN 2
                    ELSE 3
                END, p.nom
                LIMIT 200";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $returnTo = $_GET['return_to'] ?? '/vente';
            
            $this->render('produits/disponibilite-vendeur', [
                'title' => 'Disponibilité des Produits',
                'produits' => $produits,
                'filtre' => $filtre,
                'returnTo' => $returnTo
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect($_GET['return_to'] ?? '/vente');
        }
    }

    /**
     * Catalogue des prix pour le vendeur
     * Affiche les prix de vente avec recherche
     */
    public function prixVendeur(): void
    {
        $this->requireAuth();
        $this->requirePermission('stock.view');
        
        try {
            $recherche = trim($_GET['recherche'] ?? '');
            $tri = $_GET['tri'] ?? 'nom';
            $ordre = $_GET['ordre'] ?? 'ASC';
            
            $sql = "SELECT p.id, p.nom, p.dci, p.forme_pharmaceutique,
                           p.prix_vente,
                           COALESCE((SELECT t.taux FROM tva_taux t WHERE t.code = 'TVA_18' AND t.is_actif = 1 LIMIT 1), 0) AS tva_taux,
                           c.nom as categorie
                    FROM produits p
                    LEFT JOIN categories c ON p.categorie_id = c.id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            if (!empty($recherche)) {
                // With native PDO prepared statements, each occurrence needs its
                // own placeholder (emulated prepares are disabled in Database).
                $sql .= " AND (p.nom LIKE :recherche_nom OR p.dci LIKE :recherche_dci OR p.code_cip LIKE :recherche_cip)";
            }
            
            $sql .= " ORDER BY " . match($tri) {
                'nom' => 'p.nom',
                'prix' => 'p.prix_vente',
                'dci' => 'p.dci',
                default => 'p.nom'
            } . " " . ($ordre === 'DESC' ? 'DESC' : 'ASC') . " LIMIT 200";
            
            $stmt = $this->db->prepare($sql);
            
            if (!empty($recherche)) {
                $term = "%$recherche%";
                $stmt->bindValue(':recherche_nom', $term);
                $stmt->bindValue(':recherche_dci', $term);
                $stmt->bindValue(':recherche_cip', $term);
            }
            
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $returnTo = $_GET['return_to'] ?? '/vente';
            
            $this->render('produits/prix-vendeur', [
                'title' => 'Catalogue des Prix',
                'produits' => $produits,
                'recherche' => $recherche,
                'tri' => $tri,
                'ordre' => $ordre,
                'returnTo' => $returnTo
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect($_GET['return_to'] ?? '/vente');
        }
    }
}
