<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes automatiques</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php
    $roleLabel = (string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? $_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
    $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
    $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
    $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
    $defaultReturnTo = match (true) {
        in_array($roleCode, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true) => '/commande/dashboard',
        $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
        $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
        default => '/stock',
    };
    $returnTo = (string)($returnTo ?? $_GET['return_to'] ?? $defaultReturnTo);
    $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    $preview = $analyse['produits_a_commander'] ?? [];
    $recentOrders = $analyse['commandes'] ?? [];
    $resume = is_array($resume ?? null) ? $resume : [];
?>
<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <div class="flex items-center">
            <i class="fas fa-robot text-teal-600 text-2xl mr-3"></i>
            <h1 class="text-xl font-bold text-gray-800">Commandes automatiques</h1>
        </div>
        <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
            <i class="fas fa-arrow-left mr-2"></i>Retour
        </a>
    </div>
</nav>

<main class="max-w-7xl mx-auto p-6 space-y-6">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded p-4"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div id="ajax-feedback" class="hidden rounded p-4"></div>

    <?php if (!empty($resume)): ?>
        <section class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Commandes generees</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($resume as $item): ?>
                    <article class="border rounded-lg p-4">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars((string)($item['fournisseur'] ?? '-')) ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?= (int)($item['produits'] ?? 0) ?> produit(s)</p>
                        <a href="/commande/historique" class="inline-block mt-3 text-sm text-blue-600 hover:underline">
                            Commande #<?= (int)($item['commande_id'] ?? 0) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Analyse des besoins</h2>
                <p class="text-gray-600 text-sm mt-1">
                    Produits actifs avec stock &le; seuil d alerte, regroupes par fournisseur.
                </p>
            </div>
            <div class="flex gap-3">
                <button type="button" id="btn-generer-ajax" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-bolt mr-2"></i>Generer (API)
                </button>
                <form method="POST" action="/stock/commandes-automatiques?return_to=<?= urlencode($returnTo) ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                    <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-magic mr-2"></i>Generer les commandes
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($preview)): ?>
            <div class="overflow-x-auto mb-6">
                <table class="w-full border-collapse min-w-[760px]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Seuil</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Qte proposee</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Fournisseur</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2">
                                    <strong><?= htmlspecialchars((string)($item['produit_nom'] ?? '-')) ?></strong>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string)($item['code_cip'] ?? '')) ?></div>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= (int)($item['quantite_disponible'] ?? 0) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= (int)($item['stock_alerte'] ?? 0) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center font-semibold"><?= (int)($item['quantite'] ?? 0) ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($item['fournisseur_nom'] ?? '-')) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        <?= htmlspecialchars((string)($item['niveau_stock'] ?? 'ALERTE')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-gray-500 py-8 mb-6">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                <p>Aucun produit sous seuil pour le moment.</p>
            </div>
        <?php endif; ?>

        <h3 class="text-md font-semibold text-gray-800 mb-3">Commandes automatiques recentes (30 jours)</h3>
        <?php if (!empty($recentOrders)): ?>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse min-w-[700px]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Commande</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Fournisseur</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Produits</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Montant</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($order['numero_commande'] ?? '-')) ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($order['fournisseur_nom'] ?? '-')) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= (int)($order['nombre_produits'] ?? 0) ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-right"><?= number_format((float)($order['montant_total'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= htmlspecialchars((string)($order['statut'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-500">Aucune commande automatique enregistree sur les 30 derniers jours.</p>
        <?php endif; ?>
    </section>
</main>

<script>
document.getElementById('btn-generer-ajax')?.addEventListener('click', async () => {
    const box = document.getElementById('ajax-feedback');
    box.className = 'rounded p-4 bg-blue-50 border border-blue-200 text-blue-800';
    box.textContent = 'Generation en cours...';
    box.classList.remove('hidden');

    try {
        const response = await fetch('/stock/commandes-automatiques?return_to=<?= urlencode($returnTo) ?>', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const payload = await response.json();
        if (!payload.success) {
            throw new Error(payload.message || 'Echec de generation');
        }

        const lines = (payload.data || []).map(item =>
            `${item.fournisseur}: ${item.produits} produit(s), commande #${item.commande_id}`
        );
        box.className = 'rounded p-4 bg-green-50 border border-green-200 text-green-800';
        box.innerHTML = `<strong>${payload.message}</strong>` +
            (lines.length ? `<ul class="mt-2 list-disc pl-5 text-sm">${lines.map(l => `<li>${l}</li>`).join('')}</ul>` : '');
        if (lines.length) {
            setTimeout(() => window.location.reload(), 1500);
        }
    } catch (error) {
        box.className = 'rounded p-4 bg-red-50 border border-red-200 text-red-800';
        box.textContent = error.message;
    }
});
</script>
</body>
</html>
