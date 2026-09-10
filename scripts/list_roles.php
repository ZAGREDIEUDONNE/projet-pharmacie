<?php
require __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();
echo json_encode($pdo->query('SELECT id, code, nom FROM roles')->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
