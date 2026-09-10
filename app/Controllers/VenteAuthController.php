<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Compatibility controller for legacy /vente/login routes.
 *
 * The application now uses the global authentication session for every module.
 * This controller must never create or rewrite a sale-specific user session,
 * otherwise an admin or assistant can be silently converted to a seller.
 */
class VenteAuthController extends BaseController
{
    public function login(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirectBack('/vente');
            return;
        }

        $this->redirect('/login');
    }

    public function authenticate(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirectBack('/vente');
            return;
        }

        $this->redirect('/login');
    }

    public function logout(): void
    {
        $this->redirect('/logout');
    }
}
