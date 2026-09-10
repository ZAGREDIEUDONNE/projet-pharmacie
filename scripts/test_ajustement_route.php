<?php
session_start();
$_SESSION['user'] = [
    'id' => 1,
    'username' => 'test',
    'role_id' => 4,
    'role_code' => 'CHARGE_COMMANDE',
    'role_name' => 'charge_commande',
];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/stock/ajustement';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET['return_to'] = '/commande/dashboard';

ob_start();
try {
    require __DIR__ . '/../public/index.php';
    $out = ob_get_clean();
    echo "OK len=" . strlen($out) . PHP_EOL;
    echo substr($out, 0, 200) . PHP_EOL;
} catch (Throwable $e) {
    ob_end_clean();
    echo 'FATAL: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
