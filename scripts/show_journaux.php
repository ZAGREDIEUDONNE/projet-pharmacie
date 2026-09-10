<?php
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();
echo json_encode($db->query('SELECT * FROM journaux_comptables')->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
