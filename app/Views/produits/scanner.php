<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Scanner un produit') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
<main class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div><h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-barcode text-teal-600 mr-2"></i>Scanner un produit</h1><p class="text-sm text-gray-500">Présentez le code-barres devant le scanner.</p></div>
        <a href="<?= htmlspecialchars($returnTo ?? '/vente') ?>" class="px-4 py-2 bg-gray-600 text-white rounded"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
    </div>
    <section class="bg-white rounded-lg shadow p-6">
        <form id="scanner-form" class="flex flex-col sm:flex-row gap-3">
            <label class="sr-only" for="code-barres">Code-barres</label>
            <input id="code-barres" type="text" maxlength="50" autocomplete="off" inputmode="numeric" placeholder="Code-barres" class="flex-1 border rounded px-4 py-3 text-lg focus:ring-2 focus:ring-teal-500 focus:outline-none">
            <button class="bg-teal-600 hover:bg-teal-700 text-white font-medium rounded px-6 py-3" type="submit"><i class="fas fa-search mr-2"></i>Rechercher</button>
        </form>
        <p class="mt-3 text-xs text-gray-500">Compatible douchette USB/Bluetooth : le code suivi d'Entrée lance automatiquement la recherche.</p>
    </section>
    <section id="resultat" class="hidden mt-5 bg-white rounded-lg shadow p-6" aria-live="polite"></section>
</main>
<script>
(() => {
    const input = document.getElementById('code-barres');
    const form = document.getElementById('scanner-form');
    const resultat = document.getElementById('resultat');
    const returnTo = <?= json_encode($returnTo ?? '/vente') ?>;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const focusScanner = () => setTimeout(() => { input.value = ''; input.focus(); }, 50);

    function afficherMessage(message, couleur = 'red') {
        resultat.className = `mt-5 bg-${couleur}-50 border border-${couleur}-200 rounded-lg p-6`;
        resultat.innerHTML = `<p class="text-${couleur}-700 font-medium">${escapeHtml(message)}</p><button type="button" id="reessayer" class="mt-4 text-sm text-${couleur}-700 underline">Réessayer</button>`;
        resultat.classList.remove('hidden');
        document.getElementById('reessayer').addEventListener('click', focusScanner);
    }

    function afficherProduit(produit) {
        const etat = produit.statut || 'INDISPONIBLE';
        const badge = produit.vendable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
        const raison = etat === 'INACTIF' ? 'Produit inactif' : (etat === 'PERIME' ? 'Produit périmé' : (etat === 'STOCK_INSUFFISANT' ? 'Produit trouvé mais stock insuffisant' : 'Produit trouvé'));
        resultat.className = 'mt-5 bg-white rounded-lg shadow p-6';
        resultat.innerHTML = `<div class="flex items-start justify-between gap-4"><div><p class="text-sm font-medium ${produit.vendable ? 'text-green-700' : 'text-red-700'}">${raison}</p><h2 class="text-xl font-bold text-gray-900 mt-1">${escapeHtml(produit.nom)}</h2></div><span class="px-2 py-1 rounded-full text-xs font-semibold ${badge}">${escapeHtml(etat)}</span></div><dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mt-5 text-sm"><div><dt class="text-gray-500">Code produit</dt><dd class="font-medium">${escapeHtml(produit.code_cip || '-')}</dd></div><div><dt class="text-gray-500">Code-barres</dt><dd class="font-medium">${escapeHtml(produit.code_barre || '-')}</dd></div><div><dt class="text-gray-500">Forme pharmaceutique</dt><dd class="font-medium">${escapeHtml(produit.forme_pharmaceutique || '-')}</dd></div><div><dt class="text-gray-500">DCI</dt><dd class="font-medium">${escapeHtml(produit.dci || '-')}</dd></div><div><dt class="text-gray-500">Prix de vente</dt><dd class="font-medium">${Number(produit.prix_vente || 0).toLocaleString('fr-FR')} FCFA</dd></div><div><dt class="text-gray-500">Stock disponible</dt><dd class="font-medium ${Number(produit.quantite_disponible) > 0 ? '' : 'text-red-700'}">${Number(produit.quantite_disponible || 0)}</dd></div></dl>${produit.vendable ? `<a class="inline-block mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-3 rounded" href="/vente/create?scanner_produit_id=${encodeURIComponent(produit.id)}&return_to=${encodeURIComponent('/produits/scanner?return_to=' + returnTo)}"><i class="fas fa-cart-plus mr-2"></i>Commencer une nouvelle vente</a>` : '<p class="mt-6 text-sm text-red-700">Ce produit ne peut pas être ajouté à la vente dans son état actuel.</p>'}`;
        resultat.classList.remove('hidden');
        focusScanner();
    }

    async function rechercher() {
        const code = input.value.trim();
        if (!code) { focusScanner(); return; }
        try {
            const response = await fetch('/produits/scanner?code=' + encodeURIComponent(code), {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (!data.success) { afficherMessage(data.message || 'Aucun produit trouvé pour ce code-barres.'); focusScanner(); return; }
            afficherProduit(data.produit);
        } catch (_) { afficherMessage('Recherche indisponible, veuillez réessayer.'); focusScanner(); }
    }

    form.addEventListener('submit', event => { event.preventDefault(); rechercher(); });
    window.addEventListener('pageshow', focusScanner);
    focusScanner();
})();
</script>
</body></html>
