<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Encaissements') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-arrow-down text-green-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Encaissements</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? ($user['username'] ?? '')) ?></span>
                    <a href="/caisse" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
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

        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Référence ou description">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                    <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 w-full">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Liste des encaissements -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Liste des encaissements</h2>
                <div class="space-x-2">
                    <a href="/caisse/rapports/export?format=excel&date_debut=<?= urlencode($date_debut ?? '') ?>&date_fin=<?= urlencode($date_fin ?? '') ?>" 
                       class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm">
                        <i class="fas fa-file-excel mr-1"></i>Excel
                    </a>
                    <a href="/caisse/rapports/export?format=pdf&date_debut=<?= urlencode($date_debut ?? '') ?>&date_fin=<?= urlencode($date_fin ?? '') ?>" 
                       class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-sm">
                        <i class="fas fa-file-pdf mr-1"></i>PDF
                    </a>
                </div>
            </div>

            <?php if (!empty($encaissements)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Session</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($encaissements as $encaissement): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= !empty($encaissement['date_mouvement']) ? date('d/m/Y H:i', strtotime($encaissement['date_mouvement'])) : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($encaissement['numero_session'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?= htmlspecialchars($encaissement['type_mouvement']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= htmlspecialchars($encaissement['reference'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= htmlspecialchars($encaissement['description'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= htmlspecialchars($encaissement['utilisateur_nom'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-600">
                                        <?= number_format((float)($encaissement['montant'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="showDetail(<?= $encaissement['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-800" title="Voir détail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="printReceipt(<?= $encaissement['id'] ?>)" 
                                                    class="text-green-600 hover:text-green-800" title="Imprimer reçu">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3"></i>
                    <p>Aucun encaissement trouvé pour cette période.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function showDetail(id) {
            alert('Détail de l\'encaissement #' + id + '\n\nCette fonctionnalité sera implémentée prochainement.');
        }

        function printReceipt(id) {
            window.print();
        }
    </script>
</body>
</html>
