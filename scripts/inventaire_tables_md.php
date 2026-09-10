<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$out = "# Inventaire base `$db` — " . count($tables) . " tables\n\n";

foreach ($tables as $table) {
    $cols = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $out .= "## `$table` (" . count($cols) . " champs)\n\n";
    $out .= "| Champ | Type | Null | Clé | Défaut | Extra |\n";
    $out .= "|-------|------|------|-----|--------|-------|\n";
    foreach ($cols as $c) {
        $out .= sprintf(
            "| `%s` | %s | %s | %s | %s | %s |\n",
            $c['Field'],
            str_replace('|', '\\|', $c['Type']),
            $c['Null'],
            $c['Key'] ?: '—',
            $c['Default'] === null ? 'NULL' : (string)$c['Default'],
            $c['Extra'] ?: '—'
        );
    }
    $out .= "\n";
}

file_put_contents(__DIR__ . '/../database/INVENTAIRE_TABLES.md', $out);
echo "OK: " . count($tables) . " tables -> database/INVENTAIRE_TABLES.md\n";
