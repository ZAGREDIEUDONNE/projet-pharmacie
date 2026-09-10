<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Journal de Caisse') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php if (isset($print) && $print): ?>
        <style>
            body { background: white; }
            .no-print { display: none !important; }
            @media print {
                body { background: white; }
                .no-print { display: none !important; }
            }
        </style>
    <?php endif; ?>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-book text-indigo-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Journal de Caisse</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? ($user['username'] ?? '')) ?></span>
                    <a href="/caisse" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
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

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6 no-print">
            <div class="bg-blue-50 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Solde actuel</p>
                <p class="text-xl font-bold text-blue-600"><?= number_format($statistiques['solde_actuel'] ?? 0, 0, ',', ' ') ?> FCFA</p>
            </div>
            <div class="bg-green-50 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Total encaissements</p>
                <p class="text-xl font-bold text-green-600"><?= number_format($statistiques['total_encaissements'] ?? 0, 0, ',', ' ') ?> FCFA</p>
            </div>
            <div class="bg-red-50 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Total décaissements</p>
                <p class="text-xl font-bold text-red-600"><?= number_format($statistiques['total_decaissements'] ?? 0, 0, ',', ' ') ?> FCFA</p>
            </div>
            <div class="bg-purple-50 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Opérations</p>
                <p class="text-xl font-bold text-purple-600"><?= number_format($statistiques['total_operations'] ?? 0, 0, ',', ' ') ?></p>
            </div>
            <div class="bg-orange-50 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Annulations</p>
                <p class="text-xl font-bold text-orange-600"><?= number_format($statistiques['nombre_annulations'] ?? 0, 0, ',', ' ') ?></p>
            </div>
            <div class="bg-yellow-50 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Remboursements</p>
                <p class="text-xl font-bold text-yellow-600"><?= number_format($statistiques['nombre_remboursements'] ?? 0, 0, ',', ' ') ?></p>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 no-print">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>Filtres
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                    <input type="date" name="date_debut" value="<?= htmlspecialchars($filters['date_debut'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($filters['date_fin'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Utilisateur</label>
                    <select name="utilisateur_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Tous</option>
                        <?php foreach ($utilisateurs ?? [] as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($filters['utilisateur_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Session</label>
                    <select name="session_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Toutes</option>
                        <?php foreach ($sessions ?? [] as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($filters['session_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['numero_session']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type d'opération</label>
                    <select name="type_operation" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Tous</option>
                        <option value="VENTE" <?= ($filters['type_operation'] ?? '') === 'VENTE' ? 'selected' : '' ?>>Vente</option>
                        <option value="REMBOURSEMENT" <?= ($filters['type_operation'] ?? '') === 'REMBOURSEMENT' ? 'selected' : '' ?>>Remboursement</option>
                        <option value="APPROVISIONNEMENT" <?= ($filters['type_operation'] ?? '') === 'APPROVISIONNEMENT' ? 'selected' : '' ?>>Approvisionnement</option>
                        <option value="DECAISSEMENT" <?= ($filters['type_operation'] ?? '') === 'DECAISSEMENT' ? 'selected' : '' ?>>Décaissement</option>
                        <option value="ENCAISSEMENT_CLIENT" <?= ($filters['type_operation'] ?? '') === 'ENCAISSEMENT_CLIENT' ? 'selected' : '' ?>>Encaissement client</option>
                        <option value="REGLEMENT_CREANCE" <?= ($filters['type_operation'] ?? '') === 'REGLEMENT_CREANCE' ? 'selected' : '' ?>>Règlement créance</option>
                        <option value="ANNULATION_VENTE" <?= ($filters['type_operation'] ?? '') === 'ANNULATION_VENTE' ? 'selected' : '' ?>>Annulation vente</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
                    <input type="text" name="reference" value="<?= htmlspecialchars($filters['reference'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Référence...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Montant min</label>
                    <input type="number" name="montant_min" value="<?= htmlspecialchars($filters['montant_min'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Montant max</label>
                    <input type="number" name="montant_max" value="<?= htmlspecialchars($filters['montant_max'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="0">
                </div>
                <div class="flex items-end space-x-2 md:col-span-4">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex-1">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    <button type="button" onclick="window.location.href='/caisse/journal'" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Recherche instantanée -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
            <div class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" id="searchInput" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Rechercher par référence, observation ou utilisateur...">
                    <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                </div>
                <div class="space-x-2">
                    <a href="/caisse/journal/export-excel?<?= http_build_query($filters) ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-file-excel mr-2"></i>Excel
                    </a>
                    <a href="/caisse/journal/export-pdf?<?= http_build_query($filters) ?>" 
                       class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </a>
                    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tableau principal -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-list mr-2"></i>Opérations de caisse
                </h3>
                <p class="text-sm text-gray-600">
                    <?= count($operations ?? []) ?> opérations affichées
                </p>
            </div>

            <?php if (!empty($operations)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heure</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Entrée</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sortie</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Solde après</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observation</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($operations as $op): ?>
                                <?php
                                $date = date('d/m/Y', strtotime($op['date_mouvement']));
                                $heure = date('H:i', strtotime($op['date_mouvement']));
                                $typeLibelle = getLibelleType($op['type_mouvement']);
                                $reference = $op['reference'] ?? ($op['vente_reference'] ?? '-');
                                $utilisateur = $op['utilisateur_nom'] ?? '-';
                                $observation = $op['description'] ?? '-';
                                $isEntree = in_array($op['type_mouvement'], ['VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE']);
                                $isSortie = in_array($op['type_mouvement'], ['REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE']);
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= $date ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= $heure ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $isEntree ? 'bg-green-100 text-green-800' : ($isSortie ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                                            <?= htmlspecialchars($typeLibelle) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($reference) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($utilisateur) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold <?= $isEntree ? 'text-green-600' : '' ?>">
                                        <?= $isEntree ? number_format($op['montant'], 0, ',', ' ') . ' FCFA' : '' ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold <?= $isSortie ? 'text-red-600' : '' ?>">
                                        <?= $isSortie ? number_format($op['montant'], 0, ',', ' ') . ' FCFA' : '' ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-blue-600">
                                        <?= number_format($op['solde_apres'] ?? 0, 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= htmlspecialchars($observation) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center no-print">
                                        <div class="flex justify-center space-x-1">
                                            <a href="/caisse/journal/detail?id=<?= $op['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-800" title="Voir le détail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button onclick="printOperation(<?= $op['id'] ?>)" 
                                                    class="text-gray-600 hover:text-gray-800" title="Imprimer">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3"></i>
                    <p>Aucune opération trouvée pour cette période.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Recherche instantanée
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value;
            if (query.length >= 2) {
                fetch('/caisse/journal/search?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        // Mettre à jour le tableau avec les résultats
                        if (data.success) {
                            // Recharger la page avec le filtre de recherche
                            window.location.href = '/caisse/journal?reference=' + encodeURIComponent(query);
                        }
                    })
                    .catch(error => console.error('Erreur:', error));
            }
        });

        // Imprimer une opération
        function printOperation(id) {
            window.open('/caisse/journal/detail?id=' + id + '&print=1', '_blank');
        }

        <?php
        function getLibelleType($type) {
            $libelles = [
                'VENTE' => 'Vente comptant',
                'REMBOURSEMENT' => 'Remboursement',
                'APPROVISIONNEMENT' => 'Approvisionnement',
                'RETRAIT' => 'Retrait',
                'DECAISSEMENT' => 'Décaissement',
                'OUVERTURE_CAISSE' => 'Ouverture caisse',
                'FERMETURE_CAISSE' => 'Fermeture caisse',
                'ENCAISSEMENT_CLIENT' => 'Encaissement client',
                'REGLEMENT_CREANCE' => 'Règlement créance',
                'ACOMPTE_CLIENT' => 'Acompte client',
                'ANNULATION_VENTE' => 'Annulation vente',
                'CORRECTION_CAISSE' => 'Correction caisse',
                'AJUSTEMENT_CAISSE' => 'Ajustement caisse',
                'DEPOT_BANCAIRE' => 'Dépôt bancaire',
                'RETRAIT_BANCAIRE' => 'Retrait bancaire'
            ];
            return $libelles[$type] ?? $type;
        }
        ?>
    </script>
</body>
</html>
