<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Statistiques Clients')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $stats = $stats ?? [];
        $clientsActifs = $clients_actifs ?? [];
        $money = static function ($value): string {
            return number_format((float)$value, 2, ',', ' ') . ' FCFA';
        };
        $number = static function ($value): string {
            return number_format((float)$value, 0, ',', ' ');
        };
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $defaultReturnTo = match (true) {
            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/clients',
        };
        $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>

    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-chart-bar text-2xl"></i>
                    <h1 class="text-xl font-bold">STATISTIQUES CLIENTS</h1>
                </div>
                <div class="flex items-center space-x-3 text-sm">
                    <span>Utilisateur: <?= htmlspecialchars((string)($user['username'] ?? $_SESSION['username'] ?? 'Non connecte')) ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded">
                        <i class="fas fa-arrow-left mr-1"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-sm text-gray-500">Total clients</div>
                <div class="text-3xl font-bold text-gray-800"><?= $number($stats['total_clients'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-sm text-gray-500">Clients debiteurs</div>
                <div class="text-3xl font-bold text-red-600"><?= $number($stats['clients_debiteurs'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-sm text-gray-500">Total debiteur</div>
                <div class="text-2xl font-bold text-red-600"><?= $money($stats['total_debiteur'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-sm text-gray-500">Plafond credit total</div>
                <div class="text-2xl font-bold text-blue-600"><?= $money($stats['total_plafond'] ?? 0) ?></div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-layer-group mr-2 text-blue-600"></i>Repartition par type
                </h2>
                <div class="space-y-3">
                    <?php
                        $types = [
                            'Ordinaire' => $stats['ordinaires'] ?? 0,
                            'Courant' => $stats['courants'] ?? 0,
                            'Courant - Dépôt' => $stats['courants_depot'] ?? 0,
                            'Courant - Bon' => $stats['courants_bon'] ?? 0,
                            'Courant - Carnet' => $stats['courants_carnet'] ?? 0,
                            'Autres clients' => $stats['autres_clients'] ?? 0,
                        ];
                    ?>
                    <?php foreach ($types as $label => $count): ?>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600"><?= htmlspecialchars($label) ?></span>
                            <span class="font-semibold"><?= $number($count) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">
                        <i class="fas fa-star mr-2 text-purple-600"></i>Clients les plus actifs
                    </h2>
                    <span class="text-sm text-gray-500">Top 10</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-3 py-2 text-left">Client</th>
                                <th class="border border-gray-200 px-3 py-2 text-center">Type</th>
                                <th class="border border-gray-200 px-3 py-2 text-right">Achats</th>
                                <th class="border border-gray-200 px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientsActifs as $client): ?>
                                <tr>
                                    <td class="border border-gray-200 px-3 py-2">
                                        <a href="/clients/fiche?id=<?= (int)($client['id'] ?? 0) ?>" class="font-semibold text-blue-700 hover:text-blue-900">
                                            <?= htmlspecialchars(trim((string)($client['nom'] ?? '') . ' ' . (string)($client['prenom'] ?? ''))) ?>
                                        </a>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string)($client['code'] ?? '')) ?></div>
                                    </td>
                                    <td class="border border-gray-200 px-3 py-2 text-center">
                                        <?= htmlspecialchars(\App\Models\Client::getClientTypeLabel((string)($client['type_client'] ?? 'ORDINAIRE'))) ?>
                                    </td>
                                    <td class="border border-gray-200 px-3 py-2 text-right">
                                        <?= $number($client['nombre_achats'] ?? 0) ?>
                                    </td>
                                    <td class="border border-gray-200 px-3 py-2 text-right">
                                        <?= $money($client['total_achats'] ?? 0) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($clientsActifs)): ?>
                                <tr>
                                    <td colspan="4" class="border border-gray-200 px-3 py-6 text-center text-gray-500">
                                        Aucun client trouve
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
