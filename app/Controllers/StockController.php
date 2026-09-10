<?php

namespace App\Controllers;

use App\Services\StockAvanceService;
use App\Services\PeremptionService;
use App\Services\CommandeAutomatiqueService;
use App\Services\AuditService;
use App\Services\ChargeCommandeService;
use App\Services\PharmacyProductService;
use App\Services\StockFluxService;
use App\Core\BaseController;
use PDO;
use Exception;

class StockController extends BaseController
{
    private const COEFFICIENT_MARGE_VENTE = 1.48;

    private StockAvanceService $stockService;
    private PeremptionService $peremptionService;
    private CommandeAutomatiqueService $commandeService;

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);

        if (!$this->db instanceof PDO) {
            throw new Exception('Connexion base de donnees indisponible');
        }

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->stockService = new StockAvanceService($this->db, new AuditService($this->db));
        $this->peremptionService = new PeremptionService($this->db, new AuditService($this->db));
        $this->commandeService = new CommandeAutomatiqueService($this->db, new AuditService($this->db));
    }

    /**
     * Vérifie les permissions pour voir le stock (tous les rôles)
     */
    private function requireStockViewAccess(): void
    {
        $this->requireAuth();
        // Tous les rôles peuvent voir le stock
    }

    /**
     * Affiche le dashboard de stock et approvisionnement
     */
    public function dashboard(): void
    {
        $this->requireStockViewAccess();
        $this->render('stock/dashboard', [
            'title' => 'Dashboard Stock et Approvisionnement'
        ]);
    }

    /**
     * Vérifie les permissions pour gérer le stock (Admin, Commande, Assistant)
     */
    private function requireStockManageAccess(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));

        // Autorise Admin, Assistant et Charge de commande.
        if (in_array($roleId, [1, 3, 4], true)
            || in_array($roleCode, ['ADMIN', 'ASSISTANT', 'CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true)) {
            return;
        }
        
        $this->requirePermission('stock.manage');
    }

    /**
     * Verifie les permissions pour ajouter un fournisseur (Admin, Assistant).
     */
    private function requireFournisseurCreateAccess(): void
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));

        if (in_array($roleId, [1, 3], true)
            || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR', 'ASSISTANT'], true)) {
            return;
        }

        http_response_code(403);
        echo "Acces refuse";
        exit;
    }

    /**
     * Verifie les permissions pour modifier les prix (Admin, Charge commande).
     */
    private function requirePriceEditAccess(): void
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));

        if (in_array($roleId, [1, 4], true)
            || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR', 'CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true)) {
            return;
        }

        http_response_code(403);
        echo "Acces refuse";
        exit;
    }

    /**
     * Page principale du stock
     */
    public function index(): void
    {
        $this->requireStockViewAccess(); // Tous les rôles peuvent voir le stock
        
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
            $offset = ($page - 1) * $perPage;

            // Compter le total
            $sqlCount = "SELECT COUNT(*) as total
                        FROM produits p
                        LEFT JOIN stock s ON p.id = s.produit_id
                        WHERE p.deleted_at IS NULL AND p.is_actif = 1";
            
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // Récupérer le stock complet avec stock théorique
            $sql = "SELECT 
                        p.id, p.nom, p.code_cip, p.prix_vente, p.stock_securite, p.stock_alerte, p.rayon,
                        s.quantite_disponible, s.quantite_theorique, s.valeur_stock,
                        s.dernier_mouvement,
                        c.nom as categorie,
                        f.nom as fournisseur,
                        (s.quantite_disponible - s.quantite_theorique) as stock_reserve,
                        CASE 
                            WHEN s.quantite_disponible <= p.stock_securite THEN 'CRITIQUE'
                            WHEN s.quantite_disponible <= p.stock_alerte THEN 'ALERTE'
                            ELSE 'NORMAL'
                        END as niveau_stock
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    LEFT JOIN categories c ON p.categorie_id = c.id
                    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                    WHERE p.deleted_at IS NULL AND p.is_actif = 1
                    ORDER BY 
                        CASE 
                            WHEN s.quantite_disponible <= p.stock_securite THEN 1
                            WHEN s.quantite_disponible <= p.stock_alerte THEN 2
                            ELSE 3
                        END,
                        p.nom
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$perPage, $offset]);
            $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = (int)ceil($total / $perPage);
            
            $this->render('stock/index', [
                'stocks' => $stocks,
                'user' => $this->currentUser,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'prev_page' => $page - 1,
                    'next_page' => $page + 1
                ]
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirectBack('/stock');
        }
    }

    /**
     * Vue détaillée du stock d'un produit
     */
    public function details(): void
    {
        $this->requireStockViewAccess();
        $produitId = intval($_GET['id'] ?? 0);
        $returnTo = $this->getSafeReturnUrl('/stock');
        
        if (!$produitId) {
            $_SESSION['error'] = 'ID produit non fourni';
            $this->redirect($returnTo);
            return;
        }

        try {
            // Récupérer les détails du produit
            $sql = "SELECT p.*, s.quantite_disponible, s.quantite_theorique, s.valeur_stock,
                           c.nom as categorie, f.nom as fournisseur
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    LEFT JOIN categories c ON p.categorie_id = c.id
                    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                    WHERE p.id = ? AND p.deleted_at IS NULL";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$produitId]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produit) {
                $_SESSION['error'] = 'Produit non trouvé';
                $this->redirect($returnTo);
                return;
            }
            
            // Récupérer les lots
            $sql = "SELECT l.*, 
                           DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                           CASE 
                               WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                               WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                               WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                               ELSE 'NORMAL'
                           END as niveau_peremption
                    FROM lots l
                    WHERE l.produit_id = ? AND l.is_actif = 1
                    ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$produitId]);
            $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les mouvements récents
            $sql = "SELECT ms.*, u.username as utilisateur_nom
                    FROM mouvements_stock ms
                    LEFT JOIN utilisateurs u ON ms.utilisateur_id = u.id
                    WHERE ms.produit_id = ?
                    ORDER BY ms.date_mouvement DESC
                    LIMIT 20";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$produitId]);
            $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('stock/details', [
                'produit' => $produit,
                'lots' => $lots,
                'mouvements' => $mouvements,
                'user' => $this->currentUser,
                'returnTo' => $returnTo,
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect($returnTo);
        }
    }

    /**
     * Formulaire d'ajout de stock
     */
    public function ajouter(): void
    {
        $this->requireStockManageAccess(); // Admin, Commande, Assistant
        
        try {
            // Récupérer les produits pour le formulaire
            $sql = "SELECT id, nom, code_cip, prix_achat, prix_vente, forme_pharmaceutique, dci, rayon, stock_minimum FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT id, nom FROM fournisseurs WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom");
            $stmt->execute();
            $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formes pharmaceutiques depuis le service (valeurs prédéfinies)
            $formesPharmaceutiques = \App\Services\PharmacyProductService::formesPharmaceutiques();
            
            // Rayons prédéfinis
            $rayons = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'Frigo', 'Armoire à clé', 'Vitrine', 'Comptoir', 'Magasin', 'Autres'];
            
            // Types de délivrance (pour gestion des ordonnances)
            $typesDelivrance = \App\Services\PharmacyProductService::typeDelivranceLabels();
            
            $this->render('stock/ajouter', [
                'produits' => $produits,
                'fournisseurs' => $fournisseurs,
                'formes_pharmaceutiques' => $formesPharmaceutiques,
                'rayons' => $rayons,
                'types_delivrance' => $typesDelivrance,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * Traite l'entrée directe en stock (sans lots)
     */
    public function entreeDirecte(): void
    {
        $this->requireStockManageAccess();
        $returnTo = $this->getSafeReturnUrl('/stock');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stock/ajouter?return_to=' . urlencode($returnTo));
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            $produitId = intval($_POST['produit_id'] ?? 0);
            $nouveauProduitNom = trim($_POST['nouveau_produit_nom'] ?? '');
            $quantite = intval($_POST['quantite'] ?? 0);
            $prixAchat = floatval($_POST['prix_achat'] ?? 0);
            $coefficient = floatval($_POST['coefficient'] ?? self::COEFFICIENT_MARGE_VENTE);
            $prixVente = floatval($_POST['prix_vente'] ?? 0);
            $tva = floatval($_POST['tva'] ?? 0);
            $remise = floatval($_POST['remise'] ?? 0);
            $formePharmaceutique = trim($_POST['forme_pharmaceutique'] ?? '');
            $fournisseurId = intval($_POST['fournisseur_id'] ?? 0);
            $dateEntree = $_POST['date_entree'] ?? date('Y-m-d');
            $observations = trim($_POST['observations'] ?? '');
            $codeCip = trim($_POST['code_cip'] ?? '');
            $dci = trim($_POST['dci'] ?? '');
            $rayon = trim($_POST['rayon'] ?? '');
            $stockMinimum = intval($_POST['stock_minimum'] ?? 0);
            $typeDelivrance = trim($_POST['type_delivrance'] ?? 'MEDICAMENT_CONSEIL');
            $action = $_POST['action'] ?? 'save';
            $utilisateurId = (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0);
            
            // Validation
            if ($quantite <= 0) {
                throw new \Exception('La quantité doit être supérieure à 0');
            }
            if ($prixAchat <= 0) {
                throw new \Exception('Le prix d\'achat doit être supérieur à 0');
            }
            
            // Créer un nouveau produit si nécessaire
            if ($produitId <= 0 && !empty($nouveauProduitNom)) {
                if (empty($formePharmaceutique)) {
                    throw new \Exception('Veuillez sélectionner une forme pharmaceutique pour le nouveau produit');
                }
                if (empty($dci)) {
                    throw new \Exception('Veuillez renseigner la DCI (Dénomination Commune Internationale)');
                }
                if (empty($rayon)) {
                    throw new \Exception('Veuillez sélectionner un rayon');
                }
                
                // Calculer le prix de vente si non fourni
                if ($prixVente <= 0) {
                    $prixVente = $prixAchat * $coefficient;
                }
                
                // Appliquer la remise si applicable
                if ($remise > 0) {
                    $prixVente = $prixVente * (1 - $remise / 100);
                }
                
                // Créer le produit (fournisseur facultatif)
                $sqlProduit = "INSERT INTO produits (nom, code_cip, prix_achat, prix_vente, forme_pharmaceutique, fournisseur_id, dci, rayon, stock_minimum, type_delivrance, is_actif, created_at, updated_at)
                              VALUES (:nom, :code_cip, :prix_achat, :prix_vente, :forme_pharmaceutique, :fournisseur_id, :dci, :rayon, :stock_minimum, :type_delivrance, 1, NOW(), NOW())";
                $stmtProduit = $this->db->prepare($sqlProduit);
                $stmtProduit->execute([
                    'nom' => $nouveauProduitNom,
                    'code_cip' => $codeCip,
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'forme_pharmaceutique' => $formePharmaceutique,
                    'fournisseur_id' => $fournisseurId > 0 ? $fournisseurId : null,
                    'dci' => $dci,
                    'rayon' => $rayon,
                    'stock_minimum' => $stockMinimum,
                    'type_delivrance' => $typeDelivrance
                ]);
                $produitId = (int)$this->db->lastInsertId();
                
                // Créer l'enregistrement de stock
                $sqlStock = "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                            VALUES (:produit_id, 0, 0, 0, NOW(), NOW())";
                $stmtStock = $this->db->prepare($sqlStock);
                $stmtStock->execute(['produit_id' => $produitId]);
                
            } elseif ($produitId <= 0) {
                throw new \Exception('Veuillez sélectionner un produit existant ou créer un nouveau produit');
            } else {
                // Mettre à jour le prix d'achat et prix de vente si fournis
                if ($prixAchat > 0) {
                    if ($prixVente <= 0) {
                        $prixVente = $prixAchat * $coefficient;
                    }
                    if ($remise > 0) {
                        $prixVente = $prixVente * (1 - $remise / 100);
                    }
                    
                    $sqlUpdateProduit = "UPDATE produits SET prix_achat = :prix_achat, prix_vente = :prix_vente, updated_at = NOW()
                                        WHERE id = :produit_id";
                    $stmtUpdateProduit = $this->db->prepare($sqlUpdateProduit);
                    $stmtUpdateProduit->execute([
                        'prix_achat' => $prixAchat,
                        'prix_vente' => $prixVente,
                        'produit_id' => $produitId
                    ]);
                }
            }
            
            // Récupérer le stock actuel
            $stmtStock = $this->db->prepare("SELECT quantite_disponible FROM stock WHERE produit_id = :produit_id");
            $stmtStock->execute(['produit_id' => $produitId]);
            $stockData = $stmtStock->fetch(PDO::FETCH_ASSOC);
            
            $stockAvant = $stockData ? (int)$stockData['quantite_disponible'] : 0;
            
            if (!$stockData) {
                // Créer l'enregistrement de stock s'il n'existe pas
                $sqlCreateStock = "INSERT INTO stock (produit_id, quantite_disponible, quantite_theorique, valeur_stock, created_at, updated_at)
                                  VALUES (:produit_id, :quantite, :quantite, :valeur, NOW(), NOW())";
                $stmtCreateStock = $this->db->prepare($sqlCreateStock);
                $stmtCreateStock->execute([
                    'produit_id' => $produitId,
                    'quantite' => $quantite,
                    'valeur' => $quantite * $prixAchat
                ]);
                $stockAvant = 0;
            }
            
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
            
            // Créer le mouvement de stock
            $sqlMouvement = "INSERT INTO mouvements_stock 
                           (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, 
                            motif, reference_type, utilisateur_id, date_mouvement)
                           VALUES (:produit_id, 'ENTREE', :quantite, :quantite_avant, :quantite_apres,
                                   :motif, 'ENTREE_DIRECTE', :utilisateur_id, :date_mouvement)";
            $stmtMouvement = $this->db->prepare($sqlMouvement);
            $stmtMouvement->execute([
                'produit_id' => $produitId,
                'quantite' => $quantite,
                'quantite_avant' => $stockAvant,
                'quantite_apres' => $stockAvant + $quantite,
                'motif' => 'Entrée Directe' . ($observations ? ' - ' . $observations : ''),
                'utilisateur_id' => $utilisateurId,
                'date_mouvement' => $dateEntree . ' ' . date('H:i:s')
            ]);
            
            // Enregistrer dans l'audit
            $auditService = new AuditService($this->db);
            $auditService->log(
                $utilisateurId,
                'ENTREE_DIRECTE_STOCK',
                "Entrée Directe: Produit ID $produitId, Quantité: $quantite, Prix achat: $prixAchat, Prix vente: $prixVente"
            );
            
            $this->db->commit();
            
            $_SESSION['success'] = 'Entrée de stock enregistrée avec succès. Le produit est immédiatement disponible à la vente.';
            
            if ($action === 'save_and_new') {
                $_SESSION['old_data'] = [];
                $this->redirect('/stock/ajouter?return_to=' . urlencode($returnTo));
            } else {
                $this->redirect($returnTo);
            }
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/stock/ajouter?return_to=' . urlencode($returnTo));
        }
    }

    /**
     * Traite l'ajout de stock
     */
    public function storeAjout(): void
    {
        $this->requireStockManageAccess(); // Admin, Commande, Assistant
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stock/ajouter?return_to=' . urlencode($returnTo));
            return;
        }
        
        try {
            $produitId = intval($_POST['produit_id'] ?? 0);
            $quantite = intval($_POST['quantite'] ?? 0);
            $prixAchat = floatval($_POST['prix_achat'] ?? 0);
            
            if ($produitId <= 0 || $quantite <= 0 || $prixAchat <= 0) {
                $_SESSION['error'] = 'Veuillez remplir tous les champs obligatoires';
                $_SESSION['old_data'] = $_POST;
                $this->redirect('/stock/ajouter?return_to=' . urlencode($returnTo));
                return;
            }
            
            $service = new ChargeCommandeService($this->db, new AuditService($this->db));
            $result = $service->addStock($_POST, (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0));
            
            $_SESSION['success'] = $result['message'];
            $this->redirect($returnTo);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/stock/ajouter?return_to=' . urlencode($returnTo));
        }
    }

    /**
     * Formulaire pour ajouter un nouveau produit au stock
     */
    public function ajouterProduit(): void
    {
        $this->requireStockManageAccess();
        $this->requirePermission('stock.create_product');
        
        try {
            // Récupérer les catégories et fournisseurs
            $sqlCategories = "SELECT id, nom FROM categories ORDER BY nom";
            $stmtCategories = $this->db->prepare($sqlCategories);
            $stmtCategories->execute();
            $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
            
            $sqlFournisseurs = "SELECT id, nom FROM fournisseurs ORDER BY nom";
            $stmtFournisseurs = $this->db->prepare($sqlFournisseurs);
            $stmtFournisseurs->execute();
            $fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('stock/ajouter-produit', [
                'categories' => $categories,
                'fournisseurs' => $fournisseurs,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * Crée un nouveau produit et l'ajoute au stock
     */
    public function storeAjoutProduit(): void
    {
        $this->requireStockManageAccess(); // Admin, Commande, Assistant
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stock/ajouter-produit');
            return;
        }

        $this->storeProduitFromPost();
        return;
        
        try {
            // Récupérer et valider les données
            $nom = trim($_POST['nom'] ?? '');
            $codeCip = trim($_POST['code_cip'] ?? '');
            $codeBarre = trim($_POST['code_barre'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prixAchat = floatval($_POST['prix_achat'] ?? 0);
            $prixVente = floatval($_POST['prix_vente'] ?? 0);
            $quantite = floatval($_POST['quantite'] ?? 0);
            $stockSecurite = floatval($_POST['stock_securite'] ?? 10);
            $stockAlerte = floatval($_POST['stock_alerte'] ?? 5);
            $categorieId = intval($_POST['categorie_id'] ?? 0);
            $fournisseurId = intval($_POST['fournisseur_id'] ?? 0);
            $dosage = trim($_POST['dosage'] ?? '');
            $forme = $_POST['forme'] ?? '';
            $motif = trim($_POST['motif'] ?? '');
            
            // Validation
            $errors = [];
            if (empty($nom)) $errors[] = 'Le nom du produit est obligatoire';
            if (empty($codeCip)) $errors[] = 'Le code CIP est obligatoire';
            if ($prixAchat <= 0) $errors[] = 'Le prix d\'achat doit être supérieur à 0';
            if ($prixVente <= 0) $errors[] = 'Le prix de vente doit être supérieur à 0';
            if ($quantite <= 0) $errors[] = 'La quantité doit être supérieure à 0';
            
            // Vérifier si le code CIP existe déjà
            $stmtCheck = $this->db->prepare("SELECT id FROM produits WHERE code_cip = ? AND deleted_at IS NULL");
            $stmtCheck->execute([$codeCip]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'Ce code CIP existe déjà';
            }
            
            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_data'] = $_POST;
                $this->redirect('/stock/ajouter-produit');
                return;
            }
            
            // Démarrer la transaction
            $this->db->beginTransaction();
            
            try {
                // 1. Créer le produit
                $sqlProduit = "INSERT INTO produits (
                    nom, code_cip, code_barre, description, prix_achat, prix_vente,
                    stock_securite, stock_alerte, categorie_id, fournisseur_id,
                    dosage, forme, is_actif, created_at, updated_at
                ) VALUES (
                    :nom, :code_cip, :code_barre, :description, :prix_achat, :prix_vente,
                    :stock_securite, :stock_alerte, :categorie_id, :fournisseur_id,
                    :dosage, :forme, 1, NOW(), NOW()
                )";
                
                $stmtProduit = $this->db->prepare($sqlProduit);
                $stmtProduit->execute([
                    ':nom' => $nom,
                    ':code_cip' => $codeCip,
                    ':code_barre' => $codeBarre,
                    ':description' => $description,
                    ':prix_achat' => $prixAchat,
                    ':prix_vente' => $prixVente,
                    ':stock_securite' => $stockSecurite,
                    ':stock_alerte' => $stockAlerte,
                    ':categorie_id' => $categorieId ?: null,
                    ':fournisseur_id' => $fournisseurId ?: null,
                    ':dosage' => $dosage,
                    ':forme' => $forme
                ]);
                
                $produitId = $this->db->lastInsertId();
                
                // 2. Créer l'entrée de stock
                $sqlStock = "INSERT INTO stock (
                    produit_id, quantite_disponible, quantite_theorique, valeur_stock,
                    dernier_mouvement, created_at, updated_at
                ) VALUES (
                    :produit_id, :quantite_disponible, :quantite_theorique, :valeur_stock,
                    NOW(), NOW(), NOW()
                )";
                
                $valeurStock = $quantite * $prixAchat;
                $stmtStock = $this->db->prepare($sqlStock);
                $stmtStock->execute([
                    ':produit_id' => $produitId,
                    ':quantite_disponible' => $quantite,
                    ':quantite_theorique' => $quantite,
                    ':valeur_stock' => $valeurStock
                ]);
                
                // 3. Enregistrer le mouvement
                $sqlMouvement = "INSERT INTO mouvements_stock (
                    produit_id, type_mouvement, quantite, quantite_avant, quantite_apres,
                    utilisateur_id, motif, reference_type, reference_id, created_at
                ) VALUES (
                    :produit_id, :type_mouvement, :quantite, 0, :quantite_apres,
                    :utilisateur_id, :motif, :reference_type, :reference_id, NOW()
                )";
                
                $stmtMouvement = $this->db->prepare($sqlMouvement);
                $stmtMouvement->execute([
                    ':produit_id' => $produitId,
                    ':type_mouvement' => 'ENTREE',
                    ':quantite' => $quantite,
                    ':quantite_apres' => $quantite,
                    ':utilisateur_id' => $this->currentUser['id'],
                    ':motif' => $motif ?: 'Création produit',
                    ':reference_type' => 'CREATION_PRODUIT',
                    ':reference_id' => $produitId
                ]);
                
                // Valider la transaction
                $this->db->commit();
                
                $_SESSION['success'] = "Produit '{$nom}' créé avec succès et {$quantite} unités ajoutées au stock";
                $this->redirect('/stock');
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['old_data'] = $_POST;
            $this->redirect('/stock/ajouter-produit');
        }
    }

    public function ajouterFournisseur(): void
    {
        $this->requireFournisseurCreateAccess();

        $this->render('stock/ajouter-fournisseur', [
            'title' => 'Ajouter un fournisseur',
            'user' => $this->currentUser,
        ]);
    }

    public function storeFournisseur(): void
    {
        $this->requireFournisseurCreateAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/fournisseurs/ajouter?return_to=' . urlencode($this->getDefaultDashboardForRole()));
            return;
        }

        $oldData = $_POST;
        $returnTo = (string)($_POST['return_to'] ?? $this->getDefaultDashboardForRole());
        $returnTo = $this->isSafeFournisseurReturnUrl($returnTo) ? $returnTo : $this->getDefaultDashboardForRole();

        try {
            $nom = trim($_POST['nom'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $telephone = trim($_POST['telephone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $adresse = trim($_POST['adresse'] ?? '');
            $registreCommerce = trim($_POST['registre_commerce'] ?? '');
            $compteBancaire = trim($_POST['compte_bancaire'] ?? '');
            $delaiLivraison = (int)($_POST['delai_livraison'] ?? 7);

            $errors = [];
            if ($nom === '') {
                $errors[] = 'Le nom du fournisseur est obligatoire';
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'L email du fournisseur est invalide';
            }
            if ($delaiLivraison < 0) {
                $errors[] = 'Le delai de livraison ne peut pas etre negatif';
            }

            if ($code === '') {
                $code = $this->generateFournisseurCode();
            }

            $stmtCheck = $this->db->prepare("SELECT id FROM fournisseurs WHERE code = ? AND deleted_at IS NULL");
            $stmtCheck->execute([$code]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'Ce code fournisseur existe deja';
            }

            $stmtCheck = $this->db->prepare("SELECT id FROM fournisseurs WHERE nom = ? AND deleted_at IS NULL");
            $stmtCheck->execute([$nom]);
            if ($nom !== '' && $stmtCheck->fetch()) {
                $errors[] = 'Un fournisseur avec ce nom existe deja';
            }

            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_data'] = $oldData;
                $this->redirect('/fournisseurs/ajouter?return_to=' . urlencode($returnTo));
                return;
            }

            $sql = "INSERT INTO fournisseurs (
                        code, nom, adresse, telephone, email, registre_commerce,
                        compte_bancaire, delai_livraison, is_actif, created_at, updated_at
                    ) VALUES (
                        :code, :nom, :adresse, :telephone, :email, :registre_commerce,
                        :compte_bancaire, :delai_livraison, 1, NOW(), NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':code' => $code,
                ':nom' => $nom,
                ':adresse' => $adresse !== '' ? $adresse : null,
                ':telephone' => $telephone !== '' ? $telephone : null,
                ':email' => $email !== '' ? $email : null,
                ':registre_commerce' => $registreCommerce !== '' ? $registreCommerce : null,
                ':compte_bancaire' => $compteBancaire !== '' ? $compteBancaire : null,
                ':delai_livraison' => $delaiLivraison,
            ]);

            $_SESSION['success'] = "Fournisseur '{$nom}' ajoute avec succes";
            $this->redirect($returnTo);
        } catch (\Throwable $e) {
            error_log("StockController::storeFournisseur - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors de la creation du fournisseur: ' . $e->getMessage()];
            $_SESSION['old_data'] = $oldData;
            $this->redirect('/fournisseurs/ajouter?return_to=' . urlencode($returnTo));
        }
    }

    private function isSafeFournisseurReturnUrl(string $url): bool
    {
        return $url !== ''
            && str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_contains($url, "\n")
            && !str_contains($url, "\r");
    }

    private function generateFournisseurCode(): string
    {
        $prefix = 'FOU' . date('Ymd');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM fournisseurs WHERE code LIKE ?");
        $stmt->execute([$prefix . '%']);

        return $prefix . str_pad((string)((int)$stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }

    private function storeProduitFromPost(): void
    {
        $oldData = $_POST;
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());

        try {
            $nom = trim($_POST['nom'] ?? '');
            $codeCip = strtoupper(trim($_POST['code_cip'] ?? ''));
            $codeBarre = trim($_POST['code_barre'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categorieId = isset($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : 0;
            $fournisseurId = (int)($_POST['fournisseur_id'] ?? 0);
            $prixAchat = (float)($_POST['prix_achat'] ?? 0);
            $prixVente = round($prixAchat * self::COEFFICIENT_MARGE_VENTE, 2);
            $prixVenteAssure = ($_POST['prix_vente_assure'] ?? '') !== '' ? (float)$_POST['prix_vente_assure'] : null;
            $uniteMesure = trim($_POST['unite_mesure'] ?? 'unite');
            $stockSecurite = (int)($_POST['stock_securite'] ?? 10);
            $stockAlerte = (int)($_POST['stock_alerte'] ?? 5);
            $requiresPrescription = isset($_POST['requires_prescription']) ? 1 : 0;
            $typeDelivrance = PharmacyProductService::normalizeTypeDelivrance((string)($_POST['type_delivrance'] ?? 'MEDICAMENT_CONSEIL'));
            if (PharmacyProductService::requiresPrescription($typeDelivrance)) {
                $requiresPrescription = 1;
            }
            $rayon = trim($_POST['rayon'] ?? '');
            $dci = trim($_POST['dci'] ?? '');
            $classePharmaceutique = trim($_POST['classe_pharmaceutique'] ?? '');
            $formePharmaceutique = trim($_POST['forme_pharmaceutique'] ?? '');
            $datePeremptionDefault = trim($_POST['date_peremption_default'] ?? '');
            $quantite = (int)($_POST['quantite'] ?? 0);
            $motif = trim($_POST['motif'] ?? '');

            $allowedRayons = [
                'Rayon A', 'Rayon B', 'Rayon C', 'Rayon D', 'Rayon E',
                'Rayon F', 'Rayon G', 'Rayon H', 'Rayon I', 'Rayon J',
                'Rayon K', 'Rayon L', 'Rayon M', 'Rayon N', 'Rayon O',
                'Frigo', 'Armoire à clé', 'Vitrines', 'Comptoir', 'Magasin', 'Autres'
            ];

            $errors = [];
            if ($nom === '') $errors[] = 'Le nom du produit est obligatoire';
            if ($codeCip === '') $errors[] = 'Le code CIP est obligatoire';
            if ($rayon === '') $errors[] = 'Le rayon / emplacement est obligatoire';
            if (!in_array($rayon, $allowedRayons, true)) $errors[] = 'Le rayon / emplacement n\'est pas valide';
            if ($prixAchat <= 0) $errors[] = 'Le prix d achat doit etre superieur a 0';
            if ($prixVenteAssure !== null && $prixVenteAssure < 0) $errors[] = 'Le prix assure ne peut pas etre negatif';
            if ($quantite < 0) $errors[] = 'La quantite initiale ne peut pas etre negative';
            if ($stockSecurite < 0) $errors[] = 'Le stock de securite ne peut pas etre negatif';
            if ($stockAlerte < 0) $errors[] = 'Le stock d alerte ne peut pas etre negatif';
            if ($datePeremptionDefault !== '' && strtotime($datePeremptionDefault) === false) {
                $errors[] = 'La date de peremption par defaut est invalide';
            }

            if ($codeCip !== '') {
                $stmtCheck = $this->db->prepare("SELECT id FROM produits WHERE code_cip = ? AND deleted_at IS NULL");
                $stmtCheck->execute([$codeCip]);
                if ($stmtCheck->fetch()) {
                    $errors[] = 'Ce code CIP existe deja';
                }
            }

            if ($codeBarre !== '') {
                $stmtCheck = $this->db->prepare("SELECT id FROM produits WHERE code_barre = ? AND deleted_at IS NULL");
                $stmtCheck->execute([$codeBarre]);
                if ($stmtCheck->fetch()) {
                    $errors[] = 'Ce code barre existe deja';
                }
            }

            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_data'] = $oldData;
                $this->redirect('/stock/ajouter-produit?return_to=' . urlencode($returnTo));
                return;
            }

            $this->db->beginTransaction();

            try {
                $sqlProduit = "INSERT INTO produits (
                    code_cip, code_barre, nom, description, rayon, dci, classe_pharmaceutique,
                    forme_pharmaceutique, type_delivrance, categorie_id, fournisseur_id,
                    prix_achat, prix_vente, prix_vente_assure, unite_mesure,
                    stock_securite, stock_alerte, is_actif, requires_prescription,
                    date_peremption_default, created_at, updated_at
                ) VALUES (
                    :code_cip, :code_barre, :nom, :description, :rayon, :dci, :classe_pharmaceutique,
                    :forme_pharmaceutique, :type_delivrance, :categorie_id, :fournisseur_id,
                    :prix_achat, :prix_vente, :prix_vente_assure, :unite_mesure,
                    :stock_securite, :stock_alerte, :is_actif, :requires_prescription,
                    :date_peremption_default, NOW(), NOW()
                )";

                $stmtProduit = $this->db->prepare($sqlProduit);
                $stmtProduit->execute([
                    ':code_cip' => $codeCip,
                    ':code_barre' => $codeBarre !== '' ? $codeBarre : null,
                    ':nom' => $nom,
                    ':description' => $description !== '' ? $description : null,
                    ':rayon' => $rayon !== '' ? $rayon : null,
                    ':dci' => $dci !== '' ? $dci : null,
                    ':classe_pharmaceutique' => $classePharmaceutique !== '' ? $classePharmaceutique : null,
                    ':forme_pharmaceutique' => $formePharmaceutique !== '' ? $formePharmaceutique : null,
                    ':type_delivrance' => $typeDelivrance,
                    ':categorie_id' => $categorieId > 0 ? $categorieId : null,
                    ':fournisseur_id' => $fournisseurId > 0 ? $fournisseurId : null,
                    ':prix_achat' => $prixAchat,
                    ':prix_vente' => $prixVente,
                    ':prix_vente_assure' => $prixVenteAssure,
                    ':unite_mesure' => $uniteMesure !== '' ? $uniteMesure : 'unite',
                    ':stock_securite' => $stockSecurite,
                    ':stock_alerte' => $stockAlerte,
                    ':is_actif' => 1,
                    ':requires_prescription' => $requiresPrescription,
                    ':date_peremption_default' => $datePeremptionDefault !== '' ? $datePeremptionDefault : null
                ]);

                $produitId = (int)$this->db->lastInsertId();
                $valeurStock = $quantite * $prixAchat;

                $sqlStock = "INSERT INTO stock (
                    produit_id, quantite_disponible, quantite_theorique, valeur_stock,
                    dernier_mouvement, created_at, updated_at
                ) VALUES (
                    :produit_id, :quantite_disponible, :quantite_theorique, :valeur_stock,
                    NOW(), NOW(), NOW()
                )";

                $stmtStock = $this->db->prepare($sqlStock);
                $stmtStock->execute([
                    ':produit_id' => $produitId,
                    ':quantite_disponible' => $quantite,
                    ':quantite_theorique' => $quantite,
                    ':valeur_stock' => $valeurStock
                ]);

                $sqlMouvement = "INSERT INTO mouvements_stock (
                    produit_id, type_mouvement, quantite, quantite_avant, quantite_apres,
                    utilisateur_id, motif, reference_type, reference_id, created_at
                ) VALUES (
                    :produit_id, :type_mouvement, :quantite, 0, :quantite_apres,
                    :utilisateur_id, :motif, :reference_type, :reference_id, NOW()
                )";

                $stmtMouvement = $this->db->prepare($sqlMouvement);
                $stmtMouvement->execute([
                    ':produit_id' => $produitId,
                    ':type_mouvement' => 'ENTREE',
                    ':quantite' => $quantite,
                    ':quantite_apres' => $quantite,
                    ':utilisateur_id' => (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0),
                    ':motif' => $motif !== '' ? $motif : 'Creation produit',
                    ':reference_type' => 'AJUSTEMENT',
                    ':reference_id' => $produitId
                ]);

                $audit = new AuditService($this->db);
                $audit->logAction(
                    (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0),
                    'CREATE_PRODUCT_WITH_INITIAL_STOCK',
                    'produits',
                    $produitId,
                    null,
                    [
                        'code_cip' => $codeCip,
                        'nom' => $nom,
                        'prix_achat' => $prixAchat,
                        'prix_vente' => $prixVente,
                        'quantite_initiale' => $quantite,
                        'requires_prescription' => $requiresPrescription
                    ],
                    null,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                );

                $this->db->commit();

                $_SESSION['success'] = "Produit '{$nom}' cree avec succes et stock initialise a {$quantite} unite(s)";
                $this->redirect($returnTo);
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log("StockController::storeAjoutProduit - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors de la creation du produit: ' . $e->getMessage()];
            $_SESSION['old_data'] = $oldData;
            $this->redirect('/stock/ajouter-produit?return_to=' . urlencode($returnTo));
        }
    }

    public function modifier($id): void
    {
        $this->requirePriceEditAccess();
        $this->requirePermission('stock.update_product');
        $produitId = (int)$id;

        try {
            $stmt = $this->db->prepare(
                "SELECT p.*, c.nom as categorie, f.nom as fournisseur
                 FROM produits p
                 LEFT JOIN categories c ON p.categorie_id = c.id
                 LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                 WHERE p.id = ? AND p.deleted_at IS NULL"
            );
            $stmt->execute([$produitId]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produit) {
                $_SESSION['error'] = 'Produit non trouve';
                $this->redirect('/stock');
                return;
            }

            $this->render('stock/modifier', [
                'produit' => $produit,
                'user' => $this->currentUser,
                'returnTo' => $this->getSafeReturnUrl('/stock')
            ]);
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    public function update($id): void
    {
        $this->requirePriceEditAccess();
        $produitId = (int)$id;
        $returnTo = $this->getSafeReturnUrl('/stock');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stock/modifier/' . $produitId . '?return_to=' . urlencode($returnTo));
            return;
        }

        try {
            $prixAchat = (float)($_POST['prix_achat'] ?? 0);
            $prixVente = round($prixAchat * self::COEFFICIENT_MARGE_VENTE, 2);
            $motif = trim((string)($_POST['motif'] ?? ''));

            $errors = [];
            if ($prixAchat <= 0) $errors[] = 'Le prix d achat doit etre superieur a 0';
            if ($motif === '') $errors[] = 'Le motif de modification est obligatoire';

            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                $this->redirect('/stock/modifier/' . $produitId . '?return_to=' . urlencode($returnTo));
                return;
            }

            // DDL MySQL (CREATE TABLE) provoque un commit implicite : hors transaction.
            $this->ensureProductPriceHistoryTable();

            $stmt = $this->db->prepare("SELECT id, nom, prix_achat, prix_vente FROM produits WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
            $this->db->beginTransaction();
            $stmt->execute([$produitId]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produit) {
                throw new \Exception('Produit non trouve');
            }

            $oldPrixAchat = (float)$produit['prix_achat'];
            $oldPrixVente = (float)$produit['prix_vente'];

            if ($oldPrixAchat !== $prixAchat || $oldPrixVente !== $prixVente) {
                $stmtHistory = $this->db->prepare(
                    "INSERT INTO product_price_history (
                        produit_id, old_prix_achat, new_prix_achat, old_prix_vente, new_prix_vente,
                        motif, utilisateur_id, changed_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $stmtHistory->execute([
                    $produitId,
                    $oldPrixAchat,
                    $prixAchat,
                    $oldPrixVente,
                    $prixVente,
                    $motif,
                    (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0)
                ]);

                $stmtUpdate = $this->db->prepare("UPDATE produits SET prix_achat = ?, prix_vente = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$prixAchat, $prixVente, $produitId]);

                $audit = new AuditService($this->db);
                $audit->logAction(
                    (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0),
                    'UPDATE_PRODUCT_PRICE',
                    'produits',
                    $produitId,
                    ['prix_achat' => $oldPrixAchat, 'prix_vente' => $oldPrixVente],
                    ['prix_achat' => $prixAchat, 'prix_vente' => $prixVente, 'motif' => $motif],
                    null,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                );
            }

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            $_SESSION['success'] = 'Prix du produit mis a jour avec succes';
            $this->redirect($returnTo);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $_SESSION['errors'] = [$e->getMessage()];
            $this->redirect('/stock/modifier/' . $produitId . '?return_to=' . urlencode($returnTo));
        }
    }

    public function historiquePrix(): void
    {
        $this->requirePriceEditAccess();
        $this->requirePermission('product.price.history');

        $returnTo = $this->getSafeReturnUrl('/stock');
        $historique = [];

        try {
            $this->ensureProductPriceHistoryTable();
            $stmt = $this->db->prepare(
                "SELECT h.*, p.nom as produit_nom, p.code_cip, u.username as utilisateur_nom
                 FROM product_price_history h
                 JOIN produits p ON p.id = h.produit_id
                 LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
                 ORDER BY h.changed_at DESC
                 LIMIT 200"
            );
            $stmt->execute();
            $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('StockController::historiquePrix - ' . $e->getMessage());
            $_SESSION['error'] = 'Impossible de charger l historique des prix.';
        }

        $this->render('stock/historique-prix', [
            'historique' => $historique,
            'user' => $this->currentUser,
            'returnTo' => $returnTo,
        ]);
    }

    private function ensureProductPriceHistoryTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS product_price_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                produit_id INT NOT NULL,
                old_prix_achat DECIMAL(12,2) NOT NULL,
                new_prix_achat DECIMAL(12,2) NOT NULL,
                old_prix_vente DECIMAL(12,2) NOT NULL,
                new_prix_vente DECIMAL(12,2) NOT NULL,
                motif TEXT NOT NULL,
                utilisateur_id INT NOT NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product_price_history_produit (produit_id),
                INDEX idx_product_price_history_changed_at (changed_at),
                INDEX idx_product_price_history_user (utilisateur_id)
            )"
        );
    }

    /**
     * Gestion des lots
     */
    public function lots(): void
    {
        $this->requireStockManageAccess(); // Admin, Commande, Assistant
        
        try {
            $sql = "SELECT l.*, 
                           p.nom as produit_nom, p.code_cip,
                           f.nom as fournisseur_nom,
                           DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                           CASE 
                               WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                               WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                               WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                               ELSE 'NORMAL'
                           END as niveau_peremption
                    FROM lots l
                    JOIN produits p ON l.produit_id = p.id
                    LEFT JOIN fournisseurs f ON l.fournisseur_id = f.id
                    WHERE l.is_actif = 1
                    ORDER BY l.date_peremption ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('stock/lots', [
                'lots' => $lots,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * Ajoute un lot
     */
    public function ajouterLot(): void
    {
        $this->requireStockManageAccess();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Vérifier si c'est une requête AJAX
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                     $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

            $data = [
                'produit_id' => intval($_POST['produit_id'] ?? 0),
                'numero_lot' => strtoupper($_POST['numero_lot'] ?? ''),
                'date_fabrication' => $_POST['date_fabrication'] ?? '',
                'date_peremption' => $_POST['date_peremption'] ?? '',
                'quantite' => intval($_POST['quantite'] ?? 0),
                'prix_achat_unitaire' => floatval($_POST['prix_achat_unitaire'] ?? 0),
                'fournisseur_id' => intval($_POST['fournisseur_id'] ?? 0),
                'commande_id' => intval($_POST['commande_id'] ?? 0),
                'utilisateur_id' => $this->currentUser['id']
            ];

            try {
                $result = $this->stockService->gererLot($data);
                
                if ($isAjax) {
                    // Réponse JSON pour AJAX
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $result['message'],
                        'data' => $result
                    ]);
                    exit;
                } else {
                    $_SESSION['success'] = $result['message'];
                    $this->redirect('/stock/lots');
                }
                
            } catch (\Exception $e) {
                if ($isAjax) {
                    // Réponse JSON d'erreur pour AJAX
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'data' => $data
                    ]);
                    exit;
                } else {
                    $_SESSION['error'] = $e->getMessage();
                    $_SESSION['old_data'] = $data;
                    $this->redirect('/stock/ajouter-lot');
                }
            }
        } else {
            // Récupérer les produits et fournisseurs
            $sql = "SELECT id, nom, code_cip FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sql = "SELECT id, nom FROM fournisseurs WHERE is_actif = 1 ORDER BY nom";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('stock/ajouter-lot', [
                'produits' => $produits,
                'fournisseurs' => $fournisseurs,
                'user' => $this->currentUser
            ]);
        }
    }

    /**
     * Mouvements de stock
     */
    public function mouvements(): void
    {
        $this->requirePermission('view_stock_movements');

        try {
            $sql = "SELECT ms.*, 
                           p.nom as produit_nom, p.code_cip,
                           l.numero_lot,
                           u.username as utilisateur_nom
                    FROM mouvements_stock ms
                    JOIN produits p ON ms.produit_id = p.id
                    LEFT JOIN lots l ON ms.lot_id = l.id
                    LEFT JOIN utilisateurs u ON ms.utilisateur_id = u.id
                    WHERE 1=1";
            
            $params = [];
            
            // Filtre par recherche
            if (!empty($_GET['recherche'])) {
                $sql .= " AND (p.nom LIKE :recherche OR p.code_cip LIKE :recherche OR ms.motif LIKE :recherche OR u.username LIKE :recherche)";
                $params[':recherche'] = '%' . $_GET['recherche'] . '%';
            }
            
            // Filtre par période
            if (!empty($_GET['date_debut'])) {
                $sql .= " AND DATE(ms.date_mouvement) >= :date_debut";
                $params[':date_debut'] = $_GET['date_debut'];
            }
            if (!empty($_GET['date_fin'])) {
                $sql .= " AND DATE(ms.date_mouvement) <= :date_fin";
                $params[':date_fin'] = $_GET['date_fin'];
            }
            
            // Filtre par produit
            if (!empty($_GET['produit_id'])) {
                $sql .= " AND ms.produit_id = :produit_id";
                $params[':produit_id'] = (int)$_GET['produit_id'];
            }
            
            // Filtre par type
            if (!empty($_GET['type'])) {
                $sql .= " AND ms.type_mouvement = :type";
                $params[':type'] = $_GET['type'];
            }
            
            // Filtre par utilisateur
            if (!empty($_GET['utilisateur_id'])) {
                $sql .= " AND ms.utilisateur_id = :utilisateur_id";
                $params[':utilisateur_id'] = (int)$_GET['utilisateur_id'];
            }
            
            $sql .= " ORDER BY ms.date_mouvement DESC LIMIT 500";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les produits pour les filtres
            $sqlProduits = "SELECT id, nom FROM produits WHERE is_actif = 1 AND deleted_at IS NULL ORDER BY nom";
            $stmtProduits = $this->db->prepare($sqlProduits);
            $stmtProduits->execute();
            $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les utilisateurs pour les filtres
            $sqlUsers = "SELECT id, username FROM utilisateurs WHERE is_active = 1 ORDER BY username";
            $stmtUsers = $this->db->prepare($sqlUsers);
            $stmtUsers->execute();
            $utilisateurs = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('stock/mouvements', [
                'mouvements' => $mouvements,
                'produits' => $produits,
                'utilisateurs' => $utilisateurs,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * Gestion des péremptions
     */
    public function peremptions(): void
    {
        $this->requireStockManageAccess();
        $this->requirePermission('stock.view_expiry');

        try {
            $horizon = (int)($_GET['horizon'] ?? 180);
            $horizon = in_array($horizon, [30, 60, 180], true) ? $horizon : 180;
            $includeExpired = ($_GET['include_expired'] ?? '1') !== '0';
            $analyse = $this->peremptionService->analyserPeremptions($horizon, $includeExpired);
            
            $this->render('stock/peremptions', [
                'analyse' => $analyse,
                'user' => $this->currentUser,
                'returnTo' => $this->getSafeReturnUrl($this->getDefaultDashboardForRole()),
                'horizon' => $horizon,
                'includeExpired' => $includeExpired,
            ]);
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * Traite les produits périmés
     */
    public function traiterPerimes(): void
    {
        $this->requireStockManageAccess();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $lotsPerimes = $_POST['lots_perimes'] ?? [];
            $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());
            $peremptionsUrl = '/stock/peremptions?return_to=' . urlencode($returnTo);
            
            if (empty($lotsPerimes)) {
                $_SESSION['error'] = 'Aucun lot sélectionné';
                $this->redirect($peremptionsUrl);
                return;
            }

            try {
                $result = $this->peremptionService->traiterPerimes($lotsPerimes, $this->currentUser['id']);
                
                $_SESSION['success'] = $result['message'];
                $this->redirect($peremptionsUrl);
                
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                $this->redirect($peremptionsUrl);
            }
        }
    }

    /**
     * Commandes automatiques (Admin + Charge de commande).
     */
    private function requireCommandesAutomatiquesAccess(): void
    {
        $this->requirePermission('create_supplier_orders');
    }

    private function wantsJsonResponse(): bool
    {
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $requestedWith = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json')
            || strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
    }

    public function commandesAutomatiques(): void
    {
        $this->requireCommandesAutomatiquesAccess();
        $returnTo = $this->getSafeReturnUrl($this->getDefaultDashboardForRole());
        $userId = (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0);

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = $this->commandeService->genererCommandesAutomatiques($userId);

                if ($this->wantsJsonResponse()) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($result);
                    exit;
                }

                $_SESSION['success'] = $result['message'];
                $_SESSION['commandes_auto_resume'] = $result['data'] ?? [];
                $this->redirect('/stock/commandes-automatiques?return_to=' . urlencode($returnTo));
                return;
            }

            $dateDebut = date('Y-m-d', strtotime('-30 days'));
            $dateFin = date('Y-m-d');
            $analyse = $this->commandeService->analyserCommandesAutomatiques($dateDebut, $dateFin);

            $this->render('stock/commandes-automatiques', [
                'analyse' => $analyse,
                'user' => $this->currentUser,
                'returnTo' => $returnTo,
                'resume' => $_SESSION['commandes_auto_resume'] ?? [],
            ]);
            unset($_SESSION['commandes_auto_resume']);
        } catch (\Throwable $e) {
            if ($this->wantsJsonResponse()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => [],
                ]);
                exit;
            }

            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock/commandes-automatiques?return_to=' . urlencode($returnTo));
        }
    }

    /**
     * Synchronise les stocks théoriques
     */
    public function synchroniserStocks(): void
    {
        $this->requireStockManageAccess();

        try {
            $result = $this->stockService->synchroniserStocksTheoriques();
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'nombre_mises_a_jour' => $result['nombre_mises_a_jour']
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Ajustement manuel de stock
     */
    public function ajustement(): void
    {
        try {
            $this->requireStockManageAccess();
            $this->requirePermission('stock.adjust');

            $returnTo = $this->getSafeReturnUrl('/stock');

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $returnTo = $this->getSafeReturnUrl('/stock');
                $produitId = intval($_POST['produit_id'] ?? 0);
                $quantite = intval($_POST['quantite'] ?? 0);
                $typeMouvement = strtoupper(trim((string)($_POST['type_mouvement'] ?? '')));
                $motif = trim((string)($_POST['motif'] ?? ''));

                if (!$produitId || !$quantite || !in_array($typeMouvement, ['ENTREE', 'SORTIE'], true)) {
                    $_SESSION['error'] = 'Donnees invalides';
                    $_SESSION['old_data'] = $_POST;
                    $this->redirect('/stock/ajustement?return_to=' . urlencode($returnTo));
                    return;
                }

                if ($motif === '') {
                    $_SESSION['error'] = 'Le motif est obligatoire pour tout ajustement de stock';
                    $_SESSION['old_data'] = $_POST;
                    $this->redirect('/stock/ajustement?return_to=' . urlencode($returnTo));
                    return;
                }

                $details = [
                    'utilisateur_id' => (int)($this->currentUser['id'] ?? $_SESSION['user']['id'] ?? 0),
                    'motif' => $motif,
                    'reference_type' => 'AJUSTEMENT',
                    'reference_id' => null
                ];

                $result = $this->stockService->mettreAJourStockTempsReel($produitId, $quantite, $typeMouvement, $details);

                $_SESSION['success'] = $result['message'];
                $this->redirect($returnTo);
                return;
            }

            $stmt = $this->db->prepare(
                "SELECT id, nom, code_cip
                 FROM produits
                 WHERE is_actif = 1
                 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
                 ORDER BY nom"
            );
            $stmt->execute();
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->render('stock/ajustement', [
                'produits' => $produits,
                'user' => $this->currentUser,
                'returnTo' => $returnTo,
            ]);
        } catch (\Throwable $e) {
            error_log('StockController::ajustement - ' . $e->getMessage());
            $_SESSION['error'] = defined('APP_DEBUG') && APP_DEBUG
                ? $e->getMessage()
                : 'Impossible d ouvrir la page d ajustement de stock.';
            $this->redirect($this->getSafeReturnUrl('/commande/dashboard'));
        }
    }

    /**
     * Rapports de stock
     */
    public function rapports(): void
    {
        $this->requireAuth();

        if ((int)($this->getCurrentUser()['role_id'] ?? 0) === 3) {
            http_response_code(403);
            echo "Acces refuse";
            return;
        }

        try {
            $typeRapport = $_GET['type'] ?? 'global';
            
            switch ($typeRapport) {
                case 'global':
                    $sql = "SELECT 
                                COUNT(*) as nombre_produits,
                                SUM(s.quantite_disponible) as stock_total,
                                SUM(s.valeur_stock) as valeur_totale,
                                COUNT(CASE WHEN s.quantite_disponible <= p.stock_alerte THEN 1 END) as produits_alerte,
                                COUNT(CASE WHEN s.quantite_disponible = 0 THEN 1 END) as produits_rupture
                            FROM produits p
                            JOIN stock s ON p.id = s.produit_id
                            WHERE p.is_actif = 1 AND p.deleted_at IS NULL";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute();
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $this->render('stock/rapports/global', [
                        'stats' => $stats,
                        'user' => $this->currentUser
                    ]);
                    break;
                    
                case 'rotation':
                    $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
                    $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
                    
                    $sql = "SELECT 
                                p.id, p.nom, p.code_cip,
                                s.quantite_disponible,
                                s.valeur_stock,
                                COALESCE(SUM(vi.quantite), 0) as quantite_vendue,
                                COALESCE(SUM(vi.montant_total), 0) as chiffre_affaires
                            FROM produits p
                            JOIN stock s ON p.id = s.produit_id
                            LEFT JOIN ventes_items vi ON p.id = vi.produit_id
                            LEFT JOIN ventes v ON vi.vente_id = v.id
                            WHERE p.is_actif = 1 
                            AND p.deleted_at IS NULL
                            AND v.date_vente BETWEEN ? AND ?
                            GROUP BY p.id, p.nom, p.code_cip, s.quantite_disponible, s.valeur_stock
                            ORDER BY quantite_vendue DESC";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$dateDebut, $dateFin]);
                    $rotation = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $this->render('stock/rapports/rotation', [
                        'rotation' => $rotation,
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin,
                        'user' => $this->currentUser
                    ]);
                    break;
                    
                default:
                    $this->redirect('/stock');
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * API: Recherche de produits pour le stock
     */
    public function rechercherProduits(): void
    {
        header('Content-Type: application/json');
        
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'produits' => []]);
            return;
        }

        try {
            $sql = "SELECT p.*, s.quantite_disponible, s.quantite_theorique,
                           (s.quantite_disponible - s.quantite_theorique) as stock_reserve
                    FROM produits p
                    LEFT JOIN stock s ON p.id = s.produit_id
                    WHERE (p.code_cip LIKE ? OR p.code_barre LIKE ? OR p.nom LIKE ?
                           OR p.dci LIKE ? OR p.rayon LIKE ?)
                    AND p.is_actif = 1 AND p.deleted_at IS NULL
                    ORDER BY p.nom
                    LIMIT 20";
            
            $stmt = $this->db->prepare($sql);
            $searchTerm = '%' . $query . '%';
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            
            $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'produits' => $produits
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Flux détaillé de stock par produit.
     */
    public function flux(): void
    {
        $this->requirePermission('stock.flux');

        $fluxService = new StockFluxService($this->db);
        $fluxService->recalculerValeursStock();

        $this->render('stock/flux', [
            'title' => 'Flux de stock',
            'produits' => $fluxService->getFluxProduits($_GET),
            'valeur_globale' => $fluxService->getValeurGlobale(),
            'valeur_categories' => $fluxService->getValeurParCategorie(),
            'mouvements' => $fluxService->getHistoriqueMouvements($_GET, 100),
            'user' => $this->currentUser,
        ]);
    }

    /**
     * Valorisation du stock.
     */
    public function valeurStock(): void
    {
        $this->requirePermission('stock.flux');
        $fluxService = new StockFluxService($this->db);

        $this->render('stock/valeur', [
            'title' => 'Valeur du stock',
            'valeur_globale' => $fluxService->getValeurGlobale(),
            'par_categorie' => $fluxService->getValeurParCategorie(),
            'par_rayon' => $fluxService->getValeurParRayon(),
            'produits' => $fluxService->getFluxProduits($_GET),
            'user' => $this->currentUser,
        ]);
    }

    /**
     * Export du stock en CSV
     */
    public function exportStockCSV(): void
    {
        $this->requireStockViewAccess();

        try {
            $exportService = new \App\Services\ExportService($this->db);
            $filepath = $exportService->exportStockCSV();
            $filename = basename($filepath);
            $exportService->downloadFile($filepath, $filename);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    /**
     * Export des mouvements en CSV
     */
    public function exportMouvementsCSV(): void
    {
        $this->requirePermission('view_stock_movements');

        try {
            $dateDebut = $_GET['date_debut'] ?? null;
            $dateFin = $_GET['date_fin'] ?? null;
            
            $exportService = new \App\Services\ExportService($this->db);
            $filepath = $exportService->exportMouvementsCSV($dateDebut, $dateFin);
            $filename = basename($filepath);
            $exportService->downloadFile($filepath, $filename);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock/mouvements');
        }
    }

    /**
     * Export des péremptions en CSV
     */
    public function exportPeremptionsCSV(): void
    {
        $this->requireStockManageAccess();

        try {
            $exportService = new \App\Services\ExportService($this->db);
            $filepath = $exportService->exportPeremptionsCSV();
            $filename = basename($filepath);
            $exportService->downloadFile($filepath, $filename);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock/peremptions');
        }
    }

    /**
     * Impression PDF du stock
     */
    public function printStock(): void
    {
        $this->requireStockViewAccess();

        try {
            $exportService = new \App\Services\ExportService($this->db);
            $html = $exportService->generateStockHTML();
            
            echo $html;
            exit;
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/stock');
        }
    }

    public function listFournisseurs(): void
    {
        $this->requireStockViewAccess();

        $stmt = $this->db->query(
            'SELECT f.*, COUNT(DISTINCT p.id) AS nb_produits
             FROM fournisseurs f
             LEFT JOIN produits p ON p.fournisseur_id = f.id AND p.deleted_at IS NULL
             WHERE f.deleted_at IS NULL
             GROUP BY f.id ORDER BY f.nom'
        );

        $this->render('stock/fournisseurs', [
            'title' => 'Fournisseurs',
            'fournisseurs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'user' => $this->currentUser,
        ]);
    }
}
