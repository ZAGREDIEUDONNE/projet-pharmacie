<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Coefficients de Vente')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-calculator text-red-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Coefficients de Vente</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/produits" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour au Dashboard Produits
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
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Simulation des coefficients</h2>
            <form method="GET" action="/produits/coefficients-vente" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Coefficient minimum</label>
                    <input type="number" step="0.01" name="coefficient_min" value="<?= htmlspecialchars($coefficient_min ?? 1.48) ?>" class="w-full border rounded-lg px-3 py-2" min="1.0" max="5.0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Coefficient maximum</label>
                    <input type="number" step="0.01" name="coefficient_max" value="<?= htmlspecialchars($coefficient_max ?? 2.0) ?>" class="w-full border rounded-lg px-3 py-2" min="1.0" max="5.0">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-calculator mr-2"></i>Simuler
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($simulation)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Aperçu de la simulation</h2>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    Coefficient appliqué: <strong><?= number_format((float)$coefficient_min, 2, ',', ' ') ?></strong>
                    | Prix de vente simulé = Prix achat × Coefficient
                </p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Nom</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix achat</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix vente actuel</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Coef. actuel</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix vente simulé</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Coef. simulé</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulation as $item): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4"><?= htmlspecialchars($produits[array_search($item['id'], array_column($produits, 'id'))]['code_cip'] ?? '-') ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($item['nom']) ?></td>
                                <td class="py-3 px-4 text-right"><?= number_format((float)$item['prix_achat'], 0, ',', ' ') ?> FCFA</td>
                                <td class="py-3 px-4 text-right"><?= number_format((float)$item['prix_vente_actuel'], 0, ',', ' ') ?> FCFA</td>
                                <td class="py-3 px-4 text-right"><?= number_format((float)$item['coefficient_actuel'], 2, ',', ' ') ?></td>
                                <td class="py-3 px-4 text-right text-blue-600 font-semibold"><?= number_format((float)$item['prix_vente_simule'], 0, ',', ' ') ?> FCFA</td>
                                <td class="py-3 px-4 text-right text-blue-600 font-semibold"><?= number_format((float)$item['coefficient_simule'], 2, ',', ' ') ?></td>
                                <td class="py-3 px-4 text-right <?= ($item['prix_vente_simule'] - $item['prix_vente_actuel']) < 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= number_format((float)($item['prix_vente_simule'] - $item['prix_vente_actuel']), 0, ',', ' ') ?> FCFA
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Liste des produits</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Nom</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix achat</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix vente</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Coefficient actuel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($produits)): ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['code_cip'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['nom'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['prix_achat'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['coefficient_actuel'] ?? 0), 2, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-500">Aucun produit trouvé</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
