<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Produits Expirés')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-clock text-red-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Produits Expirés</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/produits" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700">Jours d'alerte:</label>
                <select name="jours_alerte" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="7" <?= ($jours_alerte ?? 30) == 7 ? 'selected' : '' ?>>7 jours</option>
                    <option value="15" <?= ($jours_alerte ?? 30) == 15 ? 'selected' : '' ?>>15 jours</option>
                    <option value="30" <?= ($jours_alerte ?? 30) == 30 ? 'selected' : '' ?>>30 jours</option>
                    <option value="60" <?= ($jours_alerte ?? 30) == 60 ? 'selected' : '' ?>>60 jours</option>
                    <option value="90" <?= ($jours_alerte ?? 30) == 90 ? 'selected' : '' ?>>90 jours</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Filtrer
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code CIP</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro Lot</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Péremption</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jours Restants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach (($produits ?? []) as $produit): ?>
                        <tr class="<?= $produit['statut'] === 'EXPIRE' ? 'bg-red-50' : ($produit['statut'] === 'ALERT' ? 'bg-yellow-50' : '') ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($produit['nom']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($produit['code_cip']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($produit['numero_lot']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($produit['date_peremption']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium <?= $produit['jours_restants'] < 0 ? 'text-red-600' : ($produit['jours_restants'] <= ($jours_alerte ?? 30) ? 'text-yellow-600' : 'text-gray-900') ?>">
                                    <?= $produit['jours_restants'] ?> jours
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= $produit['quantite_restante'] ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($produit['statut'] === 'EXPIRE'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Expiré
                                    </span>
                                <?php elseif ($produit['statut'] === 'ALERT'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Alerte
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        OK
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                Aucun produit trouvé
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="font-semibold text-red-800 mb-2"><i class="fas fa-exclamation-circle mr-2"></i>Expirés</h3>
                <p class="text-2xl font-bold text-red-900"><?= count(array_filter($produits ?? [], fn($p) => $p['statut'] === 'EXPIRE')) ?></p>
            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-800 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>En Alerte</h3>
                <p class="text-2xl font-bold text-yellow-900"><?= count(array_filter($produits ?? [], fn($p) => $p['statut'] === 'ALERT')) ?></p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 class="font-semibold text-green-800 mb-2"><i class="fas fa-check-circle mr-2"></i>OK</h3>
                <p class="text-2xl font-bold text-green-900"><?= count(array_filter($produits ?? [], fn($p) => $p['statut'] === 'OK')) ?></p>
            </div>
        </div>
    </main>
</body>
</html>
