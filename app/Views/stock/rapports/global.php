<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports stock</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-chart-line text-gray-700 text-2xl mr-3"></i>
                <h1 class="text-xl font-bold text-gray-800">Rapport global du stock</h1>
            </div>
            <a href="/stock" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Produits</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format((float)($stats['nombre_produits'] ?? 0), 0, ',', ' ') ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Stock total</p>
                <p class="text-3xl font-bold text-green-600"><?= number_format((float)($stats['stock_total'] ?? 0), 0, ',', ' ') ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-5 md:col-span-2">
                <p class="text-sm text-gray-500">Valeur totale</p>
                <p class="text-3xl font-bold text-purple-600"><?= number_format((float)($stats['valeur_totale'] ?? 0), 0, ',', ' ') ?> FCFA</p>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Alertes</p>
                <p class="text-3xl font-bold text-orange-600"><?= number_format((float)($stats['produits_alerte'] ?? 0), 0, ',', ' ') ?></p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Synthese</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded p-4">
                    <p class="text-sm text-gray-500">Produits en rupture</p>
                    <p class="text-2xl font-bold text-red-600"><?= number_format((float)($stats['produits_rupture'] ?? 0), 0, ',', ' ') ?></p>
                </div>
                <div class="border border-gray-200 rounded p-4">
                    <p class="text-sm text-gray-500">Taux d'alerte</p>
                    <?php
                        $total = (float)($stats['nombre_produits'] ?? 0);
                        $alerte = (float)($stats['produits_alerte'] ?? 0);
                        $taux = $total > 0 ? ($alerte / $total) * 100 : 0;
                    ?>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($taux, 1, ',', ' ') ?>%</p>
                </div>
            </div>

            <div class="mt-6 flex space-x-3">
                <a href="/stock/mouvements" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-exchange-alt mr-2"></i>Voir mouvements
                </a>
                <a href="/stock/peremptions" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-clock mr-2"></i>Voir peremptions
                </a>
            </div>
        </div>
    </main>
</body>
</html>
