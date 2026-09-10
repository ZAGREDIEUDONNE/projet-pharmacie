<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Détails commande fournisseur')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow"><div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between"><h1 class="text-xl font-bold text-gray-800"><i class="fas fa-eye text-blue-600 mr-3"></i>Détails commande fournisseur</h1><div class="flex gap-2"><a href="/commande/print?id=<?= (int)($order['id'] ?? 0) ?>" class="bg-blue-600 text-white px-4 py-2 rounded"><i class="fas fa-print mr-2"></i>Imprimer</a><a href="/commande/historique" class="bg-gray-600 text-white px-4 py-2 rounded">Retour</a></div></div></nav>
<main class="max-w-7xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Informations générales</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><span class="text-gray-500">Numéro commande:</span><br><strong><?= htmlspecialchars((string)($order['numero_commande'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Référence:</span><br><strong><?= htmlspecialchars((string)($order['reference_commande'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Fournisseur:</span><br><strong><?= htmlspecialchars((string)($order['fournisseur_nom'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Date commande:</span><br><strong><?= htmlspecialchars((string)($order['date_commande'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Livraison prévue:</span><br><strong><?= htmlspecialchars((string)($order['date_livraison_prevue'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Statut:</span><br><strong><?= htmlspecialchars(str_replace('_', ' ', (string)($order['statut'] ?? ''))) ?></strong></div>
            <div><span class="text-gray-500">Créé par:</span><br><strong><?= htmlspecialchars((string)($order['utilisateur_nom'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Créé le:</span><br><strong><?= htmlspecialchars((string)($order['created_at'] ?? '-')) ?></strong></div>
            <div><span class="text-gray-500">Modifié le:</span><br><strong><?= htmlspecialchars((string)($order['updated_at'] ?? '-')) ?></strong></div>
        </div>
        <?php if (!empty($order['observations'])): ?>
        <div class="mt-4"><span class="text-gray-500">Observations:</span><br><strong><?= htmlspecialchars((string)$order['observations']) ?></strong></div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Produits commandés</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left text-xs font-bold uppercase text-gray-500">Produit</th>
                        <th class="p-3 text-center text-xs font-bold uppercase text-gray-500">Quantité</th>
                        <th class="p-3 text-center text-xs font-bold uppercase text-gray-500">Prix achat</th>
                        <th class="p-3 text-center text-xs font-bold uppercase text-gray-500">Remise %</th>
                        <th class="p-3 text-center text-xs font-bold uppercase text-gray-500">TVA %</th>
                        <th class="p-3 text-center text-xs font-bold uppercase text-gray-500">Total HT</th>
                        <th class="p-3 text-center text-xs font-bold uppercase text-gray-500">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($items ?? []) as $item): ?>
                        <?php 
                        $lineHT = $item['quantite_commandee'] * $item['prix_achat'];
                        $lineRemise = $lineHT * (($item['remise'] ?? 0) / 100);
                        $lineApresRemise = $lineHT - $lineRemise;
                        $lineTVA = $lineApresRemise * (($item['tva'] ?? 0) / 100);
                        $lineTTC = $lineApresRemise + $lineTVA;
                        ?>
                        <tr class="border-t">
                            <td class="p-3"><?= htmlspecialchars((string)($item['produit_nom'] ?? '-')) ?></td>
                            <td class="p-3 text-center"><?= (int)$item['quantite_commandee'] ?></td>
                            <td class="p-3 text-center"><?= number_format((float)$item['prix_achat'], 2, ',', ' ') ?> FCFA</td>
                            <td class="p-3 text-center"><?= number_format((float)($item['remise'] ?? 0), 2, ',', ' ') ?> %</td>
                            <td class="p-3 text-center"><?= number_format((float)($item['tva'] ?? 0), 2, ',', ' ') ?> %</td>
                            <td class="p-3 text-center"><?= number_format($lineHT, 2, ',', ' ') ?> FCFA</td>
                            <td class="p-3 text-center font-bold"><?= number_format($lineTTC, 2, ',', ' ') ?> FCFA</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-gray-500">Aucun produit dans cette commande.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4 p-4 bg-gray-50 rounded">
            <div class="flex justify-between mb-2"><span>Total HT:</span><span><?= number_format((float)($order['montant_ht'] ?? 0), 2, ',', ' ') ?> FCFA</span></div>
            <div class="flex justify-between mb-2"><span>Remise globale (<?= number_format((float)($order['remise_globale'] ?? 0), 2, ',', ' ') ?>%):</span><span><?= number_format((float)($order['montant_ht'] ?? 0) * ((float)($order['remise_globale'] ?? 0) / 100), 2, ',', ' ') ?> FCFA</span></div>
            <div class="flex justify-between mb-2"><span>TVA (<?= number_format((float)($order['tva_globale'] ?? 0), 2, ',', ' ') ?>%):</span><span><?= number_format(((float)($order['montant_ht'] ?? 0) - ((float)($order['montant_ht'] ?? 0) * ((float)($order['remise_globale'] ?? 0) / 100))) * ((float)($order['tva_globale'] ?? 0) / 100), 2, ',', ' ') ?> FCFA</span></div>
            <div class="flex justify-between font-bold text-lg"><span>Total TTC:</span><span><?= number_format((float)($order['montant_ttc'] ?? 0), 2, ',', ' ') ?> FCFA</span></div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Actions</h2>
        <div class="flex flex-wrap gap-3">
            <?php if (in_array(($order['statut'] ?? ''), ['BROUILLON', 'EN_ATTENTE'], true)): ?>
                <a href="/commande/edit?id=<?= (int)($order['id'] ?? 0) ?>" class="bg-blue-600 text-white px-4 py-2 rounded"><i class="fas fa-edit mr-2"></i>Modifier</a>
            <?php endif; ?>
            <?php if (in_array(($order['statut'] ?? ''), ['BROUILLON', 'EN_ATTENTE', 'ENVOYEE'], true)): ?>
                <form method="POST" action="/commande/cancel" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette commande ?');">
                    <input type="hidden" name="order_id" value="<?= (int)($order['id'] ?? 0) ?>">
                    <input type="hidden" name="motif" value="Annulation manuelle">
                    <button class="bg-red-600 text-white px-4 py-2 rounded"><i class="fas fa-times mr-2"></i>Annuler</button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/commande/duplicate" class="inline">
                <input type="hidden" name="order_id" value="<?= (int)($order['id'] ?? 0) ?>">
                <button class="bg-green-600 text-white px-4 py-2 rounded"><i class="fas fa-copy mr-2"></i>Dupliquer</button>
            </form>
            <a href="/commande/export-excel" class="bg-purple-600 text-white px-4 py-2 rounded"><i class="fas fa-file-excel mr-2"></i>Exporter Excel</a>
        </div>
    </div>
</main>
</body>
</html>
