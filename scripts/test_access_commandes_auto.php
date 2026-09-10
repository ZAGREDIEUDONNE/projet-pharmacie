<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

// Simule la session utilisateur KAMBOU (role commande, id 4)
$_SESSION['user'] = [
    'id' => 6,
    'username' => 'KAMBOU',
    'role_id' => 4,
    'role_code' => 'COMMANDE',
    'role_name' => 'commande',
];

$controller = new class extends App\Core\BaseController {
    public function test(): bool
    {
        try {
            $this->requirePermission('create_supplier_orders');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
};

// requirePermission calls exit on failure - capture via output buffering won't work for exit
// Test via reflection on hasDefaultRolePermission instead
$ref = new ReflectionClass(App\Core\BaseController::class);
$method = $ref->getMethod('hasDefaultRolePermission');
$method->setAccessible(true);
$instance = new App\Core\BaseController(Database::getConnection());
$ok = $method->invoke($instance, 4, 'create_supplier_orders', 'COMMANDE');
echo 'KAMBOU (role 4 COMMANDE) create_supplier_orders: ' . ($ok ? 'OK' : 'DENIED') . PHP_EOL;

$okAdmin = $method->invoke($instance, 1, 'create_supplier_orders', 'ADMIN');
echo 'Admin create_supplier_orders: ' . ($okAdmin ? 'OK' : 'DENIED') . PHP_EOL;

$policy = new App\Services\ChargeCommandePolicy();
echo 'Policy create_supplier_orders: ' . ($policy->can('create_supplier_orders') ? 'OK' : 'DENIED') . PHP_EOL;
