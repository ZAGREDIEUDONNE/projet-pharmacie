<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Dettes fournisseurs')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
<?php
    $returnTo = (string)($_GET['return_to'] ?? '/admin/dashboard');
    if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
        $returnTo = '/admin/dashboard';
    }
    $money = static fn($v): string => number_format((float)$v, 0, ',', ' ') . ' FCFA';
?>
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-file-invoice-dollar text-red-600 mr-2"></i>Suivi des dettes fournisseurs
            </h1>
            <p class="text-sm text-gray-500 mt-1">Factures, acomptes, paiements et reste à payer</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($returnTo) ?>" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <a href="/fournisseurs?return_to=<?= urlencode('/finance/fournisseurs') ?>" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-truck-field"></i> Liste fournisseurs
            </a>
            <a href="/commande/reception" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-dolly"></i> Réception & facture
            </a>
            <a href="/finance/clients" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-hand-holding-dollar"></i> Créances clients
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded-lg mb-4"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['errors'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-lg mb-4">
            <?php foreach ((array)$_SESSION['errors'] as $err): ?>
                <div><?= htmlspecialchars((string)$err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php unset($_SESSION['errors']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left">
                    <th class="p-3">Fournisseur</th>
                    <th class="p-3 text-right">Factures</th>
                    <th class="p-3 text-right">Payé</th>
                    <th class="p-3 text-right">Reste à payer</th>
                    <th class="p-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($fournisseurs ?? []) as $f): ?>
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3 font-medium"><?= htmlspecialchars((string)($f['nom'] ?? '')) ?></td>
                    <td class="p-3 text-right"><?= $money($f['montant_factures'] ?? 0) ?></td>
                    <td class="p-3 text-right text-green-700"><?= $money($f['montant_paye'] ?? 0) ?></td>
                    <td class="p-3 text-right font-semibold text-red-700"><?= $money($f['reste_a_payer'] ?? 0) ?></td>
                    <td class="p-3 text-center">
                        <a href="/finance/fournisseurs/detail?id=<?= (int)$f['id'] ?>" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-eye"></i> Détail
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fournisseurs)): ?>
                <tr>
                    <td colspan="5" class="p-10 text-center text-gray-500">
                        Aucune dette fournisseur enregistrée.
                        <div class="mt-3">
                            <a href="/commande/reception" class="text-green-600 hover:underline font-medium">Enregistrer une réception avec facture</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
