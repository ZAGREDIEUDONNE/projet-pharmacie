<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Controller pour la page d'accueil
 */
class HomeController extends BaseController
{
    /**
     * Page d'accueil principale
     */
    public function index(): void
    {
        // Si l'utilisateur est connecté, rediriger selon son rôle
        if ($this->isLoggedIn()) {
            $this->redirect($this->getDefaultDashboardForRole());
            return;
        }

        // Sinon afficher la page d'accueil publique
        $this->render('home/index', [
            'title' => 'ERP Pharmacy - Accueil'
        ]);
    }
}
