<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="login-card rounded-lg shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <i class="fas fa-pills text-4xl text-purple-600 mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-800">Pharmacie ERP JDS</h1>
            <p class="text-gray-600">Connectez-vous à votre espace</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login/auth" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
            
            <div>
                <label for="login" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2"></i>Email ou Nom d'utilisateur
                </label>
                <input 
                    type="text" 
                    id="login" 
                    name="login" 
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition duration-200"
                    placeholder="Entrez votre email ou nom d'utilisateur"
                    value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2"></i>Mot de passe
                </label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition duration-200 pr-12"
                        placeholder="Entrez votre mot de passe"
                    >
                    <button 
                        type="button" 
                        onclick="togglePassword()"
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <i class="fas fa-eye" id="passwordToggle"></i>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" class="mr-2 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <span class="text-sm text-gray-600">Se souvenir de moi</span>
                </label>
                <a href="#" class="text-sm text-purple-600 hover:text-purple-800">Mot de passe oublié?</a>
            </div>

            <button 
                type="submit" 
                class="w-full bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700 transition duration-200 transform hover:scale-105"
            >
                <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Pas encore de compte? 
                <a href="https://wa.me/qr/L6YPMELIQEXNP1" class="text-purple-600 hover:text-purple-800 font-semibold">Contactez l'administrateur</a>
            </p>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="text-center text-xs text-gray-500">
                <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> - JED DIGITAL SOLUTIONS</p>
                <p class="mt-1">Version 1.0.0</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Auto-focus sur le premier champ
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('login').focus();
        });

        // Validation simple du formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('login').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs');
                return false;
            }

            if (username.length < 3) {
                e.preventDefault();
                alert('L\'identifiant doit contenir au moins 3 caractères');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères');
                return false;
            }
        });
    </script>
</body>
</html>
