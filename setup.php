<?php

/**
 * Script d'installation et de configuration de la base de données
 */

require_once __DIR__ . '/config/database.php';

// Activer l'affichage des erreurs pour le setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Pharmacie ERP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .step {
            margin-bottom: 30px;
            padding: 20px;
            border-left: 4px solid #3498db;
            background-color: #ecf0f1;
        }
        .success {
            border-left-color: #27ae60;
            background-color: #d5f4e6;
        }
        .error {
            border-left-color: #e74c3c;
            background-color: #fadbd8;
        }
        .warning {
            border-left-color: #f39c12;
            background-color: #fef9e7;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #2980b9;
        }
        .button-success {
            background-color: #27ae60;
        }
        .button-success:hover {
            background-color: #229954;
        }
        .button-warning {
            background-color: #f39c12;
        }
        .button-warning:hover {
            background-color: #e67e22;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .progress {
            width: 100%;
            height: 20px;
            background-color: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-bar {
            height: 100%;
            background-color: #3498db;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 Installation - Pharmacie ERP</h1>

        <?php
        $step = $_GET['step'] ?? 'welcome';
        $error = '';
        $success = '';

        switch ($step) {
            case 'welcome':
                ?>
                <div class="step">
                    <h2>Bienvenue dans l'assistant d'installation</h2>
                    <p>Cet assistant va vous guider dans la configuration de votre base de données pour le système de gestion de pharmacie.</p>
                    <p><strong>Prérequis :</strong></p>
                    <ul>
                        <li>PHP 8.0 ou supérieur</li>
                        <li>MySQL/MariaDB 5.7 ou supérieur</li>
                        <li>Extensions PHP : PDO, PDO_MYSQL, mbstring</li>
                    </ul>
                    <form method="get" action="">
                        <input type="hidden" name="step" value="check">
                        <button type="submit">Commencer l'installation →</button>
                    </form>
                </div>
                <?php
                break;

            case 'check':
                ?>
                <div class="step">
                    <h2>Vérification des prérequis</h2>
                    <?php
                    $checks = [];
                    
                    // Vérification PHP
                    $checks['php_version'] = version_compare(PHP_VERSION, '8.0.0', '>=');
                    $checks['pdo'] = extension_loaded('pdo');
                    $checks['pdo_mysql'] = extension_loaded('pdo_mysql');
                    $checks['mbstring'] = extension_loaded('mbstring');
                    
                    foreach ($checks as $check => $result) {
                        $class = $result ? 'success' : 'error';
                        $icon = $result ? '✅' : '❌';
                        echo "<div class='step $class'>$icon " . ucfirst(str_replace('_', ' ', $check)) . "</div>";
                    }
                    
                    $allChecksPass = array_reduce($checks, fn($carry, $item) => $carry && $item, true);
                    
                    if ($allChecksPass) {
                        ?>
                        <form method="get" action="">
                            <input type="hidden" name="step" value="config">
                            <button type="submit" class="button-success">Continuer →</button>
                        </form>
                        <?php
                    } else {
                        echo "<div class='step error'>❌ Certains prérequis ne sont pas satisfaits. Veuillez les corriger avant de continuer.</div>";
                    }
                    ?>
                </div>
                <?php
                break;

            case 'config':
                ?>
                <div class="step">
                    <h2>Configuration de la base de données</h2>
                    <form method="post" action="">
                        <input type="hidden" name="step" value="test">
                        
                        <div class="form-group">
                            <label for="host">Hôte de la base de données :</label>
                            <input type="text" id="host" name="host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="port">Port :</label>
                            <input type="number" id="port" name="port" value="3306" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="database">Nom de la base de données :</label>
                            <input type="text" id="database" name="database" value="medecin" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Nom d'utilisateur :</label>
                            <input type="text" id="username" name="username" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Mot de passe :</label>
                            <input type="password" id="password" name="password">
                        </div>
                        
                        <button type="submit">Tester la connexion →</button>
                    </form>
                </div>
                <?php
                break;

            case 'test':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $host = $_POST['host'];
                    $port = $_POST['port'];
                    $database = $_POST['database'];
                    $username = $_POST['username'];
                    $password = $_POST['password'];
                    
                    // Test de connexion
                    try {
                        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
                        $pdo = new PDO($dsn, $username, $password, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                        
                        echo "<div class='step success'>✅ Connexion à la base de données réussie !</div>";
                        
                        // Vérification des tables
                        $tablesCheck = Database::checkTables();
                        
                        if ($tablesCheck['success']) {
                            echo "<div class='step success'>✅ Les tables requises existent déjà.</div>";
                            echo "<form method='get' action=''>
                                <input type='hidden' name='step' value='complete'>
                                <button type='submit' class='button-success'>Terminer l'installation →</button>
                            </form>";
                        } else {
                            echo "<div class='step warning'>⚠️ Les tables n'existent pas encore. Elles vont être créées.</div>";
                            echo "<form method='post' action=''>
                                <input type='hidden' name='step' value='install'>
                                <input type='hidden' name='host' value='$host'>
                                <input type='hidden' name='port' value='$port'>
                                <input type='hidden' name='database' value='$database'>
                                <input type='hidden' name='username' value='$username'>
                                <input type='hidden' name='password' value='$password'>
                                <button type='submit' class='button-warning'>Créer les tables →</button>
                            </form>";
                        }
                        
                    } catch (Exception $e) {
                        echo "<div class='step error'>❌ Erreur de connexion : " . htmlspecialchars($e->getMessage()) . "</div>";
                        echo "<form method='get' action=''>
                            <input type='hidden' name='step' value='config'>
                            <button type='submit'>← Retour</button>
                        </form>";
                    }
                }
                break;

            case 'install':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Mettre à jour les variables d'environnement
                    $_ENV['DB_HOST'] = $_POST['host'];
                    $_ENV['DB_PORT'] = $_POST['port'];
                    $_ENV['DB_DATABASE'] = $_POST['database'];
                    $_ENV['DB_USERNAME'] = $_POST['username'];
                    $_ENV['DB_PASSWORD'] = $_POST['password'];
                    
                    // Créer le fichier .env
                    $envContent = "DB_HOST={$_POST['host']}\n";
                    $envContent .= "DB_PORT={$_POST['port']}\n";
                    $envContent .= "DB_DATABASE={$_POST['database']}\n";
                    $envContent .= "DB_USERNAME={$_POST['username']}\n";
                    $envContent .= "DB_PASSWORD={$_POST['password']}\n";
                    
                    file_put_contents(__DIR__ . '/.env', $envContent);
                    
                    // Créer les tables
                    $result = Database::createTables();
                    
                    if ($result['success']) {
                        echo "<div class='step success'>✅ Tables créées avec succès !</div>";
                        echo "<div class='step'>📊 {$result['queries_executed']} requêtes exécutées</div>";
                        
                        // Insérer les données de base
                        $this->insertDefaultData();
                        
                        echo "<form method='get' action=''>
                            <input type='hidden' name='step' value='complete'>
                            <button type='submit' class='button-success'>Terminer l'installation →</button>
                        </form>";
                    } else {
                        echo "<div class='step error'>❌ Erreur lors de la création des tables : " . htmlspecialchars($result['message']) . "</div>";
                    }
                }
                break;

            case 'complete':
                ?>
                <div class="step success">
                    <h2>🎉 Installation terminée avec succès !</h2>
                    <p>Le système de gestion de pharmacie est maintenant installé et prêt à être utilisé.</p>
                    
                    <h3>Prochaines étapes :</h3>
                    <ol>
                        <li><a href="login.php">Se connecter avec les identifiants par défaut</a></li>
                        <li>Configurer les utilisateurs et les rôles</li>
                        <li>Ajouter les produits et les fournisseurs</li>
                        <li>Configurer les paramètres de la caisse</li>
                    </ol>
                    
                    <h3>Identifiants par défaut :</h3>
                    <pre>
Administrateur : admin / admin123
Pharmacien   : pharmacien / pharma123
Caissier     : caissier / caisse123
                    </pre>
                    
                    <div class="warning">
                        <p><strong>⚠️ Important :</strong> Pensez à changer les mots de passe par défaut après la première connexion.</p>
                    </div>
                    
                    <form method="get" action="login.php">
                        <button type="submit" class="button-success">Accéder à l'application →</button>
                    </form>
                </div>
                <?php
                break;

            default:
                header('Location: ?step=welcome');
                break;
        }

        /**
         * Insère les données par défaut
         */
        function insertDefaultData() {
            try {
                $pdo = Database::getConnection();
                
                // Rôles par défaut
                $roles = [
                    ['administrateur', 'Administrateur système'],
                    ['vendeur', 'Vendeur / caissier'],
                    ['assistant', 'Assistant pharmacie'],
                    ['charge_commande', 'Chargé de commandes'],
                ];
                
                foreach ($roles as $role) {
                    $stmt = $pdo->prepare("INSERT INTO roles (nom, description) VALUES (?, ?)");
                    $stmt->execute($role);
                }
                
                // Permissions par défaut
                $permissions = [
                    ['dashboard_view', 'Voir le dashboard', 'dashboard'],
                    ['ventes_create', 'Créer des ventes', 'ventes'],
                    ['ventes_view', 'Voir les ventes', 'ventes'],
                    ['stock_manage', 'Gérer le stock', 'stock'],
                    ['clients_manage', 'Gérer les clients', 'clients'],
                    ['caisse_manage', 'Gérer la caisse', 'caisse'],
                    ['comptabilite_view', 'Voir la comptabilité', 'comptabilite'],
                    ['system_admin', 'Administration système', 'system']
                ];
                
                foreach ($permissions as $permission) {
                    $stmt = $pdo->prepare("INSERT INTO permissions (nom, description, module) VALUES (?, ?, ?)");
                    $stmt->execute($permission);
                }
                
                // Utilisateurs par défaut
                $users = [
                    ['admin', 'admin@admin.com', 'Administrateur', 'System', 1, 'admin123'],
                    ['pharmacien', 'pharma@pharma.com', 'Pharmacien', 'Responsable', 2, 'pharma123'],
                    ['caissier', 'caisse@caisse.com', 'Caissier', 'Principal', 3, 'caisse123']
                ];
                
                foreach ($users as $user) {
                    $hashedPassword = password_hash($user[5], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO utilisateurs (username, email, nom, prenom, role_id, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user[0], $user[1], $user[2], $user[3], $user[4], $hashedPassword]);
                }
                
                echo "<div class='step success'>✅ Données par défaut insérées</div>";
                
            } catch (Exception $e) {
                echo "<div class='step warning'>⚠️ Erreur lors de l'insertion des données par défaut : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        ?>

        <?php if (!empty($error)): ?>
            <div class="step error">
                <strong>Erreur :</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="step success">
                <strong>Succès :</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
