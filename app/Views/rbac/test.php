<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Module de Test RBAC' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-card { transition: all 0.3s ease; }
        .test-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .pulse-animation { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-red-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Module de Test RBAC</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Admin: <?= htmlspecialchars($user['name'] ?? 'Admin') ?></span>
                    <a href="/admin/dashboard" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto p-6">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">
                <i class="fas fa-vial text-red-600 mr-3"></i>
                Module de Test RBAC
            </h1>
            <p class="text-gray-600 mb-6">
                Validation complète du système de contrôle d'accès en conditions réelles
            </p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-2xl mx-auto">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Ce module exécute des tests automatisés pour valider la sécurité du système RBAC.
                    Les tests simulent les actions de tous les rôles et tentatives de contournement.
                </p>
            </div>
        </div>

        <!-- Test Controls -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="test-card bg-white rounded-lg shadow p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-user-tag text-blue-600 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold">Tests VENDEUR</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">7 scénarios de test</p>
                <div class="space-y-2">
                    <div class="text-xs text-gray-500">✓ vente.create</div>
                    <div class="text-xs text-gray-500">✗ vente.cancel</div>
                    <div class="text-xs text-gray-500">✗ stock.update</div>
                </div>
            </div>

            <div class="test-card bg-white rounded-lg shadow p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-user-tie text-green-600 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold">Tests ASSISTANT</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">7 scénarios de test</p>
                <div class="space-y-2">
                    <div class="text-xs text-gray-500">✓ vente.create</div>
                    <div class="text-xs text-gray-500">✓ caisse.close</div>
                    <div class="text-xs text-gray-500">✗ user.manage</div>
                </div>
            </div>

            <div class="test-card bg-white rounded-lg shadow p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-truck text-purple-600 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold">Tests COMMANDE</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">7 scénarios de test</p>
                <div class="space-y-2">
                    <div class="text-xs text-gray-500">✓ commande.create</div>
                    <div class="text-xs text-gray-500">✓ remise.apply</div>
                    <div class="text-xs text-gray-500">✗ stock.update</div>
                </div>
            </div>

            <div class="test-card bg-white rounded-lg shadow p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-user-shield text-red-600 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold">Tests ADMIN</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">7 scénarios de test</p>
                <div class="space-y-2">
                    <div class="text-xs text-gray-500">✓ system.config</div>
                    <div class="text-xs text-gray-500">✓ user.manage</div>
                    <div class="text-xs text-gray-500">✓ audit.view</div>
                </div>
            </div>
        </div>

        <!-- Attack Tests -->
        <div class="bg-red-50 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-red-800 mb-4">
                <i class="fas fa-shield-virus mr-2"></i>
                Tests d'Attaque (Sécurité)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white rounded p-4 border border-red-200">
                    <h4 class="font-semibold text-red-700 mb-2">
                        <i class="fas fa-link mr-2"></i>Accès URL Direct
                    </h4>
                    <p class="text-sm text-gray-600">Tentative d'accès sans permission</p>
                </div>
                <div class="bg-white rounded p-4 border border-red-200">
                    <h4 class="font-semibold text-red-700 mb-2">
                        <i class="fas fa-user-secret mr-2"></i>Session Hijack
                    </h4>
                    <p class="text-sm text-gray-600">Modification rôle en session</p>
                </div>
                <div class="bg-white rounded p-4 border border-red-200">
                    <h4 class="font-semibold text-red-700 mb-2">
                        <i class="fas fa-key mr-2"></i>API Bypass
                    </h4>
                    <p class="text-sm text-gray-600">Appel API sans token</p>
                </div>
                <div class="bg-white rounded p-4 border border-red-200">
                    <h4 class="font-semibold text-red-700 mb-2">
                        <i class="fas fa-arrow-up mr-2"></i>Privilege Escalation
                    </h4>
                    <p class="text-sm text-gray-600">Élévation de privilèges</p>
                </div>
                <div class="bg-white rounded p-4 border border-red-200">
                    <h4 class="font-semibold text-red-700 mb-2">
                        <i class="fas fa-injection mr-2"></i>Injection Test
                    </h4>
                    <p class="text-sm text-gray-600">Permission invalide</p>
                </div>
            </div>
        </div>

        <!-- Execute Button -->
        <div class="text-center mb-8">
            <form method="POST" action="/rbac/test/run" class="inline-block">
                <button type="submit" class="bg-red-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition transform hover:scale-105 pulse-animation">
                    <i class="fas fa-play mr-3"></i>
                    Lancer les Tests RBAC
                </button>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
            </form>
            <p class="text-gray-500 text-sm mt-4">
                Les tests prendront environ 30 secondes et seront enregistrés dans l'audit
            </p>
        </div>

        <!-- Recent Test Results (if any) -->
        <?php if (!empty($testResults)): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-line mr-2"></i>
                Résultats des Tests Précédents
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600">
                        <?= $testResults['summary']['passed_scenarios'] ?? 0 ?>/<?= $testResults['summary']['total_scenarios'] ?? 0 ?>
                    </div>
                    <p class="text-gray-600">Scénarios Réussis</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600">
                        <?= $testResults['summary']['blocked_attacks'] ?? 0 ?>/<?= $testResults['summary']['total_attacks'] ?? 0 ?>
                    </div>
                    <p class="text-gray-600">Attaques Bloquées</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600">
                        <?= $testResults['summary']['overall_status'] ?? 'UNKNOWN' ?>
                    </div>
                    <p class="text-gray-600">Statut Global</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Test Information -->
        <div class="bg-gray-50 rounded-lg p-6 mt-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle mr-2"></i>
                Information sur les Tests
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Scénarios Testés</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Permissions par rôle (28 tests)</li>
                        <li>• Validation des accès refusés</li>
                        <li>• Performance du système RBAC</li>
                        <li>• Cohérence des permissions</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Tests de Sécurité</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Contournement des permissions</li>
                        <li>• Injection de permissions invalides</li>
                        <li>• Élévation de privilèges</li>
                        <li>• Attaques par session/API</li>
                    </ul>
                </div>
            </div>
            <div class="mt-4 p-3 bg-blue-50 rounded">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <strong>Important:</strong> Tous les tests sont journalisés dans l'audit pour traçabilité complète.
                    Les résultats sont conservés pour analyse de sécurité.
                </p>
            </div>
        </div>
    </main>

    <script>
        // Animation du bouton de test
        document.addEventListener('DOMContentLoaded', function() {
            const testButton = document.querySelector('button[type="submit"]');
            if (testButton) {
                testButton.addEventListener('click', function() {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i>Tests en cours...';
                    this.disabled = true;
                });
            }
        });

        // Confirmation avant de lancer les tests
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir lancer les tests RBAC ?\n\nCela va exécuter 28 scénarios de test et 5 tests d\'attaque.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
