<?php
require_once __DIR__ . '/../config/database.php';
$db = db();
echo "ALL PERMISSIONS:\n";
foreach ($db->query('SELECT id, nom, module FROM permissions ORDER BY nom') as $r) {
    echo $r['id'] . ' | ' . $r['nom'] . ' | ' . $r['module'] . "\n";
}
