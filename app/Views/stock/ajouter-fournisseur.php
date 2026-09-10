<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Ajouter un fournisseur')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $old = $_SESSION['old_data'] ?? [];
        $value = function (string $key, $default = '') use ($old) {
            return htmlspecialchars((string)($old[$key] ?? $default));
        };
        $defaultReturnTo = (int)($_SESSION['user']['role_id'] ?? 0) === 1 ? '/admin/dashboard' : '/stock/ajouter-produit';
        $returnTo = (string)($old['return_to'] ?? $_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>

    <nav class="bg-red-600">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-truck text-teal-600 text-2xl mr-3"></i>
                <h1 class="text-xl font-red text-red-800">Ajouter un fournisseur</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">Utilisateur: <?= htmlspecialchars((string)($user['username'] ?? $_SESSION['user']['username'] ?? '')) ?></span>
                <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto p-6">
        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h4 class="text-red-800 font-semibold mb-2">Erreurs</h4>
                <ul class="text-red-700 list-disc ml-5">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars((string)$error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <form method="POST" action="/fournisseurs/store" id="fournisseurForm" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-id-card mr-2 text-teal-600"></i>Informations fournisseur
                </h2>
            </div>

            <div class="mb-6">
                <input type="submit" value="Enregistrer le fournisseur"
                       class="w-full bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-700 font-semibold cursor-pointer">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" value="<?= $value('nom') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Code</label>
                    <input type="text" name="code" value="<?= $value('code') ?>" placeholder="Genere automatiquement si vide"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Telephone</label>
                    <input type="text" name="telephone" value="<?= $value('telephone') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" value="<?= $value('email') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Registre commerce</label>
                    <input type="text" name="registre_commerce" value="<?= $value('registre_commerce') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Compte bancaire</label>
                    <input type="text" name="compte_bancaire" value="<?= $value('compte_bancaire') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Delai livraison (jours)</label>
                    <input type="number" name="delai_livraison" value="<?= $value('delai_livraison', 7) ?>" min="0" step="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                    <textarea name="adresse" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"><?= $value('adresse') ?></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                <input type="submit" value="Enregistrer le fournisseur"
                       class="bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-700 font-semibold cursor-pointer">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>
            </div>
        </form>
        <?php unset($_SESSION['old_data']); ?>
    </main>
</body>
</html>
