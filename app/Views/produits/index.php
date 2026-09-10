<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Dashboard Produits')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-boxes-stacked text-green-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Dashboard Produits</h1>
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
            <h2 class="text-lg font-semibold text-gray-800 mb-6">Fonctionnalités Produits</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="/stock/ajouter-produit" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-green-50 hover:border-green-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Ajouter Produit</h3>
                        <p class="text-sm text-gray-600">Créer un nouveau produit</p>
                    </div>
                </a>

                <a href="/produits/inventaire" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Inventaire Produits</h3>
                        <p class="text-sm text-gray-600">Liste complète et filtrée</p>
                    </div>
                </a>

                <a href="/produits/etat-stocks" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">État des Stocks</h3>
                        <p class="text-sm text-gray-600">Disponibilité et alertes</p>
                    </div>
                </a>

                <a href="/produits/liste-prix" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-amber-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Liste des Prix</h3>
                        <p class="text-sm text-gray-600">Tarifs et exportations</p>
                    </div>
                </a>

                <a href="/produits/gestion-mini-maxi" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-scale-balanced text-cyan-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Gestion Mini / Maxi</h3>
                        <p class="text-sm text-gray-600">Seuils et écarts</p>
                    </div>
                </a>

                <a href="/produits/produits-specifiques" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-filter text-violet-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Produits Spécifiques</h3>
                        <p class="text-sm text-gray-600">Filtres avancés</p>
                    </div>
                </a>

                <a href="/produits/coefficients-vente" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calculator text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Coefficients de Vente</h3>
                        <p class="text-sm text-gray-600">Simulation et marge</p>
                    </div>
                </a>

                <a href="/produits/sortie-stock" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-minus-circle text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Sortie de Stock</h3>
                        <p class="text-sm text-gray-600">Retraits traçables</p>
                    </div>
                </a>

                <a href="/produits/historique-sorties" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-history text-gray-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Historique Sorties</h3>
                        <p class="text-sm text-gray-600">Journal des retraits</p>
                    </div>
                </a>

                <a href="/produits/entree-stock" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Entrée de Stock</h3>
                        <p class="text-sm text-gray-600">Réception et ajout</p>
                    </div>
                </a>

                <a href="/produits/ajustement-stock" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sliders text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Ajustement de Stock</h3>
                        <p class="text-sm text-gray-600">Corrections manuelles</p>
                    </div>
                </a>

                <a href="/produits/produits-expires" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Produits Expirés</h3>
                        <p class="text-sm text-gray-600">Gestion des péremptions</p>
                    </div>
                </a>

                <a href="/produits/rapports" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-lines text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Rapports Produits</h3>
                        <p class="text-sm text-gray-600">Export et analyses</p>
                    </div>
                </a>

                <a href="/produits/price-history" class="flex items-center gap-4 p-4 border rounded-lg hover:bg-green-50 hover:border-green-300 transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-history text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Historique des Prix</h3>
                        <p class="text-sm text-gray-600">Traçabilité des modifications</p>
                    </div>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
