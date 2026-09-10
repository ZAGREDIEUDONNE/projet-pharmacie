<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string)($title ?? 'Valeur du stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Valeur du stock</h1>
        <a href="/stock/flux" class="bg-gray-600 text-white px-4 py-2 rounded">Flux détaillé</a>
    </div>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <p class="text-gray-500">Formule : quantité disponible × prix d'achat unitaire</p>
        <p class="text-3xl font-bold text-blue-700 mt-2"><?= number_format((float)($valeur_globale ?? 0), 0, ',', ' ') ?> FCFA</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-bold mb-4">Par catégorie</h2>
            <table class="w-full text-sm"><thead><tr class="text-left border-b"><th class="pb-2">Catégorie</th><th class="pb-2">Produits</th><th class="pb-2 text-right">Valeur</th></tr></thead>
            <tbody><?php foreach (($par_categorie ?? []) as $c): ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)$c['categorie']) ?></td><td class="py-2"><?= (int)$c['nb_produits'] ?></td><td class="py-2 text-right font-semibold"><?= number_format((float)$c['valeur_stock'], 0, ',', ' ') ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <div class="bg-white rounded-lg shadow p-6 max-h-96 overflow-y-auto">
            <h2 class="font-bold mb-4">Par produit</h2>
            <table class="w-full text-sm"><thead><tr class="text-left border-b"><th class="pb-2">Produit</th><th class="pb-2">Qté</th><th class="pb-2 text-right">Valeur</th></tr></thead>
            <tbody><?php foreach (($produits ?? []) as $p): ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)$p['nom']) ?></td><td class="py-2"><?= (int)$p['stock_actuel'] ?></td><td class="py-2 text-right"><?= number_format((float)$p['valeur_stock'], 0, ',', ' ') ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
    </div>
</div>
</body>
</html>
