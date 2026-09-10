<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Historique des Ventes')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: Inter, system-ui, sans-serif; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Historique des Ventes</h1>
                <p class="text-sm text-gray-500">Consultation des ventes effectuées</p>
            </div>
            <a href="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>

        <!-- Filtres -->
        <div class="card p-6 mb-6">
            <form method="GET" action="/vente/historique">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <!-- Recherche -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" name="recherche" value="<?= htmlspecialchars($recherche ?? '') ?>" 
                               placeholder="N° facture ou client" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Période -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
                        <select name="periode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Toutes</option>
                            <option value="aujourd'hui" <?= ($periode ?? '') === 'aujourd\'hui' ? 'selected' : '' ?>>Aujourd'hui</option>
                            <option value="semaine" <?= ($periode ?? '') === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                            <option value="mois" <?= ($periode ?? '') === 'mois' ? 'selected' : '' ?>>Ce mois</option>
                        </select>
                    </div>
                    
                    <!-- Date début -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Date fin -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Filtre utilisateur (admin uniquement) -->
                    <?php if ($isAdmin ?? false): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                            <select name="utilisateur_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tous</option>
                                <?php foreach ($utilisateurs ?? [] as $utilisateur): ?>
                                    <option value="<?= $utilisateur['id'] ?>" <?= ($filtreUserId ?? 0) == $utilisateur['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($utilisateur['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end gap-2 mt-4">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                    <a href="/vente/historique?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>" 
                       class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Liste des ventes -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">N° Facture</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Heure</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Client</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Articles</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Montant</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Paiement</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Statut</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Utilisateur</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventes ?? [])): ?>
                            <tr>
                                <td colspan="10" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-receipt text-4xl mb-3 text-gray-300"></i>
                                    <p>Aucune vente trouvée</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventes as $vente): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm font-medium">
                                        <?= htmlspecialchars($vente['numero_facture'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= date('d/m/Y', strtotime($vente['date_vente'] ?? '')) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= date('H:i', strtotime($vente['date_vente'] ?? '')) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars(($vente['client_nom'] ?? '') . ' ' . ($vente['client_prenom'] ?? '')) ?: '<span class="text-gray-400">-</span>' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <?= (int)($vente['nombre_articles'] ?? 0) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right font-medium">
                                        <?= number_format((float)($vente['montant_net'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($vente['type_paiement'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?= ($vente['statut_vente'] ?? '') === 'PAYEE' ? 'bg-green-100 text-green-700' : 
                                               (($vente['statut_vente'] ?? '') === 'EN_COURS' ? 'bg-amber-100 text-amber-700' : 
                                               'bg-red-100 text-red-700') ?>">
                                            <?= htmlspecialchars($vente['statut_vente'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($vente['utilisateur'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="/vente/impression?id=<?= $vente['id'] ?>&return_to=<?= urlencode((string)($returnTo ?? '/vente/historique')) ?>" 
                                               class="px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded text-xs font-medium"
                                               title="Voir détail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/vente/impression?id=<?= $vente['id'] ?>&return_to=<?= urlencode((string)($returnTo ?? '/vente/historique')) ?>" 
                                               class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-medium"
                                               title="Réimprimer">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($ventes ?? [])): ?>
                <div class="px-4 py-3 border-t border-gray-200 text-sm text-gray-500">
                    <?= count($ventes) ?> vente(s) trouvée(s)
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
