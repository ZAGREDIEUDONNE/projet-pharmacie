<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Connexion Module Vente' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body class="login-bg">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo et titre -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-green-600">
                    <i class="fas fa-cash-register text-white text-xl"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Module Vente
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Veuillez vous identifier pour accéder au système
                </p>
            </div>

            <!-- Formulaire de connexion -->
            <div class="login-card rounded-lg shadow-xl p-8">
                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                            <div>
                                <h4 class="text-red-800 font-semibold">Erreur de connexion</h4>
                                <ul class="text-red-700 mt-2">
                                    <?php foreach ($_SESSION['errors'] as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-600 mr-3"></i>
                            <div>
                                <h4 class="text-green-800 font-semibold">Succès</h4>
                                <p class="text-green-700 mt-2"><?= htmlspecialchars($_SESSION['success']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <form method="POST" action="/vente/login/auth" class="space-y-6">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">

                    <!-- Username/Email -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-user mr-2"></i>Nom d'utilisateur ou Email
                        </label>
                        <div class="mt-1">
                            <input id="username" 
                                   name="username" 
                                   type="text" 
                                   required
                                   value="<?= htmlspecialchars($_SESSION['old_input']['username'] ?? '') ?>"
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                   placeholder="Entrez votre nom d'utilisateur ou email">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-lock mr-2"></i>Mot de passe
                        </label>
                        <div class="mt-1">
                            <input id="password" 
                                   name="password" 
                                   type="password" 
                                   required
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                   placeholder="Entrez votre mot de passe">
                        </div>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" 
                                   name="remember" 
                                   type="checkbox" 
                                   class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-900">
                                Se souvenir de moi
                            </label>
                        </div>
                        <div class="text-sm">
                            <a href="/login" class="font-medium text-green-600 hover:text-green-500">
                                Retour login principal
                            </a>
                        </div>
                    </div>

                    <!-- Bouton de connexion -->
                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt h-5 w-5 text-green-500 group-hover:text-green-400"></i>
                            </span>
                            Connexion
                        </button>
                    </div>
                </form>

                <!-- Informations -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Module Vente</span>
                        </div>
                    </div>

                    <div class="mt-6 text-center text-sm text-gray-600">
                        <p>
                            <i class="fas fa-info-circle mr-2"></i>
                            Accès réservé au personnel autorisé
                        </p>
                        <p class="mt-2">
                            Rôles autorisés : Vendeur, Assistant, Admin, Chargé de commande
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Focus automatique sur le premier champ
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Validation du formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username || !password) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères');
                return false;
            }

            // Afficher un indicateur de chargement
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Connexion en cours...';
            submitBtn.disabled = true;

            // Restaurer le bouton après 5 secondes (au cas où)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });
    </script>
</body>
</html>
