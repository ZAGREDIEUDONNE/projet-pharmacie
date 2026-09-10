<?php
require __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();
echo json_encode($pdo->query('SELECT u.id, u.username, u.role_id, r.nom AS role_name FROM utilisateurs u JOIN roles r ON r.id = u.role_id')->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
