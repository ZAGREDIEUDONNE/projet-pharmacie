<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Historique des modifications de prix')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-history text-blue-600 mr-3"></i>Historique des modifications de prix</h1>
        <div class="flex items-center gap-3">
            <a href="/produits" class="bg-gray-600 text-white px-4 py-2 rounded"><i class="fas fa-arrow-left mr-2"></i>Retour</a>
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

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-edit text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total modifications</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format((int)($stats['total_modifications'] ?? 0), 0, ',', ' ') ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-box text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Produits modifiés</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format((int)($stats['produits_modifies'] ?? 0), 0, ',', ' ') ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Utilisateurs</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format((int)($stats['utilisateurs_modificateurs'] ?? 0), 0, ',', ' ') ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-amber-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Augmentation moyenne</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format((float)($stats['augmentation_moyenne'] ?? 0), 2, ',', ' ') ?> FCFA</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="/produits/price-history" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                       placeholder="Produit, code CIP..." class="border border-gray-300 rounded px-3 py-2 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motif</label>
                <select name="motif" class="border border-gray-300 rounded px-3 py-2 w-full">
                    <option value="">Tous</option>
                    <?php foreach ($motifs ?? [] as $key => $value): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($filters['motif'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                <select name="utilisateur_id" class="border border-gray-300 rounded px-3 py-2 w-full">
                    <option value="">Tous</option>
                    <?php foreach ($utilisateurs ?? [] as $user): ?>
                        <option value="<?= (int)$user['id'] ?>" <?= ($filters['utilisateur_id'] ?? '') == $user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
                <select name="periode" class="border border-gray-300 rounded px-3 py-2 w-full">
                    <option value="">Toutes</option>
                    <option value="today" <?= ($filters['periode'] ?? '') === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                    <option value="week" <?= ($filters['periode'] ?? '') === 'week' ? 'selected' : '' ?>>Cette semaine</option>
                    <option value="month" <?= ($filters['periode'] ?? '') === 'month' ? 'selected' : '' ?>>Ce mois</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                <input type="date" name="date_debut" value="<?= htmlspecialchars($filters['date_debut'] ?? '') ?>" 
                       class="border border-gray-300 rounded px-3 py-2 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                <input type="date" name="date_fin" value="<?= htmlspecialchars($filters['date_fin'] ?? '') ?>" 
                       class="border border-gray-300 rounded px-3 py-2 w-full">
            </div>
            <div class="md:col-span-2 flex gap-2 items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded flex-1"><i class="fas fa-filter mr-2"></i>Filtrer</button>
                <a href="/produits/price-history" class="bg-gray-600 text-white px-4 py-2 rounded flex-1"><i class="fas fa-times mr-2"></i>Réinitialiser</a>
                <a href="/produits/price-history/export?<?= http_build_query($filters) ?>" class="bg-green-600 text-white px-4 py-2 rounded flex-1"><i class="fas fa-file-excel mr-2"></i>Excel</a>
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded flex-1"><i class="fas fa-print mr-2"></i>Imprimer</button>
            </div>
        </form>
    </div>

    <!-- Tableau historique -->
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full min-w-[1200px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Date/Heure</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Produit</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Code CIP</th>
                    <th class="p-3 text-right text-xs font-bold uppercase text-gray-500">Ancien prix achat</th>
                    <th class="p-3 text-right text-xs font-bold uppercase text-gray-500">Nouveau prix achat</th>
                    <th class="p-3 text-right text-xs font-bold uppercase text-gray-500">Ancien prix vente</th>
                    <th class="p-3 text-right text-xs font-bold uppercase text-gray-500">Nouveau prix vente</th>
                    <th class="p-3 text-right text-xs font-bold uppercase text-gray-500">Différence</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Motif</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Utilisateur</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Observation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history ?? [] as $record): ?>
                    <?php 
                    $diffVente = ($record['nouveau_prix_vente'] ?? 0) - ($record['ancien_prix_vente'] ?? 0);
                    $diffClass = $diffVente > 0 ? 'text-green-600' : ($diffVente < 0 ? 'text-red-600' : 'text-gray-600');
                    ?>
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-3">
                            <div class="text-sm font-medium text-gray-900"><?= date('d/m/Y', strtotime($record['created_at'])) ?></div>
                            <div class="text-xs text-gray-500"><?= date('H:i', strtotime($record['created_at'])) ?></div>
                        </td>
                        <td class="p-3">
                            <strong class="text-gray-900"><?= htmlspecialchars($record['produit_nom']) ?></strong>
                        </td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($record['code_cip'] ?? '-') ?></td>
                        <td class="p-3 text-right text-gray-600"><?= number_format((float)($record['ancien_prix_achat'] ?? 0), 2, ',', ' ') ?> FCFA</td>
                        <td class="p-3 text-right text-gray-900"><?= $record['nouveau_prix_achat'] ? number_format((float)$record['nouveau_prix_achat'], 2, ',', ' ') . ' FCFA' : '-' ?></td>
                        <td class="p-3 text-right text-gray-600"><?= number_format((float)($record['ancien_prix_vente'] ?? 0), 2, ',', ' ') ?> FCFA</td>
                        <td class="p-3 text-right text-gray-900 font-semibold"><?= number_format((float)($record['nouveau_prix_vente'] ?? 0), 2, ',', ' ') ?> FCFA</td>
                        <td class="p-3 text-right <?= $diffClass ?> font-semibold">
                            <?= $diffVente != 0 ? ($diffVente > 0 ? '+' : '') . number_format($diffVente, 2, ',', ' ') . ' FCFA' : '-' ?>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">
                                <?= htmlspecialchars($record['motif']) ?>
                            </span>
                        </td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($record['utilisateur_nom']) ?></td>
                        <td class="p-3 text-gray-500 text-sm max-w-xs truncate" title="<?= htmlspecialchars($record['observation'] ?? '') ?>">
                            <?= htmlspecialchars($record['observation'] ?? '-') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                    <tr><td colspan="11" class="p-8 text-center text-gray-500">Aucune modification de prix enregistrée.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($history)): ?>
    <div class="mt-4 text-sm text-gray-500">
        <p><?= count($history) ?> modification(s) affichée(s)</p>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
