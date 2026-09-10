<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Dashboard Gestion des Clients')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-users text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Dashboard Gestion des Clients</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/admin/dashboard" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i>Retour au Dashboard Administrateur
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-6">Fonctionnalités Clients</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="/clients" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-address-book text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">👥 Liste des clients</h3>
                        <p class="text-sm text-gray-600">Consulter et rechercher</p>
                    </div>
                </a>

                <a href="/clients/creer" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-plus text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">➕ Ajouter un client</h3>
                        <p class="text-sm text-gray-600">Créer nouveau compte</p>
                    </div>
                </a>

                <a href="/clients/modifier" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-edit text-amber-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">✏ Modifier un client</h3>
                        <p class="text-sm text-gray-600">Mettre à jour informations</p>
                    </div>
                </a>

                <a href="/clients/debiteurs" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">💳 Clients débiteurs</h3>
                        <p class="text-sm text-gray-600">Gérer les crédits</p>
                    </div>
                </a>

                <a href="/clients/statistiques" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">📊 Statistiques clients</h3>
                        <p class="text-sm text-gray-600">Analyses et rapports</p>
                    </div>
                </a>

                <a href="/suivi-client/releve-reglements" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-cyan-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">📄 Relevés clients</h3>
                        <p class="text-sm text-gray-600">Historique des règlements</p>
                    </div>
                </a>

                <a href="/finance/clients" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-hand-holding-dollar text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">💰 Créances clients</h3>
                        <p class="text-sm text-gray-600">Règlements et soldes</p>
                    </div>
                </a>

                <a href="/clients/rechercher" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-search text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">🔎 Recherche client</h3>
                        <p class="text-sm text-gray-600">Trouver un client</p>
                    </div>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
