<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Suivi Client')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-users-viewfinder text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Dashboard Suivi Client</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/admin/dashboard" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i>Retour au Dashboard Principal
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
            <h2 class="text-lg font-semibold text-gray-800 mb-6">Fonctionnalités Suivi Client</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="/suivi-client/saisie-reglement" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-transfer text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Saisie de règlement</h3>
                        <p class="text-sm text-gray-600">Enregistrer un règlement</p>
                    </div>
                </a>

                <a href="/suivi-client/recu-reglement" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-receipt text-cyan-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Reçu de règlement</h3>
                        <p class="text-sm text-gray-600">Imprimer un reçu</p>
                    </div>
                </a>

                <a href="/suivi-client/ouvrir-saisie" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-folder-open text-amber-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Ouvrir une saisie</h3>
                        <p class="text-sm text-gray-600">Reprendre une saisie</p>
                    </div>
                </a>

                <a href="/suivi-client/releve-reglements" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Relevé des règlements</h3>
                        <p class="text-sm text-gray-600">Historique des règlements</p>
                    </div>
                </a>

                <a href="/suivi-client/liste-clients" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-address-card text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Liste des clients</h3>
                        <p class="text-sm text-gray-600">Annuaire clients</p>
                    </div>
                </a>

                <a href="/suivi-client/solde-courant" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-scale-balanced text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Solde client courant</h3>
                        <p class="text-sm text-gray-600">État des comptes</p>
                    </div>
                </a>

                <a href="/suivi-client/releve-courant" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-lines text-cyan-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Relevé client courant</h3>
                        <p class="text-sm text-gray-600">Relevé période en cours</p>
                    </div>
                </a>

                <a href="/suivi-client/solde-arrete" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-check text-amber-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Solde client arrêté</h3>
                        <p class="text-sm text-gray-600">Soldes périodiques</p>
                    </div>
                </a>

                <a href="/suivi-client/releve-arrete" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-contract text-violet-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Relevé client arrêté</h3>
                        <p class="text-sm text-gray-600">Relevés périodiques</p>
                    </div>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
