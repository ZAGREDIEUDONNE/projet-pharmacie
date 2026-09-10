<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Créances clients')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between mb-6"><h1 class="text-2xl font-bold">Suivi des créances clients</h1><a href="/clients" class="bg-gray-600 text-white px-4 py-2 rounded">Clients</a></div>
    <?php if (!empty($_SESSION['success'])): ?><div class="bg-green-50 text-green-800 p-3 rounded mb-4"><?= htmlspecialchars((string)$_SESSION['success']) ?></div><?php unset($_SESSION['success']); endif; ?>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm"><thead><tr class="bg-gray-50 text-left"><th class="p-3">Client</th><th class="p-3 text-right">Dû ventes</th><th class="p-3 text-right">Payé</th><th class="p-3 text-right">Reste</th><th class="p-3 text-right">Solde crédit</th><th class="p-3"></th></tr></thead>
        <tbody><?php foreach (($clients ?? []) as $c): ?><tr class="border-t">
            <td class="p-3"><?= htmlspecialchars(trim((string)($c['nom'] ?? '') . ' ' . (string)($c['prenom'] ?? ''))) ?></td>
            <td class="p-3 text-right"><?= number_format((float)($c['credit_total'] ?? 0), 0, ',', ' ') ?></td>
            <td class="p-3 text-right"><?= number_format((float)($c['montant_paye'] ?? 0), 0, ',', ' ') ?></td>
            <td class="p-3 text-right font-semibold text-red-700"><?= number_format((float)($c['reste_a_payer'] ?? 0), 0, ',', ' ') ?></td>
            <td class="p-3 text-right"><?= number_format((float)($c['solde_credit'] ?? 0), 0, ',', ' ') ?></td>
            <td class="p-3"><a href="/finance/clients/detail?id=<?= (int)$c['id'] ?>" class="text-blue-600">Détail</a></td>
        </tr><?php endforeach; ?></tbody></table>
    </div>
</div></body></html>
