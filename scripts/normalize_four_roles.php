<?php

/**
 * Normalise les rôles : 4 profils uniquement (admin, vendeur, assistant, commande).
 * Usage: php scripts/normalize_four_roles.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/RoleCatalog.php';

use App\Services\RoleCatalog;

$pdo = Database::getConnection();
$steps = [];

function tableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
}

function hasColumn(array $columns, string $name): bool
{
    return in_array($name, $columns, true);
}

try {
    $roleColumns = tableColumns($pdo, 'roles');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('SET SQL_SAFE_UPDATES=0');

    $mapSql = "UPDATE utilisateurs u
        INNER JOIN roles r ON r.id = u.role_id
        SET u.role_id = CASE
            WHEN LOWER(COALESCE(r.nom, '')) IN ('admin', 'administrateur')
                 OR UPPER(COALESCE(r.code, '')) IN ('ADMIN', 'ADMINISTRATEUR') THEN 1
            WHEN LOWER(COALESCE(r.nom, '')) = 'vendeur'
                 OR UPPER(COALESCE(r.code, '')) = 'VENDEUR' THEN 2
            WHEN LOWER(COALESCE(r.nom, '')) = 'assistant'
                 OR UPPER(COALESCE(r.code, '')) = 'ASSISTANT' THEN 3
            WHEN LOWER(COALESCE(r.nom, '')) IN ('charge_commande', 'charge de commande', 'commande')
                 OR UPPER(COALESCE(r.code, '')) IN ('CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE', 'COMMANDE') THEN 4
            WHEN LOWER(COALESCE(r.nom, '')) = 'pharmacien'
                 OR UPPER(COALESCE(r.code, '')) = 'PHARMACIEN' THEN 4
            ELSE 2
        END";
    $pdo->exec($mapSql);
    $steps[] = 'Utilisateurs réassignés aux 4 rôles canoniques';

    $pdo->exec('DELETE FROM role_permissions');
    $pdo->exec('DELETE FROM roles');
    $steps[] = 'Anciens rôles supprimés';

    $roles = RoleCatalog::all();
    foreach ($roles as $id => $role) {
        $fields = ['id'];
        $placeholders = [':id'];
        $params = ['id' => $id];

        if (hasColumn($roleColumns, 'nom')) {
            $fields[] = 'nom';
            $placeholders[] = ':nom';
            $params['nom'] = $role['nom'];
        }
        if (hasColumn($roleColumns, 'code')) {
            $fields[] = 'code';
            $placeholders[] = ':code';
            $params['code'] = $role['code'];
        }
        if (hasColumn($roleColumns, 'libelle')) {
            $fields[] = 'libelle';
            $placeholders[] = ':libelle';
            $params['libelle'] = $role['label'];
        }
        if (hasColumn($roleColumns, 'name')) {
            $fields[] = 'name';
            $placeholders[] = ':name';
            $params['name'] = $role['label'];
        }
        if (hasColumn($roleColumns, 'description')) {
            $fields[] = 'description';
            $placeholders[] = ':description';
            $descriptions = [
                1 => 'Accès complet système et administration',
                2 => 'Point de vente et caisse',
                3 => 'Vente avancée, stock et actions métier',
                4 => 'Stock, commandes fournisseurs et réceptions',
            ];
            $params['description'] = $descriptions[$id] ?? $role['label'];
        }
        if (hasColumn($roleColumns, 'is_actif')) {
            $fields[] = 'is_actif';
            $placeholders[] = ':is_actif';
            $params['is_actif'] = 1;
        }
        if (hasColumn($roleColumns, 'statut')) {
            $fields[] = 'statut';
            $placeholders[] = ':statut';
            $params['statut'] = 1;
        }
        if (hasColumn($roleColumns, 'level')) {
            $fields[] = 'level';
            $placeholders[] = ':level';
            $params['level'] = $id;
        }
        if (hasColumn($roleColumns, 'created_at')) {
            $fields[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        if (hasColumn($roleColumns, 'updated_at')) {
            $fields[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }
        if (hasColumn($roleColumns, 'date_creation')) {
            $fields[] = 'date_creation';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO roles (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    $steps[] = '4 rôles insérés (admin, vendeur, assistant, commande)';

    if (in_array('role_discount_limits', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), true)) {
        foreach ($roles as $id => $role) {
            $percent = match ($id) {
                RoleCatalog::ADMIN_ID => 25.0,
                RoleCatalog::ASSISTANT_ID => 15.0,
                RoleCatalog::VENDEUR_ID => 10.0,
                default => 0.0,
            };
            $pdo->prepare(
                'INSERT INTO role_discount_limits (role_id, max_discount_percent)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE max_discount_percent = VALUES(max_discount_percent)'
            )->execute([$id, $percent]);
        }
        $steps[] = 'Plafonds remise alignés';
    }

    $demoUsers = [
        ['username' => 'admin', 'email' => 'admin@pharmacie.local', 'nom' => 'Admin', 'prenom' => 'Systeme', 'role_id' => 1],
        ['username' => 'vendeur', 'email' => 'vendeur@pharmacie.local', 'nom' => 'Vendeur', 'prenom' => 'Demo', 'role_id' => 2],
        ['username' => 'assistant', 'email' => 'assistant@pharmacie.local', 'nom' => 'Assistant', 'prenom' => 'Demo', 'role_id' => 3],
        ['username' => 'commande', 'email' => 'commande@pharmacie.local', 'nom' => 'Commande', 'prenom' => 'Demo', 'role_id' => 4],
    ];
    $passwordHash = password_hash('demo1234', PASSWORD_DEFAULT);
    foreach ($demoUsers as $demo) {
        $check = $pdo->prepare('SELECT id FROM utilisateurs WHERE username = ? LIMIT 1');
        $check->execute([$demo['username']]);
        if ($check->fetchColumn()) {
            $pdo->prepare('UPDATE utilisateurs SET role_id = ? WHERE username = ?')->execute([$demo['role_id'], $demo['username']]);
            continue;
        }
        $pdo->prepare(
            'INSERT INTO utilisateurs (username, email, password_hash, nom, prenom, role_id, is_active, date_creation)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
        )->execute([
            $demo['username'],
            $demo['email'],
            $passwordHash,
            $demo['nom'],
            $demo['prenom'],
            $demo['role_id'],
        ]);
    }
    $steps[] = 'Comptes démo vérifiés (admin, vendeur, assistant, commande / mot de passe demo1234)';

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('SET SQL_SAFE_UPDATES=1');

    echo json_encode(['success' => true, 'steps' => $steps, 'roles' => array_keys($roles)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->exec('SET SQL_SAFE_UPDATES=1');
    } catch (Throwable $ignored) {
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'steps' => $steps], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
