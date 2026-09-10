<?php
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$cols = $db->query('SHOW COLUMNS FROM ecritures_comptables')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(array_column($cols, 'Field'), JSON_PRETTY_PRINT);
