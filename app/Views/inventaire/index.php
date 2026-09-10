<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Inventaires')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between mb-6"><h1 class="text-2xl font-bold">Inventaires</h1><a href="/inventaire/create" class="bg-green-600 text-white px-4 py-2 rounded">Nouvel inventaire</a></div>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm"><thead><tr class="bg-gray-50 text-left"><th class="p-3">Référence</th><th class="p-3">Type</th><th class="p-3">Statut</th><th class="p-3">Début</th><th class="p-3">Articles</th><th class="p-3"></th></tr></thead>
        <tbody><?php foreach (($inventaires ?? []) as $inv): ?><tr class="border-t">
            <td class="p-3"><?= htmlspecialchars((string)($inv['reference'] ?? '')) ?></td>
            <td class="p-3"><?= htmlspecialchars((string)($inv['type_inventaire'] ?? '')) ?></td>
            <td class="p-3"><span class="px-2 py-1 rounded text-xs bg-gray-100"><?= htmlspecialchars((string)($inv['statut'] ?? '')) ?></span></td>
            <td class="p-3"><?= htmlspecialchars((string)($inv['date_debut'] ?? '')) ?></td>
            <td class="p-3"><?= (int)($inv['nombre_articles'] ?? 0) ?></td>
            <td class="p-3"><?php if (($inv['statut'] ?? '') === 'EN_COURS'): ?><a href="/inventaire/saisie?id=<?= (int)$inv['id'] ?>" class="text-blue-600">Saisir</a><?php else: ?><a href="/inventaire/detail?id=<?= (int)$inv['id'] ?>" class="text-blue-600">Voir</a><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody></table>
    </div>
</div></body></html>
