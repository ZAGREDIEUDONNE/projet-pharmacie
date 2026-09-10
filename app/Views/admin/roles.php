<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Gestion des roles') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <?php
        $adminActive = 'roles';
        require __DIR__ . '/../partials/admin-header.php';
    ?>

    <main class="admin-container">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Roles & Permissions</h1>
            <p class="text-sm text-gray-500">Consultation des roles disponibles dans le systeme</p>
        </div>

        <div id="permissions" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($roles ?? [] as $role): ?>
                <div class="admin-card p-5">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">
                                <?= htmlspecialchars($role['nom'] ?? 'Role') ?>
                            </h2>
                            <p class="text-sm text-gray-500">ID <?= (int)($role['id'] ?? 0) ?></p>
                        </div>
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold">
                            <?= (int)($permissionsByRole[(int)($role['id'] ?? 0)] ?? 0) ?> permissions
                        </span>
                    </div>

                    <p class="text-gray-600 min-h-12">
                        <?= htmlspecialchars($role['description'] ?? 'Aucune description renseignee') ?>
                    </p>

                    <div class="mt-4 text-xs text-gray-600 space-y-2">
                        <div>Statut : <strong><?= !empty($role['is_actif']) && !empty($role['statut']) ? 'Actif' : 'Inactif' ?></strong></div>
                        <?php foreach (($permissionsDetailByRole[(int)($role['id'] ?? 0)] ?? []) as $module => $permissions): ?>
                            <div>
                                <strong><?= htmlspecialchars(ucfirst((string)$module)) ?></strong>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <?php foreach ($permissions as $permission): ?>
                                        <span class="px-2 py-1 rounded bg-gray-100"><?= htmlspecialchars($permission) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                        <div class="mt-5 pt-4 border-t border-gray-200 text-sm text-gray-500">
                            Cree le <?= htmlspecialchars($role['created_at'] ?? '') ?>
                        </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($roles)): ?>
            <div class="admin-card p-10 text-center text-gray-500">
                Aucun role trouve.
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
