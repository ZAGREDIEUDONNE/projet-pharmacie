<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Sortie de Stock')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-minus-circle text-orange-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Sortie de Stock</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?= htmlspecialchars((string)($returnTo ?? '/produits')) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
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
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Formulaire de sortie</h2>
            <form method="POST" action="/produits/sortie-stock/store">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Produit *</label>
                        <select name="produit_id" id="produit_id" class="w-full border rounded-lg px-3 py-2" required>
                            <option value="">Sélectionner un produit</option>
                            <?php foreach ($produits ?? [] as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (($_SESSION['old_data']['produit_id'] ?? '') == $p['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($p['nom']) ?> (<?= htmlspecialchars($p['code_cip']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot (optionnel)</label>
                        <select name="lot_id" id="lot_id" class="w-full border rounded-lg px-3 py-2" disabled>
                            <option value="">Sélectionner d'abord un produit</option>
                        </select>
                        <div id="lot_message" class="text-sm text-gray-500 mt-1"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantité *</label>
                        <input type="number" name="quantite" value="<?= htmlspecialchars($_SESSION['old_data']['quantite'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2" min="1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date de sortie *</label>
                        <input type="date" name="date_sortie" value="<?= htmlspecialchars($_SESSION['old_data']['date_sortie'] ?? date('Y-m-d')) ?>" class="w-full border rounded-lg px-3 py-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motif *</label>
                        <select name="motif" class="w-full border rounded-lg px-3 py-2" required>
                            <option value="">Sélectionner un motif</option>
                            <?php foreach ($motifs ?? [] as $code => $label): ?>
                                <option value="<?= $code ?>" <?php if (($_SESSION['old_data']['motif'] ?? '') == $code): ?>selected<?php endif; ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock disponible</label>
                        <input type="text" id="stock_disponible" class="w-full border rounded-lg px-3 py-2 bg-gray-100" readonly value="-">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observations</label>
                        <textarea name="observations" rows="3" class="w-full border rounded-lg px-3 py-2"><?= htmlspecialchars($_SESSION['old_data']['observations'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-6">
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700">
                        <i class="fas fa-save mr-2"></i>Enregistrer la sortie
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#produit_id').change(function() {
                const produitId = $(this).val();
                
                if (produitId) {
                    // Récupérer le stock disponible
                    $.get('/produits/api/stock?id=' + produitId, function(data) {
                        if (data.error) {
                            $('#stock_disponible').val('Erreur');
                        } else {
                            $('#stock_disponible').val(data.stock);
                        }
                    }).fail(function() {
                        $('#stock_disponible').val('Erreur');
                    });
                    
                    // Récupérer les lots
                    $.get('/produits/api/lots?id=' + produitId, function(data) {
                        $('#lot_id').empty();
                        $('#lot_message').text('');
                        
                        if (data.error) {
                            $('#lot_id').append('<option value="">Erreur de chargement</option>');
                            $('#lot_id').prop('disabled', true);
                            $('#lot_message').text('Erreur: ' + data.error).addClass('text-red-500');
                        } else if (data.has_lots) {
                            $('#lot_id').prop('disabled', false);
                            $('#lot_id').append('<option value="">Sélectionner un lot</option>');
                            
                            $.each(data.lots, function(index, lot) {
                                const peremption = lot.date_peremption ? new Date(lot.date_peremption).toLocaleDateString('fr-FR') : 'N/A';
                                const optionText = lot.numero_lot + ' (Péremption: ' + peremption + ', Qté: ' + lot.quantite_restante + ')';
                                $('#lot_id').append('<option value="' + lot.id + '">' + optionText + '</option>');
                            });
                        } else {
                            $('#lot_id').prop('disabled', true);
                            $('#lot_id').append('<option value="">Ce produit n\'est pas géré par lots</option>');
                            $('#lot_message').text('Ce produit n\'est pas géré par lots').addClass('text-gray-500');
                        }
                    }).fail(function() {
                        $('#lot_id').empty();
                        $('#lot_id').append('<option value="">Erreur de chargement</option>');
                        $('#lot_id').prop('disabled', true);
                        $('#lot_message').text('Erreur de connexion au serveur').addClass('text-red-500');
                    });
                } else {
                    $('#stock_disponible').val('-');
                    $('#lot_id').empty();
                    $('#lot_id').append('<option value="">Sélectionner d\'abord un produit</option>');
                    $('#lot_id').prop('disabled', true);
                    $('#lot_message').text('');
                }
            });
            
            // Validation de la quantité par rapport au lot sélectionné
            $('#lot_id').change(function() {
                const lotId = $(this).val();
                if (lotId) {
                    const selectedOption = $(this).find('option:selected');
                    const lotText = selectedOption.text();
                    const quantiteMatch = lotText.match(/Qté:\s*(\d+)/);
                    
                    if (quantiteMatch) {
                        const lotQuantite = parseInt(quantiteMatch[1]);
                        const quantiteInput = $('input[name="quantite"]');
                        const currentQuantite = parseInt(quantiteInput.val()) || 0;
                        
                        if (currentQuantite > lotQuantite) {
                            quantiteInput.val(lotQuantite);
                            $('#lot_message').text('Quantité maximale pour ce lot: ' + lotQuantite).addClass('text-orange-500');
                        } else {
                            $('#lot_message').text('Stock disponible dans ce lot: ' + lotQuantite).removeClass('text-orange-500').addClass('text-green-600');
                        }
                    }
                } else {
                    $('#lot_message').text('');
                }
            });
        });
    </script>
</body>
</html>
