<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche produit — <?= htmlspecialchars((string)($produit['nom'] ?? '')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php
    $returnTo = (string)($returnTo ?? '/stock');
    $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/stock';
    $stock = (int)($produit['quantite_disponible'] ?? 0);
    $seuil = (int)($produit['stock_alerte'] ?? $produit['stock_securite'] ?? 0);
    $statut = $stock <= 0 ? 'RUPTURE' : ($stock <= $seuil ? 'STOCK FAIBLE' : 'DISPONIBLE');
    $statutClass = $stock <= 0 ? 'bg-red-100 text-red-700' : ($stock <= $seuil ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700');
?>
<nav class="bg-white shadow">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-box text-blue-600 mr-3"></i>Fiche produit</h1>
        <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded"><i class="fas fa-arrow-left mr-2"></i>Retour aux alertes</a>
    </div>
</nav>
<main class="max-w-6xl mx-auto p-6 space-y-6">
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars((string)($produit['nom'] ?? '-')) ?></h2>
                <p class="text-sm text-gray-500 mt-1">Code : <?= htmlspecialchars((string)($produit['code_cip'] ?? '-')) ?></p>
            </div>
            <span class="<?= $statutClass ?> px-3 py-1 rounded-full text-sm font-bold"><?= $statut ?></span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="bg-gray-50 rounded p-4"><p class="text-sm text-gray-500">Stock actuel</p><p class="text-2xl font-bold <?= $stock <= 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= number_format($stock, 0, ',', ' ') ?></p></div>
            <div class="bg-gray-50 rounded p-4"><p class="text-sm text-gray-500">Seuil minimum</p><p class="text-2xl font-bold text-gray-900"><?= number_format($seuil, 0, ',', ' ') ?></p></div>
            <div class="bg-gray-50 rounded p-4"><p class="text-sm text-gray-500">Fournisseur</p><p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars((string)($produit['fournisseur'] ?? '-')) ?></p></div>
        </div>
    </section>

    <section class="bg-white rounded-lg shadow overflow-x-auto">
        <div class="p-4 border-b"><h2 class="font-semibold text-gray-900">Lots</h2></div>
        <table class="w-full min-w-[700px]">
            <thead class="bg-gray-50"><tr><th class="p-3 text-left text-xs uppercase text-gray-500">Lot</th><th class="p-3 text-left text-xs uppercase text-gray-500">Péremption</th><th class="p-3 text-center text-xs uppercase text-gray-500">Quantité</th><th class="p-3 text-center text-xs uppercase text-gray-500">Statut</th></tr></thead>
            <tbody>
            <?php foreach (($lots ?? []) as $lot): ?>
                <tr class="border-t"><td class="p-3"><?= htmlspecialchars((string)($lot['numero_lot'] ?? '-')) ?></td><td class="p-3"><?= !empty($lot['date_peremption']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$lot['date_peremption']))) : '-' ?></td><td class="p-3 text-center"><?= number_format((float)($lot['quantite_restante'] ?? 0), 0, ',', ' ') ?></td><td class="p-3 text-center"><?= htmlspecialchars((string)($lot['niveau_peremption'] ?? '-')) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($lots)): ?><tr><td colspan="4" class="p-6 text-center text-gray-500">Aucun lot actif.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
