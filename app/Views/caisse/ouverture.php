<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouverture Session Caisse - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-cash-register text-2xl"></i>
                    <h1 class="text-xl font-bold">OUVERTURE SESSION CAISSE</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm">
                        <span class="font-semibold">Caissier:</span> 
                        <?= htmlspecialchars($user['name'] ?? 'Invité') ?>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Date:</span> 
                        <?= date('d/m/Y H:i') ?>
                    </div>
                    <a href="/dashboard" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded text-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <!-- Carte d'ouverture -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-6">
                    <i class="fas fa-cash-register text-6xl text-blue-600 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800">Ouvrir Session Caisse</h2>
                    <p class="text-gray-600 mt-2">Commencez votre journée de travail</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/caisse/traiter-ouverture" class="space-y-6">
                    <!-- Informations de la session -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            Informations Session
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Numéro Session:</span>
                                <span class="font-semibold" id="numeroSession">CS<?= date('Ymd') ?>001</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Caissier:</span>
                                <span class="font-semibold"><?= htmlspecialchars($user['name'] ?? 'Invité') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date Ouverture:</span>
                                <span class="font-semibold"><?= date('d/m/Y H:i') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Montant d'ouverture -->
                    <div>
                        <label for="montant_ouverture" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-2"></i>
                            Montant d'Ouverture (FCFA)
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   id="montant_ouverture" 
                                   name="montant_ouverture"
                                   value="0"
                                   min="0"
                                   step="100"
                                   required
                                   class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-semibold">
                            <span class="absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-coins"></i>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Montant disponible dans la caisse au début de la session
                        </p>
                    </div>

                    <!-- Dernières sessions -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-800 mb-3">
                            <i class="fas fa-history mr-2"></i>
                            Vos Dernières Sessions
                        </h3>
                        <div class="space-y-2 text-sm">
                            <?php
                            // Simulation des dernières sessions - à remplacer avec vraies données
                            $dernieresSessions = [
                                ['numero' => 'CS20240114001', 'date' => '2024-01-14 08:00', 'ventes' => '125,000 FCFA', 'ecart' => '+500 FCFA'],
                                ['numero' => 'CS20240113001', 'date' => '2024-01-13 08:00', 'ventes' => '98,500 FCFA', 'ecart' => '0 FCFA'],
                                ['numero' => 'CS20240112001', 'date' => '2024-01-12 08:00', 'ventes' => '156,200 FCFA', 'ecart' => '-200 FCFA']
                            ];
                            ?>
                            <?php foreach ($dernieresSessions as $session): ?>
                                <div class="flex justify-between items-center py-1 border-b border-gray-200">
                                    <div>
                                        <span class="font-medium"><?= $session['numero'] ?></span>
                                        <span class="text-gray-500 ml-2"><?= $session['date'] ?></span>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs"><?= $session['ventes'] ?></div>
                                        <div class="text-xs <?= strpos($session['ecart'], '+') !== false ? 'text-green-600' : (strpos($session['ecart'], '-') !== false ? 'text-red-600' : 'text-gray-600') ?>">
                                            Écart: <?= $session['ecart'] ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="space-y-3">
                        <button type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                            <i class="fas fa-play-circle mr-2"></i>
                            Ouvrir la Session
                        </button>
                        
                        <button type="button" 
                                onclick="window.location.href='/dashboard'"
                                class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                            <i class="fas fa-times-circle mr-2"></i>
                            Annuler
                        </button>
                    </div>
                </form>

                <!-- Instructions -->
                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-2">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Instructions
                    </h4>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• Comptez soigneusement le montant disponible dans la caisse</li>
                        <li>• Vérifiez que le montant saisi correspond exactement au montant réel</li>
                        <li>• Une seule session peut être ouverte à la fois par caissier</li>
                        <li>• La session restera ouverte jusqu'à la fermeture manuelle</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Générer le numéro de session automatiquement
        function genererNumeroSession() {
            const date = new Date();
            const dateStr = date.getFullYear() + 
                           String(date.getMonth() + 1).padStart(2, '0') + 
                           String(date.getDate()).padStart(2, '0');
            
            // Simuler le compteur - en réalité, faire un appel AJAX pour obtenir le vrai numéro
            const numeroSession = 'CS' + dateStr + '001';
            document.getElementById('numeroSession').textContent = numeroSession;
        }

        // Validation du montant
        document.getElementById('montant_ouverture').addEventListener('input', function(e) {
            const value = parseFloat(e.target.value);
            if (value < 0) {
                e.target.value = 0;
            }
            // Forcer les multiples de 100
            if (value % 100 !== 0) {
                e.target.value = Math.round(value / 100) * 100;
            }
        });

        // Confirmation avant soumission
        document.querySelector('form').addEventListener('submit', function(e) {
            const montant = parseFloat(document.getElementById('montant_ouverture').value);
            
            if (confirm(`Confirmer l'ouverture de la session avec un montant de ${new Intl.NumberFormat('fr-FR').format(montant)} FCFA ?`)) {
                // Afficher un message de chargement
                const submitBtn = document.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Ouverture en cours...';
            } else {
                e.preventDefault();
            }
        });

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            genererNumeroSession();
            document.getElementById('montant_ouverture').focus();
        });
    </script>
</body>
</html>
