<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Fiche Lot')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-boxes text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Fiche Lot</h1>
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
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Informations du lot</h2>
                <span class="px-3 py-1 rounded text-sm font-semibold <?= ($lot['statut_lot'] ?? 'ACTIF') === 'SUSPENDU' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                    <?= htmlspecialchars($lot['statut_lot'] ?? 'ACTIF') ?>
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Numéro de lot</label>
                    <p class="text-gray-900 font-semibold"><?= htmlspecialchars($lot['numero_lot'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Produit</label>
                    <p class="text-gray-900"><?= htmlspecialchars($lot['produit_nom'] ?? '-') ?> (<?= htmlspecialchars($lot['code_cip'] ?? '-') ?>)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Date de fabrication</label>
                    <p class="text-gray-900"><?= $lot['date_fabrication'] ? date('d/m/Y', strtotime($lot['date_fabrication'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Date de péremption</label>
                    <p class="text-gray-900"><?= $lot['date_peremption'] ? date('d/m/Y', strtotime($lot['date_peremption'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Quantité initiale</label>
                    <p class="text-gray-900"><?= number_format((int)($lot['quantite_initiale'] ?? 0), 0, ',', ' ') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Quantité restante</label>
                    <p class="text-gray-900 font-semibold"><?= number_format((int)($lot['quantite_restante'] ?? 0), 0, ',', ' ') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Prix achat unitaire</label>
                    <p class="text-gray-900"><?= number_format((float)($lot['prix_achat_unitaire'] ?? 0), 0, ',', ' ') ?> FCFA</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Fournisseur</label>
                    <p class="text-gray-900"><?= htmlspecialchars($lot['fournisseur_nom'] ?? '-') ?></p>
                </div>
            </div>
        </div>

        <?php if (($lot['statut_lot'] ?? 'ACTIF') === 'SUSPENDU'): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-red-800 mb-4">Informations de suspension</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Motif de suspension</label>
                    <p class="text-gray-900"><?= htmlspecialchars($lot['motif_suspension'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Date de suspension</label>
                    <p class="text-gray-900"><?= $lot['date_suspension'] ? date('d/m/Y', strtotime($lot['date_suspension'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Utilisateur ayant suspendu</label>
                    <p class="text-gray-900"><?= htmlspecialchars($lot['utilisateur_suspension_nom'] ?? '-') ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($lot['statut_lot'] ?? 'ACTIF') === 'ACTIF'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Suspender le lot</h2>
            <form method="POST" action="/produits/suspendre-lot">
                <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif de suspension *</label>
                    <textarea name="motif_suspension" rows="3" class="w-full border rounded-lg px-3 py-2" required placeholder="Expliquez la raison de la suspension..."></textarea>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        <i class="fas fa-ban mr-2"></i>Suspender le lot
                    </button>
                    <a href="/produits" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-times mr-2"></i>Annuler
                    </a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Réactiver le lot</h2>
            <p class="text-gray-600 mb-4">Ce lot est actuellement suspendu. Vous pouvez le réactiver pour le rendre disponible à nouveau.</p>
            <div class="flex space-x-2">
                <a href="/produits/reactiver-lot?id=<?= $lot['id'] ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    <i class="fas fa-check mr-2"></i>Réactiver le lot
                </a>
                <a href="/produits" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
