<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Changement de Date Système' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $returnTo = (string)($returnTo ?? $_GET['return_to'] ?? '/dashboard');
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/dashboard';
    ?>
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-calendar-alt text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Changement de Date Système</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto p-6">
        <!-- Messages d'erreur/succès -->
        <?php if (isset($_SESSION['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    <div>
                        <h4 class="text-red-800 font-semibold">Erreurs</h4>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Formulaire de changement de date -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-calendar-day mr-2"></i>Changer la Date Système
                </h2>
                
                <form method="POST" action="/date/change">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2"></i>Date Actuelle
                            </label>
                            <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                                <span class="text-gray-900 font-semibold"><?= date('d/m/Y', strtotime($currentDate)) ?></span>
                                <span class="text-gray-500 ml-2">(Format: AAAA-MM-JJ)</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-plus mr-2"></i>Nouvelle Date
                            </label>
                            <input type="date" name="new_date" 
                                   value="<?= htmlspecialchars($_SESSION['old_input']['new_date'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-comment-alt mr-2"></i>Raison du Changement
                            </label>
                            <textarea name="reason" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Expliquez pourquoi vous changez la date système..."
                                      required><?= htmlspecialchars($_SESSION['old_input']['reason'] ?? '') ?></textarea>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-1"></i>
                                <div>
                                    <h4 class="text-yellow-800 font-semibold">⚠️ Attention</h4>
                                    <p class="text-yellow-700 mt-2">
                                        Le changement de date système affecte toutes les opérations de vente, 
                                        de stock et les rapports. Assurez-vous d'avoir une autorisation 
                                        et une raison valide pour cette modification.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i>Changer la Date
                            </button>
                            <button type="button" onclick="window.location.href='/system/info'" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                                <i class="fas fa-info-circle mr-2"></i>Informations Système
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Informations système -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-server mr-2"></i>Informations Système
                </h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-sm text-gray-600">Date Actuelle</span>
                        <span class="font-semibold text-gray-900"><?= date('d/m/Y H:i:s', strtotime($currentDate ?? date('Y-m-d'))) ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-sm text-gray-600">Version PHP</span>
                        <span class="font-semibold text-gray-900"><?= PHP_VERSION ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-sm text-gray-600">Fuseau Horaire</span>
                        <span class="font-semibold text-gray-900"><?= date_default_timezone_get() ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-sm text-gray-600">Heure Serveur</span>
                        <span class="font-semibold text-gray-900"><?= date('H:i:s') ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-600">Mémoire Utilisée</span>
                        <span class="font-semibold text-gray-900">
                            <?= round(memory_get_usage(true) / 1024 / 1024, 2) ?> MB
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Limiter la date minimale à aujourd'hui
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.querySelector('input[name="new_date"]');
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
            
            // Afficher un calendrier simple au clic sur le champ
            dateInput.addEventListener('focus', function() {
                this.showPicker = true;
            });
        });

        // Confirmation avant soumission
        document.querySelector('form').addEventListener('submit', function(e) {
            const newDate = document.querySelector('input[name="new_date"]').value;
            const reason = document.querySelector('textarea[name="reason"]').value.trim();
            
            if (!newDate || !reason) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires');
                return false;
            }
            
            if (reason.length < 10) {
                e.preventDefault();
                alert('La raison doit contenir au moins 10 caractères');
                return false;
            }
            
            if (!confirm('Êtes-vous sûr de vouloir changer la date système ?\n\nCette action affectera toutes les opérations du système.')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
