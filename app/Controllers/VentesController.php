<?php

namespace App\Controllers;

use App\Services\VenteService;
use App\Services\StockService;
use App\Services\CaisseService;
use App\Services\AuditService;
use Exception;
use PDO;

class VentesController
{
    private const COEFFICIENT_MARGE_VENTE = 1.48;

    private VenteService $venteService;
    private StockService $stockService;
    private CaisseService $caisseService;
    private PDO $db;
    private array $currentUser;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        
        // Initialisation des services
        $auditService = new AuditService($db);
        $stockService = new StockService($db, $auditService);
        $caisseService = new CaisseService($db, $auditService);
        $this->venteService = new VenteService($db, $stockService, $caisseService, new \App\Services\ComptabiliteService($db, $auditService), $auditService);
        $this->stockService = $stockService;
        $this->caisseService = $caisseService;
        
        // Récupérer l'utilisateur courant (à adapter avec auth)
        $this->currentUser = $this->getCurrentUser();
    }

    /**
     * Page principale de la caisse
     */
    public function index(): void
    {
        // Vérifier si une session de caisse est ouverte
        $sessionOuverte = $this->caisseService->getSessionOuverte($this->currentUser['id']);
        
        if (!$sessionOuverte) {
            // Rediriger vers l'ouverture de session
            $this->redirect('/caisse/ouverture');
            return;
        }

        // Charger la vue caisse
        $data = [
            'session' => $sessionOuverte,
            'user' => $this->currentUser
        ];
        
        $this->render('ventes/caisse', $data);
    }

    /**
     * Recherche rapide de produits (AJAX)
     */
    public function rechercherProduits(): void
    {
        header('Content-Type: application/json');
        
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            echo json_encode([]);
            return;
        }
        
        try {
            $produits = $this->venteService->rechercherProduits($query);
            
            // Ajouter les informations de stock et prix
            foreach ($produits as &$produit) {
                $stock = $this->stockService->verifierDisponibilite($produit['id'], 1);
                $produit['stock_disponible'] = $stock['quantite_disponible'];
                $produit['stock_theorique'] = $stock['quantite_theorique'];
                $produit['niveau_stock'] = $stock['disponible'] ? 'OK' : 'RUPTURE';
                $produit['prix_vente_catalogue'] = $produit['prix_vente'];
                $produit['prix_vente'] = $this->calculerPrixVenteDepuisAchat((float)$produit['prix_achat']);
                $produit['marge'] = $produit['prix_vente'] > 0 ? (($produit['prix_vente'] - $produit['prix_achat']) / $produit['prix_vente']) * 100 : 0;
            }
            
            echo json_encode([
                'success' => true,
                'produits' => $produits
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Ajoute un article au panier (AJAX)
     */
    public function ajouterArticle(): void
    {
        header('Content-Type: application/json');
        
        $produitId = $_POST['produit_id'] ?? 0;
        $quantite = $_POST['quantite'] ?? 1;
        $remise = $_POST['remise'] ?? 0;
        
        if (!$produitId || $quantite <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Données invalides'
            ]);
            return;
        }
        
        try {
            // Vérifier la disponibilité
            $stock = $this->stockService->verifierDisponibilite($produitId, $quantite);
            
            if (!$stock['disponible']) {
                echo json_encode([
                    'success' => false,
                    'message' => $stock['message'],
                    'stock_disponible' => $stock['quantite_disponible']
                ]);
                return;
            }
            
            // Récupérer les détails du produit
            $produit = $this->getProduitDetails($produitId);
            
            // Calculer le prix avec remise
            $prixUnitaire = $this->calculerPrixVenteDepuisAchat((float)$produit['prix_achat']);
            $prixAvecRemise = $prixUnitaire * (1 - $remise / 100);
            $montantTotal = $prixAvecRemise * $quantite;
            
            // Récupérer le lot FIFO
            $lot = $this->stockService->getLotFIFO($produitId, $quantite);
            
            $article = [
                'produit_id' => $produitId,
                'code_cip' => $produit['code_cip'],
                'designation' => $produit['nom'],
                'prix_unitaire' => $prixUnitaire,
                'prix_revient' => $produit['prix_achat'],
                'quantite' => $quantite,
                'remise' => $remise,
                'lot_id' => $lot['id'] ?? null,
                'lot_numero' => $lot['numero_lot'] ?? 'Sans lot',
                'lot_peremption' => $lot['date_peremption'] ?? null,
                'stock_reel' => $stock['quantite_disponible'],
                'stock_theorique' => $stock['quantite_theorique'],
                'montant' => $montantTotal
            ];
            
            echo json_encode([
                'success' => true,
                'article' => $article
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Valide et enregistre la vente
     */
    public function validerVente(): void
    {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            echo json_encode([
                'success' => false,
                'message' => 'Données invalides'
            ]);
            return;
        }
        
        try {
            // Vérifier la session de caisse
            $session = $this->caisseService->getSessionOuverte($this->currentUser['id']);
            if (!$session) {
                throw new Exception('Aucune session de caisse ouverte');
            }
            
            // Préparer les données de vente
            $venteData = [
                'client_id' => $data['client_id'] ?? null,
                'utilisateur_id' => $this->currentUser['id'],
                'caisse_session_id' => $session['id'],
                'type_paiement' => $data['type_paiement'] ?? 'ESPECE',
                'is_credit' => $data['is_credit'] ?? false,
                'montant_paye' => $data['montant_paye'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'articles' => $data['articles'] ?? []
            ];
            
            // Valider les articles
            if (empty($venteData['articles'])) {
                throw new Exception('Aucun article dans la vente');
            }
            
            // Enregistrer la vente
            $result = $this->venteService->creerVente($venteData);
            
            echo json_encode([
                'success' => true,
                'vente_id' => $result['vente_id'],
                'numero_facture' => $result['numero_facture'],
                'ticket_url' => '/vente/impression?id=' . $result['vente_id'],
                'message' => $result['message']
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Annule une vente
     */
    public function annulerVente(): void
    {
        header('Content-Type: application/json');
        
        $venteId = $_POST['vente_id'] ?? 0;
        
        if (!$venteId) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de vente non fourni'
            ]);
            return;
        }
        
        try {
            $result = $this->venteService->annulerVente($venteId, $this->currentUser['id']);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message']
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Corrige un ticket de vente
     */
    public function corrigerTicket(): void
    {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $venteId = $data['vente_id'] ?? 0;
        $corrections = $data['corrections'] ?? [];
        
        if (!$venteId || empty($corrections)) {
            echo json_encode([
                'success' => false,
                'message' => 'Données de correction invalides'
            ]);
            return;
        }
        
        try {
            $result = $this->venteService->corrigerTicket($venteId, $corrections, $this->currentUser['id']);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message']
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Imprime un ticket de caisse
     */
    public function imprimerTicket(): void
    {
        $venteId = $_GET['vente_id'] ?? 0;
        
        if (!$venteId) {
            $this->redirectBack('/vente');
            return;
        }
        
        try {
            $vente = $this->getVenteComplete($venteId);
            
            // Générer le ticket (format HTML pour impression)
            $this->render('ventes/ticket', [
                'vente' => $vente,
                'session' => $this->caisseService->getSessionDetails($vente['caisse_session_id'])
            ]);
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirectBack('/vente');
        }
    }

    /**
     * Imprime une facture
     */
    public function imprimerFacture(): void
    {
        $venteId = $_GET['vente_id'] ?? 0;
        
        if (!$venteId) {
            $this->redirectBack('/vente');
            return;
        }
        
        try {
            $vente = $this->getVenteComplete($venteId);
            
            $this->render('ventes/facture', [
                'vente' => $vente,
                'session' => $this->caisseService->getSessionDetails($vente['caisse_session_id'])
            ]);
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirectBack('/vente');
        }
    }

    /**
     * Récupère les détails d'un produit
     */
    private function getProduitDetails(int $produitId): array
    {
        $sql = "SELECT p.*, c.nom as categorie, s.quantite_disponible, s.quantite_theorique
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN stock s ON p.id = s.produit_id
                WHERE p.id = ? AND p.is_actif = 1 AND p.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$produitId]);
        
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produit) {
            throw new Exception("Produit non trouvé");
        }
        
        return $produit;
    }

    private function calculerPrixVenteDepuisAchat(float $prixAchat): float
    {
        return round($prixAchat * self::COEFFICIENT_MARGE_VENTE, 2);
    }

    /**
     * Récupère une vente complète avec ses articles
     */
    private function getVenteComplete(int $venteId): array
    {
        $sql = "SELECT v.*, c.nom as client_nom, c.telephone as client_telephone,
                       u.username as vendeur_nom, cs.numero_session
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
                LEFT JOIN caisse_sessions cs ON v.caisse_session_id = cs.id
                WHERE v.id = ? AND v.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vente) {
            throw new Exception("Vente non trouvée");
        }
        
        // Récupérer les articles
        $sql = "SELECT vi.*, p.nom as produit_nom, p.code_cip, l.numero_lot, l.date_peremption
                FROM ventes_items vi
                JOIN produits p ON vi.produit_id = p.id
                LEFT JOIN lots l ON vi.lot_id = l.id
                WHERE vi.vente_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        $vente['articles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $vente;
    }

    /**
     * Récupère l'utilisateur courant (à adapter avec système d'auth)
     */
    private function getCurrentUser(): array
    {
        // Simulation - à remplacer avec le vrai système d'auth
        $sessionUser = $_SESSION['user'] ?? [];
        $userId = (int)($sessionUser['id'] ?? $_SESSION['user_id'] ?? 0);
        $role = strtoupper((string)(
            $sessionUser['role_code']
            ?? $sessionUser['role_name']
            ?? $_SESSION['role']
            ?? ''
        ));

        return [
            'id' => $userId,
            'username' => $sessionUser['username'] ?? $_SESSION['username'] ?? '',
            'role' => $role
        ];
    }

    /**
     * Méthode de rendu simplifiée
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);
        include __DIR__ . "/../Views/{$view}.php";
    }

    /**
     * Méthode de redirection simplifiée
     */
    private function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    private function redirectBack(string $fallback): void
    {
        $target = $_SERVER['HTTP_REFERER'] ?? $fallback;
        if ($target === '' || str_starts_with($target, '//')) {
            $target = $fallback;
        }

        $parts = parse_url($target);
        if ($parts === false || (isset($parts['host']) && !hash_equals($_SERVER['HTTP_HOST'] ?? '', $parts['host']))) {
            $target = $fallback;
        }

        $this->redirect($target);
    }
}
