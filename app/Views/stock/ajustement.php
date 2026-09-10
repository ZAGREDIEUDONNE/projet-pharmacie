<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Ajustement de stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php
    $old = $_SESSION['old_data'] ?? [];
    $fieldValue = static function (string $key, string $default = '') use ($old): string {
        return htmlspecialchars((string)($old[$key] ?? $default));
    };
    $defaultReturnTo = (string)($returnTo ?? '/stock');
    $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
    $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
?>
<nav class="bg-green-50 shadow">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <div class="flex items-center">
            <i class="fas fa-sliders-h text-green-600 text-2xl mr-3"></i>
            <h1 class="text-xl font-bold text-gray-800">Correction de stock</h1>
        </div>
        <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Retour
        </a>
    </div>
</nav>

<main class="max-w-3xl mx-auto p-6">
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <p class="text-sm text-gray-600">
            Utilisez cette page pour corriger le stock apres inventaire. Le motif est obligatoire pour la tracabilite.
        </p>
    </div>

    <form method="POST" action="/stock/ajustement?return_to=<?= urlencode($returnTo) ?>" class="bg-white rounded-lg shadow p-6 space-y-6">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Produit <span class="text-red-500">*</span></label>
            <select name="produit_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Selectionner un produit</option>
                <?php foreach (($produits ?? []) as $produit): ?>
                    <option value="<?= (int)$produit['id'] ?>" <?= $fieldValue('produit_id') === (string)$produit['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$produit['nom']) ?> (<?= htmlspecialchars((string)($produit['code_cip'] ?? '')) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type de correction <span class="text-red-500">*</span></label>
            <select name="type_mouvement" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="ENTREE" <?= $fieldValue('type_mouvement') === 'ENTREE' ? 'selected' : '' ?>>Entree (augmentation)</option>
                <option value="SORTIE" <?= $fieldValue('type_mouvement') === 'SORTIE' ? 'selected' : '' ?>>Sortie (diminution)</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Quantite <span class="text-red-500">*</span></label>
            <input type="number" name="quantite" value="<?= $fieldValue('quantite') ?>" required min="1" step="1"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Motif <span class="text-red-500">*</span></label>
            <textarea name="motif" rows="4" required placeholder="Ex: ecart inventaire, casse, erreur de saisie..."
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"><?= $fieldValue('motif') ?></textarea>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="<?= htmlspecialchars($returnTo) ?>" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Annuler</a>
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                <i class="fas fa-check mr-2"></i>Valider l ajustement
            </button>
        </div>
    </form>
</main>
</body>
</html>
