<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Redirection vers le dashboard propre à chaque rôle.
 */
class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->redirect($this->getDefaultDashboardForRole());
    }
}
