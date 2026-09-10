<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query('SELECT * FROM permissions ORDER BY name');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . ' - ' . $row['description'] . ' - ' . $row['module'] . PHP_EOL;
}
