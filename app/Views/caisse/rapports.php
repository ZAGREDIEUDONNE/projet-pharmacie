<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Rapports Caisse') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php if (isset($print) && $print): ?>
        <style>
            body { background: white; }
            .no-print { display: none !important; }
        </style>
    <?php endif; ?>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-file-lines text-indigo-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Rapports Caisse</h1>
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
        <div class="bg-white rounded-lg shadow p-6 mb-6 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de rapport</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="journalier" <?= ($type_rapport ?? '') === 'journalier' ? 'selected' : '' ?>>Journalier</option>
                        <option value="mensuel" <?= ($type_rapport ?? '') === 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                        <option value="utilisateur" <?= ($type_rapport ?? '') === 'utilisateur' ? 'selected' : '' ?>>Par utilisateur</option>
                        <option value="session" <?= ($type_rapport ?? '') === 'session' ? 'selected' : '' ?>>Par session</option>
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Utilisateur</label>
                    <select name="utilisateur_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Tous</option>
                        <?php foreach ($utilisateurs ?? [] as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($utilisateur_id ?? '') == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex-1">
                        <i class="fas fa-search mr-2"></i>Générer
                    </button>
                    <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Boutons d'export -->
        <div class="flex space-x-2 mb-6 no-print">
            <a href="/caisse/rapports/export?format=excel&date_debut=<?= urlencode($date_debut ?? '') ?>&date_fin=<?= urlencode($date_fin ?? '') ?>&utilisateur_id=<?= urlencode($utilisateur_id ?? '') ?>" 
               class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                <i class="fas fa-file-excel mr-2"></i>Exporter Excel
            </a>
            <a href="/caisse/rapports/export?format=pdf&date_debut=<?= urlencode($date_debut ?? '') ?>&date_fin=<?= urlencode($date_fin ?? '') ?>&utilisateur_id=<?= urlencode($utilisateur_id ?? '') ?>" 
               class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                <i class="fas fa-file-pdf mr-2"></i>Exporter PDF
            </a>
        </div>

        <!-- Rapport -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-chart-bar mr-2"></i>Rapport <?= ucfirst($type_rapport ?? 'journalier') ?>
                </h2>
                <p class="text-sm text-gray-600">
                    Du <?= date('d/m/Y', strtotime($date_debut ?? '')) ?> au <?= date('d/m/Y', strtotime($date_fin ?? '')) ?>
                </p>
            </div>

            <?php if (!empty($sessions)): ?>
                <!-- Résumé -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Sessions</p>
                        <p class="text-2xl font-bold text-blue-600"><?= count($sessions) ?></p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Total Ventes</p>
                        <p class="text-2xl font-bold text-green-600">
                            <?= number_format(array_sum(array_column($sessions, 'total_ventes')), 0, ',', ' ') ?> FCFA
                        </p>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Remboursements</p>
                        <p class="text-2xl font-bold text-red-600">
                            <?= number_format(array_sum(array_column($sessions, 'total_remboursements')), 0, ',', ' ') ?> FCFA
                        </p>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Décaissements</p>
                        <p class="text-2xl font-bold text-orange-600">
                            <?= number_format(array_sum(array_column($sessions, 'total_decaissements')), 0, ',', ' ') ?> FCFA
                        </p>
                    </div>
                </div>

                <!-- Tableau détaillé -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Session</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caissier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ouverture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fermeture</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ventes</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Remb.</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Décaiss.</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Écart</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sessions as $session): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($session['numero_session'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= htmlspecialchars($session['caissier_nom'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= !empty($session['date_ouverture']) ? date('d/m/Y H:i', strtotime($session['date_ouverture'])) : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= !empty($session['date_fermeture']) ? date('d/m/Y H:i', strtotime($session['date_fermeture'])) : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-600">
                                        <?= number_format($session['total_ventes'] ?? 0, 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-red-600">
                                        <?= number_format($session['total_remboursements'] ?? 0, 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-orange-600">
                                        <?= number_format($session['total_decaissements'] ?? 0, 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php 
                                        $ecart = ($session['ecart'] ?? 0);
                                        $color = $ecart >= 0 ? 'text-green-600' : 'text-red-600';
                                        ?>
                                        <span class="<?= $color ?> font-semibold">
                                            <?= number_format(abs($ecart), 0, ',', ' ') ?> FCFA
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                        $statusClass = ($session['statut_session'] ?? '') === 'OUVERTE' ? 'bg-green-100 text-green-800' : 
                                                       (($session['statut_session'] ?? '') === 'FERMEE' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800');
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                            <?= htmlspecialchars($session['statut_session'] ?? '-') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3"></i>
                    <p>Aucune session trouvée pour cette période.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
