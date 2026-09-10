<?php
$pdo = new PDO('mysql:host=localhost;dbname=medecin;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT u.id, u.username, u.email, u.is_active, r.nom as role_nom, r.code as role_code FROM utilisateurs u LEFT JOIN roles r ON u.role_id = r.id WHERE LOWER(r.nom) = "comptable" OR LOWER(r.code) = "comptable"');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($users as $u) {
    echo 'ID: '.$u['id'].', Username: '.$u['username'].', Email: '.$u['email'].', Actif: '.$u['is_active'].', Role: '.$u['role_nom'].', Code: '.$u['role_code'].PHP_EOL;
}
