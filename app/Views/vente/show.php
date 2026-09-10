<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Détail vente') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
<main class="max-w-5xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Détail de la vente</h1>
        <div class="space-x-2">
            <a class="px-4 py-2 rounded bg-gray-600 text-white" href="<?= htmlspecialchars($returnTo ?? '/vente') ?>">Retour</a>
            <?php if ($vente): ?><a class="px-4 py-2 rounded bg-blue-600 text-white" href="/vente/impression?id=<?= (int)$vente['id'] ?>&amp;return_to=<?= urlencode($returnTo ?? '/vente') ?>">Imprimer</a><?php endif; ?>
        </div>
    </div>
    <?php if (!$vente): ?>
        <section class="bg-white shadow rounded p-6 text-red-700">La vente demandée est introuvable.</section>
    <?php else: ?>
        <section class="bg-white shadow rounded p-6 mb-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><p class="text-sm text-gray-500">Numéro</p><p class="font-semibold"><?= htmlspecialchars($vente['numero_facture'] ?? '') ?></p></div>
            <div><p class="text-sm text-gray-500">Date</p><p class="font-semibold"><?= htmlspecialchars($vente['date_vente'] ?? '') ?></p></div>
            <div><p class="text-sm text-gray-500">Statut</p><p class="font-semibold"><?= htmlspecialchars($vente['statut_vente'] ?? '') ?></p></div>
            <div><p class="text-sm text-gray-500">Client</p><p class="font-semibold"><?= htmlspecialchars(trim(($vente['client_nom'] ?? '') . ' ' . ($vente['client_prenom'] ?? '')) ?: 'Comptant') ?></p></div>
            <div><p class="text-sm text-gray-500">Vendeur</p><p class="font-semibold"><?= htmlspecialchars($vente['utilisateur_nom'] ?? $vente['vendeur_nom'] ?? '-') ?></p></div>
            <div><p class="text-sm text-gray-500">Paiement</p><p class="font-semibold"><?= htmlspecialchars($vente['type_paiement'] ?? '') ?></p></div>
        </section>
        <section class="bg-white shadow rounded overflow-x-auto">
            <table class="w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Produit</th><th class="p-3 text-right">Qté</th><th class="p-3 text-right">Prix</th><th class="p-3 text-right">Remise</th><th class="p-3 text-right">Total</th></tr></thead>
            <tbody><?php foreach (($vente['articles'] ?? []) as $article): ?><tr class="border-t"><td class="p-3"><?= htmlspecialchars($article['produit_nom'] ?? $article['nom'] ?? '') ?></td><td class="p-3 text-right"><?= (float)($article['quantite'] ?? 0) ?></td><td class="p-3 text-right"><?= number_format((float)($article['prix_unitaire'] ?? 0), 2, ',', ' ') ?></td><td class="p-3 text-right"><?= number_format((float)($article['remise'] ?? 0), 2, ',', ' ') ?> %</td><td class="p-3 text-right"><?= number_format((float)($article['montant_total'] ?? 0), 2, ',', ' ') ?></td></tr><?php endforeach; ?></tbody></table>
            <div class="border-t p-5 grid grid-cols-2 md:grid-cols-5 gap-3 text-right"><div><span class="text-gray-500">HT</span><br><b><?= number_format((float)($vente['montant_ht'] ?? 0), 2, ',', ' ') ?></b></div><div><span class="text-gray-500">TVA</span><br><b><?= number_format((float)($vente['montant_tva'] ?? 0), 2, ',', ' ') ?></b></div><div><span class="text-gray-500">TTC</span><br><b><?= number_format((float)($vente['montant_ttc'] ?? 0), 2, ',', ' ') ?></b></div><div><span class="text-gray-500">Remise</span><br><b><?= number_format((float)($vente['montant_remise'] ?? 0), 2, ',', ' ') ?></b></div><div><span class="text-gray-500">Net</span><br><b><?= number_format((float)($vente['montant_net'] ?? 0), 2, ',', ' ') ?></b></div></div>
        </section>
    <?php endif; ?>
</main>
</body></html>
