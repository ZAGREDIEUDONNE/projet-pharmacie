<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Annulations') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-ban text-orange-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Annulations</h1>
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
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Référence ou motif">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type d'opération</label>
                    <select name="type_operation" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Tous</option>
                        <option value="vente" <?= ($type_operation ?? '') === 'vente' ? 'selected' : '' ?>>Vente</option>
                        <option value="paiement" <?= ($type_operation ?? '') === 'paiement' ? 'selected' : '' ?>>Paiement</option>
                        <option value="mouvement_caisse" <?= ($type_operation ?? '') === 'mouvement_caisse' ? 'selected' : '' ?>>Mouvement Caisse</option>
                    </select>
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
                <div class="flex items-end md:col-span-4">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Liste des annulations -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-history mr-2"></i>Historique des Annulations
            </h2>

            <?php if (!empty($annulations)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date annulation</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motif</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($annulations as $annulation): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= !empty($annulation['date_annulation']) ? date('d/m/Y H:i', strtotime($annulation['date_annulation'])) : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">
                                            <?= htmlspecialchars(strtoupper($annulation['type_document'] ?? 'AUTRE')) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= htmlspecialchars($annulation['reference_operation'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= htmlspecialchars($annulation['motif'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= htmlspecialchars($annulation['utilisateur_nom'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-orange-600">
                                        <?= number_format((float)($annulation['montant_operation'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="showDetail(<?= $annulation['id'] ?>, '<?= htmlspecialchars($annulation['type_document'] ?? '') ?>', <?= $annulation['document_id'] ?? 0 ?>)" 
                                                    class="text-blue-600 hover:text-blue-800" title="Voir détail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="printJustificatif(<?= $annulation['id'] ?>)" 
                                                    class="text-green-600 hover:text-green-800" title="Imprimer justificatif">
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
                    <i class="fas fa-check-circle text-4xl mb-3"></i>
                    <p>Aucune annulation enregistrée pour cette période.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contrôles de sécurité -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mt-6">
            <h3 class="font-semibold text-yellow-800 mb-2">
                <i class="fas fa-shield-alt mr-2"></i>Contrôles de Sécurité
            </h3>
            <ul class="text-sm text-yellow-700 space-y-1">
                <li>• Toutes les annulations sont tracées dans le journal d'audit</li>
                <li>• L'utilisateur ayant effectué l'annulation est enregistré</li>
                <li>• Le motif de l'annulation est obligatoire</li>
                <li>• Les annulations nécessitent une autorisation (code PIN ou mot de passe)</li>
                <li>• Les rapports d'annulations sont générés automatiquement</li>
            </ul>
        </div>
    </main>

    <script>
        function showDetail(id, type, documentId) {
            let message = 'Détail de l\'annulation #' + id + '\n';
            message += 'Type: ' + type.toUpperCase() + '\n';
            message += 'Document ID: ' + documentId + '\n\n';
            message += 'Cette fonctionnalité sera implémentée prochainement.';
            alert(message);
        }

        function printJustificatif(id) {
            window.print();
        }
    </script>
</body>
</html>
