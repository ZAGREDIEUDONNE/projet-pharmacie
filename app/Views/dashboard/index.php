<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-tachometer-alt text-2xl"></i>
                    <h1 class="text-xl font-bold">Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">
                        <span class="font-semibold">Utilisateur:</span> 
                        <?= htmlspecialchars($user['username'] ?? 'Non connecté') ?>
                    </span>
                    <span class="text-sm">
                        <span class="font-semibold">Rôle:</span> 
                        <?= htmlspecialchars($stats['role'] ?? 'Utilisateur') ?>
                    </span>
                    <a href="/logout" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                        <i class="fas fa-sign-out-alt mr-1"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Welcome Section -->
        <section class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        Bienvenue, <?= htmlspecialchars($user['username'] ?? 'Utilisateur') ?>!
                    </h2>
                    <p class="text-gray-600 mt-2">
                        Accès rapide aux fonctionnalités selon votre rôle: <?= htmlspecialchars($stats['role'] ?? 'Utilisateur') ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">
                        <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/caisse-status.php'; ?>

        <!-- Quick Actions -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php if (isset($stats['actions']) && is_array($stats['actions'])): ?>
                <?php foreach ($stats['actions'] as $index => $action): ?>
                    <?php
                        $actionLabel = is_array($action) ? (string)($action['label'] ?? '') : (string)$action;
                        $actionUrl = is_array($action) ? (string)($action['url'] ?? '/dashboard') : '/dashboard';
                        $actionIcon = is_array($action) ? (string)($action['icon'] ?? 'fa-arrow-circle-right') : 'fa-arrow-circle-right';
                        $actionColor = is_array($action) ? (string)($action['color'] ?? 'text-blue-600') : 'text-blue-600';
                    ?>
                    <a href="<?= htmlspecialchars($actionUrl) ?>" class="block bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:bg-gray-50 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas <?= htmlspecialchars($actionIcon) ?> text-2xl <?= htmlspecialchars($actionColor) ?>"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold"><?= htmlspecialchars($actionLabel) ?></h3>
                                <p class="text-sm text-gray-600">Action rapide</p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Actions par défaut selon le rôle -->
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-bar text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold">Statistiques</h3>
                            <p class="text-sm text-gray-600">Voir les rapports</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-cog text-2xl text-gray-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold">Paramètres</h3>
                            <p class="text-sm text-gray-600">Configuration</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user text-2xl text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold">Profil</h3>
                            <p class="text-sm text-gray-600">Mon compte</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Navigation Modules -->
        <section class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold mb-4">Navigation Rapide</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="/clients" class="border rounded p-4 hover:bg-gray-50 transition">
                    <i class="fas fa-users text-2xl text-blue-600 mb-2"></i>
                    <h4 class="font-semibold">Clients</h4>
                    <p class="text-sm text-gray-600">Gestion des clients</p>
                </a>
                <a href="/vente" class="border rounded p-4 hover:bg-gray-50 transition">
                    <i class="fas fa-shopping-cart text-2xl text-green-600 mb-2"></i>
                    <h4 class="font-semibold">Ventes</h4>
                    <p class="text-sm text-gray-600">Point de vente</p>
                </a>
                <a href="/caisse/session" class="border rounded p-4 hover:bg-gray-50 transition">
                    <i class="fas fa-cash-register text-2xl text-purple-600 mb-2"></i>
                    <h4 class="font-semibold">Caisse</h4>
                    <p class="text-sm text-gray-600">Gestion caisse</p>
                </a>
                <?php if ((int)($user['role_id'] ?? 0) === 1): ?>
                    <a href="/admin/dashboard" class="border rounded p-4 hover:bg-gray-50 transition">
                        <i class="fas fa-cog text-2xl text-gray-600 mb-2"></i>
                        <h4 class="font-semibold">Admin</h4>
                        <p class="text-sm text-gray-600">Administration</p>
                    </a>
                <?php elseif ((int)($user['role_id'] ?? 0) === 3): ?>
                    <a href="/assistant/dashboard" class="border rounded p-4 hover:bg-gray-50 transition">
                        <i class="fas fa-user-tie text-2xl text-orange-600 mb-2"></i>
                        <h4 class="font-semibold">Actions assistant</h4>
                        <p class="text-sm text-gray-600">Toutes les actions autorisees</p>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
