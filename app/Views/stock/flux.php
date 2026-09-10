<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Flux de stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow mb-6"><div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
    <h1 class="text-xl font-bold"><i class="fas fa-arrows-rotate text-cyan-600 mr-2"></i>Flux de stock</h1>
    <div class="space-x-2">
        <a href="/stock/valeur" class="bg-blue-600 text-white px-3 py-2 rounded text-sm">Valeur stock</a>
        <a href="/stock" class="bg-gray-600 text-white px-3 py-2 rounded text-sm">Retour stock</a>
    </div>
</div></nav>
<main class="max-w-7xl mx-auto px-4 pb-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-5"><p class="text-sm text-gray-500">Valeur globale</p><p class="text-2xl font-bold text-blue-700"><?= number_format((float)($valeur_globale ?? 0), 0, ',', ' ') ?> FCFA</p></div>
        <div class="bg-white rounded-lg shadow p-5 md:col-span-2">
            <p class="text-sm text-gray-500 mb-2">Valeur par catégorie</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach (($valeur_categories ?? []) as $cat): ?>
                    <span class="text-xs bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars((string)$cat['categorie']) ?> : <?= number_format((float)$cat['valeur_stock'], 0, ',', ' ') ?> FCFA</span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-bold mb-4">Synthèse par produit</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-left">
                    <th class="p-2">Produit</th><th class="p-2">Stock actuel</th><th class="p-2">Entrées</th><th class="p-2">Sorties</th><th class="p-2">Prix achat</th><th class="p-2">Valeur</th><th class="p-2">Dernier mvt</th>
                </tr></thead>
                <tbody>
                <?php foreach (($produits ?? []) as $p): ?>
                    <tr class="border-t">
                        <td class="p-2"><a href="/stock/details?id=<?= (int)$p['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars((string)$p['nom']) ?></a><div class="text-xs text-gray-500"><?= htmlspecialchars((string)($p['code_cip'] ?? '')) ?></div></td>
                        <td class="p-2 text-center"><?= (int)$p['stock_actuel'] ?></td>
                        <td class="p-2 text-center text-green-700"><?= (int)$p['total_entrees'] ?></td>
                        <td class="p-2 text-center text-red-700"><?= (int)$p['total_sorties'] ?></td>
                        <td class="p-2 text-right"><?= number_format((float)$p['prix_achat'], 0, ',', ' ') ?></td>
                        <td class="p-2 text-right font-semibold"><?= number_format((float)$p['valeur_stock'], 0, ',', ' ') ?></td>
                        <td class="p-2 text-xs text-gray-500"><?= htmlspecialchars((string)($p['dernier_mouvement'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold mb-4">Historique des mouvements</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-left"><th class="p-2">Date</th><th class="p-2">Produit</th><th class="p-2">Type</th><th class="p-2">Qté</th><th class="p-2">Motif</th><th class="p-2">Utilisateur</th></tr></thead>
                <tbody>
                <?php foreach (($mouvements ?? []) as $m): ?>
                    <tr class="border-t">
                        <td class="p-2 whitespace-nowrap"><?= htmlspecialchars((string)($m['date_mouvement'] ?? '')) ?></td>
                        <td class="p-2"><?= htmlspecialchars((string)($m['produit_nom'] ?? '')) ?></td>
                        <td class="p-2"><span class="px-2 py-0.5 rounded text-xs <?= ($m['type_mouvement'] ?? '') === 'ENTREE' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= htmlspecialchars((string)($m['type_mouvement'] ?? '')) ?></span></td>
                        <td class="p-2 text-center"><?= (int)($m['quantite'] ?? 0) ?></td>
                        <td class="p-2"><?= htmlspecialchars((string)($m['motif'] ?? '')) ?></td>
                        <td class="p-2"><?= htmlspecialchars((string)($m['utilisateur_nom'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
