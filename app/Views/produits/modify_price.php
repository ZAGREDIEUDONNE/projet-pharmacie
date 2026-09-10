<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Modifier le prix')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-money-bill-wave text-green-600 mr-3"></i>Modifier le prix</h1>
        <a href="<?= htmlspecialchars($return_to ?? '/produits') ?>" class="bg-gray-600 text-white px-4 py-2 rounded"><i class="fas fa-arrow-left mr-2"></i>Retour</a>
    </div>
</nav>
<main class="max-w-4xl mx-auto p-6">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Informations produit</h2>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Produit</label>
                <p class="text-gray-900 font-semibold"><?= htmlspecialchars($produit['nom'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code CIP</label>
                <p class="text-gray-900"><?= htmlspecialchars($produit['code_cip'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prix d'achat actuel</label>
                <p class="text-gray-900"><?= number_format((float)($produit['prix_achat'] ?? 0), 2, ',', ' ') ?> FCFA</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prix de vente actuel</label>
                <p class="text-gray-900"><?= number_format((float)($produit['prix_vente'] ?? 0), 2, ',', ' ') ?> FCFA</p>
            </div>
            <?php if (!empty($produit['prix_vente_assure'])): ?>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prix de vente assuré actuel</label>
                <p class="text-gray-900"><?= number_format((float)$produit['prix_vente_assure'], 2, ',', ' ') ?> FCFA</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="/produits/modify-price" class="bg-white rounded-lg shadow p-6">
        <input type="hidden" name="produit_id" value="<?= (int)($produit['id'] ?? 0) ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to ?? '/produits') ?>">

        <h2 class="text-lg font-bold text-gray-900 mb-4">Nouveaux prix</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau prix d'achat (facultatif)</label>
                <input type="number" step="0.01" min="0" name="nouveau_prix_achat" 
                       placeholder="<?= number_format((float)($produit['prix_achat'] ?? 0), 2, ',', ' ') ?>"
                       class="border border-gray-300 rounded px-3 py-2 w-full">
                <p class="text-xs text-gray-500 mt-1">Laisser vide pour ne pas modifier</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau prix de vente <span class="text-red-600">*</span></label>
                <input type="number" step="0.01" min="0" name="nouveau_prix_vente" required
                       value="<?= number_format((float)($produit['prix_vente'] ?? 0), 2, ',', ' ') ?>"
                       class="border border-gray-300 rounded px-3 py-2 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau prix de vente assuré (facultatif)</label>
                <input type="number" step="0.01" min="0" name="nouveau_prix_vente_assure"
                       placeholder="<?= !empty($produit['prix_vente_assure']) ? number_format((float)$produit['prix_vente_assure'], 2, ',', ' ') : '' ?>"
                       class="border border-gray-300 rounded px-3 py-2 w-full">
                <p class="text-xs text-gray-500 mt-1">Laisser vide pour ne pas modifier</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date d'application <span class="text-red-600">*</span></label>
                <input type="date" name="date_application" required value="<?= date('Y-m-d') ?>"
                       class="border border-gray-300 rounded px-3 py-2 w-full">
            </div>
        </div>

        <h2 class="text-lg font-bold text-gray-900 mb-4">Motif et observation</h2>

        <div class="grid grid-cols-1 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motif <span class="text-red-600">*</span></label>
                <select name="motif" required class="border border-gray-300 rounded px-3 py-2 w-full">
                    <option value="">Sélectionner un motif</option>
                    <?php foreach ($motifs ?? [] as $key => $value): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observation</label>
                <textarea name="observation" rows="3" placeholder="Détails supplémentaires..."
                          class="border border-gray-300 rounded px-3 py-2 w-full"></textarea>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-yellow-600 mt-1"></i>
                <div>
                    <h3 class="font-semibold text-yellow-800">Règles de validation</h3>
                    <ul class="text-sm text-yellow-700 mt-2 space-y-1">
                        <li>• Les prix ne peuvent pas être négatifs</li>
                        <li>• Les prix ne peuvent pas être égaux à zéro</li>
                        <li>• Le prix de vente ne peut pas être inférieur au prix d'achat</li>
                        <li>• Le motif est obligatoire</li>
                        <li>• Toute modification sera enregistrée dans l'historique</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                <i class="fas fa-save mr-2"></i>Enregistrer la modification
            </button>
            <a href="<?= htmlspecialchars($return_to ?? '/produits') ?>" class="bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-times mr-2"></i>Annuler
            </a>
        </div>
    </form>
</main>
</body>
</html>
