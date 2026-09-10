<?php

require 'config/database.php';

$pdo = new PDO('mysql:host=localhost;dbname=medecin', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
try {
    foreach ([
        ['ordonnance.view', 'Consulter les ordonnances'],
        ['ordonnance.process', 'Préparer une vente à partir d une ordonnance'],
    ] as [$nom, $description]) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO permissions (nom, description, module) VALUES (?, ?, ?)');
        $stmt->execute([$nom, $description, 'ordonnances']);
    }
    $pdo->exec("INSERT INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p ON p.nom IN ('ordonnance.view', 'ordonnance.process')
                WHERE LOWER(r.nom) = 'vendeur' AND NOT EXISTS
                (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id)");
    $pdo->commit();
    echo "Migration 029 exécutée.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Erreur: {$e->getMessage()}\n");
    exit(1);
}
