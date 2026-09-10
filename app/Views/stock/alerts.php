<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string)($title ?? 'Alertes Stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-gray-800">Alertes Stock</h2>
            <div class="flex gap-2">
                <a href="<?= htmlspecialchars((string)($returnTo ?? '/stock')) ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
                <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
                <button onclick="exportPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-file-pdf mr-2"></i>PDF
                </button>
                <button onclick="exportExcel()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-file-excel mr-2"></i>Excel
                </button>
            </div>
        </div>

        <!-- Filtres -->
        <form method="GET" action="/stock/alerts" class="bg-white rounded-lg shadow p-4 mb-6">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars((string)($returnTo ?? '')) ?>">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type d'alerte</label>
                    <select name="type" class="border border-gray-300 rounded px-3 py-2 w-full">
                        <option value="">Tous</option>
                        <option value="RUPTURE" <?= ($type_alerte ?? '') === 'RUPTURE' ? 'selected' : '' ?>>Rupture de stock</option>
                        <option value="STOCK_MINIMUM" <?= ($type_alerte ?? '') === 'STOCK_MINIMUM' ? 'selected' : '' ?>>Stock minimum</option>
                        <option value="EXPIRE" <?= ($type_alerte ?? '') === 'EXPIRE' ? 'selected' : '' ?>>Expiré</option>
                        <option value="EXPIRATION_30J" <?= ($type_alerte ?? '') === 'EXPIRATION_30J' ? 'selected' : '' ?>>Expiration 30 jours</option>
                        <option value="EXPIRATION_60J" <?= ($type_alerte ?? '') === 'EXPIRATION_60J' ? 'selected' : '' ?>>Expiration 60 jours</option>
                        <option value="EXPIRATION_90J" <?= ($type_alerte ?? '') === 'EXPIRATION_90J' ? 'selected' : '' ?>>Expiration 90 jours</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Niveau d'urgence</label>
                    <select name="urgence" class="border border-gray-300 rounded px-3 py-2 w-full">
                        <option value="">Tous</option>
                        <option value="CRITIQUE" <?= ($niveau_urgence ?? '') === 'CRITIQUE' ? 'selected' : '' ?>>Critique</option>
                        <option value="HAUT" <?= ($niveau_urgence ?? '') === 'HAUT' ? 'selected' : '' ?>>Haut</option>
                        <option value="MOYEN" <?= ($niveau_urgence ?? '') === 'MOYEN' ? 'selected' : '' ?>>Moyen</option>
                        <option value="FAIBLE" <?= ($niveau_urgence ?? '') === 'FAIBLE' ? 'selected' : '' ?>>Faible</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                </div>
            </div>
        </form>

        <!-- Produits en rupture de stock -->
        <?php if (!empty($ruptures)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-red-600 text-white px-4 py-3 rounded-t-lg">
                <h3 class="font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>Produits en rupture de stock (<?= count($ruptures) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-ruptures">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Code CIP</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil minimum</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Statut</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Date expiration</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau d'urgence</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ruptures as $alert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['nom']) ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-red-600"><?= $alert['stock_actuel'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['stock_alerte'] ?? '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded">RUPTURE</span></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['date_expiration'] ? date('d/m/Y', strtotime($alert['date_expiration'])) : '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded"><?= $alert['niveau_urgence'] ?></span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><a href="/stock/details?id=<?= (int)$alert['id'] ?>&amp;return_to=<?= urlencode((string)($returnTo ?? '/stock')) ?>" class="text-blue-600 hover:underline text-sm">Voir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Produits sous le stock minimum -->
        <?php if (!empty($minimums)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-orange-500 text-white px-4 py-3 rounded-t-lg">
                <h3 class="font-semibold"><i class="fas fa-exclamation-circle mr-2"></i>Produits sous le stock minimum (<?= count($minimums) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-minimums">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Code CIP</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil minimum</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Statut</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Date expiration</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau d'urgence</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($minimums as $alert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['nom']) ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-orange-600"><?= $alert['stock_actuel'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['stock_alerte'] ?? '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><span class="bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-1 rounded">STOCK FAIBLE</span></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['date_expiration'] ? date('d/m/Y', strtotime($alert['date_expiration'])) : '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-1 rounded"><?= $alert['niveau_urgence'] ?></span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><a href="/stock/details?id=<?= (int)$alert['id'] ?>&amp;return_to=<?= urlencode((string)($returnTo ?? '/stock')) ?>" class="text-blue-600 hover:underline text-sm">Voir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Produits expirés -->
        <?php if (!empty($expires)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-red-700 text-white px-4 py-3 rounded-t-lg">
                <h3 class="font-semibold"><i class="fas fa-skull-crossbones mr-2"></i>Produits expirés (<?= count($expires) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-expires">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Code CIP</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil minimum</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Date expiration</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau d'urgence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expires as $alert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2">
                                <?= htmlspecialchars($alert['nom']) ?>
                                <?php if ($alert['lot_numero']): ?>
                                <div class="text-xs text-gray-500">Lot: <?= htmlspecialchars($alert['lot_numero']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-red-600"><?= $alert['stock_actuel'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['stock_alerte'] ?? '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-red-600"><?= date('d/m/Y', strtotime($alert['date_expiration'])) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded"><?= $alert['niveau_urgence'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Produits expirant dans 30 jours -->
        <?php if (!empty($expiring30)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-yellow-500 text-white px-4 py-3 rounded-t-lg">
                <h3 class="font-semibold"><i class="fas fa-clock mr-2"></i>Produits expirant dans 30 jours (<?= count($expiring30) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-expiring30">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Code CIP</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil minimum</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Date expiration</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau d'urgence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring30 as $alert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2">
                                <?= htmlspecialchars($alert['nom']) ?>
                                <?php if ($alert['lot_numero']): ?>
                                <div class="text-xs text-gray-500">Lot: <?= htmlspecialchars($alert['lot_numero']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-yellow-600"><?= $alert['stock_actuel'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['stock_alerte'] ?? '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <?= date('d/m/Y', strtotime($alert['date_expiration'])) ?>
                                <div class="text-xs text-gray-500">Jours restants: <?= $alert['jours_restants'] ?></div>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="bg-yellow-100 text-yellow-700 text-xs font-semibold px-2 py-1 rounded"><?= $alert['niveau_urgence'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Produits expirant dans 60 jours -->
        <?php if (!empty($expiring60)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-blue-500 text-white px-4 py-3 rounded-t-lg">
                <h3 class="font-semibold"><i class="fas fa-calendar-alt mr-2"></i>Produits expirant dans 60 jours (<?= count($expiring60) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-expiring60">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Code CIP</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil minimum</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Date expiration</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau d'urgence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring60 as $alert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2">
                                <?= htmlspecialchars($alert['nom']) ?>
                                <?php if ($alert['lot_numero']): ?>
                                <div class="text-xs text-gray-500">Lot: <?= htmlspecialchars($alert['lot_numero']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-blue-600"><?= $alert['stock_actuel'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['stock_alerte'] ?? '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <?= date('d/m/Y', strtotime($alert['date_expiration'])) ?>
                                <div class="text-xs text-gray-500">Jours restants: <?= $alert['jours_restants'] ?></div>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded"><?= $alert['niveau_urgence'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Produits expirant dans 90 jours -->
        <?php if (!empty($expiring90)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-green-500 text-white px-4 py-3 rounded-t-lg">
                <h3 class="font-semibold"><i class="fas fa-info-circle mr-2"></i>Produits expirant dans 90 jours (<?= count($expiring90) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-expiring90">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Code CIP</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil minimum</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Date expiration</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau d'urgence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring90 as $alert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2">
                                <?= htmlspecialchars($alert['nom']) ?>
                                <?php if ($alert['lot_numero']): ?>
                                <div class="text-xs text-gray-500">Lot: <?= htmlspecialchars($alert['lot_numero']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-green-600"><?= $alert['stock_actuel'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center"><?= $alert['stock_alerte'] ?? '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <?= date('d/m/Y', strtotime($alert['date_expiration'])) ?>
                                <div class="text-xs text-gray-500">Jours restants: <?= $alert['jours_restants'] ?></div>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="bg-green-100 text-green-700 text-xs font-semibold px-2 py-1 rounded"><?= $alert['niveau_urgence'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($ruptures) && empty($minimums) && empty($expires) && empty($expiring30) && empty($expiring60) && empty($expiring90)): ?>
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
            <p class="text-gray-600 text-lg">Aucune alerte de stock détectée.</p>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function exportPDF() {
            alert('Export PDF - Fonctionnalité à implémenter avec une librairie comme jsPDF ou TCPDF');
        }

        function exportExcel() {
            let html = '<table><thead><tr><th>Type</th><th>Produit</th><th>Code CIP</th><th>Stock</th><th>Seuil minimum</th><th>Date expiration</th><th>Niveau urgence</th></tr></thead><tbody>';
            
            <?php if (!empty($ruptures)): ?>
            <?php foreach ($ruptures as $alert): ?>
            html += '<tr><td>Rupture</td><td><?= htmlspecialchars($alert['nom']) ?></td><td><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td><td><?= $alert['stock_actuel'] ?></td><td><?= $alert['stock_alerte'] ?? '-' ?></td><td><?= $alert['date_expiration'] ? date('d/m/Y', strtotime($alert['date_expiration'])) : '-' ?></td><td><?= $alert['niveau_urgence'] ?></td></tr>';
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($minimums)): ?>
            <?php foreach ($minimums as $alert): ?>
            html += '<tr><td>Stock minimum</td><td><?= htmlspecialchars($alert['nom']) ?></td><td><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td><td><?= $alert['stock_actuel'] ?></td><td><?= $alert['stock_alerte'] ?? '-' ?></td><td><?= $alert['date_expiration'] ? date('d/m/Y', strtotime($alert['date_expiration'])) : '-' ?></td><td><?= $alert['niveau_urgence'] ?></td></tr>';
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($expires)): ?>
            <?php foreach ($expires as $alert): ?>
            html += '<tr><td>Expiré</td><td><?= htmlspecialchars($alert['nom']) ?></td><td><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td><td><?= $alert['stock_actuel'] ?></td><td><?= $alert['stock_alerte'] ?? '-' ?></td><td><?= date('d/m/Y', strtotime($alert['date_expiration'])) ?></td><td><?= $alert['niveau_urgence'] ?></td></tr>';
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($expiring30)): ?>
            <?php foreach ($expiring30 as $alert): ?>
            html += '<tr><td>Expiration 30j</td><td><?= htmlspecialchars($alert['nom']) ?></td><td><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td><td><?= $alert['stock_actuel'] ?></td><td><?= $alert['stock_alerte'] ?? '-' ?></td><td><?= date('d/m/Y', strtotime($alert['date_expiration'])) ?></td><td><?= $alert['niveau_urgence'] ?></td></tr>';
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($expiring60)): ?>
            <?php foreach ($expiring60 as $alert): ?>
            html += '<tr><td>Expiration 60j</td><td><?= htmlspecialchars($alert['nom']) ?></td><td><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td><td><?= $alert['stock_actuel'] ?></td><td><?= $alert['stock_alerte'] ?? '-' ?></td><td><?= date('d/m/Y', strtotime($alert['date_expiration'])) ?></td><td><?= $alert['niveau_urgence'] ?></td></tr>';
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($expiring90)): ?>
            <?php foreach ($expiring90 as $alert): ?>
            html += '<tr><td>Expiration 90j</td><td><?= htmlspecialchars($alert['nom']) ?></td><td><?= htmlspecialchars($alert['code_cip'] ?? '-') ?></td><td><?= $alert['stock_actuel'] ?></td><td><?= $alert['stock_alerte'] ?? '-' ?></td><td><?= date('d/m/Y', strtotime($alert['date_expiration'])) ?></td><td><?= $alert['niveau_urgence'] ?></td></tr>';
            <?php endforeach; ?>
            <?php endif; ?>
            
            html += '</tbody></table>';
            
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var link = document.createElement('a');
            link.download = 'alertes_stock_' + new Date().toISOString().slice(0,10) + '.xls';
            link.href = url;
            link.click();
        }
    </script>
</body>
</html>
