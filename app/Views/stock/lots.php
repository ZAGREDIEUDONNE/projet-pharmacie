<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des lots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php $canManageStock = in_array((int)($user['role_id'] ?? 0), [1, 3, 4], true); ?>
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-barcode text-purple-600 text-2xl mr-3"></i>
                <h1 class="text-xl font-bold text-gray-800">Gestion des lots</h1>
            </div>
            <div class="flex items-center space-x-3">
                <?php if ($canManageStock): ?>
                    <a href="/stock/ajouter-lot" class="bg-white-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Ajouter lot
                    </a>
                <?php endif; ?>
                <a href="/stock" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Lot</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Fournisseur</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Fabrication</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Peremption</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Jours</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Initiale</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Restante</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($lots ?? []) as $lot): ?>
                            <?php
                                $niveau = (string)($lot['niveau_peremption'] ?? 'NORMAL');
                                $colors = [
                                    'PERIME' => 'bg-red-600 text-white',
                                    'URGENT' => 'bg-red-100 text-red-700',
                                    'ALERTE' => 'bg-yellow-100 text-yellow-700',
                                    'NORMAL' => 'bg-green-100 text-green-700',
                                ];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2 font-semibold"><?= htmlspecialchars((string)($lot['numero_lot'] ?? '')) ?></td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <div><?= htmlspecialchars((string)($lot['produit_nom'] ?? '')) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string)($lot['code_cip'] ?? '')) ?></div>
                                </td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($lot['fournisseur_nom'] ?? '-')) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= !empty($lot['date_fabrication']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$lot['date_fabrication']))) : '-' ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= htmlspecialchars(date('d/m/Y', strtotime((string)($lot['date_peremption'] ?? 'now')))) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= (int)($lot['jours_restants'] ?? 0) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= number_format((float)($lot['quantite_initiale'] ?? 0), 0, ',', ' ') ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?= number_format((float)($lot['quantite_restante'] ?? 0), 0, ',', ' ') ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <span class="<?= $colors[$niveau] ?? 'bg-gray-100 text-gray-700' ?> text-xs font-semibold px-2 py-1 rounded">
                                        <?= htmlspecialchars($niveau) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($lots)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-gray-500 py-8">Aucun lot actif trouve.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
