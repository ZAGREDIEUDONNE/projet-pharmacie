<?php
require_once __DIR__ . '/../config/database.php';
$db = db();
foreach ($db->query('DESCRIBE permissions') as $r) {
    echo $r['Field'] . ' | ' . $r['Type'] . "\n";
}
