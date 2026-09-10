<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Gestion des utilisateurs') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <?php
        $adminActive = 'users';
        require __DIR__ . '/../partials/admin-header.php';
    ?>

    <main class="admin-container">
        <div class="admin-page-head">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Utilisateurs</h1>
                <p class="text-sm text-gray-500">Comptes, roles et statuts d'acces</p>
            </div>
            <a href="/admin/users/create" class="admin-button admin-button-primary admin-page-actions">
                <i class="fas fa-user-plus mr-2"></i>Nouvel utilisateur
            </a>
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php foreach ($_SESSION['errors'] as $error): ?>
                    <div><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <form method="GET" action="/admin/users" class="admin-card p-4 mb-5 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Rechercher utilisateur, nom ou email" class="border rounded px-3 py-2">
            <select name="role_id" class="border rounded px-3 py-2">
                <option value="0">Tous les rôles</option>
                <?php foreach ($roles ?? [] as $role): ?>
                    <option value="<?= (int)$role['id'] ?>" <?= (int)($filters['role_id'] ?? 0) === (int)$role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['nom'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <select name="is_active" class="border rounded px-3 py-2">
                <option value="">Tous les statuts</option>
                <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Actif</option>
                <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactif</option>
            </select>
            <button type="submit" class="admin-button admin-button-muted">Rechercher</button>
        </form>

        <div class="admin-card admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creation</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users ?? [] as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?= htmlspecialchars(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: ($user['username'] ?? '')) ?>
                                </div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($user['username'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?= htmlspecialchars($user['email'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?= htmlspecialchars($user['role_name'] ?? 'Non defini') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($user['is_active'])): ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Actif</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($user['created_at'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="/admin/users/<?= (int)$user['id'] ?>/edit" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit mr-1"></i>Modifier
                                </a>
                                <form method="POST" action="/admin/users/<?= (int)$user['id'] ?>/delete" class="inline" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                Aucun utilisateur trouve.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 admin-card p-5">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Roles disponibles</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($roles ?? [] as $role): ?>
                    <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm">
                        <?= htmlspecialchars($role['nom'] ?? '') ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>
