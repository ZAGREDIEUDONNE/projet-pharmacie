<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'État des Stocks')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-chart-line text-green-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">État des Stocks</h1>
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
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtres</h2>
            <form method="GET" action="/produits/etat-stocks" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="filtre" class="w-full border rounded-lg px-3 py-2">
                        <option value="tous" <?= ($filtre ?? '') === 'tous' ? 'selected' : '' ?>>Tous</option>
                        <option value="disponible" <?= ($filtre ?? '') === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                        <option value="stock_nul" <?= ($filtre ?? '') === 'stock_nul' ? 'selected' : '' ?>>Stock nul</option>
                        <option value="stock_negatif" <?= ($filtre ?? '') === 'stock_negatif' ? 'selected' : '' ?>>Stock négatif</option>
                        <option value="sous_minimum" <?= ($filtre ?? '') === 'sous_minimum' ? 'selected' : '' ?>>Sous stock minimum</option>
                        <option value="rupture" <?= ($filtre ?? '') === 'rupture' ? 'selected' : '' ?>>Rupture</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">État des stocks</h2>
                <div class="space-x-2">
                    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Nom</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Stock</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Min</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Max</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Statut</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix vente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($produits)): ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['code_cip'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['nom'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((int)($produit['stock'] ?? 0), 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((int)($produit['stock_minimum'] ?? 0), 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((int)($produit['stock_maximum'] ?? 0), 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 rounded text-xs font-semibold <?= match($produit['statut'] ?? '') {
                                            'RUPTURE' => 'bg-red-100 text-red-800',
                                            'CRITIQUE' => 'bg-red-100 text-red-800',
                                            'ALERTE' => 'bg-yellow-100 text-yellow-800',
                                            'NORMAL' => 'bg-green-100 text-green-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        } ?>">
                                            <?= htmlspecialchars($produit['statut'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-500">Aucun produit trouvé</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
