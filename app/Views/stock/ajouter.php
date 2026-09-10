<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Entrée Directe en Stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php
$old = $_SESSION['old_data'] ?? [];
$value = fn(string $key, $default = '') => htmlspecialchars((string)($old[$key] ?? $default));
$returnTo = (string)($old['return_to'] ?? $_GET['return_to'] ?? '/stock');
$returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : '/stock';
?>
<nav class="bg-blue-50 shadow">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <div class="flex items-center">
            <i class="fas fa-boxes text-indigo-600 text-2xl mr-3"></i>
            <h1 class="text-xl font-bold text-gray-800">Entrée Directe en Stock</h1>
        </div>
        <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Retour
        </a>
    </div>
</nav>

<main class="max-w-7xl mx-auto p-6">
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (empty($formes_pharmaceutiques ?? [])): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 rounded p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold">Aucune forme pharmaceutique disponible.</p>
                    <p class="text-sm">Veuillez d'abord créer une forme pharmaceutique dans la base de données.</p>
                </div>
                <a href="/produits" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                    <i class="fas fa-arrow-right mr-2"></i>Aller aux Produits
                </a>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="/stock/entree-directe" id="stockForm" class="bg-white rounded-lg shadow p-6">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
        <input type="hidden" id="produit_id" name="produit_id" value="<?= $value('produit_id') ?>">
        <input type="hidden" name="action" value="save">

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informations Produit</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Produit existant <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="text" id="produit-search" name="produit_nom" value="<?= $value('produit_nom') ?>" autocomplete="off"
                               placeholder="Rechercher un produit existant (laisser vide pour créer un nouveau)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <div id="produit-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden max-h-60 overflow-y-auto"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Forme Pharmaceutique</label>
                    <select name="forme_pharmaceutique" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner</option>
                        <?php foreach (($formes_pharmaceutiques ?? []) as $forme): ?>
                            <option value="<?= htmlspecialchars((string)$forme) ?>" <?= $value('forme_pharmaceutique') === $forme ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$forme) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="nouveau-produit-fields" class="md:col-span-2 hidden">
                    <div class="border-t pt-4 mt-4">
                        <h3 class="text-md font-medium text-gray-700 mb-3">Créer un nouveau produit</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom du produit <span class="text-red-500">*</span></label>
                                <input type="text" name="nouveau_produit_nom" value="<?= $value('nouveau_produit_nom') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Code CIP</label>
                                <input type="text" name="code_cip" value="<?= $value('code_cip') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">DCI (Dénomination Commune Internationale) <span class="text-red-500">*</span></label>
                                <input type="text" name="dci" value="<?= $value('dci') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rayon <span class="text-red-500">*</span></label>
                                <select name="rayon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Sélectionner</option>
                                    <?php foreach (($rayons ?? []) as $r): ?>
                                        <option value="<?= htmlspecialchars((string)$r) ?>" <?= $value('rayon') === $r ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$r) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stock minimum</label>
                                <input type="number" name="stock_minimum" value="<?= $value('stock_minimum', 0) ?>" step="1" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type de délivrance</label>
                                <select name="type_delivrance" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <?php foreach (($types_delivrance ?? []) as $code => $label): ?>
                                        <option value="<?= htmlspecialchars((string)$code) ?>" <?= $value('type_delivrance') === $code ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Ordonnance obligatoire pour: Ordonnancier, Psychotrope, Anticancéreux</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informations Stock</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantité <span class="text-red-500">*</span></label>
                    <input type="number" name="quantite" value="<?= $value('quantite', 1) ?>" required step="1" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix d'achat <span class="text-red-500">*</span></label>
                    <input type="number" name="prix_achat" value="<?= $value('prix_achat') ?>" required step="0.01" min="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Coefficient multiplicateur</label>
                    <input type="number" name="coefficient" value="<?= $value('coefficient', 1.48) ?>" step="0.01" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix de vente</label>
                    <input type="number" name="prix_vente" value="<?= $value('prix_vente') ?>" step="0.01" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">TVA (%)</label>
                    <select name="tva" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0" <?= $value('tva') === '0' ? 'selected' : '' ?>>0%</option>
                        <option value="18" <?= $value('tva') === '18' ? 'selected' : '' ?>>18%</option>
                        <option value="19.25" <?= $value('tva') === '19.25' ? 'selected' : '' ?>>19.25%</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remise (%)</label>
                    <input type="number" name="remise" value="<?= $value('remise', 0) ?>" step="0.01" min="0" max="100"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informations Fournisseur</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label>
                    <select name="fournisseur_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner</option>
                        <?php foreach (($fournisseurs ?? []) as $fournisseur): ?>
                            <option value="<?= (int)$fournisseur['id'] ?>" <?= $value('fournisseur_id') === (string)$fournisseur['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$fournisseur['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date d'entrée <span class="text-red-500">*</span></label>
                    <input type="date" name="date_entree" value="<?= $value('date_entree', date('Y-m-d')) ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Observations</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Observations</label>
                <textarea name="observations" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= $value('observations') ?></textarea>
            </div>
        </div>

        <div class="flex justify-between gap-4 mt-6">
            <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-times mr-2"></i>Annuler
            </a>
            <div class="flex gap-4">
                <button type="button" onclick="submitForm('save_and_new')" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer et Nouveau
                </button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </div>
    </form>
    <?php unset($_SESSION['old_data']); ?>
</main>

<script>
const produits = <?= json_encode($produits ?? []) ?>;
const searchInput = document.getElementById('produit-search');
const produitIdInput = document.getElementById('produit_id');
const suggestionsDiv = document.getElementById('produit-suggestions');
const nouveauProduitFields = document.getElementById('nouveau-produit-fields');
const form = document.getElementById('stockForm');

function selectProduit(produit) {
    produitIdInput.value = produit.id;
    searchInput.value = produit.nom;
    nouveauProduitFields.classList.add('hidden');
    if (form.prix_achat && !form.prix_achat.value && produit.prix_achat) {
        form.prix_achat.value = produit.prix_achat;
    }
    if (form.prix_vente && !form.prix_vente.value && produit.prix_vente) {
        form.prix_vente.value = produit.prix_vente;
    }
    if (form.forme_pharmaceutique_id && produit.forme_pharmaceutique_id) {
        form.forme_pharmaceutique_id.value = produit.forme_pharmaceutique_id;
    } else if (form.forme_pharmaceutique_id) {
        form.forme_pharmaceutique_id.value = '';
    }
    suggestionsDiv.classList.add('hidden');
}

searchInput.addEventListener('input', function () {
    const query = this.value.toLowerCase();
    if (query.length < 1) {
        suggestionsDiv.classList.add('hidden');
        nouveauProduitFields.classList.remove('hidden');
        return;
    }
    const results = produits.filter(p => (p.nom || '').toLowerCase().includes(query) || (p.code_cip || '').toLowerCase().includes(query)).slice(0, 20);
    suggestionsDiv.innerHTML = results.length ? results.map(p => `
        <button type="button" class="w-full text-left px-4 py-3 hover:bg-gray-100 border-b" data-id="${p.id}">
            <span class="font-medium">${p.nom}</span>
            <span class="block text-xs text-gray-500">CIP: ${p.code_cip || '-'}</span>
        </button>`).join('') : '<div class="p-3 text-gray-500">Aucun produit trouvé</div>';
    suggestionsDiv.classList.remove('hidden');
    nouveauProduitFields.classList.add('hidden');
    suggestionsDiv.querySelectorAll('button[data-id]').forEach(button => {
        button.addEventListener('click', () => selectProduit(produits.find(p => String(p.id) === button.dataset.id)));
    });
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('#produit-search') && !event.target.closest('#produit-suggestions')) {
        suggestionsDiv.classList.add('hidden');
    }
});

function submitForm(action) {
    form.action.value = action;
    form.submit();
}

form.addEventListener('submit', function (event) {
    const produitId = produitIdInput.value;
    const nouveauNom = form.nouveau_produit_nom.value;
    
    if (!produitId && !nouveauNom) {
        event.preventDefault();
        alert('Veuillez sélectionner un produit existant ou créer un nouveau produit.');
        searchInput.focus();
        return;
    }
    
    if (parseInt(form.quantite.value, 10) <= 0) {
        event.preventDefault();
        alert('La quantité doit être supérieure à 0.');
        form.quantite.focus();
        return;
    }
    
    if (parseFloat(form.prix_achat.value) <= 0) {
        event.preventDefault();
        alert('Le prix d\'achat doit être supérieur à 0.');
        form.prix_achat.focus();
        return;
    }
    
    if (nouveauNom && !form.forme_pharmaceutique.value) {
        event.preventDefault();
        alert('Veuillez sélectionner une forme pharmaceutique pour le nouveau produit.');
        form.forme_pharmaceutique.focus();
        return;
    }
    
    if (nouveauNom && !form.dci.value) {
        event.preventDefault();
        alert('Veuillez renseigner la DCI (Dénomination Commune Internationale) pour le nouveau produit.');
        form.dci.focus();
        return;
    }
    
    if (nouveauNom && !form.rayon.value) {
        event.preventDefault();
        alert('Veuillez sélectionner un rayon pour le nouveau produit.');
        form.rayon.focus();
        return;
    }
});
</script>
</body>
</html>
