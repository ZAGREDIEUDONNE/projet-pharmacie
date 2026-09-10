<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des prix</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php
    $defaultReturnTo = (string)($returnTo ?? '/stock');
    $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
    $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
?>
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-history text-yellow-600 mr-2"></i>Historique des modifications de prix</h1>
            <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="border px-4 py-2">Date</th>
                        <th class="border px-4 py-2">Produit</th>
                        <th class="border px-4 py-2 text-right">Ancien achat</th>
                        <th class="border px-4 py-2 text-right">Nouvel achat</th>
                        <th class="border px-4 py-2 text-right">Ancienne vente</th>
                        <th class="border px-4 py-2 text-right">Nouvelle vente</th>
                        <th class="border px-4 py-2">Utilisateur</th>
                        <th class="border px-4 py-2">Motif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($historique ?? []) as $item): ?>
                        <tr>
                            <td class="border px-4 py-2"><?= htmlspecialchars((string)$item['changed_at']) ?></td>
                            <td class="border px-4 py-2">
                                <div class="font-semibold"><?= htmlspecialchars((string)$item['produit_nom']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string)$item['code_cip']) ?></div>
                            </td>
                            <td class="border px-4 py-2 text-right"><?= number_format((float)$item['old_prix_achat'], 2, ',', ' ') ?></td>
                            <td class="border px-4 py-2 text-right"><?= number_format((float)$item['new_prix_achat'], 2, ',', ' ') ?></td>
                            <td class="border px-4 py-2 text-right"><?= number_format((float)$item['old_prix_vente'], 2, ',', ' ') ?></td>
                            <td class="border px-4 py-2 text-right"><?= number_format((float)$item['new_prix_vente'], 2, ',', ' ') ?></td>
                            <td class="border px-4 py-2"><?= htmlspecialchars((string)($item['utilisateur_nom'] ?? '')) ?></td>
                            <td class="border px-4 py-2"><?= htmlspecialchars((string)$item['motif']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($historique)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-gray-500 py-8">Aucune modification de prix enregistree.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
