<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Rapports Produits')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-file-lines text-indigo-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Rapports Produits</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/produits" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
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

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex space-x-4">
                <a href="/produits/rapports?type=general" class="px-4 py-2 rounded <?= ($type_rapport ?? '') === 'general' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    <i class="fas fa-chart-pie mr-2"></i>Général
                </a>
                <a href="/produits/rapports?type=mouvements" class="px-4 py-2 rounded <?= ($type_rapport ?? '') === 'mouvements' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    <i class="fas fa-exchange-alt mr-2"></i>Mouvements
                </a>
                <a href="/produits/rapports?type=peremptions" class="px-4 py-2 rounded <?= ($type_rapport ?? '') === 'peremptions' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    <i class="fas fa-clock mr-2"></i>Péremptions
                </a>
            </div>
        </div>

        <?php if (($type_rapport ?? '') === 'general'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Produits</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format(($data['general']['total_produits'] ?? 0)) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Produits en Stock</p>
                            <p class="text-2xl font-bold text-green-600"><?= number_format(($data['general']['produits_en_stock'] ?? 0)) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Produits sans Stock</p>
                            <p class="text-2xl font-bold text-red-600"><?= number_format(($data['general']['produits_sans_stock'] ?? 0)) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Valeur Stock Total</p>
                            <p class="text-2xl font-bold text-indigo-600"><?= number_format(($data['general']['valeur_stock_total'] ?? 0), 2) ?> FCFA</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-euro-sign text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Stock Total</h3>
                <p class="text-3xl font-bold text-gray-900"><?= number_format(($data['general']['stock_total'] ?? 0)) ?> unités</p>
            </div>

        <?php elseif (($type_rapport ?? '') === 'mouvements'): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Mouvement</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité Totale</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (($data['mouvements'] ?? []) as $mouvement): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $mouvement['type_mouvement'] === 'ENTREE' ? 'bg-green-100 text-green-800' : 
                                           ($mouvement['type_mouvement'] === 'SORTIE' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= htmlspecialchars($mouvement['type_mouvement']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($mouvement['nombre']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($mouvement['total_quantite']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($data['mouvements'])): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Aucun mouvement sur les 30 derniers jours
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif (($type_rapport ?? '') === 'peremptions'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Lots Actifs</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format(($data['peremptions']['total'] ?? 0)) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-barcode text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Produits Expirés</p>
                            <p class="text-2xl font-bold text-red-600"><?= number_format(($data['peremptions']['expires'] ?? 0)) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">En Alerte (30 jours)</p>
                            <p class="text-2xl font-bold text-yellow-600"><?= number_format(($data['peremptions']['alerte'] ?? 0)) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Information</h3>
                <p class="text-sm text-yellow-700">Ce rapport affiche les lots actifs avec quantité restante > 0. Les produits en alerte sont ceux dont la date de péremption est dans les 30 prochains jours.</p>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <p class="text-gray-500">Sélectionnez un type de rapport ci-dessus</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
