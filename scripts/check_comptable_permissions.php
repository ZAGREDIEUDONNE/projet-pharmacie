<?php
$pdo = new PDO('mysql:host=localhost;dbname=medecin;charset=utf8mb4', 'root', '');
// First check the structure of roles table
$stmt = $pdo->query('DESCRIBE roles');
echo 'Structure table roles:'.PHP_EOL;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '  '.$row['Field'].PHP_EOL;
}
echo PHP_EOL;
// Now get the role
$stmt = $pdo->query('SELECT * FROM roles WHERE LOWER(nom) = "comptable" OR LOWER(code) = "comptable"');
$role = $stmt->fetch(PDO::FETCH_ASSOC);
if($role){
    echo 'Role trouvé: '.json_encode($role).PHP_EOL;
    $roleId = $role['id'] ?? null;
    if($roleId){
        $stmt = $pdo->prepare('SELECT p.nom as permission_nom, p.description FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = ?');
        $stmt->execute([$roleId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo 'Permissions ('.count($permissions).'):'.PHP_EOL;
        foreach($permissions as $p){
            echo '  - '.$p['permission_nom'].': '.$p['description'].PHP_EOL;
        }
    }
}else{
    echo 'Role COMPTABLE non trouvé'.PHP_EOL;
}
