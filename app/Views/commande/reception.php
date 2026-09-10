<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Reception produits')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow"><div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between"><h1 class="text-xl font-bold text-gray-800"><i class="fas fa-dolly text-green-600 mr-3"></i>Reception produits</h1><a href="/commande/dashboard" class="bg-gray-600 text-white px-4 py-2 rounded">Retour</a></div></nav>
<main class="max-w-7xl mx-auto p-6">
    <?php if (!empty($_SESSION['error'])): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div><?php unset($_SESSION['error']); endif; ?>
    <form method="POST" action="/commande/reception" id="reception-form" class="bg-white rounded-lg shadow p-6">
        <input type="hidden" name="items_json" id="items_json">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Commande</label><select name="supplier_order_id" id="order" required class="w-full border rounded px-3 py-2"><option value="">Sélectionner</option><?php foreach (($orders ?? []) as $o): ?><option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars((string)$o['numero_commande'] . ' — ' . (string)$o['fournisseur_nom'] . ' — ' . (string)$o['date_commande'] . ' — ' . str_replace('_', ' ', (string)$o['statut'])) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Date reception</label><input type="date" name="date_reception" value="<?= date('Y-m-d') ?>" required class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Numero facture *</label><input type="text" name="numero_facture" required class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Date facture</label><input type="date" name="date_facture" value="<?= date('Y-m-d') ?>" class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Montant facture (FCFA) *</label><input type="number" name="montant_facture" min="0" step="0.01" required class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Reference facture</label><input type="text" name="reference_facture" class="w-full border rounded px-3 py-2"></div>
        </div>
        <div class="overflow-x-auto mb-6"><table class="w-full"><thead><tr class="bg-gray-50"><th class="p-2 text-left">Produit</th><th class="p-2">Commande</th><th class="p-2">Deja recu</th><th class="p-2">Reception</th></tr></thead><tbody id="items"><tr><td colspan="4" class="p-6 text-center text-gray-500">Selectionnez une commande.</td></tr></tbody></table></div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Observations</label>
        <textarea name="observations" rows="3" class="w-full border rounded px-3 py-2 mb-6"></textarea>
        <button class="bg-green-600 text-white rounded px-6 py-2"><i class="fas fa-save mr-2"></i>Receptionner</button>
    </form>
</main>
<script>
let currentItems = [];
document.getElementById('order').addEventListener('change', function () {
    if (!this.value) return;
    fetch('/commande/order-items?order_id=' + encodeURIComponent(this.value))
        .then(r => r.json())
        .then(payload => {
            currentItems = payload.items || [];
            document.getElementById('items').innerHTML = currentItems.length ? currentItems.map(i => {
                const reste = parseInt(i.quantite_commandee, 10) - parseInt(i.quantite_recue, 10);
                return `<tr class="border-t"><td class="p-2">${i.produit_nom}</td><td class="p-2 text-center">${i.quantite_commandee}</td><td class="p-2 text-center">${i.quantite_recue}</td><td class="p-2 text-center"><input data-id="${i.id}" type="number" min="0" max="${reste}" value="${reste}" class="border rounded px-3 py-2 w-28"></td></tr>`;
            }).join('') : '<tr><td colspan="4" class="p-6 text-center text-gray-500">Aucune ligne a receptionner.</td></tr>';
        });
});
document.getElementById('reception-form').onsubmit = e => {
    const items = Array.from(document.querySelectorAll('#items input[data-id]')).map(input => ({supplier_order_item_id: input.dataset.id, quantite_recue: parseInt(input.value || '0', 10)})).filter(i => i.quantite_recue > 0);
    if (!items.length) { e.preventDefault(); alert('Saisissez au moins une quantite recue.'); return; }
    document.getElementById('items_json').value = JSON.stringify(items);
};
</script>
</body>
</html>
