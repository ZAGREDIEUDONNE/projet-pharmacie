<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\RoleService;
use App\Services\VenteService;
use App\Services\PharmacyDashboardService;
use App\Services\AuditService;
use App\Services\StockService;
use App\Services\CaisseService;
use App\Services\ComptabiliteService;
use App\Services\RoleCatalog;
use PDO;
use Exception;

class AdminController extends BaseController
{
    private ?RoleService $roleService = null;
    private ?VenteService $venteService = null;

    private function getVenteService(): VenteService
    {
        if ($this->venteService === null) {
            $audit = new AuditService($this->db);
            $this->venteService = new VenteService(
                $this->db,
                new StockService($this->db, $audit),
                new CaisseService($this->db, $audit),
                new ComptabiliteService($this->db, $audit),
                $audit
            );
        }

        return $this->venteService;
    }

    public function __construct()
    {
        parent::__construct();

        if ($this->db) {
            try {
                $this->roleService = new RoleService($this->db);
            } catch (\Throwable $e) {
                error_log("AdminController RoleService unavailable: " . $e->getMessage());
            }
        }
    }

    /**
     * Affiche le dashboard d'administration (accès admin uniquement)
     */
    public function adminDashboard(): void
    {
        $this->requireAdminPermission('reports.view');
        $this->render('admin/index', [
            'title' => 'Dashboard Administration'
        ]);
    }

    /**
     * Vérifie que l'utilisateur est administrateur
     */
    private function requireAdminAccess(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = strtoupper((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));
        
        // Seuls les administrateurs peuvent accéder
        if ($roleId !== 1 && !in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true)) {
            $_SESSION['error'] = 'Accès refusé. Cette fonctionnalité est réservée aux administrateurs.';
            $this->redirect('/admin/dashboard');
            exit;
        }
    }

    private function requireAdminPermission(string $permission): void
    {
        $this->requireAuth();
        $this->requireRole(RoleCatalog::ADMIN_ID);
        $this->requirePermission($permission);
    }

    public function index(): void
    {
        $this->redirect('/admin/dashboard');
    }

    /**
     * Dashboard administrateur
     */
    public function dashboard(): void
    {
        $this->requireAdminPermission('reports.view');

        // Statistiques globales
        $stats = [
            'total_utilisateurs' => $this->getTotalUsers(),
            'utilisateurs_actifs' => $this->getActiveUsers(),
            'total_sessions' => $this->getTotalSessions(),
            'sessions_actives' => $this->getActiveSessions(),
            'corrections_stock' => $this->getStockCorrections(),
            'annulations' => $this->getAnnulations(),
            'roles_actifs' => $this->getActiveRoleCount(),
            'permissions_actives' => $this->getActivePermissionCount(),
            'produits_actifs' => $this->countWhere('produits', 'is_actif = 1 AND deleted_at IS NULL'),
            'fournisseurs_actifs' => $this->countWhere('fournisseurs', 'is_actif = 1 AND deleted_at IS NULL'),
            'clients_actifs' => $this->countWhere('clients', 'is_actif = 1 AND deleted_at IS NULL'),
            'stock_total' => $this->getStockQuantityTotal(),
            'valeur_stock' => $this->getStockValueTotal(),
            'commandes_en_cours' => $this->getOpenSupplierOrderCount(),
            'receptions_mois' => $this->countWhere('receptions', 'date_reception >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'),
            'ventes_jour' => $this->getSalesSummary('CURDATE()', 'CURDATE()'),
            'tickets_annules_jour' => $this->countWhere('ventes', "DATE(date_vente) = CURDATE() AND statut_vente = 'ANNULEE'"),
            'ecritures_comptables' => $this->countWhere('ecritures_comptables', '1=1'),
            'audit_jour' => $this->countWhere('audit_logs', 'DATE(date_action) = CURDATE()')
        ];

        // Utilisateurs par rôle
        $usersByRole = $this->getUsersByRole();
        $caisseData = $this->getSafeDashboardCaisseData();

        $this->render('admin/dashboard', [
            'stats' => $stats,
            'usersByRole' => $usersByRole,
            'title' => 'Dashboard Administrateur',
            'user' => $_SESSION['user'] ?? null,
            'adminData' => $this->getAdminDashboardData(),
            'metier' => (new PharmacyDashboardService($this->db))->getIndicateurs(),
        ] + $caisseData);
    }

    /**
     * Page de statistiques administrateur.
     */
    public function statistiques(): void
    {
        $this->requireAdminPermission('reports.view');

        $this->render('admin/statistiques', [
            'title' => 'Statistiques Administrateur',
            'user' => $_SESSION['user'] ?? null,
            'stats' => $this->getAdminStatistiquesData(),
        ]);
    }

    public function statistiquesLive(): void
    {
        $this->requireAdminPermission('reports.view');
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'stats' => $this->getAdminStatistiquesData(),
            'metier' => (new PharmacyDashboardService($this->db))->getIndicateurs(),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Page d'annulation de tickets pour les administrateurs.
     */
    public function annulationTickets(): void
    {
        $this->requireAdminPermission('vente.cancel');

        $this->render('assistant/action-page', [
            'title' => 'Annuler ticket',
            'sectionLabel' => 'Admin',
            'sectionIcon' => 'fa-user-shield',
            'sectionColor' => 'text-blue-600',
            'returnUrl' => '/admin/dashboard',
            'user' => $_SESSION['user'] ?? null,
            'actions' => [
                ['label' => 'Rechercher un ticket', 'url' => '/vente/impression?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-receipt', 'color' => 'text-orange-600'],
                ['label' => 'Retour point de vente', 'url' => '/vente', 'icon' => 'fa-shopping-cart', 'color' => 'text-green-600'],
                ['label' => 'Journal de tracabilite', 'url' => '/admin/audit', 'icon' => 'fa-history', 'color' => 'text-red-600'],
            ],
            'accessNote' => 'Selectionnez un ticket enregistre, indiquez le motif puis confirmez l annulation.',
            'tickets' => $this->getVenteService()->getTicketsAnnulables(),
            'cancelReturnTo' => '/admin/annulation-tickets',
        ]);
    }

    /**
     * Page de gestion des utilisateurs
     */
    public function users(): void
    {
        $this->requireAdminPermission('user.manage');
        $this->clearClientFlashErrors();

        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'role_id' => max(0, (int)($_GET['role_id'] ?? 0)),
            'is_active' => in_array((string)($_GET['is_active'] ?? ''), ['0', '1'], true)
                ? (string)$_GET['is_active']
                : '',
        ];
        $users = $this->getAllUsers($filters);
        $roles = $this->roleService ? $this->roleService->getAllRoles() : $this->getAllRoles();

        $this->render('admin/users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $filters,
            'title' => 'Gestion des Utilisateurs'
        ]);
    }

    public function createUser(): void
    {
        $this->requireAdminPermission('user.manage');

        $this->render('admin/user_form', [
            'roles' => $this->getAllRoles(),
            'action' => '/admin/users/store',
            'isEdit' => false,
            'returnTo' => $this->getSafeReturnUrl('/admin/users'),
            'title' => 'Nouvel utilisateur'
        ]);
    }

    public function storeUser(): void
    {
        $this->requireAdminPermission('user.manage');
        $returnTo = $this->getSafeReturnUrl('/admin/users');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/users/create?return_to=' . urlencode($returnTo));
            return;
        }

        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'role_id' => (int)($_POST['role_id'] ?? 0),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $errors = $this->validateUserData($data);

        if (!empty($errors)) {
            $this->render('admin/user_form', [
                'roles' => $this->getAllRoles(),
                'errors' => $errors,
                'old' => $data,
                'action' => '/admin/users/store',
                'isEdit' => false,
                'returnTo' => $returnTo,
                'title' => 'Nouvel utilisateur'
            ]);
            return;
        }

        try {
            $sql = "INSERT INTO utilisateurs (
                        username, email, password_hash, nom, prenom, telephone,
                        role_id, is_active, created_at, updated_at
                    ) VALUES (
                        :username, :email, :password_hash, :nom, :prenom, :telephone,
                        :role_id, :is_active, NOW(), NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'telephone' => $data['telephone'] !== '' ? $data['telephone'] : null,
                'role_id' => $data['role_id'],
                'is_active' => $data['is_active']
            ]);

            $this->auditUserChange(
                'CREATE_USER',
                (int)$this->db->lastInsertId(),
                null,
                $data
            );

            $_SESSION['success'] = 'Utilisateur cree avec succes';
            $this->redirect($returnTo);
        } catch (Exception $e) {
            error_log("AdminController::storeUser - " . $e->getMessage());
            $this->render('admin/user_form', [
                'roles' => $this->getAllRoles(),
                'errors' => ['Erreur lors de la creation de l utilisateur'],
                'old' => $data,
                'action' => '/admin/users/store',
                'isEdit' => false,
                'returnTo' => $returnTo,
                'title' => 'Nouvel utilisateur'
            ]);
        }
    }

    public function editUser(int $id): void
    {
        $this->requireAdminPermission('user.manage');

        $user = $this->getUserById($id);
        if (!$user) {
            $_SESSION['errors'] = ['Utilisateur introuvable'];
            $this->redirect('/admin/users');
            return;
        }

        $this->render('admin/user_form', [
            'roles' => $this->getAllRoles(),
            'old' => $user,
            'action' => "/admin/users/{$id}/update",
            'isEdit' => true,
            'title' => 'Modifier utilisateur'
        ]);
    }

    public function updateUser(int $id): void
    {
        $this->requireAdminPermission('user.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/admin/users/{$id}/edit");
            return;
        }

        $oldUser = $this->getUserById($id);
        if (!$oldUser) {
            $_SESSION['errors'] = ['Utilisateur introuvable'];
            $this->redirect('/admin/users');
            return;
        }

        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'role_id' => (int)($_POST['role_id'] ?? 0),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $errors = $this->validateUserData($data, $id, false);

        if (!empty($errors)) {
            $this->render('admin/user_form', [
                'roles' => $this->getAllRoles(),
                'errors' => $errors,
                'old' => array_merge($data, ['id' => $id]),
                'action' => "/admin/users/{$id}/update",
                'isEdit' => true,
                'title' => 'Modifier utilisateur'
            ]);
            return;
        }

        try {
            $fields = [
                'username = :username',
                'email = :email',
                'nom = :nom',
                'prenom = :prenom',
                'telephone = :telephone',
                'role_id = :role_id',
                'is_active = :is_active',
                'updated_at = NOW()'
            ];

            $params = [
                'username' => $data['username'],
                'email' => $data['email'],
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'telephone' => $data['telephone'] !== '' ? $data['telephone'] : null,
                'role_id' => $data['role_id'],
                'is_active' => $data['is_active'],
                'id' => $id
            ];

            if ($data['password'] !== '') {
                $fields[] = 'password_hash = :password_hash';
                $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $sql = "UPDATE utilisateurs SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $action = (int)$oldUser['role_id'] !== $data['role_id']
                ? 'CHANGE_USER_ROLE'
                : ((int)$oldUser['is_active'] !== $data['is_active']
                    ? ($data['is_active'] === 1 ? 'ACTIVATE_USER' : 'DEACTIVATE_USER')
                    : 'UPDATE_USER');
            $this->auditUserChange($action, $id, $oldUser, $data);

            $_SESSION['success'] = 'Utilisateur modifie avec succes';
            $this->redirect('/admin/users');
        } catch (Exception $e) {
            error_log("AdminController::updateUser - " . $e->getMessage());
            $this->render('admin/user_form', [
                'roles' => $this->getAllRoles(),
                'errors' => ['Erreur lors de la modification de l utilisateur'],
                'old' => array_merge($data, ['id' => $id]),
                'action' => "/admin/users/{$id}/update",
                'isEdit' => true,
                'title' => 'Modifier utilisateur'
            ]);
        }
    }

    public function deleteUser(int $id): void
    {
        $this->requireAdminPermission('user.manage');

        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        if ($id === $currentUserId) {
            $_SESSION['errors'] = ['Vous ne pouvez pas supprimer votre propre compte'];
            $this->redirect('/admin/users');
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM utilisateurs WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("UPDATE utilisateurs SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $auditService = new \App\Services\AuditService($this->db);
            $auditService->logAction(
                $currentUserId ?: null,
                'DELETE_USER',
                'utilisateurs',
                $id,
                $this->sanitizeUserForAudit($oldUser ?: []),
                ['deleted_at' => date('Y-m-d H:i:s')],
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );

            $_SESSION['success'] = 'Utilisateur supprime avec succes';
        } catch (Exception $e) {
            error_log("AdminController::deleteUser - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors de la suppression de l utilisateur'];
        }

        $this->redirect('/admin/users');
    }

    /**
     * Page de gestion des rôles
     */
    public function roles(): void
    {
        $this->requireAdminPermission('user.manage');

        $roles = $this->roleService ? $this->roleService->getAllRoles() : $this->getAllRoles();

        $this->render('admin/roles', [
            'roles' => $roles,
            'permissionsByRole' => $this->getPermissionCountsByRole(),
            'permissionsDetailByRole' => $this->getPermissionsDetailByRole(),
            'title' => 'Gestion des Rôles'
        ]);
    }

    /**
     * Page de journal de traçabilité
     */
    public function audit(): void
    {
        $this->requireAdminPermission('audit.view');

        $auditLogs = $this->getAuditLogs();
        $stats = $this->getAuditStats();

        $this->render('admin/audit', [
            'auditLogs' => $auditLogs,
            'stats' => $stats,
            'title' => 'Journal de Traçabilité'
        ]);
    }

    public function auditLive(): void
    {
        $this->requireAdminPermission('audit.view');
        header('Content-Type: application/json');

        $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
        $sinceId = max(0, (int)($_GET['since_id'] ?? 0));

        echo json_encode([
            'success' => true,
            'auditLogs' => $this->getAuditLogs($limit, $sinceId),
            'stats' => $this->getAuditStats(),
            'generated_at' => date('c'),
        ]);
    }

    /**
     * Récupère le nombre total d'utilisateurs
     */
    public function system(): void
    {
        $this->requireAdminPermission('settings.manage');

        $this->render('admin/system', [
            'title' => 'Parametres Systeme',
            'systemInfo' => $this->getAdminSystemInfo()
        ]);
    }

    private function getTotalUsers(): int
    {
        $sql = "SELECT COUNT(*) as total FROM utilisateurs";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Récupère le nombre d'utilisateurs actifs
     */
    private function getActiveUsers(): int
    {
        $sql = "SELECT COUNT(*) as total FROM utilisateurs WHERE is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Récupère le nombre total de sessions
     */
    private function getTotalSessions(): int
    {
        if (!$this->tableExists('caisse_sessions')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as total FROM caisse_sessions";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Récupère le nombre de sessions actives
     */
    private function getActiveSessions(): int
    {
        if (!$this->tableExists('caisse_sessions')) {
            return 0;
        }

        $statusColumn = $this->getFirstExistingColumn('caisse_sessions', ['statut_session', 'statut']);
        if ($statusColumn === null) {
            return 0;
        }

        try {
            $sql = "SELECT COUNT(*) as total FROM caisse_sessions WHERE {$statusColumn} = 'OUVERTE'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("AdminController::getActiveSessions - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère les corrections de stock récentes
     */
    private function getStockCorrections(): int
    {
        if (!$this->tableExists('trace_corrections_stock')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as total FROM trace_corrections_stock 
                WHERE date_correction >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Récupère les annulations récentes
     */
    private function getAnnulations(): int
    {
        if (!$this->tableExists('trace_annulations')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as total FROM trace_annulations 
                WHERE date_annulation >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Récupère les utilisateurs par rôle
     */
    private function getUsersByRole(): array
    {
        if (!$this->tableExists('roles') || !$this->tableExists('utilisateurs')) {
            return [];
        }

        $roleNameColumn = $this->getFirstExistingColumn('roles', ['nom', 'name', 'libelle', 'code']);
        if ($roleNameColumn === null) {
            return [];
        }

        try {
            $sql = "SELECT r.{$roleNameColumn} as role_name, COUNT(u.id) as user_count
                    FROM roles r
                    LEFT JOIN utilisateurs u ON r.id = u.role_id AND u.is_active = 1
                    GROUP BY r.id, r.{$roleNameColumn}
                    ORDER BY r.id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AdminController::getUsersByRole - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère tous les utilisateurs
     */
    private function getAllUsers(array $filters = []): array
    {
        $sql = "SELECT u.*, r.nom as role_name
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.deleted_at IS NULL";
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (u.username LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if ((int)($filters['role_id'] ?? 0) > 0) {
            $sql .= ' AND u.role_id = :role_id';
            $params['role_id'] = (int)$filters['role_id'];
        }
        if (in_array((string)($filters['is_active'] ?? ''), ['0', '1'], true)) {
            $sql .= ' AND u.is_active = :is_active';
            $params['is_active'] = (int)$filters['is_active'];
        }
        $sql .= "
                ORDER BY u.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUserById(int $id): ?array
    {
        $sql = "SELECT u.*, r.nom as role_name
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
                AND u.deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getAllRoles(): array
    {
        $ids = implode(',', RoleCatalog::ALLOWED_IDS);
        $sql = "SELECT * FROM roles WHERE id IN ({$ids}) ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validateUserData(array $data, ?int $userId = null, bool $requirePassword = true): array
    {
        $errors = [];

        if ($data['username'] === '') {
            $errors[] = 'Le nom utilisateur est obligatoire';
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Une adresse email valide est obligatoire';
        }

        if ($data['nom'] === '') {
            $errors[] = 'Le nom est obligatoire';
        }

        if ($data['prenom'] === '') {
            $errors[] = 'Le prenom est obligatoire';
        }

        if ($data['role_id'] <= 0 || !$this->roleExists((int)$data['role_id'])) {
            $errors[] = 'Le role selectionne est invalide';
        }

        if ($requirePassword && strlen($data['password']) < MIN_PASSWORD_LENGTH) {
            $errors[] = 'Le mot de passe doit contenir au moins ' . MIN_PASSWORD_LENGTH . ' caracteres';
        }

        if (!$requirePassword && $data['password'] !== '' && strlen($data['password']) < MIN_PASSWORD_LENGTH) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins ' . MIN_PASSWORD_LENGTH . ' caracteres';
        }

        if (($requirePassword || $data['password'] !== '' || $data['password_confirm'] !== '') && $data['password'] !== $data['password_confirm']) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        }

        if ($data['username'] !== '' && $this->userFieldExists('username', $data['username'], $userId)) {
            $errors[] = 'Ce nom utilisateur existe deja';
        }

        if ($data['email'] !== '' && $this->userFieldExists('email', $data['email'], $userId)) {
            $errors[] = 'Cette adresse email existe deja';
        }

        return $errors;
    }

    private function userFieldExists(string $field, string $value, ?int $excludeUserId = null): bool
    {
        if (!in_array($field, ['username', 'email'], true)) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM utilisateurs WHERE {$field} = ? AND deleted_at IS NULL";
        $params = [$value];

        if ($excludeUserId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function roleExists(int $roleId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM roles WHERE id = ? AND is_actif = 1 AND statut = 1");
        $stmt->execute([$roleId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getPermissionCountsByRole(): array
    {
        $sql = "SELECT role_id, COUNT(*) as total
                FROM role_permissions
                GROUP BY role_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int)$row['role_id']] = (int)$row['total'];
        }

        return $counts;
    }

    private function getPermissionsDetailByRole(): array
    {
        $sql = "SELECT rp.role_id, p.nom, COALESCE(NULLIF(p.module, ''), 'autre') AS module
                FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                ORDER BY rp.role_id, module, p.nom";
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $details = [];
        foreach ($rows as $row) {
            $details[(int)$row['role_id']][(string)$row['module']][] = (string)$row['nom'];
        }
        return $details;
    }

    private function auditUserChange(string $action, int $userId, ?array $oldValues, array $newValues): void
    {
        $audit = new AuditService($this->db);
        $audit->logAction(
            (int)($_SESSION['user']['id'] ?? 0) ?: null,
            $action,
            'utilisateurs',
            $userId,
            $oldValues !== null ? $this->sanitizeUserForAudit($oldValues) : null,
            $this->sanitizeUserForAudit($newValues),
            null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }

    private function sanitizeUserForAudit(array $values): array
    {
        unset($values['password'], $values['password_confirm'], $values['password_hash'], $values['csrf_token']);
        return $values;
    }

    private function getAdminSystemInfo(): array
    {
        return [
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Application',
            'app_version' => defined('APP_VERSION') ? APP_VERSION : 'N/A',
            'environment' => defined('APP_ENV') ? APP_ENV : 'N/A',
            'debug' => defined('APP_DEBUG') && APP_DEBUG ? 'Actif' : 'Inactif',
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->getMySQLVersion(),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'database' => DB_DATABASE,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
        ];
    }

    private function getMySQLVersion(): string
    {
        try {
            $stmt = $this->db->query("SELECT VERSION() as version");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['version'] ?? 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Récupère les logs d'audit avec traduction
     */
    private function getAuditLogs(int $limit = 100, int $sinceId = 0): array
    {
        if (!$this->tableExists('audit_logs')) {
            return [];
        }

        try {
            $auditService = new \App\Services\AuditService($this->db);
            return $auditService->getAllAuditLogs($limit, $sinceId);
        } catch (Exception $e) {
            error_log("AdminController::getAuditLogs - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les statistiques d'audit
     */
    private function getAuditStats(): array
    {
        if (!$this->tableExists('audit_logs')) {
            return ['total_logs' => 0, 'creations' => 0, 'modifications' => 0, 'suppressions' => 0];
        }

        $sql = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(CASE WHEN action LIKE '%CREATE%' OR action LIKE '%CREATION%' OR action LIKE '%OUVRIR%' THEN 1 END) as creations,
                    COUNT(CASE WHEN action LIKE '%UPDATE%' OR action LIKE '%MODIFICATION%' THEN 1 END) as modifications,
                    COUNT(CASE WHEN action LIKE '%DELETE%' OR action LIKE '%SUPPRESSION%' OR action LIKE '%FERMER%' THEN 1 END) as suppressions
                FROM audit_logs 
                WHERE date_action >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_logs' => 0, 'creations' => 0, 'modifications' => 0, 'suppressions' => 0];
        } catch (Exception $e) {
            error_log("AdminController::getAuditStats - " . $e->getMessage());
            return ['total_logs' => 0, 'creations' => 0, 'modifications' => 0, 'suppressions' => 0];
        }
    }

    private function getAdminStatistiquesData(): array
    {
        return [
            'resume' => [
                'ventes_jour' => $this->getSalesSummary('CURDATE()', 'CURDATE()'),
                'ventes_mois' => $this->getSalesSummary('DATE_FORMAT(CURDATE(), "%Y-%m-01")', 'CURDATE()'),
                'clients_actifs' => $this->countWhere('clients', 'is_actif = 1 AND deleted_at IS NULL'),
                'produits_actifs' => $this->countWhere('produits', 'is_actif = 1 AND deleted_at IS NULL'),
                'produits_alerte' => $this->getLowStockCount(),
                'sessions_ouvertes' => $this->getActiveSessions(),
                'actions_jour' => $this->countWhere('audit_logs', 'DATE(date_action) = CURDATE()'),
            ],
            'ventes_7_jours' => $this->getSalesByDay(7),
            'top_produits' => $this->getTopProducts(8),
            'sessions_recentes' => $this->getRecentCaisseSessions(8),
            'audit_recent' => array_slice($this->getAuditLogs(), 0, 8),
        ];
    }

    private function getSalesSummary(string $dateStartExpression, string $dateEndExpression): array
    {
        if (!$this->tableExists('ventes')) {
            return ['count' => 0, 'total' => 0, 'average' => 0];
        }

        try {
            $sql = "SELECT
                        COUNT(*) AS count,
                        COALESCE(SUM(montant_net), 0) AS total,
                        COALESCE(AVG(montant_net), 0) AS average
                    FROM ventes
                    WHERE deleted_at IS NULL
                    AND statut_vente != 'ANNULEE'
                    AND DATE(date_vente) BETWEEN {$dateStartExpression} AND {$dateEndExpression}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'count' => (int)($row['count'] ?? 0),
                'total' => (float)($row['total'] ?? 0),
                'average' => (float)($row['average'] ?? 0),
            ];
        } catch (Exception $e) {
            error_log("AdminController::getSalesSummary - " . $e->getMessage());
            return ['count' => 0, 'total' => 0, 'average' => 0];
        }
    }

    private function getSalesByDay(int $days): array
    {
        if (!$this->tableExists('ventes')) {
            return [];
        }

        try {
            $dateDebut = date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
            $sql = "SELECT
                        DATE(date_vente) AS date,
                        COUNT(*) AS nombre,
                        COALESCE(SUM(montant_net), 0) AS total
                    FROM ventes
                    WHERE deleted_at IS NULL
                    AND statut_vente != 'ANNULEE'
                    AND DATE(date_vente) >= :date_debut
                    GROUP BY DATE(date_vente)
                    ORDER BY date ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':date_debut', $dateDebut);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AdminController::getSalesByDay - " . $e->getMessage());
            return [];
        }
    }

    private function getTopProducts(int $limit): array
    {
        if (!$this->tableExists('ventes_items') || !$this->tableExists('produits')) {
            return [];
        }

        try {
            $sql = "SELECT
                        p.nom,
                        COALESCE(SUM(vi.quantite), 0) AS quantite,
                        COALESCE(SUM(vi.montant_total), 0) AS total
                    FROM ventes_items vi
                    JOIN produits p ON p.id = vi.produit_id
                    JOIN ventes v ON v.id = vi.vente_id
                    WHERE v.deleted_at IS NULL
                    AND v.statut_vente != 'ANNULEE'
                    GROUP BY p.id, p.nom
                    ORDER BY quantite DESC, total DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AdminController::getTopProducts - " . $e->getMessage());
            return [];
        }
    }

    private function getLowStockCount(): int
    {
        if (!$this->tableExists('produits') || !$this->tableExists('stock')) {
            return 0;
        }

        try {
            $sql = "SELECT COUNT(*)
                    FROM produits p
                    LEFT JOIN stock s ON s.produit_id = p.id
                    WHERE p.is_actif = 1
                    AND p.deleted_at IS NULL
                    AND COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0)";
            return (int)$this->db->query($sql)->fetchColumn();
        } catch (Exception $e) {
            error_log("AdminController::getLowStockCount - " . $e->getMessage());
            return 0;
        }
    }

    private function getRecentCaisseSessions(int $limit): array
    {
        if (!$this->tableExists('caisse_sessions')) {
            return [];
        }

        try {
            $cashierColumn = $this->columnExists('caisse_sessions', 'caissier_id') ? 'caissier_id' : 'utilisateur_id';
            if (!$this->columnExists('caisse_sessions', $cashierColumn)) {
                $cashierColumn = null;
            }

            $statusColumn = $this->getFirstExistingColumn('caisse_sessions', ['statut_session', 'statut']);
            $sessionColumn = $this->getFirstExistingColumn('caisse_sessions', ['numero_session', 'session_number']);
            $userJoin = $cashierColumn ? "LEFT JOIN utilisateurs u ON u.id = cs.{$cashierColumn}" : "LEFT JOIN utilisateurs u ON 1 = 0";
            $sql = "SELECT cs.*, u.username AS caissier_nom
                    FROM caisse_sessions cs
                    {$userJoin}
                    ORDER BY cs.date_ouverture DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(static function (array $row) use ($statusColumn, $sessionColumn): array {
                $row['statut_session'] = $statusColumn ? ($row[$statusColumn] ?? null) : null;
                $row['numero_session'] = $sessionColumn ? ($row[$sessionColumn] ?? ($row['id'] ?? null)) : ($row['id'] ?? null);
                return $row;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log("AdminController::getRecentCaisseSessions - " . $e->getMessage());
            return [];
        }
    }

    private function countWhere(string $table, string $where): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("AdminController::countWhere({$table}) - " . $e->getMessage());
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE " . $this->db->quote($table));
            return $stmt && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getFirstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function getSafeDashboardCaisseData(): array
    {
        try {
            return $this->getDashboardCaisseData();
        } catch (Exception $e) {
            error_log("AdminController::getSafeDashboardCaisseData - " . $e->getMessage());
            return [
                'canViewCaisse' => false,
                'activeCaisseSessions' => [],
                'activeCaisseSession' => null,
            ];
        }
    }

    private function getActiveRoleCount(): int
    {
        if (!$this->tableExists('roles')) {
            return 0;
        }

        $where = $this->columnExists('roles', 'statut') ? "statut = 'ACTIF'" : '1=1';
        return $this->countWhere('roles', $where);
    }

    private function getActivePermissionCount(): int
    {
        if (!$this->tableExists('permissions')) {
            return 0;
        }

        $where = $this->columnExists('permissions', 'statut') ? "statut = 'ACTIF'" : '1=1';
        return $this->countWhere('permissions', $where);
    }

    private function getStockQuantityTotal(): int
    {
        if (!$this->tableExists('stock')) {
            return 0;
        }

        try {
            return (int)$this->db->query('SELECT COALESCE(SUM(quantite_disponible), 0) FROM stock')->fetchColumn();
        } catch (Exception $e) {
            error_log("AdminController::getStockQuantityTotal - " . $e->getMessage());
            return 0;
        }
    }

    private function getStockValueTotal(): float
    {
        if (!$this->tableExists('stock')) {
            return 0.0;
        }

        try {
            return (float)$this->db->query('SELECT COALESCE(SUM(valeur_stock), 0) FROM stock')->fetchColumn();
        } catch (Exception $e) {
            error_log("AdminController::getStockValueTotal - " . $e->getMessage());
            return 0.0;
        }
    }

    private function getOpenSupplierOrderCount(): int
    {
        if ($this->tableExists('supplier_orders')) {
            return $this->countWhere('supplier_orders', "statut IN ('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')");
        }

        return $this->countWhere('commandes', "deleted_at IS NULL AND statut_commande IN ('BROUILLON', 'VALIDEE', 'PARTIELLEMENT_LIVREE')");
    }

    private function getAdminDashboardData(): array
    {
        return [
            'stock_alerts' => $this->getLowStockAlerts(6),
            'pending_orders' => $this->getPendingSupplierOrdersForDashboard(6),
            'recent_sales' => $this->getRecentSalesForDashboard(6),
            'recent_audit' => array_slice($this->getAuditLogs(), 0, 6),
        ];
    }

    private function getLowStockAlerts(int $limit): array
    {
        if (!$this->tableExists('produits') || !$this->tableExists('stock')) {
            return [];
        }

        try {
            $sql = "SELECT
                        p.nom,
                        p.code_cip,
                        COALESCE(s.quantite_disponible, 0) AS stock_reel,
                        COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0) AS seuil
                    FROM produits p
                    LEFT JOIN stock s ON s.produit_id = p.id
                    WHERE p.is_actif = 1
                    AND p.deleted_at IS NULL
                    AND COALESCE(s.quantite_disponible, 0) <= COALESCE(NULLIF(p.stock_alerte, 0), p.stock_securite, 0)
                    ORDER BY stock_reel ASC, p.nom ASC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AdminController::getLowStockAlerts - " . $e->getMessage());
            return [];
        }
    }

    private function getPendingSupplierOrdersForDashboard(int $limit): array
    {
        if ($this->tableExists('supplier_orders')) {
            try {
                $sql = "SELECT so.numero_commande, so.date_commande, so.statut, so.montant_total, f.nom AS fournisseur_nom
                        FROM supplier_orders so
                        LEFT JOIN fournisseurs f ON f.id = so.fournisseur_id
                        WHERE so.statut IN ('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE')
                        ORDER BY so.date_livraison_prevue IS NULL, so.date_livraison_prevue ASC, so.date_commande DESC
                        LIMIT :limit";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("AdminController::getPendingSupplierOrdersForDashboard supplier_orders - " . $e->getMessage());
                return [];
            }
        }

        if (!$this->tableExists('commandes')) {
            return [];
        }

        try {
            $sql = "SELECT c.numero_commande, c.date_commande, c.statut_commande AS statut, c.montant_total, f.nom AS fournisseur_nom
                    FROM commandes c
                    LEFT JOIN fournisseurs f ON f.id = c.fournisseur_id
                    WHERE c.deleted_at IS NULL
                    AND c.statut_commande IN ('BROUILLON', 'VALIDEE', 'PARTIELLEMENT_LIVREE')
                    ORDER BY c.date_livraison_prevue IS NULL, c.date_livraison_prevue ASC, c.date_commande DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AdminController::getPendingSupplierOrdersForDashboard commandes - " . $e->getMessage());
            return [];
        }
    }

    private function getRecentSalesForDashboard(int $limit): array
    {
        if (!$this->tableExists('ventes')) {
            return [];
        }

        try {
            $sql = "SELECT
                        v.numero_facture,
                        v.date_vente,
                        v.montant_net,
                        v.statut_vente,
                        COALESCE(CONCAT(NULLIF(c.prenom, ''), ' ', c.nom), c.nom, 'Client comptoir') AS client_nom
                    FROM ventes v
                    LEFT JOIN clients c ON c.id = v.client_id
                    WHERE v.deleted_at IS NULL
                    AND v.statut_vente != 'ANNULEE'
                    ORDER BY v.date_vente DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AdminController::getRecentSalesForDashboard - " . $e->getMessage());
            return [];
        }
    }

    private function clearClientFlashErrors(): void
    {
        if (empty($_SESSION['errors']) || !is_array($_SESSION['errors'])) {
            return;
        }

        $_SESSION['errors'] = array_values(array_filter($_SESSION['errors'], static function ($error): bool {
            return stripos((string)$error, 'client') === false;
        }));

        if (empty($_SESSION['errors'])) {
            unset($_SESSION['errors']);
        }
    }
}
