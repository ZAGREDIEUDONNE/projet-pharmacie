<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Ajouter un Produit au Stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $old = $_SESSION['old_data'] ?? [];
        $value = function (string $key, $default = '') use ($old) {
            return htmlspecialchars((string)($old[$key] ?? $default));
        };
        $defaultReturnTo = (int)($_SESSION['user']['role_id'] ?? 0) === 1 ? '/admin/dashboard' : '/stock';
        $returnTo = (string)($old['return_to'] ?? $_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
        $classesPharma = ['Antibiotique', 'Antidepresseur', 'Antalgique', 'Antipaludique', 'Antitussif', 'Antihypertenseur', 'Anti-inflammatoire'];
        $formesPharma = ['Comprime', 'Gelule', 'Sirop', 'Suspension', 'Injectable', 'Vaccin', 'Serum', 'Collyre', 'Pommade', 'Gel', 'Lotion', 'Suppositoire', 'Tisane', 'Bain de bouche', 'Parapharmacie', 'Consommable medical', 'Phytomédicament', 'Équipements médicaux', 'Autres'];
        $typesDelivrance = [
            'MEDICAMENT_CONSEIL' => 'Medicament conseil',
            'HORS_LISTE' => 'Hors liste',
            'ORDONNANCIER' => 'Ordonnancier',
            'PSYCHOTROPE' => 'Psychotrope',
            'ANTICANCEREUX' => 'Anticancereux',
        ];
        $rayons = [
            'Rayon A', 'Rayon B', 'Rayon C', 'Rayon D', 'Rayon E',
            'Rayon F', 'Rayon G', 'Rayon H', 'Rayon I', 'Rayon J',
            'Rayon K', 'Rayon L', 'Rayon M', 'Rayon N', 'Rayon O',
            'Frigo', 'Armoire à clé', 'Vitrines', 'Comptoir', 'Magasin', 'Autres'
        ];
    ?>

    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                     <i class="fas fa-box text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Ajouter un Nouveau Produit</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars((string)($_SESSION['user']['username'] ?? '')) ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h4 class="text-red-800 font-semibold mb-2">Erreurs</h4>
                <ul class="text-red-700 list-disc ml-5">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars((string)$error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <h4 class="text-green-800 font-semibold">Succès</h4>
                <p class="text-green-700"><?= htmlspecialchars((string)$_SESSION['success']) ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="POST" action="/stock/ajouter-produit" id="produitForm" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-plus-circle mr-2"></i>Informations du Produit
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Informations de base -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom du produit <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" value="<?= $value('nom') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Code CIP <span class="text-red-500">*</span></label>
                    <input type="text" name="code_cip" value="<?= $value('code_cip') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Code barre</label>
                    <input type="text" name="code_barre" value="<?= $value('code_barre') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= $value('description') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rayon / Emplacement <span class="text-red-500">*</span></label>
                    <select name="rayon" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner un rayon</option>
                        <?php foreach ($rayons as $rayon): ?>
                            <option value="<?= htmlspecialchars($rayon) ?>" <?= $value('rayon') === $rayon ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rayon) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">DCI</label>
                    <input type="text" name="dci" value="<?= $value('dci') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Classe pharmaceutique</label>
                    <select name="classe_pharmaceutique"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Selectionner</option>
                        <?php foreach ($classesPharma as $classe): ?>
                            <option value="<?= htmlspecialchars($classe) ?>" <?= $value('classe_pharmaceutique') === $classe ? 'selected' : '' ?>><?= htmlspecialchars($classe) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Forme pharmaceutique</label>
                    <select name="forme_pharmaceutique"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Selectionner</option>
                        <?php foreach ($formesPharma as $formePharma): ?>
                            <option value="<?= htmlspecialchars($formePharma) ?>" <?= $value('forme_pharmaceutique') === $formePharma ? 'selected' : '' ?>><?= htmlspecialchars($formePharma) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de delivrance</label>
                    <select name="type_delivrance" id="type_delivrance"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($typesDelivrance as $code => $label): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $value('type_delivrance', 'MEDICAMENT_CONSEIL') === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="ordonnanceRule" class="text-xs text-gray-500 mt-1">Ordonnance facultative pour medicament conseil et hors liste.</p>
                </div>

                <!-- Prix et stock -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix d'achat <span class="text-red-500">*</span></label>
                    <input type="number" name="prix_achat" value="<?= $value('prix_achat') ?>" required step="0.01" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix de vente <span class="text-red-500">*</span></label>
                    <input type="number" name="prix_vente" value="<?= $value('prix_vente') ?>" required step="0.01" min="0" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Calcule automatiquement: prix d'achat x 1,48.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantité initiale <span class="text-red-500">*</span></label>
                    <input type="number" name="quantite" value="<?= $value('quantite', 0) ?>" required step="1" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock de sécurité</label>
                    <input type="number" name="stock_securite" value="<?= $value('stock_securite', 10) ?>" step="1" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock d'alerte</label>
                    <input type="number" name="stock_alerte" value="<?= $value('stock_alerte', 5) ?>" step="1" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Fournisseur -->
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Fournisseur</label>
                        <?php if (in_array((int)($_SESSION['user']['role_id'] ?? 0), [1, 3], true)): ?>
                            <a href="/fournisseurs/ajouter?return_to=<?= urlencode($returnTo) ?>" class="text-sm text-teal-700 hover:text-teal-900 font-semibold">
                                <i class="fas fa-plus mr-1"></i>Ajouter
                            </a>
                        <?php endif; ?>
                    </div>
                    <select name="fournisseur_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach ($fournisseurs ?? [] as $fournisseur): ?>
                            <option value="<?= $fournisseur['id'] ?>" <?= $value('fournisseur_id') == $fournisseur['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fournisseur['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Informations complémentaires -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Unite de mesure</label>
                    <input type="text" name="unite_mesure" value="<?= $value('unite_mesure', 'unite') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prix assure</label>
                            <input type="number" name="prix_vente_assure" value="<?= $value('prix_vente_assure') ?>" step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date peremption par defaut</label>
                            <input type="date" name="date_peremption_default" value="<?= $value('date_peremption_default') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 mb-2">Motif de l'ajout</label>
                    <textarea name="motif" rows="3" placeholder="Ex: Nouvelle livraison, premier stock..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= $value('motif') ?></textarea>
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-save mr-2"></i>Créer le Produit et Ajouter au Stock
                </button>
            </div>
        </form>
        <?php unset($_SESSION['old_data']); ?>
    </main>

    <script>
        const allowedRayons = [
            'Rayon A', 'Rayon B', 'Rayon C', 'Rayon D', 'Rayon E',
            'Rayon F', 'Rayon G', 'Rayon H', 'Rayon I', 'Rayon J',
            'Rayon K', 'Rayon L', 'Rayon M', 'Rayon N', 'Rayon O',
            'Frigo', 'Armoire à clé', 'Vitrines', 'Comptoir', 'Magasin', 'Autres'
        ];

        document.getElementById('produitForm').addEventListener('submit', function(e) {
            const form = e.target;
            const nom = form.nom.value.trim();
            const codeCip = form.code_cip.value.trim();
            const prixAchat = parseFloat(form.prix_achat.value);
            const prixVente = parseFloat(form.prix_vente.value);
            const quantite = parseFloat(form.quantite.value);
            const rayon = form.rayon.value.trim();
            
            // Validation
            if (!nom) {
                e.preventDefault();
                alert('Le nom du produit est obligatoire');
                form.nom.focus();
                return false;
            }
            
            if (!codeCip) {
                e.preventDefault();
                alert('Le code CIP est obligatoire');
                form.code_cip.focus();
                return false;
            }
            
            if (!rayon) {
                e.preventDefault();
                alert('Le rayon / emplacement est obligatoire');
                form.rayon.focus();
                return false;
            }
            
            if (!allowedRayons.includes(rayon)) {
                e.preventDefault();
                alert('Rayon / emplacement non valide');
                form.rayon.focus();
                return false;
            }
            
            if (!prixAchat || prixAchat <= 0) {
                e.preventDefault();
                alert('Le prix d\'achat doit être supérieur à 0');
                form.prix_achat.focus();
                return false;
            }
            
            if (!prixVente || prixVente <= 0) {
                e.preventDefault();
                alert('Le prix de vente doit être supérieur à 0');
                form.prix_vente.focus();
                return false;
            }
            
            if (Number.isNaN(quantite) || quantite < 0) {
                e.preventDefault();
                alert('La quantité doit être supérieure à 0');
                form.quantite.focus();
                return false;
            }
            
            // Confirmation
            if (!confirm(`Créer le produit "${nom}" et ajouter ${quantite} unités au stock ?`)) {
                e.preventDefault();
                return false;
            }
        });

        function updatePrixVente() {
            const form = document.getElementById('produitForm');
            const prixAchat = parseFloat(form.prix_achat.value);
            form.prix_vente.value = prixAchat > 0 ? (prixAchat * 1.48).toFixed(2) : '';
        }

        document.querySelector('input[name="prix_achat"]').addEventListener('input', updatePrixVente);
        updatePrixVente();

        const typeDelivrance = document.getElementById('type_delivrance');
        const ordonnanceRule = document.getElementById('ordonnanceRule');
        function updateOrdonnanceRule() {
            const required = ['ORDONNANCIER', 'PSYCHOTROPE', 'ANTICANCEREUX'].includes(typeDelivrance.value);
            ordonnanceRule.textContent = required
                ? 'Ordonnance obligatoire pour ce type de delivrance.'
                : 'Ordonnance facultative pour ce type de delivrance.';
            ordonnanceRule.className = required ? 'text-xs text-red-600 mt-1' : 'text-xs text-gray-500 mt-1';
        }
        typeDelivrance.addEventListener('change', updateOrdonnanceRule);
        updateOrdonnanceRule();
    </script>
</body>
</html>
