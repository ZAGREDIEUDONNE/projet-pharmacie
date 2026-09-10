<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Modifier commande fournisseur')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow"><div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between"><h1 class="text-xl font-bold text-gray-800"><i class="fas fa-edit text-blue-600 mr-3"></i>Modifier commande fournisseur</h1><a href="/commande/historique" class="bg-gray-600 text-white px-4 py-2 rounded">Retour</a></div></nav>
<main class="max-w-7xl mx-auto p-6">
    <?php if (!empty($_SESSION['error'])): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div><?php unset($_SESSION['error']); endif; ?>
    <form method="POST" action="/commande/update" id="order-form" class="bg-white rounded-lg shadow p-6">
        <input type="hidden" name="order_id" value="<?= (int)($order['id'] ?? 0) ?>">
        <input type="hidden" name="items_json" id="items_json">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label><select name="fournisseur_id" required class="w-full border rounded px-3 py-2"><option value="">Selectionner</option><?php foreach (($fournisseurs ?? []) as $f): ?><option value="<?= (int)$f['id'] ?>" <?= ($order['fournisseur_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$f['nom']) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Reference commande</label><input type="text" name="reference_commande" value="<?= htmlspecialchars((string)($order['reference_commande'] ?? '')) ?>" placeholder="Ref-2024-001" class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Statut</label><input type="text" value="<?= htmlspecialchars(str_replace('_', ' ', (string)($order['statut'] ?? ''))) ?>" disabled class="w-full border rounded px-3 py-2 bg-gray-100"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Date commande</label><input type="date" name="date_commande" value="<?= htmlspecialchars((string)($order['date_commande'] ?? date('Y-m-d'))) ?>" required class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Livraison prevue</label><input type="date" name="date_livraison_prevue" value="<?= htmlspecialchars((string)($order['date_livraison_prevue'] ?? '')) ?>" class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Remise globale (%)</label><input type="number" name="remise_globale" value="<?= (float)($order['remise_globale'] ?? 0) ?>" min="0" max="100" step="0.01" class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">TVA globale (%)</label><input type="number" name="tva_globale" value="<?= (float)($order['tva_globale'] ?? 0) ?>" min="0" max="100" step="0.01" class="w-full border rounded px-3 py-2"></div>
        </div>
        <div class="border rounded p-4 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">Produits</h2>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
                <select id="product" class="border rounded px-3 py-2"><option value="">Produit</option><?php foreach (($produits ?? []) as $p): ?><option value="<?= (int)$p['id'] ?>" data-price="<?= htmlspecialchars((string)$p['prix_achat']) ?>"><?= htmlspecialchars((string)$p['nom']) ?></option><?php endforeach; ?></select>
                <input id="qty" type="number" min="1" step="1" placeholder="Quantite" class="border rounded px-3 py-2">
                <input id="price" type="number" min="0.01" step="0.01" placeholder="Prix achat" class="border rounded px-3 py-2">
                <input id="remise" type="number" min="0" max="100" step="0.01" placeholder="Remise %" class="border rounded px-3 py-2">
                <input id="tva" type="number" min="0" max="100" step="0.01" placeholder="TVA %" class="border rounded px-3 py-2">
                <button type="button" id="add-line" class="bg-blue-600 text-white rounded px-4 py-2 col-span-5 md:col-span-1"><i class="fas fa-plus mr-2"></i>Ajouter</button>
            </div>
            <div class="overflow-x-auto"><table class="w-full"><thead><tr class="bg-gray-50"><th class="p-2 text-left">Produit</th><th class="p-2 text-center">Quantite</th><th class="p-2 text-center">Prix</th><th class="p-2 text-center">Remise %</th><th class="p-2 text-center">TVA %</th><th class="p-2 text-center">Total HT</th><th class="p-2 text-center">Total TTC</th><th class="p-2"></th></tr></thead><tbody id="lines"></tbody></table></div>
            <div class="mt-4 p-4 bg-gray-50 rounded">
                <div class="flex justify-between mb-2"><span>Total HT:</span><span id="total-ht">0.00 FCFA</span></div>
                <div class="flex justify-between mb-2"><span>Remise globale:</span><span id="total-remise">0.00 FCFA</span></div>
                <div class="flex justify-between mb-2"><span>TVA:</span><span id="total-tva">0.00 FCFA</span></div>
                <div class="flex justify-between font-bold text-lg"><span>Total TTC:</span><span id="total-ttc">0.00 FCFA</span></div>
            </div>
        </div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Observations</label>
        <textarea name="observations" rows="3" class="w-full border rounded px-3 py-2 mb-6"><?= htmlspecialchars((string)($order['observations'] ?? '')) ?></textarea>
        <button class="bg-blue-600 text-white rounded px-6 py-2"><i class="fas fa-save mr-2"></i>Enregistrer les modifications</button>
    </form>
</main>
<script>
const items = <?php echo json_encode(array_map(function($item) {
    return [
        'produit_id' => $item['produit_id'],
        'name' => $item['produit_nom'],
        'quantite' => (int)$item['quantite_commandee'],
        'prix_achat' => (float)$item['prix_achat'],
        'remise' => (float)($item['remise'] ?? 0),
        'tva' => (float)($item['tva'] ?? 0)
    ];
}, $items ?? [])); ?>;
const product = document.getElementById('product');
product.addEventListener('change', () => { document.getElementById('price').value = product.selectedOptions[0]?.dataset.price || ''; });

function formatMoney(amount) {
    return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' FCFA';
}

function calculateTotals() {
    const remiseGlobale = parseFloat(document.querySelector('[name="remise_globale"]').value) || 0;
    const tvaGlobale = parseFloat(document.querySelector('[name="tva_globale"]').value) || 0;
    
    let totalHT = 0;
    items.forEach(item => {
        const lineHT = item.quantite * item.prix_achat;
        totalHT += lineHT;
    });
    
    const remiseMontant = totalHT * (remiseGlobale / 100);
    const montantApresRemise = totalHT - remiseMontant;
    const tvaMontant = montantApresRemise * (tvaGlobale / 100);
    const montantTTC = montantApresRemise + tvaMontant;
    
    document.getElementById('total-ht').textContent = formatMoney(totalHT);
    document.getElementById('total-remise').textContent = formatMoney(remiseMontant);
    document.getElementById('total-tva').textContent = formatMoney(tvaMontant);
    document.getElementById('total-ttc').textContent = formatMoney(montantTTC);
}

function render() {
  document.getElementById('lines').innerHTML = items.map((i, idx) => {
      const lineHT = i.quantite * i.prix_achat;
      const lineRemise = lineHT * (i.remise / 100);
      const lineApresRemise = lineHT - lineRemise;
      const lineTVA = lineApresRemise * (i.tva / 100);
      const lineTTC = lineApresRemise + lineTVA;
      return `<tr class="border-t"><td class="p-2">${i.name}</td><td class="p-2 text-center">${i.quantite}</td><td class="p-2 text-center">${i.prix_achat.toFixed(2)}</td><td class="p-2 text-center">${i.remise}%</td><td class="p-2 text-center">${i.tva}%</td><td class="p-2 text-center">${lineHT.toFixed(2)}</td><td class="p-2 text-center">${lineTTC.toFixed(2)}</td><td class="p-2 text-center"><button type="button" data-idx="${idx}" class="text-red-600 remove">Retirer</button></td></tr>`;
  }).join('');
  document.querySelectorAll('.remove').forEach(b => b.onclick = () => { items.splice(parseInt(b.dataset.idx, 10), 1); render(); calculateTotals(); });
  calculateTotals();
}

document.getElementById('add-line').onclick = () => {
  const option = product.selectedOptions[0], qty = parseInt(document.getElementById('qty').value, 10), price = parseFloat(document.getElementById('price').value), remise = parseFloat(document.getElementById('remise').value) || 0, tva = parseFloat(document.getElementById('tva').value) || 0;
  if (!option?.value || qty <= 0 || price <= 0) return alert('Produit, quantite et prix requis.');
  items.push({produit_id: option.value, name: option.textContent, quantite: qty, prix_achat: price, remise, tva});
  document.getElementById('qty').value = ''; document.getElementById('remise').value = ''; document.getElementById('tva').value = ''; render();
};

document.querySelectorAll('[name="remise_globale"], [name="tva_globale"]').forEach(el => el.addEventListener('input', calculateTotals));

document.getElementById('order-form').onsubmit = e => { if (!items.length) { e.preventDefault(); alert('Ajoutez au moins un produit.'); } document.getElementById('items_json').value = JSON.stringify(items); };

render();
</script>
</body>
</html>
