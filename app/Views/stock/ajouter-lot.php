<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un lot - Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $old = $_SESSION['old_data'] ?? [];
        $value = function (string $key, $default = '') use ($old) {
            return htmlspecialchars((string)($old[$key] ?? $default));
        };
    ?>

    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-boxes text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Ajouter un lot</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/stock" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars((string)$_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form method="POST" action="/stock/ajouter-lot" class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-plus-circle mr-2"></i>Informations du lot
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Produit <span class="text-red-500">*</span></label>
                    <select name="produit_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">Selectionner un produit</option>
                        <?php foreach (($produits ?? []) as $produit): ?>
                            <?php $selected = (string)($old['produit_id'] ?? '') === (string)($produit['id'] ?? ''); ?>
                            <option value="<?= (int)($produit['id'] ?? 0) ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($produit['nom'] ?? '')) ?><?= !empty($produit['code_cip']) ? ' - ' . htmlspecialchars((string)$produit['code_cip']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Numero de lot <span class="text-red-500">*</span></label>
                    <input type="text" name="numero_lot" value="<?= $value('numero_lot') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date fabrication</label>
                    <input type="date" name="date_fabrication" value="<?= $value('date_fabrication') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date peremption <span class="text-red-500">*</span></label>
                    <input type="date" name="date_peremption" value="<?= $value('date_peremption') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantite <span class="text-red-500">*</span></label>
                    <input type="number" name="quantite" value="<?= $value('quantite', 1) ?>" min="1" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix achat unitaire</label>
                    <input type="number" name="prix_achat_unitaire" value="<?= $value('prix_achat_unitaire', 0) ?>" min="0" step="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label>
                    <select name="fournisseur_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="0">Aucun fournisseur</option>
                        <?php foreach (($fournisseurs ?? []) as $fournisseur): ?>
                            <?php $selected = (string)($old['fournisseur_id'] ?? '') === (string)($fournisseur['id'] ?? ''); ?>
                            <option value="<?= (int)($fournisseur['id'] ?? 0) ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($fournisseur['nom'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <a href="/stock" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer le lot
                </button>
            </div>
        </form>
        <?php unset($_SESSION['old_data']); ?>
    </main>
</body>
</html>
