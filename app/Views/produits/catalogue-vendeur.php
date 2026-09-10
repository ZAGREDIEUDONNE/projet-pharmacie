<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Catalogue des Produits')) ?></title>
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
                <h1 class="text-2xl font-bold text-gray-900">Catalogue des Produits</h1>
                <p class="text-sm text-gray-500">Consultation des produits</p>
            </div>
            <a href="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>

        <!-- Filtres -->
        <div class="card p-6 mb-6">
            <form method="GET" action="/produits/catalogue-vendeur">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <!-- Recherche -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" name="recherche" value="<?= htmlspecialchars($recherche ?? '') ?>" 
                               placeholder="Nom, code CIP, code-barre, DCI" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Catégorie -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                        <select name="categorie" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Toutes</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($categorie ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Forme pharmaceutique -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Forme</label>
                        <select name="forme" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Toutes</option>
                            <?php foreach ($formes ?? [] as $f): ?>
                                <option value="<?= htmlspecialchars($f) ?>" <?= ($forme ?? '') === $f ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Disponibilité -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Disponibilité</label>
                        <select name="disponibilite" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Toutes</option>
                            <option value="disponible" <?= ($disponibilite ?? '') === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="faible" <?= ($disponibilite ?? '') === 'faible' ? 'selected' : '' ?>>Stock faible</option>
                            <option value="rupture" <?= ($disponibilite ?? '') === 'rupture' ? 'selected' : '' ?>>Rupture</option>
                        </select>
                    </div>
                </div>
                
                <!-- Tri -->
                <div class="flex items-center gap-4 mt-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trier par</label>
                        <select name="tri" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="nom" <?= ($tri ?? '') === 'nom' ? 'selected' : '' ?>>Nom</option>
                            <option value="code" <?= ($tri ?? '') === 'code' ? 'selected' : '' ?>>Code CIP</option>
                            <option value="prix" <?= ($tri ?? '') === 'prix' ? 'selected' : '' ?>>Prix</option>
                            <option value="stock" <?= ($tri ?? '') === 'stock' ? 'selected' : '' ?>>Stock</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ordre</label>
                        <select name="ordre" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="ASC" <?= ($ordre ?? '') === 'ASC' ? 'selected' : '' ?>>Croissant</option>
                            <option value="DESC" <?= ($ordre ?? '') === 'DESC' ? 'selected' : '' ?>>Décroissant</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                            <i class="fas fa-filter mr-2"></i>Filtrer
                        </button>
                        <a href="/produits/catalogue-vendeur?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>" 
                           class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des produits -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Nom</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">DCI</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Forme</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Classe</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Stock</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Rayon</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits ?? [])): ?>
                            <tr>
                                <td colspan="9" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-box text-4xl mb-3 text-gray-300"></i>
                                    <p>Aucun produit trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50 cursor-pointer" 
                                    onclick="window.location.href='/produits/recherche-vendeur?recherche=<?= urlencode($produit['code_cip'] ?? '') ?>&type=code_cip&return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>'">
                                    <td class="py-3 px-4 text-sm font-medium">
                                        <?= htmlspecialchars($produit['code_cip'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($produit['nom'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($produit['dci'] ?? '') ?: '<span class="text-gray-400">-</span>' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($produit['forme_pharmaceutique'] ?? '') ?: '<span class="text-gray-400">-</span>' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($produit['classe_pharmaceutique'] ?? '') ?: '<span class="text-gray-400">-</span>' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right font-medium">
                                        <?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <?= (int)($produit['stock_disponible'] ?? 0) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($produit['rayon'] ?? '') ?: '<span class="text-gray-400">-</span>' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-center">
                                        <?php $stock = (int)($produit['stock_disponible'] ?? 0); ?>
                                        <?php if ($stock > 10): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Disponible</span>
                                        <?php elseif ($stock > 0): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Faible</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Rupture</span>
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
                    <?= count($produits) ?> produit(s) trouvé(s)
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
            <i class="fas fa-info-circle mr-2"></i>
            Cliquez sur un produit pour voir ses détails. Module en lecture seule uniquement.
        </div>
    </div>
</body>
</html>
