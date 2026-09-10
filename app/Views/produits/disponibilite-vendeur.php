<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Disponibilité des Produits')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: Inter, system-ui, sans-serif; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Disponibilité des Produits</h1>
                <p class="text-sm text-gray-500">Consultation des stocks</p>
            </div>
            <a href="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>

        <!-- Filtres -->
        <div class="card p-4 mb-6">
            <div class="flex flex-wrap gap-2">
                <a href="/produits/disponibilite-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>&filtre=tous" 
                   class="px-4 py-2 rounded-lg text-sm font-medium <?= ($filtre ?? '') === 'tous' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    Tous
                </a>
                <a href="/produits/disponibilite-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>&filtre=disponible" 
                   class="px-4 py-2 rounded-lg text-sm font-medium <?= ($filtre ?? '') === 'disponible' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    🟢 Disponible
                </a>
                <a href="/produits/disponibilite-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>&filtre=faible" 
                   class="px-4 py-2 rounded-lg text-sm font-medium <?= ($filtre ?? '') === 'faible' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    🟠 Stock faible
                </a>
                <a href="/produits/disponibilite-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>&filtre=rupture" 
                   class="px-4 py-2 rounded-lg text-sm font-medium <?= ($filtre ?? '') === 'rupture' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    🔴 Rupture
                </a>
            </div>
        </div>

        <!-- Tableau -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Produit</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Quantité</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Stock min.</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits ?? [])): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-boxes-stacked text-4xl mb-3 text-gray-300"></i>
                                    <p>Aucun produit trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm font-medium"><?= htmlspecialchars($produit['code_cip'] ?? '') ?></td>
                                    <td class="py-3 px-4 text-sm font-medium"><?= htmlspecialchars($produit['nom'] ?? '') ?></td>
                                    <td class="py-3 px-4 text-sm text-right font-medium">
                                        <?= number_format((int)($produit['quantite_disponible'] ?? 0), 0, ',', ' ') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <?= number_format((int)($produit['stock_minimum'] ?? 0), 0, ',', ' ') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-center">
                                        <?php if (($produit['etat'] ?? '') === 'disponible'): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                                🟢 Disponible
                                            </span>
                                        <?php elseif (($produit['etat'] ?? '') === 'faible'): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">
                                                🟠 Stock faible
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                                🔴 Rupture
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($produits ?? [])): ?>
                <div class="px-4 py-3 border-t border-gray-200 text-sm text-gray-500">
                    <?= count($produits) ?> produit(s) affiché(s)
                </div>
            <?php endif; ?>
        </div>

        <!-- Note -->
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">
                <i class="fas fa-info-circle mr-2"></i>
                Consultation uniquement. Toute modification de stock est réservée au responsable du stock.
            </p>
        </div>
    </div>
</body>
</html>
