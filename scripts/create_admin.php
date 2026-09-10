#!/usr/bin/env php
<?php

/**
 * Script de création d'utilisateur admin pour ERP Pharmacy
 * Usage: php scripts/create_admin.php [--reset]
 */

// Charger la configuration de l'application
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Connexion à la base de données
 */
function getDatabaseConnection(): PDO
{
    try {
        $host = DB_HOST;
        $port = DB_PORT;
        $database = DB_DATABASE;
        $username = DB_USERNAME;
        $password = DB_PASSWORD;
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        echo "❌ Erreur de connexion à la base de données: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Vérifier si l'admin existe déjà
 */
function adminExists(PDO $db, string $email, string $username): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM utilisateurs WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Supprimer l'admin existant
 */
function deleteExistingAdmin(PDO $db, string $email, string $username): void
{
    $stmt = $db->prepare("DELETE FROM utilisateurs WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    echo "🗑️  Ancien admin supprimé avec succès\n";
}

/**
 * Vérifier la structure de la table utilisateurs
 */
function checkTableStructure(PDO $db): void
{
    echo "📋 Vérification de la structure de la table 'utilisateurs':\n";
    echo "================================================\n";
    
    $stmt = $db->query('DESCRIBE utilisateurs');
    $fields = [];
    
    while ($row = $stmt->fetch()) {
        $fields[] = $row['Field'];
        echo sprintf("%-20s %-30s\n", $row['Field'], $row['Type']);
    }
    
    echo "\n🔍 Champs trouvés: " . implode(', ', $fields) . "\n";
    
    // Vérifier si le champ password existe
    if (!in_array('password', $fields)) {
        echo "⚠️  Le champ 'password' n'existe pas dans la table!\n";
        
        // Chercher des alternatives possibles
        $possibleFields = ['pwd', 'pass', 'mot_de_passe', 'user_password'];
        foreach ($possibleFields as $field) {
            if (in_array($field, $fields)) {
                echo "💡 Champ alternatif trouvé: '{$field}'\n";
            }
        }
    }
}

/**
 * Créer un nouvel admin
 */
function createAdmin(PDO $db, array $adminData): bool
{
    try {
        // Vérifier d'abord la structure
        $stmt = $db->query('DESCRIBE utilisateurs');
        $fields = [];
        while ($row = $stmt->fetch()) {
            $fields[] = $row['Field'];
        }
        
        // Déterminer le nom du champ password
        $passwordField = 'password_hash';
        if (!in_array('password_hash', $fields)) {
            if (in_array('password', $fields)) $passwordField = 'password';
            elseif (in_array('pwd', $fields)) $passwordField = 'pwd';
            elseif (in_array('pass', $fields)) $passwordField = 'pass';
            elseif (in_array('mot_de_passe', $fields)) $passwordField = 'mot_de_passe';
            elseif (in_array('user_password', $fields)) $passwordField = 'user_password';
            else {
                echo "❌ Aucun champ password trouvé dans la table!\n";
                echo "📋 Champs disponibles: " . implode(', ', $fields) . "\n";
                return false;
            }
        }
        
        echo "🔑 Utilisation du champ password: '{$passwordField}'\n\n";
        
        $stmt = $db->prepare("
            INSERT INTO utilisateurs (
                username, email, {$passwordField}, nom, prenom, telephone, 
                role_id, is_active, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");
        
        return $stmt->execute([
            $adminData['username'],
            $adminData['email'],
            $adminData['password_hash'],
            $adminData['nom'],
            $adminData['prenom'],
            $adminData['telephone'],
            $adminData['role_id'],
            $adminData['is_active']
        ]);
    } catch (PDOException $e) {
        echo "❌ Erreur lors de la création de l'admin: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Afficher l'aide
 */
function showHelp(): void
{
    echo "📋 Script de création d'admin pour ERP Pharmacy\n\n";
    echo "Usage:\n";
    echo "  php scripts/create_admin.php          Créer un admin\n";
    echo "  php scripts/create_admin.php --reset    Supprimer l'ancien admin et en créer un nouveau\n\n";
    echo "Identifiants par défaut:\n";
    echo "  Email: admin@erp.com\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
}

// Vérifier si l'aide est demandée
if (in_array('--help', $argv) || in_array('-h', $argv)) {
    showHelp();
    exit(0);
}

// Vérifier l'option --reset
$reset = in_array('--reset', $argv);

echo "🚀 Script de création d'admin pour ERP Pharmacy\n";
echo "========================================\n\n";

// Données de l'admin
$adminData = [
    'username' => 'admin',
    'email' => 'admin@erp.com',
    'password' => 'admin123',
    'nom' => 'Admin',
    'prenom' => 'System',
    'telephone' => '00000000',
    'role_id' => 1, // Admin
    'is_active' => 1
];

// Connexion à la base de données
$db = getDatabaseConnection();

// Vérifier la structure de la table
checkTableStructure($db);

// Vérifier si l'admin existe déjà
if (adminExists($db, $adminData['email'], $adminData['username'])) {
    echo "⚠️  Un admin avec ces identifiants existe déjà:\n";
    echo "   Email: {$adminData['email']}\n";
    echo "   Username: {$adminData['username']}\n\n";
    
    if ($reset) {
        echo "🔄 Option --reset détectée. Suppression de l'ancien admin...\n";
        deleteExistingAdmin($db, $adminData['email'], $adminData['username']);
    } else {
        echo "💡 Utilisez --reset pour supprimer l'ancien admin et en créer un nouveau\n";
        echo "   Ou modifiez les identifiants dans le script\n\n";
        exit(1);
    }
}

// Générer le hash du mot de passe
$adminData['password_hash'] = password_hash($adminData['password'], PASSWORD_DEFAULT);

echo "📝 Création de l'admin avec les données suivantes:\n";
echo "   Username: {$adminData['username']}\n";
echo "   Email: {$adminData['email']}\n";
echo "   Nom: {$adminData['nom']} {$adminData['prenom']}\n";
echo "   Téléphone: {$adminData['telephone']}\n";
echo "   Rôle: Admin (ID: {$adminData['role_id']})\n";
echo "   Password: {$adminData['password']}\n\n";

// Créer l'admin
if (createAdmin($db, $adminData)) {
    echo "✅ Admin créé avec succès!\n\n";
    echo "🔑 Identifiants de connexion:\n";
    echo "   URL: http://localhost:8000/login\n";
    echo "   Email: {$adminData['email']}\n";
    echo "   Username: {$adminData['username']}\n";
    echo "   Password: {$adminData['password']}\n\n";
    echo "🔐 Le mot de passe est hashé avec " . password_get_info($adminData['password_hash'])['algoName'] . "\n";
    echo "📅 Créé le: " . date('Y-m-d H:i:s') . "\n\n";
    echo "🎯 Vous pouvez maintenant vous connecter à l'ERP!\n";
} else {
    echo "❌ Échec de la création de l'admin\n";
    exit(1);
}
