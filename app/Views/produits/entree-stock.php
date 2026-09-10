<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Entrée de Stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-plus-circle text-green-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Entrée de Stock</h1>
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

        <form method="POST" action="/produits/entree-stock/store" class="bg-white rounded-lg shadow p-6">
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motif <span class="text-red-500">*</span></label>
                    <select name="motif" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner un motif</option>
                        <option value="DON" <?= (($_SESSION['old_data']['motif'] ?? '') === 'DON') ? 'selected' : '' ?>>Don</option>
                        <option value="RETOUR_FOURNISSEUR" <?= (($_SESSION['old_data']['motif'] ?? '') === 'RETOUR_FOURNISSEUR') ? 'selected' : '' ?>>Retour fournisseur</option>
                        <option value="CORRECTION" <?= (($_SESSION['old_data']['motif'] ?? '') === 'CORRECTION') ? 'selected' : '' ?>>Correction</option>
                        <option value="ACHAT_DIRECT" <?= (($_SESSION['old_data']['motif'] ?? '') === 'ACHAT_DIRECT') ? 'selected' : '' ?>>Achat direct</option>
                        <option value="AUTRE" <?= (($_SESSION['old_data']['motif'] ?? '') === 'AUTRE') ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label>
                    <select name="fournisseur_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach (($fournisseurs ?? []) as $fournisseur): ?>
                            <?php $selected = (($_SESSION['old_data']['fournisseur_id'] ?? '') == $fournisseur['id']) ? 'selected' : ''; ?>
                            <option value="<?= $fournisseur['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($fournisseur['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date d'Entrée</label>
                    <input type="date" name="date_entree" value="<?= htmlspecialchars($_SESSION['old_data']['date_entree'] ?? date('Y-m-d')) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantité <span class="text-red-500">*</span></label>
                    <input type="number" name="quantite" value="<?= htmlspecialchars($_SESSION['old_data']['quantite'] ?? '') ?>" required min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix d'achat <span class="text-red-500">*</span></label>
                    <input type="number" name="prix_achat" value="<?= htmlspecialchars($_SESSION['old_data']['prix_achat'] ?? '') ?>" required min="0" step="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </main>

    <?php unset($_SESSION['old_data']); ?>
</body>
</html>
