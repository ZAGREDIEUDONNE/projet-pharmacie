<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Recherche Produits')) ?></title>
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
                <h1 class="text-2xl font-bold text-gray-900">Recherche Produits</h1>
                <p class="text-sm text-gray-500">Consultation rapide des produits</p>
            </div>
            <a href="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>

        <!-- Formulaire de recherche -->
        <div class="card p-6 mb-6">
            <form method="GET" action="/produits/recherche-vendeur">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" name="recherche" value="<?= htmlspecialchars((string)($recherche ?? '')) ?>" 
                               placeholder="Nom, code CIP, code-barres, DCI..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type de recherche</label>
                        <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="tous" <?= ($type ?? '') === 'tous' ? 'selected' : '' ?>>Tous</option>
                            <option value="nom" <?= ($type ?? '') === 'nom' ? 'selected' : '' ?>>Nom</option>
                            <option value="code_cip" <?= ($type ?? '') === 'code_cip' ? 'selected' : '' ?>>Code CIP</option>
                            <option value="code_barre" <?= ($type ?? '') === 'code_barre' ? 'selected' : '' ?>>Code-barres</option>
                            <option value="dci" <?= ($type ?? '') === 'dci' ? 'selected' : '' ?>>DCI</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Résultats -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Produit</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">DCI</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Forme</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Stock</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits ?? [])): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-search text-4xl mb-3 text-gray-300"></i>
                                    <p>Aucun résultat trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm font-medium"><?= htmlspecialchars($produit['code_cip'] ?? '') ?></td>
                                    <td class="py-3 px-4 text-sm font-medium"><?= htmlspecialchars($produit['nom'] ?? '') ?></td>
                                    <td class="py-3 px-4 text-sm"><?= htmlspecialchars($produit['dci'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-sm"><?= htmlspecialchars($produit['forme_pharmaceutique'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-sm text-right font-medium">
                                        <?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <?= number_format((int)($produit['quantite_disponible'] ?? 0), 0, ',', ' ') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-center">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?= ($produit['statut'] ?? '') === 'Disponible' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= htmlspecialchars($produit['statut'] ?? '') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($produits ?? [])): ?>
                <div class="px-4 py-3 border-t border-gray-200 text-sm text-gray-500">
                    <?= count($produits) ?> résultat(s) affiché(s)
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
