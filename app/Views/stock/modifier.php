<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier prix produit</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $returnTo = (string)($returnTo ?? $_GET['return_to'] ?? '/stock');
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/stock';
    ?>
    <nav class="bg-white shadow">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-tags text-yellow-600 mr-2"></i>Modifier les prix</h1>
            <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto p-6">
        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
                <?php foreach ($_SESSION['errors'] as $error): ?>
                    <p class="text-red-700"><?= htmlspecialchars((string)$error) ?></p>
                <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <form method="POST" action="/stock/modifier/<?= (int)$produit['id'] ?>" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <div class="mb-6">
                <p class="text-sm text-gray-500">Produit</p>
                <h2 class="text-2xl font-semibold text-gray-900"><?= htmlspecialchars((string)$produit['nom']) ?></h2>
                <p class="text-gray-600"><?= htmlspecialchars((string)($produit['code_cip'] ?? '')) ?></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix d'achat</label>
                    <input type="number" id="prix_achat" name="prix_achat" value="<?= htmlspecialchars((string)$produit['prix_achat']) ?>" min="0" step="0.01" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-yellow-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix de vente</label>
                    <input type="number" id="prix_vente" name="prix_vente" value="<?= htmlspecialchars((string)$produit['prix_vente']) ?>" min="0" step="0.01" required readonly
                           class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 focus:outline-none">
                    <p class="text-xs text-gray-500 mt-1">Calcule automatiquement : prix d'achat x 1,48</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motif de modification</label>
                    <textarea name="motif" rows="4" required placeholder="Ex: Revalorisation fournisseur, correction catalogue..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-yellow-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">Annuler</a>
                <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded hover:bg-yellow-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </main>
    <script>
        const MARGE_VENTE = 1.48;
        const inputAchat = document.getElementById('prix_achat');
        const inputVente = document.getElementById('prix_vente');

        function updatePrixVente() {
            const prixAchat = parseFloat(inputAchat.value);
            inputVente.value = prixAchat > 0 ? (prixAchat * MARGE_VENTE).toFixed(2) : '';
        }

        inputAchat.addEventListener('input', updatePrixVente);
        updatePrixVente();
    </script>
</body>
</html>
