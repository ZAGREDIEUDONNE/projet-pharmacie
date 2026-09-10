<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Historique des Sorties')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-history text-gray-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Historique des Sorties</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/produits" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour au Dashboard Produits
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
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtres</h2>
            <form method="GET" action="/produits/historique-sorties" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produit</label>
                    <select name="produit_id" class="w-full border rounded-lg px-3 py-2">
                        <option value="">Tous</option>
                        <?php foreach ($produits ?? [] as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($filtre_produit ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                    <select name="utilisateur_id" class="w-full border rounded-lg px-3 py-2">
                        <option value="">Tous</option>
                        <?php foreach ($utilisateurs ?? [] as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($filtre_utilisateur ?? '') == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif</label>
                    <input type="text" name="motif" value="<?= htmlspecialchars($filtre_motif ?? '') ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Rechercher...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                    <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut ?? '') ?>" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin ?? '') ?>" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="md:col-span-5">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    <a href="/produits/historique-sorties" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 ml-2">
                        <i class="fas fa-times mr-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Historique des sorties</h2>
                <div class="space-x-2">
                    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                    <a href="/produits/historique-sorties?export=pdf" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </a>
                    <a href="/produits/historique-sorties?export=excel" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        <i class="fas fa-file-excel mr-2"></i>Excel
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Produit</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Lot</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Quantité</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Avant</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Après</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Motif</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sorties)): ?>
                            <?php foreach ($sorties as $sortie): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= date('d/m/Y H:i', strtotime($sortie['date_mouvement'])) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($sortie['produit_nom'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($sortie['code_cip'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($sortie['numero_lot'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-right text-red-600 font-semibold">-<?= number_format((int)$sortie['quantite'], 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((int)$sortie['quantite_avant'], 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((int)$sortie['quantite_apres'], 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($sortie['motif'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($sortie['utilisateur_nom'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="py-8 text-center text-gray-500">Aucune sortie trouvée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
