<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Saisie inventaire')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6"><?= htmlspecialchars((string)($title ?? 'Saisie')) ?></h1>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6 lg:col-span-1">
            <h2 class="font-bold mb-4">Saisir un produit</h2>
            <form method="POST" action="/inventaire/article" class="space-y-3">
                <input type="hidden" name="inventaire_id" value="<?= (int)($inventaire['id'] ?? 0) ?>">
                <div><label class="text-sm">Produit</label><select name="produit_id" required class="w-full border rounded px-3 py-2" id="produit_select"><?php foreach (($produits ?? []) as $p): ?><option value="<?= (int)$p['id'] ?>" data-theorique="<?= (int)$p['stock_theorique'] ?>"><?= htmlspecialchars((string)$p['nom']) ?></option><?php endforeach; ?></select></div>
                <div><label class="text-sm">Stock théorique</label><input type="number" name="quantite_theorique" id="q_theorique" readonly class="w-full border rounded px-3 py-2 bg-gray-50"></div>
                <div><label class="text-sm">Stock physique compté</label><input type="number" name="quantite_comptee" min="0" required class="w-full border rounded px-3 py-2"></div>
                <button class="bg-blue-600 text-white px-4 py-2 rounded w-full">Ajouter</button>
            </form>
            <form method="POST" action="/inventaire/cloturer" class="mt-6 border-t pt-4">
                <input type="hidden" name="inventaire_id" value="<?= (int)($inventaire['id'] ?? 0) ?>">
                <button class="bg-green-700 text-white px-4 py-2 rounded w-full" onclick="return confirm('Clôturer et générer les ajustements ?')">Valider l'inventaire</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
            <h2 class="font-bold mb-4">Articles saisis — écarts</h2>
            <table class="w-full text-sm"><thead><tr class="text-left border-b"><th class="pb-2">Produit</th><th class="pb-2">Théorique</th><th class="pb-2">Physique</th><th class="pb-2">Écart</th><th class="pb-2">Valeur écart</th></tr></thead>
            <tbody><?php foreach (($articles ?? []) as $a): $ecart = (int)($a['quantite_comptee'] ?? 0) - (int)($a['quantite_theorique'] ?? 0); $valeurEcart = $ecart * (float)($a['prix_unitaire'] ?? 0); ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)($a['produit_nom'] ?? '')) ?></td><td class="py-2"><?= (int)($a['quantite_theorique'] ?? 0) ?></td><td class="py-2"><?= (int)($a['quantite_comptee'] ?? 0) ?></td><td class="py-2 font-semibold <?= $ecart < 0 ? 'text-red-600' : ($ecart > 0 ? 'text-green-600' : '') ?>"><?= $ecart ?></td><td class="py-2 font-semibold <?= $valeurEcart < 0 ? 'text-red-600' : ($valeurEcart > 0 ? 'text-green-600' : '') ?>"><?= number_format($valeurEcart, 2, ',', ' ') ?> FCFA</td></tr><?php endforeach; ?></tbody></table>
        </div>
    </div>
</div>
<script>
document.getElementById('produit_select')?.addEventListener('change', function() {
    document.getElementById('q_theorique').value = this.selectedOptions[0]?.dataset.theorique || 0;
});
document.getElementById('produit_select')?.dispatchEvent(new Event('change'));
</script>
</body></html>
