<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Parametres Systeme') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <?php
        $adminActive = 'system';
        require __DIR__ . '/../partials/admin-header.php';
    ?>

    <main class="admin-container">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Parametres Systeme</h1>
            <p class="text-sm text-gray-500">Etat general de l'application et de l'environnement</p>
        </div>

        <div class="admin-card overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-2">
                <?php foreach ($systemInfo ?? [] as $label => $value): ?>
                    <div class="px-6 py-4 border-b border-gray-200 md:odd:border-r">
                        <div class="text-sm text-gray-500 uppercase">
                            <?= htmlspecialchars(str_replace('_', ' ', $label)) ?>
                        </div>
                        <div class="text-lg font-semibold text-gray-800 mt-1">
                            <?= htmlspecialchars((string)$value) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>
