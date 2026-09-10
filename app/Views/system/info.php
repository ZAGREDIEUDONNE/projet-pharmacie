<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Informations Systeme') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-server text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Informations Systeme</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                    <a href="/date/change" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-calendar-alt mr-2"></i>Changer la Date
                    </a>
                    <a href="/dashboard" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle mr-2"></i>Etat du Systeme
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600">Date configuree</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= htmlspecialchars(date('d/m/Y', strtotime($systemInfo['current_date'] ?? date('Y-m-d')))) ?>
                    </p>
                </div>

                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600">Heure serveur</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= htmlspecialchars($systemInfo['server_time'] ?? date('Y-m-d H:i:s')) ?>
                    </p>
                </div>

                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600">Version PHP</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= htmlspecialchars($systemInfo['php_version'] ?? PHP_VERSION) ?>
                    </p>
                </div>

                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600">Version MySQL</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= htmlspecialchars($systemInfo['mysql_version'] ?? 'Unknown') ?>
                    </p>
                </div>

                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600">Fuseau horaire</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= htmlspecialchars($systemInfo['timezone'] ?? date_default_timezone_get()) ?>
                    </p>
                </div>

                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600">Memoire utilisee</p>
                    <p class="text-xl font-semibold text-gray-900">
                        <?= number_format((float)($systemInfo['memory_usage'] ?? 0) / 1024 / 1024, 2, ',', ' ') ?> MB
                    </p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
