<?php

namespace App\Core;

use PDO;
use Exception;
use App\Services\RoleService;
use App\Services\RBACService;

/**
 * Contrôleur de base avec gestion globale de l'utilisateur
 */
abstract class BaseController
{
    protected PDO $db;
    private array $currentUser;
    private RoleService $roleService;
    private RBACService $rbacService;
    private array $globalData;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? $this->getDatabaseFromConfig();
        $this->roleService = new RoleService($this->db);
        $this->rbacService = new RBACService($this->db);
        $this->globalData = [];
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
    }

    /**
     * Bloque l'accès si mauvais rôle
     */
    protected function requireRole(int $roleId): void
    {
        if (!$this->isLoggedIn() || ($_SESSION['user']['role_id'] ?? 0) !== $roleId) {
            header('Location: /login');
            exit;
        }
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

        $this->redirect('/dashboard');
    }

    /**
     * Redirige vers une URL
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Rend une vue
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data);
        
        $viewPath = __DIR__ . "/../Views/{$view}.php";
        if (!file_exists($viewPath)) {
            throw new Exception("Vue non trouvée: {$view}");
        }
        
        require $viewPath;
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
