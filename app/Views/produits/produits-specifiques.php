<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Produits Spécifiques')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-filter text-violet-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Produits Spécifiques</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/produits" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour au Dashboard Produits
                    </a>
                </div>
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

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtres spécifiques</h2>
            <form method="GET" action="/produits/produits-specifiques" id="filtreForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="traceurs" <?= in_array('traceurs', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits traceurs</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="suspendus" <?= in_array('suspendus', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits suspendus</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="hors_extranet" <?= in_array('hors_extranet', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits hors extranet</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="hors_etiquette" <?= in_array('hors_etiquette', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits hors étiquette</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="ordonnancier" <?= in_array('ordonnancier', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits de l'ordonnancier</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="remise_plafonnee" <?= in_array('remise_plafonnee', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits remise plafonnée</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="tva" <?= in_array('tva', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox">
                        <span>Produits soumis à la TVA</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="filtres[]" value="fournisseur" <?= in_array('fournisseur', $filtres ?? []) ? 'checked' : '' ?> class="form-checkbox" id="chkFournisseur">
                        <span>Produits d'un fournisseur</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4" id="fournisseurDiv" style="display: none;">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                        <select name="fournisseur_id" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Sélectionner un fournisseur</option>
                            <?php foreach ($fournisseurs ?? [] as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Filtrer
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Résultats</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Code</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Nom</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Catégorie</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Fournisseur</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Stock</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Prix vente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($produits)): ?>
                            <?php foreach ($produits as $produit): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['code_cip'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['nom'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['categorie'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($produit['fournisseur'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((int)($produit['stock'] ?? 0), 0, ',', ' ') ?></td>
                                    <td class="py-3 px-4 text-right"><?= number_format((float)($produit['prix_vente'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-500">Aucun produit trouvé</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#chkFournisseur').change(function() {
                if ($(this).is(':checked')) {
                    $('#fournisseurDiv').show();
                } else {
                    $('#fournisseurDiv').hide();
                }
            });
            
            if ($('#chkFournisseur').is(':checked')) {
                $('#fournisseurDiv').show();
            }
        });
    </script>
</body>
</html>
