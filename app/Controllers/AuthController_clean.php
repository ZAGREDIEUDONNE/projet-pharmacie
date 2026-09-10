<?php

namespace App\Controllers;

use App\Core\BaseController;
use PDO;
use Exception;

class AuthController extends BaseController
{
    /**
     * Affiche la page de login
     */
    public function login(): void
    {
        // Si déjà connecté, rediriger selon le rôle
        if ($this->isLoggedIn()) {
            $this->redirectByRole();
            return;
        }

        $this->render('auth/login', [
            'title' => 'Connexion - ERP Pharmacy'
        ]);
    }

    /**
     * Traite la tentative de connexion
     */
    public function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        // Récupérer POST : login + password
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validation simple
        if (empty($login) || empty($password)) {
            $this->render('auth/login', [
                'error' => 'Veuillez remplir tous les champs'
            ]);
            return;
        }

        try {
            // Chercher utilisateur avec email ou username
            $stmt = $this->db->prepare("SELECT * FROM utilisateurs WHERE email = ? OR username = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            // Vérifier utilisateur existe et is_active = 1
            if (!$user || $user['is_active'] != 1) {
                $this->render('auth/login', [
                    'error' => 'Identifiants invalides'
                ]);
                return;
            }

            // Vérifier password avec password_verify
            if (!password_verify($password, $user['password'])) {
                $this->render('auth/login', [
                    'error' => 'Identifiants invalides'
                ]);
                return;
            }

            // Si OK : créer session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role_id' => $user['role_id']
            ];

            // Rediriger selon rôle : ADMIN → /admin/dashboard
            $this->redirect('/dashboard');

        } catch (Exception $e) {
            error_log("AuthController::authenticate - " . $e->getMessage());
            $this->render('auth/login', [
                'error' => 'Erreur lors de la connexion'
            ]);
        }
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        // Détruire la session
        session_destroy();
        
        // Rediriger vers login
        $this->redirect('/login');
    }
}
