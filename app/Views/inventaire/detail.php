<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Détail inventaire')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-4">
        <a href="/inventaire" class="text-blue-600">← Inventaires</a>
        <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded">
            <i class="fas fa-print mr-2"></i>Imprimer le rapport
        </button>
    </div>
    <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars((string)($inventaire['reference'] ?? '')) ?></h1>
    <p class="text-sm text-gray-600 mb-6">Statut : <?= htmlspecialchars((string)($inventaire['statut'] ?? '')) ?> — Écart valeur : <?= number_format((float)($inventaire['total_ecart_valeur'] ?? 0), 0, ',', ' ') ?> FCFA</p>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm"><thead><tr class="bg-gray-50 text-left"><th class="p-3">Produit</th><th class="p-3">Théorique</th><th class="p-3">Compté</th><th class="p-3">Écart qté</th><th class="p-3 text-right">Écart valeur</th></tr></thead>
        <tbody><?php foreach (($articles ?? []) as $a): ?><tr class="border-t">
            <td class="p-3"><?= htmlspecialchars((string)($a['produit_nom'] ?? '')) ?></td>
            <td class="p-3"><?= (int)($a['quantite_theorique'] ?? 0) ?></td>
            <td class="p-3"><?= (int)($a['quantite_comptee'] ?? 0) ?></td>
            <td class="p-3"><?= (int)($a['ecart_quantite'] ?? 0) ?></td>
            <td class="p-3 text-right"><?= number_format((float)($a['ecart_valeur'] ?? 0), 0, ',', ' ') ?></td>
        </tr><?php endforeach; ?></tbody></table>
    </div>
</div></body></html>
