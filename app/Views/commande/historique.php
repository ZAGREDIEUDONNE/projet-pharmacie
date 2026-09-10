<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Historique commandes')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php
    $returnTo = (string)($returnTo ?? $_GET['return_to'] ?? '/commande/dashboard');
    $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/commande/dashboard';
    $lateOnly = (($filters['retard'] ?? $_GET['retard'] ?? '') === '1');
?>
<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-clipboard-list text-blue-600 mr-3"></i><?= $lateOnly ? 'Commandes fournisseurs en retard' : 'Historique commandes fournisseurs' ?></h1>
        <div class="flex items-center gap-3">
            <a href="/commande/saisie" class="bg-blue-600 text-white px-4 py-2 rounded">Nouvelle commande</a>
            <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-4 py-2 rounded">Retour</a>
        </div>
    </div>
</nav>
<main class="max-w-7xl mx-auto p-6">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full min-w-[900px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Commande</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Fournisseur</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Date</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Livraison prévue</th>
                    <?php if ($lateOnly): ?><th class="p-3 text-center text-xs font-bold uppercase text-gray-500">Retard</th><?php endif; ?>
                    <th class="p-3 text-right text-xs font-bold uppercase text-gray-500">Montant</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Statut</th>
                    <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($orders ?? []) as $order): ?>
                    <?php $statut = strtoupper((string)($order['statut'] ?? '')); ?>
                    <tr class="border-t">
                        <td class="p-3">
                            <strong><?= htmlspecialchars((string)($order['numero_commande'] ?? '-')) ?></strong>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars((string)($order['utilisateur_nom'] ?? '')) ?></div>
                        </td>
                        <td class="p-3"><?= htmlspecialchars((string)($order['fournisseur_nom'] ?? '-')) ?></td>
                        <td class="p-3"><?= htmlspecialchars((string)($order['date_commande'] ?? '-')) ?></td>
                        <td class="p-3"><?= htmlspecialchars((string)($order['date_livraison_prevue'] ?? '-')) ?></td>
                        <?php if ($lateOnly): ?><td class="p-3 text-center font-semibold text-red-600"><?= (int)($order['jours_retard'] ?? 0) ?> jour(s)</td><?php endif; ?>
                        <td class="p-3 text-right"><?= number_format((float)($order['montant_total'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                        <td class="p-3"><span class="px-2 py-1 rounded-full text-xs font-bold bg-gray-100"><?= htmlspecialchars(str_replace('_', ' ', $statut)) ?></span></td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-2">
                                <?php if ($statut === 'BROUILLON'): ?>
                                    <form method="POST" action="/commande/send">
                                        <input type="hidden" name="order_id" value="<?= (int)($order['id'] ?? 0) ?>">
                                        <button class="text-xs bg-blue-600 text-white px-3 py-1 rounded">Envoyer</button>
                                    </form>
                                    <form method="POST" action="/commande/update-status">
                                        <input type="hidden" name="order_id" value="<?= (int)($order['id'] ?? 0) ?>">
                                        <input type="hidden" name="statut" value="VALIDEE">
                                        <button class="text-xs bg-green-600 text-white px-3 py-1 rounded">Valider</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($statut, ['ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE'], true)): ?>
                                    <a href="/commande/reception" class="text-xs bg-emerald-600 text-white px-3 py-1 rounded inline-block">Receptionner</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="<?= $lateOnly ? 8 : 7 ?>" class="p-8 text-center text-gray-500"><?= $lateOnly ? 'Aucune commande en retard.' : 'Aucune commande enregistree.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
