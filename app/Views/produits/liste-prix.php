<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Liste des Prix')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-money-bill-wave text-amber-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Liste des Prix</h1>
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
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recherche et tri</h2>
            <form method="GET" action="/produits/liste-prix" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                    <input type="text" name="recherche" value="<?= htmlspecialchars($recherche ?? '') ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Nom ou code...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tri par</label>
                    <select name="tri" class="w-full border rounded-lg px-3 py-2">
                        <option value="nom" <?= ($tri ?? '') === 'nom' ? 'selected' : '' ?>>Nom</option>
                        <option value="groupe" <?= ($tri ?? '') === 'groupe' ? 'selected' : '' ?>>Groupe</option>
                        <option value="prix" <?= ($tri ?? '') === 'prix' ? 'selected' : '' ?>>Prix</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ordre</label>
                    <select name="ordre" class="w-full border rounded-lg px-3 py-2">
                        <option value="ASC" <?= ($ordre ?? '') === 'ASC' ? 'selected' : '' ?>>Croissant</option>
                        <option value="DESC" <?= ($ordre ?? '') === 'DESC' ? 'selected' : '' ?>>Décroissant</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Rechercher
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Liste des prix</h2>
                <div class="space-x-2">
                    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                    <a href="/produits/liste-prix?export=pdf&recherche=<?= urlencode($recherche ?? '') ?>&tri=<?= $tri ?? '' ?>&ordre=<?= $ordre ?? '' ?>" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </a>
                    <a href="/produits/liste-prix?export=excel&recherche=<?= urlencode($recherche ?? '') ?>&tri=<?= $tri ?? '' ?>&ordre=<?= $ordre ?? '' ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        <i class="fas fa-file-excel mr-2"></i>Excel
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Nom</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Catégorie</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Groupe</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix achat</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix vente</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Marge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($produits)): ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['code_cip'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['nom'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['categorie'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['groupe'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['prix_achat'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                    <td class="py-3 px-4 text-right">
                                        <?php $marge = ($produit['prix_vente'] ?? 0) - ($produit['prix_achat'] ?? 0); ?>
                                        <?= number_format((float)$marge, 0, ',', ' ') ?> FCFA
                                    </td>
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
