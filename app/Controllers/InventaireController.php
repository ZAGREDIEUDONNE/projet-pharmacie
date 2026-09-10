<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\InventaireService;
use App\Services\AuditService;

/**
 * Module d'inventaire physique.
 */
class InventaireController extends BaseController
{
    private InventaireService $inventaireService;

    public function __construct()
    {
        parent::__construct();
        $this->inventaireService = new InventaireService($this->db);
    }

    public function index(): void
    {
        $this->requirePermission('stock.inventory');

        $this->render('inventaire/index', [
            'title' => 'Inventaires',
            'inventaires' => $this->inventaireService->getListeInventaires($_GET),
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('stock.inventory');

        $this->render('inventaire/create', [
            'title' => 'Nouvel inventaire',
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('stock.inventory');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/inventaire');
            return;
        }

        $userId = (int)($this->getCurrentUser()['id'] ?? 0);
        $reference = 'INV' . date('YmdHis');

        $result = $this->inventaireService->lancerInventaireManuel([
            'reference' => $reference,
            'utilisateur_id' => $userId,
            'notes' => trim($_POST['notes'] ?? ''),
            'type_inventaire' => 'MANUEL',
        ]);

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            $this->redirect('/inventaire/saisie?id=' . (int)$result['inventaire_id']);
        } else {
            $_SESSION['errors'] = [$result['message']];
            $this->redirect('/inventaire/create');
        }
    }

    public function saisie(): void
    {
        $this->requirePermission('stock.inventory');
        $id = (int)($_GET['id'] ?? 0);
        $rayon = $_GET['rayon'] ?? '';
        $produitId = (int)($_GET['produit_id'] ?? 0);

        $inventaire = $this->inventaireService->getInventaire($id);
        if (!$inventaire) {
            $_SESSION['errors'] = ['Inventaire introuvable.'];
            $this->redirect('/inventaire');
            return;
        }

        // Construire la requête avec filtres
        $sql = 'SELECT p.id, p.code_cip, p.nom, p.rayon, COALESCE(s.quantite_disponible, 0) AS stock_theorique
                FROM produits p
                LEFT JOIN stock s ON s.produit_id = p.id
                WHERE p.is_actif = 1 AND p.deleted_at IS NULL';
        
        $params = [];
        
        // Filtre par rayon
        if (!empty($rayon)) {
            $sql .= ' AND p.rayon = :rayon';
            $params['rayon'] = $rayon;
        }
        
        // Filtre par produit
        if ($produitId > 0) {
            $sql .= ' AND p.id = :produit_id';
            $params['produit_id'] = $produitId;
        }
        
        $sql .= ' ORDER BY p.nom';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $produits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Récupérer les rayons disponibles pour le filtre
        $rayons = $this->db->query(
            'SELECT DISTINCT rayon FROM produits WHERE rayon IS NOT NULL AND rayon != "" ORDER BY rayon'
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->render('inventaire/saisie', [
            'title' => 'Saisie inventaire ' . ($inventaire['reference'] ?? ''),
            'inventaire' => $inventaire,
            'articles' => $this->inventaireService->getArticlesInventaire($id),
            'produits' => $produits,
            'rayons' => $rayons,
            'rayon' => $rayon,
            'produit_id' => $produitId,
            'user' => $this->getCurrentUser(),
        ]);
    }

    public function storeArticle(): void
    {
        $this->requirePermission('stock.inventory');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/inventaire');
            return;
        }

        $inventaireId = (int)($_POST['inventaire_id'] ?? 0);
        $result = $this->inventaireService->saisirArticleInventaire($inventaireId, [
            'produit_id' => (int)($_POST['produit_id'] ?? 0),
            'quantite_theorique' => (int)($_POST['quantite_theorique'] ?? 0),
            'quantite_comptee' => (int)($_POST['quantite_comptee'] ?? 0),
            'lot_id' => !empty($_POST['lot_id']) ? (int)$_POST['lot_id'] : null,
            'observations' => trim($_POST['observations'] ?? ''),
        ]);

        if ($result['success']) {
            $_SESSION['success'] = 'Article saisi.';
        } else {
            $_SESSION['errors'] = [$result['message']];
        }

        $this->redirect('/inventaire/saisie?id=' . $inventaireId);
    }

    public function cloturer(): void
    {
        $this->requirePermission('stock.inventory');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/inventaire');
            return;
        }

        $id = (int)($_POST['inventaire_id'] ?? 0);
        $userId = (int)($this->getCurrentUser()['id'] ?? 0);

        $result = $this->inventaireService->cloturerInventaire($id, $userId, [
            'notes_cloture' => trim($_POST['notes_cloture'] ?? ''),
        ]);

        if ($result['success']) {
            (new AuditService($this->db))->logAction($userId, 'INVENTAIRE_CLOTURE', 'inventaires', $id, null, $result['totaux'] ?? []);
            $_SESSION['success'] = $result['message'];
            $this->redirect('/inventaire/detail?id=' . $id);
        } else {
            $_SESSION['errors'] = [$result['message']];
            $this->redirect('/inventaire/saisie?id=' . $id);
        }
    }

    public function detail(): void
    {
        $this->requirePermission('stock.inventory');
        $id = (int)($_GET['id'] ?? 0);

        $inventaire = $this->inventaireService->getInventaire($id);
        if (!$inventaire) {
            $_SESSION['errors'] = ['Inventaire introuvable.'];
            $this->redirect('/inventaire');
            return;
        }

        $this->render('inventaire/detail', [
            'title' => 'Détail inventaire',
            'inventaire' => $inventaire,
            'articles' => $this->inventaireService->getArticlesInventaire($id),
            'user' => $this->getCurrentUser(),
        ]);
    }
}
