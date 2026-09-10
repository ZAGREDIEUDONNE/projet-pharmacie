<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string)($title ?? 'Fournisseurs')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><i class="fas fa-truck-field text-indigo-600 mr-2"></i>Fournisseurs</h1>
        <div class="space-x-2">
            <a href="/fournisseurs/ajouter" class="bg-green-600 text-white px-4 py-2 rounded">Nouveau fournisseur</a>
            <a href="/finance/fournisseurs" class="bg-blue-600 text-white px-4 py-2 rounded">Dettes & paiements</a>
            <a href="<?= htmlspecialchars($_GET['return_to'] ?? '/stock') ?>" class="bg-gray-600 text-white px-4 py-2 rounded">Retour</a>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-left"><th class="p-3">Code</th><th class="p-3">Nom</th><th class="p-3">Téléphone</th><th class="p-3">Produits liés</th><th class="p-3">Actions</th></tr></thead>
            <tbody>
            <?php foreach (($fournisseurs ?? []) as $f): ?>
                <tr class="border-t">
                    <td class="p-3"><?= htmlspecialchars((string)($f['code'] ?? '')) ?></td>
                    <td class="p-3 font-medium"><?= htmlspecialchars((string)($f['nom'] ?? '')) ?></td>
                    <td class="p-3"><?= htmlspecialchars((string)($f['telephone'] ?? '—')) ?></td>
                    <td class="p-3 text-center"><?= (int)($f['nb_produits'] ?? 0) ?></td>
                    <td class="p-3"><a href="/finance/fournisseurs/detail?id=<?= (int)$f['id'] ?>" class="text-blue-600 hover:underline">Suivi financier</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fournisseurs)): ?><tr><td colspan="5" class="p-8 text-center text-gray-500">Aucun fournisseur.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
