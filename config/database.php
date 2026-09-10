<?php

/**
 * Configuration de la base de données
 * Gestion de la connexion PDO avec options de sécurité et performance
 */

class Database
{
    private static ?PDO $instance = null;
    private array $config;

    public function __construct()
    {
        $this->config = $this->getDatabaseConfig();
    }

    /**
     * Configuration de la base de données
     */
    private function getDatabaseConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'medecin',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => true, // Connexions persistantes
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_LOCAL_INFILE => false, // Sécurité
            ]
        ];
    }

    /**
     * Obtient l'instance de connexion PDO (Singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $db = new self();
                self::$instance = $db->createConnection();
            } catch (PDOException $e) {
                throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
            }
        }
        
        return self::$instance;
    }

    /**
     * Crée une nouvelle connexion PDO
     */
    private function createConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        try {
            $pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
            
            // Configuration supplémentaire pour la performance
            $this->configureConnection($pdo);
            
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Impossible de se connecter à la base de données: " . $e->getMessage());
        }
    }

    /**
     * Configure la connexion pour la performance et la sécurité
     */
    private function configureConnection(PDO $pdo): void
    {
        // Mode SQL strict
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO'");
        
        // Timezone
        $pdo->exec("SET time_zone = '+00:00'");
        
        // Optimisation des performances
        $pdo->exec("SET SESSION innodb_lock_wait_timeout = 50");
        // Sécurité
        $pdo->exec("SET SESSION sql_safe_updates = 1");
    }

    /**
     * Teste la connexion à la base de données
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnection();
            
            // Test simple
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            // Informations sur la connexion
            $version = $pdo->query("SELECT VERSION() as version")->fetch();
            $database = $pdo->query("SELECT DATABASE() as db_name")->fetch();
            
            return [
                'success' => true,
                'message' => 'Connexion réussie',
                'database' => $database['db_name'],
                'mysql_version' => $version['version'],
                'charset' => $pdo->query("SELECT @@character_set_connection as charset")->fetch()['charset']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Ferme la connexion PDO
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
    }

    /**
     * Exécute un script SQL depuis un fichier
     */
    public static function executeScript(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'Fichier SQL introuvable: ' . $filePath
            ];
        }

        try {
            $pdo = self::getConnection();
            $sql = file_get_contents($filePath);
            
            // Sépare les requêtes SQL
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            
            $pdo->beginTransaction();
            
            foreach ($queries as $query) {
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            
            // DDL statements (for example ALTER TABLE) can implicitly commit
            // on MySQL. Do not turn a successful migration into a fatal error.
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            
            return [
                'success' => true,
                'message' => 'Script SQL exécuté avec succès',
                'queries_executed' => count($queries)
            ];
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'exécution du script: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si les tables requises existent
     */
    public static function checkTables(): array
    {
        try {
            $pdo = self::getConnection();
            
            // Tables requises pour le fonctionnement
            $requiredTables = [
                'utilisateurs',
                'roles',
                'permissions',
                'role_permissions',
                'produits',
                'categories',
                'fournisseurs',
                'lots',
                'stock',
                'mouvements_stock',
                'clients',
                'ventes',
                'ventes_items',
                'caisse_sessions',
                'mouvements_caisse',
                'audit_logs',
                'system_errors'
            ];
            
            $existingTables = [];
            $missingTables = [];
            
            // Utiliser information_schema.tables pour éviter le problème MySQL 8.4 avec SHOW TABLES LIKE ?
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            
            foreach ($requiredTables as $table) {
                $stmt->execute([$table]);
                
                if ((int)$stmt->fetchColumn() > 0) {
                    $existingTables[] = $table;
                } else {
                    $missingTables[] = $table;
                }
            }
            
            return [
                'success' => empty($missingTables),
                'existing_tables' => $existingTables,
                'missing_tables' => $missingTables,
                'total_required' => count($requiredTables),
                'total_existing' => count($existingTables)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification des tables: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée les tables si elles n'existent pas
     */
    public static function createTables(): array
    {
        $schemaFile = __DIR__ . '/../database/schema.sql';
        
        if (!file_exists($schemaFile)) {
            return [
                'success' => false,
                'message' => 'Fichier schema.sql introuvable'
            ];
        }
        
        return self::executeScript($schemaFile);
    }

    /**
     * Obtient des informations sur la base de données
     */
    public static function getDatabaseInfo(): array
    {
        try {
            $pdo = self::getConnection();
            
            $info = [
                'database' => $pdo->query("SELECT DATABASE() as name")->fetch()['name'],
                'version' => $pdo->query("SELECT VERSION() as version")->fetch()['version'],
                'charset' => $pdo->query("SELECT @@character_set_database as charset")->fetch()['charset'],
                'collation' => $pdo->query("SELECT @@collation_database as collation")->fetch()['collation'],
                'tables' => $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()")->fetch()['count'],
                'size' => $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = DATABASE()")->fetch()['size']
            ];
            
            return [
                'success' => true,
                'info' => $info
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Fonction helper pour obtenir la connexion rapidement
 */
function db(): PDO
{
    return Database::getConnection();
}
