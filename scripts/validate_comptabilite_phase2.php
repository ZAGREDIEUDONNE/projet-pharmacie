<?php

declare(strict_types=1);

/**
 * Isolated Phase-2 accounting validation. It refuses to touch an existing
 * medecin_test database and always drops the database it created.
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use App\Services\AuditService;
use App\Services\BalanceGeneraleService;
use App\Services\EcritureComptableService;
use App\Services\ExerciceComptableService;
use App\Services\JournalComptableService;
use App\Services\GrandLivreService;
use App\Services\RBACService;
use App\Services\CsrfService;

$source = Database::getConnection();
$name = 'medecin_test';
$results = [];
$test = static function (string $name, callable $assertion) use (&$results): void {
    try {
        $assertion();
        $results[] = ['scenario' => $name, 'status' => 'PASS'];
    } catch (Throwable $e) {
        $results[] = ['scenario' => $name, 'status' => 'FAIL', 'detail' => $e->getMessage()];
    }
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

if ((int)$source->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'medecin_test'")->fetchColumn() > 0) {
    fwrite(STDERR, "REFUS: medecin_test existe déjà; aucune modification effectuée.\n");
    exit(2);
}

try {
    $test('RBAC comptable réel', static function () use ($source, $assert): void {
        $roles = $source->query("SELECT u.id, r.nom FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE UPPER(r.nom) IN ('COMPTABLE','VENDEUR','ASSISTANT','CHARGE_COMMANDE') GROUP BY r.nom ORDER BY u.id")->fetchAll(PDO::FETCH_ASSOC);
        $byRole = [];
        foreach ($roles as $role) {
            $byRole[strtoupper($role['nom'])] = (int)$role['id'];
        }
        $assert(isset($byRole['COMPTABLE']), 'Utilisateur COMPTABLE de recette absent');
        $rbac = new RBACService($source);
        $assert($rbac->hasPermission($byRole['COMPTABLE'], 'comptabilite_view'), 'COMPTABLE privé de comptabilite_view');
        foreach (['VENDEUR', 'ASSISTANT', 'CHARGE_COMMANDE'] as $role) {
            $assert(isset($byRole[$role]) && !$rbac->hasPermission($byRole[$role], 'comptabilite_view'), "{$role} possède un accès comptable inattendu");
        }
    });
    $test('CSRF existant', static function () use ($assert): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_save_path(sys_get_temp_dir());
            session_start();
        }
        $token = CsrfService::token();
        $assert(CsrfService::isValid($token), 'Token CSRF valide refusé');
        $assert(!CsrfService::isValid('invalid'), 'Token CSRF invalide accepté');
    });
    $source->exec("CREATE DATABASE `medecin_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO('mysql:host=localhost;port=3306;dbname=medecin_test;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['plan_comptable', 'classes_comptes', 'journaux_comptables', 'ecritures_comptables', 'lignes_ecritures', 'exercices_comptables', 'audit_logs', 'system_errors', 'utilisateurs', 'clients', 'fournisseurs'] as $table) {
        $db->exec("CREATE TABLE `{$table}` LIKE `medecin`.`{$table}`");
    }
    $db->exec("INSERT INTO exercices_comptables (exercice, date_debut, date_fin, statut) VALUES ('RECETTE-2026', '2026-01-01', '2026-12-31', 'ouvert')");
    $db->exec("INSERT INTO classes_comptes (code, libelle, is_actif) VALUES ('4','Tiers',1),('5','Trésorerie',1),('7','Produits',1)");
    $accounts = [
        ['411', 'Clients', 4, 'ACTIF'], ['571', 'Caisse', 5, 'ACTIF'],
        ['701', 'Ventes', 7, 'PRODUIT'], ['44571', 'TVA collectée', 4, 'PASSIF']
    ];
    $insert = $db->prepare('INSERT INTO plan_comptable (numero_compte, code, nom_compte, libelle, classe, classe_id, type_compte, type, is_actif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
    foreach ($accounts as [$code, $label, $classe, $type]) {
        $insert->execute([$code, $code, $label, $label, $classe, (string)$classe, $type, $type]);
    }
    $db->exec("INSERT INTO journaux_comptables (code, libelle, type_journal, is_actif) VALUES ('VT','Ventes','VENTES',1),('CA','Caisse','CAISSE',1)");

    $audit = new AuditService($db);
    $journal = new JournalComptableService($db, $audit);
    $ecritures = new EcritureComptableService($db, $audit, $journal);

    $test('moteurs InnoDB', static function () use ($db, $assert): void {
        $engines = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('plan_comptable','classes_comptes','journaux_comptables','ecritures_comptables','lignes_ecritures','exercices_comptables') AND engine <> 'InnoDB'")->fetchColumn();
        $assert((int)$engines === 0, 'Table non InnoDB détectée');
    });
    $test('vente comptant TVA équilibrée', static function () use ($ecritures, $db, $assert): void {
        $id = $ecritures->enregistrerEcriture(['journal_code' => 'VT', 'libelle' => 'Vente recette', 'reference_type' => 'VENTE', 'reference_id' => 1, 'utilisateur_id' => 1, 'date_ecriture' => '2026-06-15 10:00:00', 'lignes' => [
            ['compte_code' => '571', 'debit' => 100, 'credit' => 0], ['compte_code' => '701', 'debit' => 0, 'credit' => 84.75], ['compte_code' => '44571', 'debit' => 0, 'credit' => 15.25],
        ]]);
        $row = $db->query("SELECT total_debit,total_credit FROM ecritures_comptables WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
        $assert((float)$row['total_debit'] === 100.0 && (float)$row['total_credit'] === 100.0, 'Écriture de vente non équilibrée');
    });
    $test('écriture déséquilibrée refusée et rollback', static function () use ($ecritures, $db, $assert): void {
        $before = (int)$db->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn();
        try {
            $ecritures->enregistrerEcriture(['journal_code' => 'VT', 'libelle' => 'Erreur', 'reference_type' => 'TEST', 'reference_id' => 2, 'utilisateur_id' => 1, 'date_ecriture' => '2026-06-15', 'lignes' => [['compte_code' => '571', 'debit' => 10, 'credit' => 0], ['compte_code' => '701', 'debit' => 0, 'credit' => 9]]]);
            throw new RuntimeException('Déséquilibre accepté');
        } catch (Throwable) {
            $assert((int)$db->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn() === $before, 'Rollback absent');
        }
    });
    $test('contre-passation et seconde tentative refusée', static function () use ($ecritures, $db, $assert): void {
        $ecritures->annulerEcritureVente(1, 1);
        $inverse = $db->query("SELECT * FROM ecritures_comptables WHERE reference_type = 'ANNULATION_VENTE' AND reference_id = 1")->fetch(PDO::FETCH_ASSOC);
        $assert($inverse && (int)$inverse['ecriture_origine_id'] > 0, 'Contre-passation absente');
        try {
            $ecritures->annulerEcritureVente(1, 1);
            throw new RuntimeException('Double contre-passation acceptée');
        } catch (Throwable $e) {
            $assert(!str_contains($e->getMessage(), 'acceptée'), 'Double contre-passation acceptée');
        }
    });
    $test('restitutions excluent écriture contre-passée', static function () use ($db, $audit, $assert): void {
        $balance = new BalanceGeneraleService($db, $audit);
        $grandLivre = new GrandLivreService($db, $audit);
        $ligneCaisse = array_values(array_filter(
            $balance->genererBalance('2026-12-31')['lignes_balance'],
            static fn (array $ligne): bool => $ligne['compte_code'] === '571'
        ))[0] ?? null;
        $assert($ligneCaisse === null, 'La balance inclut une écriture contre-passée');
        $assert($grandLivre->getGrandLivreCompte('571', '2026-01-01', '2026-12-31')['nombre_ecritures'] === 0, 'Le grand livre inclut une écriture contre-passée');
    });
    $test('exercice clôturé refuse une écriture', static function () use ($db, $ecritures, $assert): void {
        $db->exec("UPDATE exercices_comptables SET statut = 'cloture'");
        try {
            $ecritures->enregistrerEcriture(['journal_code' => 'CA', 'libelle' => 'Interdit', 'reference_type' => 'TEST', 'reference_id' => 3, 'utilisateur_id' => 1, 'date_ecriture' => '2026-06-16', 'lignes' => [['compte_code' => '571', 'debit' => 1, 'credit' => 0], ['compte_code' => '701', 'debit' => 0, 'credit' => 1]]]);
            throw new RuntimeException('Écriture clôturée acceptée');
        } catch (Throwable $e) {
            $assert(!str_contains($e->getMessage(), 'acceptée'), 'Écriture clôturée acceptée');
        }
    });
} finally {
    if (isset($source)) {
        $source->exec("DROP DATABASE IF EXISTS `medecin_test`");
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(in_array('FAIL', array_column($results, 'status'), true) ? 1 : 0);
