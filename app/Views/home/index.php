<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'ERP Pharmacy' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-pills text-2xl"></i>
                    <h1 class="text-xl font-bold">ERP Pharmacie JDS</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/login" class="bg-white text-blue-600 px-4 py-2 rounded hover:bg-gray-100">
                        <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Hero Section -->
        <section class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">
                    Bienvenue sur ERP PharmacieJDS
                </h2>
                <p class="text-gray-600 mb-6">
                    Solution complète de gestion pour pharmacies
                </p>
                <div class="flex justify-center space-x-4">
                    <a href="/login" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                    </a>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <i class="fas fa-users text-3xl text-blue-600 mb-4"></i>
                    <h3 class="text-lg font-semibold mb-2">Gestion Clients</h3>
                    <p class="text-gray-600">Suivi complet des clients et historique d'achats</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <i class="fas fa-shopping-cart text-3xl text-green-600 mb-4"></i>
                    <h3 class="text-lg font-semibold mb-2">Point de Vente</h3>
                    <p class="text-gray-600">Gestion des ventes et caisse intégrée</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <i class="fas fa-pills text-3xl text-purple-600 mb-4"></i>
                    <h3 class="text-lg font-semibold mb-2">Gestion Stock</h3>
                    <p class="text-gray-600">Suivi des médicaments et alertes de stock</p>
                </div>
            </div>
        </section>

        <!-- Modules -->
        <section class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Modules Principaux</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="border rounded p-4">
                    <i class="fas fa-cash-register text-2xl text-green-600 mb-2"></i>
                    <h4 class="font-semibold">Caisse</h4>
                    <p class="text-sm text-gray-600">Gestion des transactions</p>
                </div>
                <div class="border rounded p-4">
                    <i class="fas fa-chart-line text-2xl text-blue-600 mb-2"></i>
                    <h4 class="font-semibold">Rapports</h4>
                    <p class="text-sm text-gray-600">Analyse et statistiques</p>
                </div>
                <div class="border rounded p-4">
                    <i class="fas fa-user-shield text-2xl text-purple-600 mb-2"></i>
                    <h4 class="font-semibold">Administration</h4>
                    <p class="text-sm text-gray-600">Gestion utilisateurs</p>
                </div>
                <div class="border rounded p-4">
                    <i class="fas fa-cog text-2xl text-gray-600 mb-2"></i>
                    <h4 class="font-semibold">Configuration</h4>
                    <p class="text-sm text-gray-600">Paramètres système</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2026 ERP Pharmacy. Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>
