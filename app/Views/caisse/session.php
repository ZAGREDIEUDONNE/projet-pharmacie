<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Changement de Session Caisse' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-cash-register text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Session Caisse</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                    <?php
                        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
                        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
                        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
                        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
                        $returnTo = match (true) {
                            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
                            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
                            default => '/vente',
                        };
                    ?>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
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
            <!-- Formulaire de changement de session -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-exchange-alt mr-2"></i>Nouvelle Session
                </h2>
                
                <form method="POST" action="/caisse/session">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-2"></i>Numéro de Session
                            </label>
                            <input type="text" name="numero_session" 
                                   value="<?= htmlspecialchars($_SESSION['old_input']['numero_session'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ex: 001, 002..." required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-coins mr-2"></i>Montant Théorique
                            </label>
                            <input type="number" name="montant_theorique" 
                                   value="<?= htmlspecialchars($_SESSION['old_input']['montant_theorique'] ?? '0.00') ?>"
                                   step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="0.00" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-money-bill mr-2"></i>Montant Réel
                            </label>
                            <input type="number" name="montant_reel" 
                                   value="<?= htmlspecialchars($_SESSION['old_input']['montant_reel'] ?? '0.00') ?>"
                                   step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="0.00" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-2"></i>Observations
                            </label>
                            <textarea name="observations" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Observations sur la session..."><?= htmlspecialchars($_SESSION['old_input']['observations'] ?? '') ?></textarea>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-play mr-2"></i>Ouvrir la Session
                            </button>
                            <button type="button" onclick="window.location.href='/caisse/etat'" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-chart-line mr-2"></i>Voir l'État
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Session Active -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-clock mr-2"></i>Session Active
                </h2>
                
                <?php if (isset($activeSession) && $activeSession): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Numéro</p>
                                <p class="font-semibold"><?= htmlspecialchars($activeSession['numero_session']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Ouverture</p>
                                <p class="font-semibold"><?= date('H:i:s', strtotime($activeSession['created_at'])) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Théorique</p>
                                <p class="font-semibold"><?= number_format($activeSession['montant_theorique'], 0, ',', ' ') ?> FCFA</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Réel</p>
                                <p class="font-semibold"><?= number_format($activeSession['montant_reel'], 0, ',', ' ') ?> FCFA</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($activeSession['observations'])): ?>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600">Observations</p>
                                <p class="text-gray-800"><?= htmlspecialchars($activeSession['observations']) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 flex space-x-4">
                            <a href="/caisse/fermer" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 inline-block">
                                <i class="fas fa-stop mr-2"></i>Fermer la Session
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-yellow-600 mr-3"></i>
                            <p class="text-yellow-800">Aucune session active</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique des sessions -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-history mr-2"></i>Historique des Sessions
            </h2>
            
            <?php if (!empty($sessionHistory)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ouverture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fermeture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Théorique</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Réel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Écart</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sessionHistory as $session): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($session['numero_session']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($session['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $session['closed_at'] ? date('d/m/Y H:i', strtotime($session['closed_at'])) : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($session['montant_theorique'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($session['montant_reel'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php 
                                        $ecart = $session['montant_reel'] - $session['montant_theorique'];
                                        $color = $ecart >= 0 ? 'text-green-600' : 'text-red-600';
                                        ?>
                                        <span class="<?= $color ?>">
                                            <?= number_format(abs($ecart), 0, ',', ' ') ?> FCFA
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                        $statusClass = $session['statut'] === 'OUVERTE' ? 'bg-green-100 text-green-800' : 
                                                       ($session['statut'] === 'FERMEE' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800');
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                            <?= htmlspecialchars($session['statut']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">Aucun historique de sessions</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Auto-rafraîchissement de l'état de la session
        setInterval(function() {
            fetch('/caisse/apiEtat')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.session) {
                        // Mettre à jour l'affichage de la session active
                        const activeSessionDiv = document.querySelector('.bg-green-50');
                        if (activeSessionDiv) {
                            // Rafraîchir l'affichage si nécessaire
                            location.reload();
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du rafraîchissement:', error);
                });
        }, 30000); // Toutes les 30 secondes
    </script>
</body>
</html>
