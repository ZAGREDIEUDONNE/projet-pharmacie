<?php

namespace App\Controllers;

use App\Core\BaseController;
use Exception;

class AuthController extends BaseController
{
    /**
     * Affiche la page de login
     */
    public function login(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect($this->getDefaultDashboardForRole());
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
        if (!$this->db) {
            $this->render('auth/login', [
                'error' => 'Connexion a la base de donnees indisponible'
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $this->render('auth/login', [
                'error' => 'Veuillez remplir tous les champs'
            ]);
            return;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.nom AS role_name, r.code AS role_code_db
                FROM utilisateurs u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.email = ? OR u.username = ?
            ");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if (!$user || $user['is_active'] != 1) {
                $this->render('auth/login', [
                    'error' => 'Identifiants invalides ou compte désactivé'
                ]);
                return;
            }

            if (!password_verify($password, $user['password_hash'])) {
                $this->render('auth/login', [
                    'error' => 'Identifiants invalides'
                ]);
                return;
            }

            $roleCode = $this->normalizeRole((string)($user['role_code_db'] ?? $user['role_code'] ?? $user['role_name'] ?? ''));

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'role_id' => $user['role_id'],
                'role_code' => $roleCode,
                'role_name' => $user['role_name'] ?? 'Inconnu'
            ];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            $this->redirect($this->getDefaultDashboardForRole());
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
        session_destroy();
        $this->redirect('/login');
    }
}
