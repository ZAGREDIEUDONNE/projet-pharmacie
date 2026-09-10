<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Catalogue des Prix')) ?></title>
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
                <h1 class="text-2xl font-bold text-gray-900">Catalogue des Prix</h1>
                <p class="text-sm text-gray-500">Consultation des tarifs</p>
            </div>
            <a href="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>

        <!-- Formulaire de recherche -->
        <div class="card p-6 mb-6">
            <form method="GET" action="/produits/prix-vendeur">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" name="recherche" value="<?= htmlspecialchars((string)($recherche ?? '')) ?>" 
                               placeholder="Nom, DCI, code CIP..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trier par</label>
                        <select name="tri" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="nom" <?= ($tri ?? '') === 'nom' ? 'selected' : '' ?>>Nom</option>
                            <option value="prix" <?= ($tri ?? '') === 'prix' ? 'selected' : '' ?>>Prix</option>
                            <option value="dci" <?= ($tri ?? '') === 'dci' ? 'selected' : '' ?>>DCI</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                        <?php if (($ordre ?? '') === 'ASC'): ?>
                            <a href="/produits/prix-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>&recherche=<?= urlencode((string)($recherche ?? '')) ?>&tri=<?= htmlspecialchars((string)($tri ?? 'nom')) ?>&ordre=DESC" 
                               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium" title="Ordre décroissant">
                                <i class="fas fa-sort-amount-down"></i>
                            </a>
                        <?php else: ?>
                            <a href="/produits/prix-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>&recherche=<?= urlencode((string)($recherche ?? '')) ?>&tri=<?= htmlspecialchars((string)($tri ?? 'nom')) ?>&ordre=ASC" 
                               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium" title="Ordre croissant">
                                <i class="fas fa-sort-amount-up"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tableau -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Produit</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">DCI</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Forme</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix de vente</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">TVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits ?? [])): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-tag text-4xl mb-3 text-gray-300"></i>
                                    <p>Aucun produit trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm font-medium"><?= htmlspecialchars($produit['nom'] ?? '') ?></td>
                                    <td class="py-3 px-4 text-sm"><?= htmlspecialchars($produit['dci'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-sm"><?= htmlspecialchars($produit['forme_pharmaceutique'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-sm text-right font-bold text-gray-900">
                                        <?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <?php if (!empty($produit['tva_taux'])): ?>
                                            <?= number_format((float)$produit['tva_taux'], 1, ',', ' ') ?>%
                                        <?php else: ?>
                                            -
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
                Consultation uniquement. Toute modification de prix est réservée aux rôles autorisés.
            </p>
        </div>
    </div>
</body>
</html>
