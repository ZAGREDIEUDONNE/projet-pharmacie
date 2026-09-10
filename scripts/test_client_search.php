<?php
require __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$query = 'David';
$typeFilter = '';
$params = [];
$sql = 'SELECT * FROM clients WHERE deleted_at IS NULL';
if ($query !== '') {
    $sql .= ' AND (code LIKE ? OR nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR email LIKE ? OR matricule LIKE ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($typeFilter !== '') {
    $sql .= ' AND type_client = ?';
    $params[] = $typeFilter;
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';

$stmt = $db->prepare($sql);
$stmt->execute($params);
echo 'Search David: ' . count($stmt->fetchAll(PDO::FETCH_ASSOC)) . PHP_EOL;

$params = [];
$sql = 'SELECT * FROM clients WHERE deleted_at IS NULL AND type_client = ? ORDER BY created_at DESC LIMIT 100';
$stmt = $db->prepare($sql);
$stmt->execute(['ORDINAIRE']);
echo 'Filter ORDINAIRE: ' . count($stmt->fetchAll(PDO::FETCH_ASSOC)) . PHP_EOL;
