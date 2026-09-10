<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Ajustement de Stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-sliders text-red-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Ajustement de Stock</h1>
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

        <form method="POST" action="/produits/ajustement-stock/store" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Produit <span class="text-red-500">*</span></label>
                    <select name="produit_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner un produit</option>
                        <?php foreach (($produits ?? []) as $produit): ?>
                            <?php $selected = (($_SESSION['old_data']['produit_id'] ?? '') == $produit['id']) ? 'selected' : ''; ?>
                            <option value="<?= $produit['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($produit['nom']) ?> (<?= htmlspecialchars($produit['code_cip']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nouveau Stock <span class="text-red-500">*</span></label>
                    <input type="number" name="nouveau_stock" value="<?= htmlspecialchars($_SESSION['old_data']['nouveau_stock'] ?? '') ?>" required min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motif <span class="text-red-500">*</span></label>
                    <select name="motif" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner un motif</option>
                        <option value="INVENTAIRE" <?= (($_SESSION['old_data']['motif'] ?? '') == 'INVENTAIRE') ? 'selected' : '' ?>>Inventaire</option>
                        <option value="CASSE" <?= (($_SESSION['old_data']['motif'] ?? '') == 'CASSE') ? 'selected' : '' ?>>Casse</option>
                        <option value="PERTE" <?= (($_SESSION['old_data']['motif'] ?? '') == 'PERTE') ? 'selected' : '' ?>>Perte</option>
                        <option value="ERREUR" <?= (($_SESSION['old_data']['motif'] ?? '') == 'ERREUR') ? 'selected' : '' ?>>Erreur de saisie</option>
                        <option value="AUTRE" <?= (($_SESSION['old_data']['motif'] ?? '') == 'AUTRE') ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observations</label>
                    <textarea name="observations" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($_SESSION['old_data']['observations'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-4">
                <a href="/produits" class="bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">
                    Annuler
                </a>
                <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer l'Ajustement
                </button>
            </div>
        </form>

        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="font-semibold text-yellow-800 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Attention</h3>
            <p class="text-sm text-yellow-700">L'ajustement de stock modifie directement la quantité disponible. Cette action est tracée dans l'historique des mouvements de stock.</p>
        </div>
    </main>

    <?php unset($_SESSION['old_data']); ?>
</body>
</html>
