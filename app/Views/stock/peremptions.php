<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peremptions</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $resume = $analyse['resume'] ?? [];
        $allLots = array_merge(
            array_values($analyse['perimes'] ?? []),
            array_values($analyse['urgents'] ?? []),
            array_values($analyse['alertes'] ?? []),
            array_values($analyse['attentions'] ?? [])
        );

        $roleLabel = (string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? $_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $roleId = (int)($user['role_id'] ?? $_SESSION['user']['role_id'] ?? 0);
        $defaultReturnTo = match (true) {
            in_array($roleCode, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true) => '/commande/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/stock',
        };
        $returnTo = (string)($returnTo ?? $_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>

    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-clock text-red-600 text-2xl mr-3"></i>
                <h1 class="text-xl font-bold text-gray-800">Peremptions</h1>
            </div>
            <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Perimes</p>
                <p class="text-2xl font-bold text-red-600"><?= (int)($resume['perimes'] ?? 0) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Urgents</p>
                <p class="text-2xl font-bold text-red-500"><?= (int)($resume['urgents'] ?? 0) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Alertes</p>
                <p class="text-2xl font-bold text-yellow-600"><?= (int)($resume['alertes'] ?? 0) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Valeur en risque</p>
                <p class="text-2xl font-bold text-gray-800"><?= number_format((float)($analyse['valeur_en_risque'] ?? 0), 0, ',', ' ') ?> FCFA</p>
            </div>
        </div>

        <form method="POST" action="/stock/traiter-perimes?return_to=<?= urlencode($returnTo) ?>" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Lots a surveiller</h2>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-check mr-2"></i>Traiter les lots selectionnes
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-center">Traiter</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Lot</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Peremption</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Jours</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Quantite</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Valeur</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allLots as $lot): ?>
                            <?php
                                $niveau = (string)($lot['niveau_peremption'] ?? 'NORMAL');
                                $isPerime = $niveau === 'PERIME';
                                $colors = [
                                    'PERIME' => 'bg-red-600 text-white',
                                    'URGENT' => 'bg-red-100 text-red-700',
                                    'ALERTE' => 'bg-yellow-100 text-yellow-700',
                                    'ATTENTION' => 'bg-blue-100 text-blue-700',
                                ];
                                $valeur = (float)($lot['quantite_restante'] ?? 0) * (float)($lot['prix_achat_unitaire'] ?? 0);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <input type="checkbox" name="lots_perimes[]" value="<?= (int)($lot['lot_id'] ?? 0) ?>" <?= $isPerime ? '' : 'disabled' ?>>
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <div class="font-semibold"><?= htmlspecialchars((string)($lot['produit_nom'] ?? '')) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string)($lot['code_cip'] ?? '')) ?></div>
                                </td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($lot['numero_lot'] ?? '')) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= htmlspecialchars(date('d/m/Y', strtotime((string)($lot['date_peremption'] ?? 'now')))) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= (int)($lot['jours_restants'] ?? 0) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= number_format((float)($lot['quantite_restante'] ?? 0), 0, ',', ' ') ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-right"><?= number_format($valeur, 0, ',', ' ') ?> FCFA</td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <span class="<?= $colors[$niveau] ?? 'bg-gray-100 text-gray-700' ?> text-xs font-semibold px-2 py-1 rounded">
                                        <?= htmlspecialchars($niveau) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($allLots)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-gray-500 py-8">Aucune peremption a signaler sur les 180 prochains jours.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </main>
</body>
</html>
