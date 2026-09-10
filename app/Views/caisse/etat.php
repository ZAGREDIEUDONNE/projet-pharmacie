<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Etat de la Session Caisse') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-chart-line text-green-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Etat de la Session Caisse</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? ($user['username'] ?? '')) ?></span>
                    <?php
                        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
                        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
                        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
                        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
                        $returnTo = match (true) {
                            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
                            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
                            default => '/caisse/session',
                        };
                    ?>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($session)): ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-600">Session</p>
                    <p class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($session['numero_session'] ?? '-') ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-600">Ouverture</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= !empty($session['date_ouverture']) ? date('d/m/Y H:i', strtotime($session['date_ouverture'])) : '-' ?>
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-600">Montant ouverture</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= number_format((float)($session['montant_ouverture'] ?? 0), 0, ',', ' ') ?> FCFA
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-600">Montant theorique</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= number_format((float)$montant_theorique, 0, ',', ' ') ?> FCFA
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-list mr-2"></i>Mouvements de caisse
                </h2>

                <?php if (!empty($mouvements)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($mouvements as $mouvement): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?= !empty($mouvement['date_mouvement']) ? date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])) : '-' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($mouvement['type_mouvement'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?= htmlspecialchars($mouvement['reference'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                            <?= number_format((float)($mouvement['montant'] ?? 0), 0, ',', ' ') ?> FCFA
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>Aucun mouvement enregistre pour cette session.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-yellow-600 text-2xl mr-4"></i>
                    <div>
                        <h2 class="text-lg font-semibold text-yellow-900">Aucune session ouverte</h2>
                        <p class="text-yellow-800 mt-1">Ouvrez une session caisse pour consulter son etat.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
