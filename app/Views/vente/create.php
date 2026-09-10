<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Nouvelle Vente' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $defaultReturnTo = in_array($roleCode, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true)
            ? '/commande/dashboard'
            : ((int)($_SESSION['user']['role_id'] ?? 0) === 1
                ? '/admin/dashboard'
                : (((int)($_SESSION['user']['role_id'] ?? 0) === 3 || $roleCode === 'ASSISTANT') ? '/assistant/dashboard' : '/vente'));
        $returnTo = (string)($_GET['return_to'] ?? $_POST['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
        $maxDiscountPercent = min(25, max(0, (float)($maxDiscountPercent ?? 0)));
        $venteEnCours = $venteEnCours ?? null;
        $isReprise = $venteEnCours !== null;
        $ordonnancePrefill = $ordonnancePrefill ?? null;
    ?>
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-shopping-cart text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800"><?= $isReprise ? 'Reprendre la Vente' : 'Nouvelle Vente' ?></h1>
                    <?php if ($isReprise): ?>
                        <span class="ml-3 px-2 py-1 bg-amber-100 text-amber-700 text-xs rounded-full">Ticket #<?= htmlspecialchars($venteEnCours['numero_facture'] ?? '') ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto p-6">
        <!-- Messages d'erreur/succès -->
        <?php if (isset($_SESSION['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    <div>
                        <h4 class="text-red-800 font-semibold">Erreurs</h4>
                        <ul class="text-red-700 mt-2">
                            <?php foreach ($_SESSION['errors'] as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-check-circle text-green-600 mr-3"></i>
                    <div>
                        <h4 class="text-green-800 font-semibold">Succès</h4>
                        <p class="text-green-700 mt-2"><?= htmlspecialchars($_SESSION['success']) ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Formulaire de vente -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="/vente/store" id="venteForm">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                <?php if ($ordonnancePrefill): ?><input type="hidden" name="ordonnance_id" value="<?= (int)$ordonnancePrefill['id'] ?>"><?php endif; ?>
                <?php if ($isReprise): ?>
                    <input type="hidden" name="vente_id" value="<?= htmlspecialchars((string)($venteEnCours['id'] ?? '')) ?>">
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Client -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2"></i>Client
                        </label>
                        <select name="client_id" id="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Sélectionner un client</option>
                        </select>
                    </div>

                    <!-- Articles -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-box mr-2"></i>Articles
                        </label>
                        <div id="articlesContainer" class="space-y-4">
                            <div class="article-row grid grid-cols-8 gap-4 items-center">
                                <select name="produit_id[]" class="produit-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Selectionner un produit</option>
                                </select>
                                <input type="number" name="quantite[]" placeholder="Quantité" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <input type="text" name="stock_disponible[]" placeholder="Stock" readonly class="stock-disponible px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                                <input type="text" name="rayon[]" placeholder="Rayon" readonly class="rayon-display px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700 text-sm">
                                <input type="number" name="prix_unitaire[]" placeholder="Prix unitaire" min="0" step="0.01" readonly class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                                <input type="number" name="total_ligne[]" placeholder="Total" min="0" step="0.01" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button" onclick="removeArticle(this)" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" onclick="addArticle()" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Ajouter un article
                        </button>
                    </div>

                    <!-- Montants -->
                    <div class="md:col-span-2 grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calculator mr-2"></i>Total HT
                            </label>
                            <input type="number" name="montant_total" id="montant_total" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-percentage mr-2"></i>Remise (%)
                            </label>
                            <input type="number" name="remise" id="remise" placeholder="0.00" min="0" max="<?= htmlspecialchars((string)$maxDiscountPercent) ?>" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Plafond autorise: <?= number_format($maxDiscountPercent, 2, ',', ' ') ?>%.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave mr-2"></i>Net à payer
                            </label>
                            <input type="number" name="montant_paye" id="montant_paye" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Mode de paiement -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-credit-card mr-2"></i>Mode de paiement
                        </label>
                        <select name="mode_paiement" id="mode_paiement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="togglePaymentFields()">
                            <option value="ESPECE">Espèces</option>
                            <option value="CARNET">Carnet</option>
                            <option value="DEPOT">Dépôt</option>
                            <option value="CARTE_VISA">Carte Visa</option>
                            <option value="MOBILE_MONEY">Mobile Money</option>
                            <option value="CHEQUE">Chèque</option>
                            <option value="BON">Bon</option>
                            <?php if (!empty($canCreditSale)): ?>
                                <option value="CREDIT">Crédit client</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Champs pour Dépôt -->
                    <div id="depot_fields" class="md:col-span-2 hidden bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                        <h3 class="font-semibold text-blue-800 mb-3"><i class="fas fa-building mr-2"></i>Informations du Dépôt</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom de l'établissement *</label>
                                <input type="text" name="depot_nom_etablissement" id="depot_nom_etablissement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Adresse *</label>
                                <input type="text" name="depot_adresse" id="depot_adresse" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone *</label>
                                <input type="text" name="depot_telephone" id="depot_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numéro arrêté ministériel *</label>
                                <input type="text" name="depot_numero_arrete" id="depot_numero_arrete" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Champs pour Mobile Money -->
                    <div id="mobile_money_fields" class="md:col-span-2 hidden bg-green-50 border border-green-200 rounded-lg p-4 mt-4">
                        <h3 class="font-semibold text-green-800 mb-3"><i class="fas fa-mobile-alt mr-2"></i>Informations Mobile Money</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Opérateur *</label>
                                <select name="mobile_operateur" id="mobile_operateur" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <option value="">Sélectionner</option>
                                    <option value="ORANGE_MONEY">Orange Money</option>
                                    <option value="MOOV_MONEY">Moov Money</option>
                                    <option value="TELECEL_MONEY">Telecel Money</option>
                                    <option value="AUTRE">Autre</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du titulaire *</label>
                                <input type="text" name="mobile_nom_titulaire" id="mobile_nom_titulaire" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de téléphone *</label>
                                <input type="text" name="mobile_telephone" id="mobile_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>
                    </div>

                    <!-- Champs pour Chèque -->
                    <div id="cheque_fields" class="md:col-span-2 hidden bg-purple-50 border border-purple-200 rounded-lg p-4 mt-4">
                        <h3 class="font-semibold text-purple-800 mb-3"><i class="fas fa-money-check mr-2"></i>Informations du Chèque</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numéro du chèque *</label>
                                <input type="text" name="cheque_numero" id="cheque_numero" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la banque *</label>
                                <input type="text" name="cheque_nom_banque" id="cheque_nom_banque" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                        </div>
                    </div>

                    <!-- Champs pour Bon -->
                    <div id="bon_fields" class="md:col-span-2 hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
                        <h3 class="font-semibold text-yellow-800 mb-3"><i class="fas fa-ticket-alt mr-2"></i>Informations du Bon</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du bénéficiaire *</label>
                                <input type="text" name="bon_nom_beneficiaire" id="bon_nom_beneficiaire" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone *</label>
                                <input type="text" name="bon_telephone" id="bon_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Matricule *</label>
                                <input type="text" name="bon_matricule" id="bon_matricule" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numéro du bon *</label>
                                <input type="text" name="bon_numero_bon" id="bon_numero_bon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                        </div>
                    </div>
                    <div id="prescriptionAlert" class="md:col-span-2 hidden bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-yellow-800">
                                <i class="fas fa-prescription-bottle-medical mr-2"></i>
                                Ce produit necessite une ordonnance medicale.
                            </div>
                            <button type="button" onclick="openPrescriptionModal()" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                                Saisir ordonnance
                            </button>
                        </div>
                        <p id="prescriptionStatus" class="text-sm text-yellow-700 mt-2">Ordonnance non renseignee.</p>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="flex justify-between mt-6">
                    <button type="button" onclick="suspendreVente()" class="bg-amber-600 text-white px-6 py-2 rounded-md hover:bg-amber-700">
                        <i class="fas fa-pause mr-2"></i>Suspendre la vente
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="calculateTotals()" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                            <i class="fas fa-calculator mr-2"></i>Calculer
                        </button>
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <div id="prescriptionModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800"><i class="fas fa-file-medical mr-2 text-yellow-600"></i>Informations de l'ordonnance</h2>
                <button type="button" onclick="closePrescriptionModal()" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" id="ord_numero" placeholder="Numero ordonnance" class="px-3 py-2 border rounded">
                <input type="date" id="ord_date" class="px-3 py-2 border rounded">
                <input type="text" id="ord_medecin" placeholder="Nom du medecin" class="px-3 py-2 border rounded">
                <input type="text" id="ord_structure" placeholder="Structure sanitaire" class="px-3 py-2 border rounded">
                <input type="text" id="ord_patient" placeholder="Nom du patient" class="px-3 py-2 border rounded">
                <input type="text" id="ord_telephone" placeholder="Telephone du patient" class="px-3 py-2 border rounded">
                <textarea id="ord_observation" rows="3" placeholder="Observation" class="md:col-span-2 px-3 py-2 border rounded"></textarea>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closePrescriptionModal()" class="bg-gray-600 text-white px-5 py-2 rounded hover:bg-gray-700">Annuler</button>
                <button type="button" onclick="savePrescriptionInfo()" class="bg-yellow-600 text-white px-5 py-2 rounded hover:bg-yellow-700">Valider</button>
            </div>
        </div>
    </div>

    <script>
        const isReprise = <?= $isReprise ? 'true' : 'false' ?>;
        const venteEnCours = <?= $isReprise ? json_encode($venteEnCours ?? []) : 'null' ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            if (isReprise && venteEnCours) {
                // Pré-remplir le client
                if (venteEnCours.client_id) {
                    const clientSelect = document.getElementById('client_id');
                    clientSelect.dataset.resumeClientId = String(venteEnCours.client_id);
                    clientSelect.value = venteEnCours.client_id;
                }
                
                // Pré-remplir les articles
                if (venteEnCours.articles && venteEnCours.articles.length > 0) {
                    const articlesContainer = document.getElementById('articlesContainer');
                    articlesContainer.innerHTML = ''; // Vider le conteneur
                    
                    venteEnCours.articles.forEach((article, index) => {
                        addArticle();
                        
                        const rows = articlesContainer.querySelectorAll('.article-row');
                        const currentRow = rows[index];
                        
                        if (currentRow) {
                            const produitSelect = currentRow.querySelector('select[name="produit_id[]"]');
                            const quantiteInput = currentRow.querySelector('input[name="quantite[]"]');
                            const prixInput = currentRow.querySelector('input[name="prix_unitaire[]"]');
                            const totalInput = currentRow.querySelector('input[name="total_ligne[]"]');
                            
                            if (produitSelect) {
                                produitSelect.dataset.resumeProduitId = String(article.produit_id);
                                produitSelect.value = article.produit_id;
                            }
                            if (quantiteInput) quantiteInput.value = article.quantite;
                            if (prixInput) prixInput.value = article.prix_unitaire;
                            if (totalInput) totalInput.value = article.total_ligne;
                            
                            // Déclencher le changement de produit pour mettre à jour le stock
                            if (produitSelect) {
                                produitSelect.dispatchEvent(new Event('change'));
                            }
                        }
                    });
                }
                
                // Pré-remplir les montants
                if (venteEnCours.montant_net) {
                    document.getElementById('montant_total').value = venteEnCours.montant_net;
                }
                if (venteEnCours.montant_paye) {
                    document.getElementById('montant_paye').value = venteEnCours.montant_paye;
                }
                if (venteEnCours.remise) {
                    document.getElementById('remise').value = venteEnCours.remise;
                }
                if (venteEnCours.type_paiement) {
                    const modePaiement = document.querySelector('select[name="mode_paiement"]');
                    if (modePaiement) {
                        modePaiement.value = venteEnCours.type_paiement;
                        modePaiement.dispatchEvent(new Event('change'));
                    }
                }
                
                // Recalculer les totaux
                calculateTotals();
            }
        });
    </script>
    <script>
        let articleCount = 1;
        let produitsDisponibles = [];
        let prescriptionInfo = <?= json_encode($ordonnancePrefill ? [
            'numero_ordonnance' => $ordonnancePrefill['numero_ordonnance'],
            'date_ordonnance' => $ordonnancePrefill['date_ordonnance'],
            'nom_medecin' => $ordonnancePrefill['nom_medecin'],
            'structure_sanitaire' => $ordonnancePrefill['structure_sanitaire'],
            'nom_patient' => $ordonnancePrefill['nom_patient'],
            'telephone_patient' => $ordonnancePrefill['telephone_patient'],
            'observation' => $ordonnancePrefill['observation'],
        ] : null) ?>;
        let clientsData = [];
        const maxDiscountPercent = <?= json_encode($maxDiscountPercent) ?>;
        const scannerProduitId = <?= json_encode((int)($_GET['scanner_produit_id'] ?? 0)) ?>;

        // Modes de paiement par type de client
        const paymentModesByClientType = {
            'ORDINAIRE': ['ESPECE', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE'],
            'COURANT': ['ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON'],
            'COURANT_DEPOT': ['DEPOT'],
            'COURANT_BON': ['BON'],
            'COURANT_CARNET': ['CARNET'],
            'AUTRES_CLIENTS': ['ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON']
        };
        const canCreditSale = <?= json_encode(!empty($canCreditSale)) ?>;

        // Mode de paiement par défaut selon le type de client
        const defaultPaymentModeByClientType = {
            'COURANT_DEPOT': 'DEPOT',
            'COURANT_BON': 'BON',
            'COURANT_CARNET': 'CARNET'
        };

        // Labels des modes de paiement
        const paymentModeLabels = {
            'ESPECE': 'Espèces',
            'CARNET': 'Carnet',
            'DEPOT': 'Dépôt',
            'CARTE_VISA': 'Carte Visa',
            'MOBILE_MONEY': 'Mobile Money',
            'CHEQUE': 'Chèque',
            'BON': 'Bon'
        };

        // Fonction pour adapter les modes de paiement selon le type de client
        function adaptPaymentModesByClientType(clientType) {
            const modePaiementSelect = document.getElementById('mode_paiement');
            const allowedModes = [...(paymentModesByClientType[clientType] || paymentModesByClientType['ORDINAIRE'])];
            if (canCreditSale && !allowedModes.includes('CREDIT')) allowedModes.push('CREDIT');
            
            // Sauvegarder la valeur actuelle
            const currentValue = modePaiementSelect.value;
            
            // Vider et remplir le select
            modePaiementSelect.innerHTML = '';
            allowedModes.forEach(mode => {
                const option = document.createElement('option');
                option.value = mode;
                option.textContent = paymentModeLabels[mode] || mode;
                modePaiementSelect.appendChild(option);
            });
            
            // Sélectionner le mode par défaut ou conserver l'actuel si autorisé
            const defaultMode = defaultPaymentModeByClientType[clientType];
            if (defaultMode && allowedModes.includes(defaultMode)) {
                modePaiementSelect.value = defaultMode;
            } else if (allowedModes.includes(currentValue)) {
                modePaiementSelect.value = currentValue;
            } else {
                modePaiementSelect.value = allowedModes[0];
            }
            
            // Déclencher le changement pour afficher/masquer les champs
            togglePaymentFields();
            
            // Préremplir les détails pour Courant - Dépôt si possible
            if (clientType === 'COURANT_DEPOT') {
                prefillDepotDetails();
            }
        }

        // Fonction pour préremplir les détails du dépôt
        function prefillDepotDetails() {
            const clientId = document.getElementById('client_id').value;
            if (!clientId) return;
            
            const client = clientsData.find(c => c.id == clientId);
            if (client) {
                document.getElementById('depot_nom_etablissement').value = client.nom || '';
                document.getElementById('depot_adresse').value = client.adresse || '';
                document.getElementById('depot_telephone').value = client.telephone || '';
            }
        }

        // Fonction pour afficher/masquer les champs selon le mode de paiement
        function togglePaymentFields() {
            const modePaiement = document.getElementById('mode_paiement').value;
            
            // Masquer tous les champs
            document.getElementById('depot_fields').classList.add('hidden');
            document.getElementById('mobile_money_fields').classList.add('hidden');
            document.getElementById('cheque_fields').classList.add('hidden');
            document.getElementById('bon_fields').classList.add('hidden');
            
            // Réinitialiser les champs requis
            const allPaymentFields = document.querySelectorAll('#depot_fields input, #mobile_money_fields input, #mobile_money_fields select, #cheque_fields input, #bon_fields input');
            allPaymentFields.forEach(field => {
                field.required = false;
            });
            
            // Afficher les champs appropriés selon le mode
            switch (modePaiement) {
                case 'DEPOT':
                    document.getElementById('depot_fields').classList.remove('hidden');
                    document.getElementById('depot_nom_etablissement').required = true;
                    document.getElementById('depot_adresse').required = true;
                    document.getElementById('depot_telephone').required = true;
                    document.getElementById('depot_numero_arrete').required = true;
                    break;
                    
                case 'MOBILE_MONEY':
                    document.getElementById('mobile_money_fields').classList.remove('hidden');
                    document.getElementById('mobile_operateur').required = true;
                    document.getElementById('mobile_nom_titulaire').required = true;
                    document.getElementById('mobile_telephone').required = true;
                    break;
                    
                case 'CHEQUE':
                    document.getElementById('cheque_fields').classList.remove('hidden');
                    document.getElementById('cheque_numero').required = true;
                    document.getElementById('cheque_nom_banque').required = true;
                    break;
                    
                case 'BON':
                    document.getElementById('bon_fields').classList.remove('hidden');
                    document.getElementById('bon_nom_beneficiaire').required = true;
                    document.getElementById('bon_telephone').required = true;
                    document.getElementById('bon_matricule').required = true;
                    document.getElementById('bon_numero_bon').required = true;
                    break;
                    
                case 'ESPECE':
                case 'CARNET':
                case 'CARTE_VISA':
                case 'CREDIT':
                    // Aucun champ supplémentaire requis
                    break;
            }
        }

        // Validation côté client avant soumission
        function validatePaymentFields() {
            const modePaiement = document.getElementById('mode_paiement').value;
            const errors = [];
            
            switch (modePaiement) {
                case 'DEPOT':
                    if (!document.getElementById('depot_nom_etablissement').value.trim()) {
                        errors.push('Le nom de l\'établissement est obligatoire');
                    }
                    if (!document.getElementById('depot_adresse').value.trim()) {
                        errors.push('L\'adresse est obligatoire');
                    }
                    if (!document.getElementById('depot_telephone').value.trim()) {
                        errors.push('Le téléphone est obligatoire');
                    }
                    if (!document.getElementById('depot_numero_arrete').value.trim()) {
                        errors.push('Le numéro de l\'arrêté ministériel est obligatoire');
                    }
                    break;
                    
                case 'MOBILE_MONEY':
                    if (!document.getElementById('mobile_operateur').value) {
                        errors.push('L\'opérateur est obligatoire');
                    }
                    if (!document.getElementById('mobile_nom_titulaire').value.trim()) {
                        errors.push('Le nom du titulaire est obligatoire');
                    }
                    if (!document.getElementById('mobile_telephone').value.trim()) {
                        errors.push('Le numéro de téléphone est obligatoire');
                    }
                    break;
                    
                case 'CHEQUE':
                    if (!document.getElementById('cheque_numero').value.trim()) {
                        errors.push('Le numéro du chèque est obligatoire');
                    }
                    if (!document.getElementById('cheque_nom_banque').value.trim()) {
                        errors.push('Le nom de la banque est obligatoire');
                    }
                    break;
                    
                case 'BON':
                    if (!document.getElementById('bon_nom_beneficiaire').value.trim()) {
                        errors.push('Le nom du bénéficiaire est obligatoire');
                    }
                    if (!document.getElementById('bon_telephone').value.trim()) {
                        errors.push('Le téléphone est obligatoire');
                    }
                    if (!document.getElementById('bon_matricule').value.trim()) {
                        errors.push('Le matricule est obligatoire');
                    }
                    if (!document.getElementById('bon_numero_bon').value.trim()) {
                        errors.push('Le numéro du bon est obligatoire');
                    }
                    break;
            }
            
            return errors;
        }

        // Charger les clients via API
        fetch('/vente/apiClients')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    clientsData = data.data;
                    const select = document.getElementById('client_id');
                    data.data.forEach(client => {
                        const option = document.createElement('option');
                        option.value = client.id;
                        option.textContent = `${client.code} - ${client.nom} ${client.prenom}`;
                        option.dataset.typeClient = client.type_client || 'ORDINAIRE';
                        select.appendChild(option);
                    });
                    if (select.dataset.resumeClientId) {
                        select.value = select.dataset.resumeClientId;
                        delete select.dataset.resumeClientId;
                        select.dispatchEvent(new Event('change'));
                    }
                }
            });

        // Écouter le changement de client pour adapter les modes de paiement
        document.getElementById('client_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const clientType = selectedOption.dataset.typeClient || 'ORDINAIRE';
            adaptPaymentModesByClientType(clientType);
        });

        // Charger les produits via API
        fetch('/vente/apiProduits')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    produitsDisponibles = data.data;
                    document.querySelectorAll('.produit-select').forEach(select => populateProduitSelect(select));
                    if (scannerProduitId > 0) {
                        const firstSelect = document.querySelector('.produit-select');
                        if (firstSelect && Array.from(firstSelect.options).some(option => Number(option.value) === scannerProduitId)) {
                            firstSelect.value = String(scannerProduitId);
                            firstSelect.dispatchEvent(new Event('change'));
                            document.querySelector('input[name="quantite[]"]')?.focus();
                        }
                    }
                }
            });

        function populateProduitSelect(select) {
            const resumeProduitId = select.dataset.resumeProduitId || select.value;
            select.innerHTML = '<option value="">Selectionner un produit</option>';
            if (produitsDisponibles.length === 0) {
                select.innerHTML = '<option value="">Aucun produit disponible</option>';
                return;
            }
            produitsDisponibles.forEach(produit => {
                const option = document.createElement('option');
                option.value = produit.id;
                option.dataset.prix = produit.prix_vente;
                option.dataset.stock = produit.quantite_disponible;
                option.dataset.stockTheorique = produit.quantite_theorique || 0;
                option.dataset.prixAchat = produit.prix_achat || 0;
                option.dataset.requiresPrescription = Number(produit.requires_prescription || 0);
                option.dataset.typeDelivrance = produit.type_delivrance || 'MEDICAMENT_CONSEIL';
                option.dataset.rayon = produit.rayon || '';
                const code = produit.code_cip || produit.code_barre || produit.id;
                option.textContent = `${code} - ${produit.nom}`;
                select.appendChild(option);
            });
            if (resumeProduitId && Array.from(select.options).some(option => option.value === String(resumeProduitId))) {
                select.value = String(resumeProduitId);
                delete select.dataset.resumeProduitId;
                updateProduitFields(select);
            }
        }

        // Met à jour les champs du produit sélectionné
        function updateProduitFields(select) {
            const row = select.closest('.article-row');
            const selectedOption = select.options[select.selectedIndex];
            const stockInput = row.querySelector('.stock-disponible');
            const rayonInput = row.querySelector('.rayon-display');
            const prixInput = row.querySelector('input[name="prix_unitaire[]"]');
            
            if (selectedOption.value) {
                stockInput.value = selectedOption.dataset.stock || 0;
                rayonInput.value = selectedOption.dataset.rayon || '';
                prixInput.value = selectedOption.dataset.prix || 0;
            } else {
                stockInput.value = '';
                rayonInput.value = '';
                prixInput.value = '';
            }
        }

        // Ajouter les écouteurs d'événements pour les selects de produits
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('produit-select')) {
                updateProduitFields(e.target);
            }
        });

        // Ajouter un article
        function addArticle() {
            const container = document.getElementById('articlesContainer');
            const articleRow = document.createElement('div');
            articleRow.className = 'article-row grid grid-cols-8 gap-4 items-center mt-4';
            articleRow.innerHTML = `
                <select name="produit_id[]" class="produit-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selectionner un produit</option>
                </select>
                <input type="number" name="quantite[]" placeholder="Quantité" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <input type="text" name="stock_disponible[]" placeholder="Stock" readonly class="stock-disponible px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                <input type="text" name="rayon[]" placeholder="Rayon" readonly class="rayon-display px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700 text-sm">
                <input type="number" name="prix_unitaire[]" placeholder="Prix unitaire" min="0" step="0.01" readonly class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                <input type="number" name="total_ligne[]" placeholder="Total" min="0" step="0.01" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <button type="button" onclick="removeArticle(this)" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(articleRow);
            populateProduitSelect(articleRow.querySelector('.produit-select'));
            articleCount++;
        }

        // Supprimer un article
        function removeArticle(button) {
            const row = button.closest('.article-row');
            row.remove();
            calculateTotals();
            updatePrescriptionRequirement();
        }

        // Calculer les totaux
        function calculateTotals() {
            const quantites = document.querySelectorAll('input[name="quantite[]"]');
            const prixUnitaires = document.querySelectorAll('input[name="prix_unitaire[]"]');
            const totauxLignes = document.querySelectorAll('input[name="total_ligne[]"]');
            
            let totalHT = 0;
            
            quantites.forEach((quantite, index) => {
                const q = parseFloat(quantite.value) || 0;
                const pu = parseFloat(prixUnitaires[index].value) || 0;
                const total = q * pu;
                
                totauxLignes[index].value = total.toFixed(2);
                totalHT += total;
            });
            
            document.getElementById('montant_total').value = totalHT.toFixed(2);
            
            // Calculer le net à payer
            const remise = parseFloat(document.getElementById('remise').value) || 0;
            if (remise > maxDiscountPercent) {
                document.getElementById('remise').value = maxDiscountPercent.toFixed(2);
                alert(`La remise maximale autorisee pour votre role est de ${maxDiscountPercent.toFixed(2)}%.`);
            }
            const remiseValidee = Math.min(remise, maxDiscountPercent);
            const netAPayer = totalHT - (totalHT * remiseValidee / 100);
            document.getElementById('montant_paye').value = netAPayer.toFixed(2);
        }

        // Auto-calcul quand les valeurs changent
        document.addEventListener('input', function(e) {
            if (e.target.name === 'quantite[]' || e.target.name === 'prix_unitaire[]') {
                calculateTotals();
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.name === 'produit_id[]') {
                const row = e.target.closest('.article-row');
                const selected = e.target.selectedOptions[0];
                const prixInput = row.querySelector('input[name="prix_unitaire[]"]');
                const stockInput = row.querySelector('input[name="stock_disponible[]"]');
                const quantiteInput = row.querySelector('input[name="quantite[]"]');
                const stockDisponible = selected?.dataset.stock || '';
                const stockTheorique = selected?.dataset.stockTheorique || '0';

                prixInput.value = selected?.dataset.prix || '';
                stockInput.value = stockDisponible !== '' ? `${stockDisponible} / theo ${stockTheorique}` : '';
                stockInput.classList.toggle('text-red-700', Number(stockDisponible) <= 0 && stockDisponible !== '');
                if (!quantiteInput.value) {
                    quantiteInput.value = 1;
                }
                quantiteInput.max = stockDisponible || '';
                calculateTotals();
                updatePrescriptionRequirement();
            }
        });

        function selectedPrescriptionProducts() {
            const requiredTypes = ['ORDONNANCIER', 'PSYCHOTROPE', 'ANTICANCEREUX'];
            return Array.from(document.querySelectorAll('select[name="produit_id[]"]'))
                .map(select => select.selectedOptions[0])
                .filter(option => {
                    if (!option || !option.value) return false;
                    const type = option.dataset.typeDelivrance || 'MEDICAMENT_CONSEIL';
                    return requiredTypes.includes(type) || Number(option.dataset.requiresPrescription || 0) === 1;
                });
        }

        function suspendreVente() {
            const form = document.getElementById('venteForm');
            const formData = new FormData(form);
            
            // Vérifier qu'il y a au moins un article
            const produits = document.querySelectorAll('select[name="produit_id[]"]');
            let hasProducts = false;
            produits.forEach(select => {
                if (select.value && select.value !== '') hasProducts = true;
            });
            
            if (!hasProducts) {
                alert('Veuillez ajouter au moins un produit avant de suspendre la vente.');
                return;
            }
            
            formData.append('suspendre', 'true');
            formData.append('return_to', <?= json_encode($returnTo) ?>);
            const quantites = document.querySelectorAll('input[name="quantite[]"]');
            const produitSelects = document.querySelectorAll('select[name="produit_id[]"]');
            const articles = [];
            quantites.forEach((quantiteInput, index) => {
                const produitId = parseInt(produitSelects[index].value, 10) || 0;
                const quantite = parseFloat(quantiteInput.value) || 0;
                if (produitId > 0 && quantite > 0) {
                    articles.push({produit_id: produitId, quantite: quantite});
                }
            });
            formData.append('articles', JSON.stringify(articles));
            // Suspension has no final payment; this prevents an accidental cash entry.
            formData.set('montant_paye', '0');
            const ordonnanceId = document.querySelector('input[name="ordonnance_id"]');
            if (ordonnanceId) formData.append('ordonnance_id', ordonnanceId.value);
            if (prescriptionInfo) formData.append('ordonnance', JSON.stringify(prescriptionInfo));
            
            fetch('/vente/store', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const errorElements = doc.querySelectorAll('.text-red-700 li');
                    if (errorElements.length > 0) {
                        const errors = Array.from(errorElements).map(el => el.textContent);
                        throw new Error(errors.join(', '));
                    }
                    return { success: false, message: 'Réponse serveur invalide' };
                }
            })
            .then(data => {
                if (data.success) {
                    window.location.href = '/vente/tickets-en-attente?return_to=<?= urlencode($returnTo) ?>';
                } else {
                    alert('Erreur: ' + (data.message || 'Erreur lors de la suspension'));
                }
            })
            .catch(error => {
                alert('Erreur: ' + error.message);
            });
        }

        function updatePrescriptionRequirement() {
            const alertBox = document.getElementById('prescriptionAlert');
            const status = document.getElementById('prescriptionStatus');
            const required = selectedPrescriptionProducts().length > 0;
            alertBox.classList.toggle('hidden', !required);
            if (required) {
                status.textContent = prescriptionInfo ? 'Ordonnance renseignee.' : 'Ordonnance non renseignee.';
            }
        }

        function openPrescriptionModal() {
            document.getElementById('prescriptionModal').classList.remove('hidden');
        }

        function closePrescriptionModal() {
            document.getElementById('prescriptionModal').classList.add('hidden');
        }

        function getPrescriptionFormData() {
            return {
                numero_ordonnance: document.getElementById('ord_numero').value.trim(),
                date_ordonnance: document.getElementById('ord_date').value,
                nom_medecin: document.getElementById('ord_medecin').value.trim(),
                structure_sanitaire: document.getElementById('ord_structure').value.trim(),
                nom_patient: document.getElementById('ord_patient').value.trim(),
                telephone_patient: document.getElementById('ord_telephone').value.trim(),
                observation: document.getElementById('ord_observation').value.trim()
            };
        }

        function savePrescriptionInfo() {
            const data = getPrescriptionFormData();
            const missing = Object.entries(data)
                .filter(([key, value]) => key !== 'observation' && !value)
                .map(([key]) => key);

            if (missing.length > 0) {
                alert('Veuillez renseigner toutes les informations obligatoires de l ordonnance.');
                return;
            }

            prescriptionInfo = data;
            closePrescriptionModal();
            updatePrescriptionRequirement();
        }

        // Soumettre le formulaire en AJAX
        document.getElementById('venteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            updatePrescriptionRequirement();
            if (selectedPrescriptionProducts().length > 0 && !prescriptionInfo) {
                alert('Ce produit necessite une ordonnance medicale.');
                openPrescriptionModal();
                return;
            }

            const remise = parseFloat(document.getElementById('remise').value) || 0;
            if (remise > maxDiscountPercent) {
                alert(`La remise maximale autorisee pour votre role est de ${maxDiscountPercent.toFixed(2)}%.`);
                document.getElementById('remise').focus();
                return;
            }
            
            // Valider les champs de paiement
            const paymentErrors = validatePaymentFields();
            if (paymentErrors.length > 0) {
                alert('Erreurs de paiement:\n' + paymentErrors.join('\n'));
                return;
            }
            
            // Préparer les données correctement
            const formData = new FormData();
            
            // Ajouter les champs simples
            formData.append('client_id', document.getElementById('client_id').value);
            formData.append('montant_total', document.getElementById('montant_total').value);
            formData.append('remise', document.getElementById('remise').value);
            formData.append('montant_paye', document.getElementById('montant_paye').value);
            
            // Récupérer le mode de paiement
            const modePaiement = document.querySelector('select[name="mode_paiement"]');
            formData.append('mode_paiement', modePaiement ? modePaiement.value : 'ESPECES');
            formData.append('return_to', <?= json_encode($returnTo) ?>);
            
            // Ajouter les détails de paiement selon le mode
            if (modePaiement && modePaiement.value === 'DEPOT') {
                formData.append('depot_nom_etablissement', document.getElementById('depot_nom_etablissement').value);
                formData.append('depot_adresse', document.getElementById('depot_adresse').value);
                formData.append('depot_telephone', document.getElementById('depot_telephone').value);
                formData.append('depot_numero_arrete', document.getElementById('depot_numero_arrete').value);
            } else if (modePaiement && modePaiement.value === 'MOBILE_MONEY') {
                formData.append('mobile_operateur', document.getElementById('mobile_operateur').value);
                formData.append('mobile_nom_titulaire', document.getElementById('mobile_nom_titulaire').value);
                formData.append('mobile_telephone', document.getElementById('mobile_telephone').value);
            } else if (modePaiement && modePaiement.value === 'CHEQUE') {
                formData.append('cheque_numero', document.getElementById('cheque_numero').value);
                formData.append('cheque_nom_banque', document.getElementById('cheque_nom_banque').value);
            } else if (modePaiement && modePaiement.value === 'BON') {
                formData.append('bon_nom_beneficiaire', document.getElementById('bon_nom_beneficiaire').value);
                formData.append('bon_telephone', document.getElementById('bon_telephone').value);
                formData.append('bon_matricule', document.getElementById('bon_matricule').value);
                formData.append('bon_numero_bon', document.getElementById('bon_numero_bon').value);
            }
            
            // Ajouter les articles
            const quantites = document.querySelectorAll('input[name="quantite[]"]');
            const produitSelects = document.querySelectorAll('select[name="produit_id[]"]');
            const produits = [];
            
            quantites.forEach((quantiteInput, index) => {
                const quantite = parseFloat(quantiteInput.value) || 0;
                const produitId = parseInt(produitSelects[index].value, 10) || 0;
                if (produitId > 0 && quantite > 0) {
                    const prixUnitaire = parseFloat(document.querySelectorAll('input[name="prix_unitaire[]"]')[index].value) || 0;
                    const totalLigne = parseFloat(document.querySelectorAll('input[name="total_ligne[]"]')[index].value) || 0;
                    
                    produits.push({
                        produit_id: produitId,
                        quantite: quantite,
                        prix_unitaire: prixUnitaire,
                        total_ligne: totalLigne
                    });
                }
            });
            
            formData.append('articles', JSON.stringify(produits));
            if (prescriptionInfo) {
                formData.append('ordonnance', JSON.stringify(prescriptionInfo));
            }
            
            // Afficher un indicateur de chargement
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
            submitBtn.disabled = true;
            
            fetch('/vente/store', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // Essayer de parser en JSON, mais gérer aussi les réponses HTML
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        // Si c'est du HTML, essayer d'extraire les messages d'erreur
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(text, 'text/html');
                        const errorElements = doc.querySelectorAll('.text-red-700 li');
                        if (errorElements.length > 0) {
                            const errors = Array.from(errorElements).map(el => el.textContent);
                            throw new Error(errors.join(', '));
                        }
                        return { success: false, message: 'Réponse serveur invalide' };
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    if (data.vente_id && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        alert('Vente enregistrée, mais l\'ID de vente est absent dans la réponse.');
                        window.location.href = <?= json_encode($returnTo) ?>;
                    }
                } else {
                    alert('Erreur: ' + (data.message || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Erreur détaillée:', error);
                alert('Erreur lors de l\'envoi: ' + error.message);
            })
            .finally(() => {
                // Restaurer le bouton
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
