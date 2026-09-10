<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Gestion Crédit Client') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $returnTo = (string)($_GET['return_to'] ?? '/admin/dashboard');
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/admin/dashboard';
    ?>
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-users text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Gestion Crédit Client</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Admin: <?= htmlspecialchars($user['name'] ?? 'Admin') ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Dashboard
                    </a>
                    <a href="/logout" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h4 class="text-red-800 font-semibold mb-2">Erreur</h4>
                <p class="text-red-700"><?= htmlspecialchars($_SESSION['error']) ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Crédits des Clients</h2>
                <div class="flex space-x-4">
                    <button onclick="exportCredits()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        <i class="fas fa-download mr-2"></i>Exporter
                    </button>
                    <button onclick="refreshData()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-sync mr-2"></i>Actualiser
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Crédit Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant Payé</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solde Dû</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    Aucun client trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($client['nom']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($client['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium <?= $client['credit_total'] > 0 ? 'text-green-600' : 'text-gray-900' ?>">
                                            <?= number_format($client['credit_total'], 2, ',', ' ') ?> €
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-blue-600">
                                            <?= number_format($client['montant_paye'], 2, ',', ' ') ?> €
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium <?= ($client['credit_total'] - $client['montant_paye']) > 0 ? 'text-red-600' : 'text-gray-900' ?>">
                                            <?= number_format($client['credit_total'] - $client['montant_paye'], 2, ',', ' ') ?> €
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <button onclick="viewClient(<?= $client['id'] ?>)" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editCredit(<?= $client['id'] ?>)" class="text-green-600 hover:text-green-800">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="addPayment(<?= $client['id'] ?>)" class="text-indigo-600 hover:text-indigo-800">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function viewClient(clientId) {
            window.open(`/clients/fiche?id=${clientId}`, '_blank');
        }

        function editCredit(clientId) {
            window.open(`/clients/credit/edit?id=${clientId}`, '_blank');
        }

        function addPayment(clientId) {
            window.open(`/clients/credit/payment?id=${clientId}`, '_blank');
        }

        function exportCredits() {
            window.location.href = '/clients/credit/export';
        }

        function refreshData() {
            window.location.reload();
        }
    </script>
</body>
</html>
