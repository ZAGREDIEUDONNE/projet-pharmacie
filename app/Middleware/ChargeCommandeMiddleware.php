<?php

namespace App\Middleware;

use App\Services\RBACService;
use App\Services\ChargeCommandePolicy;

class ChargeCommandeMiddleware
{
    private RBACService $rbacService;
    private ChargeCommandePolicy $policy;

    public function __construct(RBACService $rbacService, ?ChargeCommandePolicy $policy = null)
    {
        $this->rbacService = $rbacService;
        $this->policy = $policy ?? new ChargeCommandePolicy();
    }

    public function requirePermission(string $permission): void
    {
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !$this->policy->can($permission) || !$this->rbacService->hasPermission($userId, $permission)) {
            http_response_code(403);
            echo 'Acces refuse';
            exit;
        }
    }
}
