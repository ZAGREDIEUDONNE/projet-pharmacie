<?php

namespace App\Core;

use PDO;
use Exception;
use App\Services\RoleService;
use App\Services\RBACService;
use App\Services\RoleCatalog;

/**
 * Contrôleur de base avec gestion globale de l'utilisateur
 */
class BaseController
{
    protected ?PDO $db;
    protected array $currentUser = [];
    private ?RoleService $roleService;
    private ?RBACService $rbacService;
    private array $globalData;

    public function __construct(PDO $db = null)
    {
        $this->roleService = null;
        $this->rbacService = null;
        $this->globalData = [];
        $this->currentUser = $this->getCurrentUserFromSession();

        try {
            $this->db = $db ?? $this->getDatabaseFromConfig();
        } catch (Exception $e) {
            error_log("BaseController database connection error: " . $e->getMessage());
            $this->db = null;
            return;
        }

        // Initialiser les services independamment : un service optionnel casse
        // ne doit pas desactiver les controles d'autorisation principaux.
        if (class_exists('App\Services\RoleService')) {
            try {
                $this->roleService = new RoleService($this->db);
            } catch (Exception $e) {
                error_log("BaseController RoleService initialization error: " . $e->getMessage());
            }
        }

        if (class_exists('App\Services\RBACService')) {
            try {
                $this->rbacService = new RBACService($this->db);
            } catch (Exception $e) {
                error_log("BaseController RBACService initialization error: " . $e->getMessage());
            }
        }
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['user']) && !empty($_SESSION['user']);
    }

    /**
     * Bloque l'accès si pas connecté
     */
    protected function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $this->currentUser = $this->getCurrentUserFromSession();
    }

    /**
     * Bloque l'accès si mauvais rôle
     */
    protected function requireRole($role): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $user = $this->getCurrentUserFromSession();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '');

        if (is_int($role)) {
            $hasRole = $roleId === $role
                || ($role === RoleCatalog::ADMIN_ID && $this->isAdminUser());
        } else {
            $expected = $this->normalizeRole((string)$role);
            $hasRole = $roleCode === $expected
                || ($expected === 'ADMIN' && $this->isAdminUser());
        }

        if (!$hasRole) {
            $this->redirect($this->getDefaultDashboardForRole());
        }
    }

    /**
     * Bloque l'acces si l'utilisateur n'a pas la permission demandee.
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $user = $_SESSION['user'] ?? [];
        $userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '');

        if ($this->rbacService && $userId > 0) {
            try {
                if ($this->rbacService->hasPermission($userId, $permission)) {
                    return;
                }
            } catch (Exception $e) {
                error_log("BaseController requirePermission fallback: " . $e->getMessage());
            }
        }

        http_response_code(403);
        echo "Acces refuse";
        exit;
    }

    /**
     * Returns whether the current user has a permission.  Views use this to
     * hide inaccessible menu entries; controllers must still enforce access.
     */
    public function can(string $permission): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $user = $this->getCurrentUser();
        $userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '');

        try {
            return $this->rbacService !== null && $userId > 0
                && $this->rbacService->hasPermission($userId, $permission);
        } catch (Exception $e) {
            error_log('BaseController can: ' . $e->getMessage());
            return false;
        }
    }

    protected function isChargeCommandeUser(): bool
    {
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '');

        return RoleCatalog::isCommande($roleId, $roleCode);
    }

    protected function isAdminUser(): bool
    {
        $user = $this->getCurrentUser();

        return RoleCatalog::isAdmin(
            (int)($user['role_id'] ?? 0),
            $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '')
        );
    }

    protected function denyChargeCommandeRestrictedModules(): void
    {
        if ($this->isChargeCommandeUser()) {
            http_response_code(403);
            echo 'Acces refuse';
            exit;
        }
    }

    private function hasDefaultRolePermission(int $roleId, string $permission, string $roleCode = ''): bool
    {
        $salesPermissions = ['vente.view', 'vente.create', 'make_sale'];
        $clientPermissions = ['client.view', 'client.create', 'client.update', 'create_client', 'edit_client'];
        $stockPermissions = ['stock.view', 'stock.report', 'stock.manage', 'stock.create', 'stock.update', 'view_stock_movements'];
        $chargeCommandePermissions = [
            'view_stock',
            'add_stock',
            'receive_products',
            'create_supplier_orders',
            'view_stock_movements',
            'stock.create_product',
            'product.price.update',
            'product.price.history',
            'stock.update_product',
            'stock.adjust',
            'stock.view_expiry',
            'stock.inventory',
            'view_supplier_orders',
            'edit_supplier_orders',
            'send_supplier_orders',
            'stock.view',
            'stock.create',
            'commande.view',
            'commande.create',
        ];
        $assistantBusinessPermissions = [
            'cancel_ticket',
            'apply_discount',
            'close_cash_register',
            'view_statistics',
            'prepare_orders',
            'ticket_annuler',
            'remise_apply',
            'caisse_arret',
            'statistiques_view',
            'commande_preparer',
            'stock_consulter',
        ];

        $permissionsByCode = [
            // Vendeur permissions are now exclusively read from role_permissions.
            // Keeping a hard-coded allow-list here bypassed RBAC whenever the DB drifted.
            'VENDEUR' => [],
            'ASSISTANT' => array_merge($salesPermissions, $clientPermissions, $stockPermissions, $assistantBusinessPermissions),
            'COMMANDE' => $chargeCommandePermissions,
            'CHARGE_COMMANDE' => $chargeCommandePermissions,
            'CHARGE_DE_COMMANDE' => $chargeCommandePermissions,
        ];

        if ($roleCode !== '') {
            return in_array($permission, $permissionsByCode[$roleCode] ?? [], true);
        }

        $permissionsByRole = [
            RoleCatalog::VENDEUR_ID => [],
            RoleCatalog::ASSISTANT_ID => array_merge($salesPermissions, $clientPermissions, $stockPermissions, $assistantBusinessPermissions),
            RoleCatalog::COMMANDE_ID => $chargeCommandePermissions,
        ];

        return in_array($permission, $permissionsByRole[$roleId] ?? [], true);
    }

    /**
     * Redirige selon le rôle
     */
    protected function redirectByRole(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
            return;
        }

        $defaultDashboard = $this->getDefaultDashboardForRole();
        $target = $_SESSION['redirect_after_login'] ?? $defaultDashboard;
        unset($_SESSION['redirect_after_login']);

        $this->redirect($this->isSafeLocalRedirect($target) ? $target : $defaultDashboard);
    }

    protected function getDefaultDashboardForRole(): string
    {
        $sessionUser = $_SESSION['user'] ?? [];
        $roleId = (int)($sessionUser['role_id'] ?? 0);
        $roleName = (string)($sessionUser['role_name'] ?? $sessionUser['role'] ?? '');
        $roleCode = $this->normalizeRole($sessionUser['role_code'] ?? $roleName);

        return RoleCatalog::getDashboard($roleId, $roleCode, $roleName);
    }

    /**
     * Redirige vers une URL
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function redirectBack(?string $fallback = null): void
    {
        $fallback = $fallback ?? $this->getDefaultDashboardForRole();
        $target = $_SERVER['HTTP_REFERER'] ?? $fallback;
        $this->redirect($this->isSafeLocalRedirect($target) ? $target : $fallback);
    }

    protected function getSafeReturnUrl(?string $fallback = null): string
    {
        $fallback = $fallback ?? $this->getDefaultDashboardForRole();
        $target = (string)($_POST['return_to'] ?? $_GET['return_to'] ?? '');

        return $this->isSafeLocalRedirect($target) ? $target : $fallback;
    }

    protected function isCurrentUserAssistant(): bool
    {
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '');

        return RoleCatalog::isAssistant($roleId, $roleCode);
    }

    private function isSafeLocalRedirect(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        if (isset($parts['host'])) {
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if (!hash_equals($currentHost, $parts['host'])) {
                return false;
            }
        }

        return str_starts_with($parts['path'] ?? '/', '/');
    }

    /**
     * Rend une vue
     */
    protected function render(string $view, array $data = []): void
    {
        if (!array_key_exists('csrf_token', $data) && class_exists(\App\Services\CsrfService::class)) {
            $data['csrf_token'] = \App\Services\CsrfService::token();
        }
        extract($data);

        $viewPath = dirname(__DIR__) . '/Views/' . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($viewPath)) {
            throw new Exception("Vue non trouvee: {$view} ({$viewPath})");
        }

        require $viewPath;
    }

    protected function canViewCaisseStatus(): bool
    {
        $user = $this->getCurrentUser();
        $roleId = (int)($user['role_id'] ?? 0);
        $roleCode = $this->normalizeRole($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? '');

        return RoleCatalog::isAdmin($roleId, $roleCode)
            || RoleCatalog::isVendeur($roleId, $roleCode)
            || RoleCatalog::isAssistant($roleId, $roleCode);
    }

    protected function getDashboardCaisseData(): array
    {
        if (!$this->db || !$this->canViewCaisseStatus()) {
            return [
                'canViewCaisse' => false,
                'activeCaisseSessions' => [],
                'activeCaisseSession' => null,
            ];
        }

        $sql = "SELECT
                    cs.*,
                    u.username AS caissier_nom,
                    TIMESTAMPDIFF(MINUTE, cs.date_ouverture, NOW()) AS duree_minutes,
                    cs.montant_ouverture
                        + COALESCE((SELECT SUM(mc.montant) FROM mouvements_caisse mc WHERE mc.caisse_session_id = cs.id AND mc.type_mouvement = 'VENTE'), 0)
                        - COALESCE((SELECT SUM(mc.montant) FROM mouvements_caisse mc WHERE mc.caisse_session_id = cs.id AND mc.type_mouvement = 'REMBOURSEMENT'), 0)
                        - COALESCE((SELECT SUM(mc.montant) FROM mouvements_caisse mc WHERE mc.caisse_session_id = cs.id AND mc.type_mouvement = 'RETRAIT'), 0)
                        AS montant_theorique_actuel
                FROM caisse_sessions cs
                LEFT JOIN utilisateurs u ON cs.caissier_id = u.id
                WHERE cs.statut_session = 'OUVERTE'
                ORDER BY cs.date_ouverture DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("BaseController getDashboardCaisseData: " . $e->getMessage());
            $sessions = [];
        }

        return [
            'canViewCaisse' => true,
            'activeCaisseSessions' => $sessions,
            'activeCaisseSession' => $sessions[0] ?? null,
        ];
    }

    /**
     * Recupere l'utilisateur courant depuis les differents formats de session.
     */
    protected function getCurrentUser(): array
    {
        if (empty($this->currentUser)) {
            $this->currentUser = $this->getCurrentUserFromSession();
        }

        return $this->currentUser;
    }

    private function getCurrentUserFromSession(): array
    {
        $sessionUser = $_SESSION['user'] ?? [];
        if (!is_array($sessionUser)) {
            $sessionUser = [];
        }

        $role = $sessionUser['role_code']
            ?? ($sessionUser['role']['code'] ?? null)
            ?? $sessionUser['role_name']
            ?? $sessionUser['role']
            ?? $_SESSION['role']
            ?? null;

        return array_merge($sessionUser, [
            'id' => (int)($sessionUser['id'] ?? $_SESSION['user_id'] ?? 0),
            'username' => $sessionUser['username'] ?? $_SESSION['username'] ?? '',
            'role' => $this->normalizeRole($role),
            'role_id' => (int)($sessionUser['role_id'] ?? 0),
            'role_name' => $sessionUser['role_name'] ?? $_SESSION['role_name'] ?? ($role ? (string)$role : ''),
        ]);
    }

    protected function normalizeRole($role): string
    {
        $role = trim((string)$role);
        if ($role === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $role);
            if ($transliterated !== false) {
                $role = $transliterated;
            }
        }

        return strtoupper(str_replace([' ', '-'], '_', $role));
    }

    /**
     * Récupère la connexion à la base de données depuis la configuration
     */
    private function getDatabaseFromConfig(): PDO
    {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $database = $_ENV['DB_DATABASE'] ?? 'medecin';
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
}
