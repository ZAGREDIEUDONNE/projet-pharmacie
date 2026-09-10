<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Utilisateur') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <?php
        $returnTo = (string)(($returnTo ?? null) ?? ($old['return_to'] ?? null) ?? ($_GET['return_to'] ?? '/admin/users'));
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/admin/users';
        $adminActive = 'users';
        require __DIR__ . '/../partials/admin-header.php';
    ?>

    <main class="admin-container" style="max-width: 960px;">
        <div class="admin-page-head">
            <div>
                <h1 class="text-2xl font-bold text-gray-900"><?= !empty($isEdit) ? 'Modifier utilisateur' : 'Nouvel utilisateur' ?></h1>
                <p class="text-sm text-gray-500"><?= !empty($isEdit) ? 'Modification du compte applicatif' : 'Creation d un compte applicatif' ?></p>
            </div>
            <a href="<?= htmlspecialchars($returnTo) ?>" class="admin-button admin-button-muted admin-page-actions">
                <i class="fas fa-arrow-left"></i>Retour
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="font-semibold mb-2">
                    <i class="fas fa-exclamation-circle mr-2"></i>Veuillez corriger les erreurs
                </div>
                <ul class="list-disc pl-6">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($action ?? '/admin/users/store') ?>" class="admin-card p-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Nom utilisateur</label>
                    <input type="text" id="username" name="username" required
                           value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">Prenom</label>
                    <input type="text" id="prenom" name="prenom" required
                           value="<?= htmlspecialchars($old['prenom'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                    <input type="text" id="nom" name="nom" required
                           value="<?= htmlspecialchars($old['nom'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">Telephone</label>
                    <input type="text" id="telephone" name="telephone"
                           value="<?= htmlspecialchars($old['telephone'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="role_id" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select id="role_id" name="role_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Selectionner un role</option>
                        <?php foreach ($roles ?? [] as $role): ?>
                            <option value="<?= (int)$role['id'] ?>" <?= ((int)($old['role_id'] ?? 0) === (int)$role['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['nom'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <?= !empty($isEdit) ? 'Nouveau mot de passe' : 'Mot de passe' ?>
                    </label>
                    <input type="password" id="password" name="password" <?= empty($isEdit) ? 'required' : '' ?> minlength="<?= MIN_PASSWORD_LENGTH ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <?php if (!empty($isEdit)): ?>
                        <p class="text-xs text-gray-500 mt-1">Laisser vide pour conserver le mot de passe actuel.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-2">Confirmation</label>
                    <input type="password" id="password_confirm" name="password_confirm" <?= empty($isEdit) ? 'required' : '' ?> minlength="<?= MIN_PASSWORD_LENGTH ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <label class="flex items-center mt-6">
                <input type="checkbox" name="is_active" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       <?= (($old['is_active'] ?? 1) ? 'checked' : '') ?>>
                <span class="text-sm text-gray-700">Compte actif</span>
            </label>

            <div class="flex justify-end space-x-3 mt-8">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Annuler</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i><?= !empty($isEdit) ? 'Enregistrer' : 'Creer l utilisateur' ?>
                </button>
            </div>
        </form>
    </main>
</body>
</html>
