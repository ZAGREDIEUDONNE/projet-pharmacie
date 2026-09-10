<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Tickets en Attente')) ?></title>
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
                <h1 class="text-2xl font-bold text-gray-900">Tickets en Attente</h1>
                <p class="text-sm text-gray-500">Reprendre une vente suspendue</p>
            </div>
            <a href="<?= htmlspecialchars((string)($returnTo ?? '/vente')) ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>

        <!-- Filtres -->
        <div class="card p-6 mb-6">
            <form method="GET" action="/vente/tickets-en-attente">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars((string)($dateDebut ?? '')) ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars((string)($dateFin ?? '')) ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                        <select name="client_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tous les clients</option>
                            <?php foreach ($clients ?? [] as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($clientId ?? 0) == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['nom'] . ' ' . ($client['prenom'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                            <i class="fas fa-filter mr-2"></i>Filtrer
                        </button>
                        <a href="/vente/tickets-en-attente?return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>" 
                           class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des tickets -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">N° Ticket</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Heure</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Client</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Articles</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Montant</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Utilisateur</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Dernière modif.</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets ?? [])): ?>
                            <tr>
                                <td colspan="9" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-pause-circle text-4xl mb-3 text-gray-300"></i>
                                    <p>Aucun ticket en attente</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm font-medium">
                                        <?= htmlspecialchars($ticket['numero_facture'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= date('d/m/Y', strtotime($ticket['date_vente'] ?? '')) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= date('H:i', strtotime($ticket['date_vente'] ?? '')) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars(($ticket['client_nom'] ?? '') . ' ' . ($ticket['client_prenom'] ?? '')) ?: '<span class="text-gray-400">-</span>' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <?= (int)($ticket['nombre_articles'] ?? 0) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right font-medium">
                                        <?= number_format((float)($ticket['montant_net'] ?? 0), 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($ticket['utilisateur'] ?? '') ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= $ticket['derniere_modification'] ? date('d/m H:i', strtotime($ticket['derniere_modification'])) : '-' ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="/vente/reprendre?id=<?= $ticket['id'] ?>&return_to=<?= urlencode((string)($returnTo ?? '/vente')) ?>" 
                                               class="px-3 py-1 bg-green-100 hover:bg-green-200 text-green-700 rounded text-xs font-medium"
                                               title="Reprendre">
                                                <i class="fas fa-play"></i>
                                            </a>
                                            <button onclick="showAnnulationModal(<?= $ticket['id'] ?>, '<?= htmlspecialchars($ticket['numero_facture'] ?? '') ?>')" 
                                                    class="px-3 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-xs font-medium"
                                                    title="Annuler">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($tickets ?? [])): ?>
                <div class="px-4 py-3 border-t border-gray-200 text-sm text-gray-500">
                    <?= count($tickets) ?> ticket(s) en attente
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal d'annulation -->
    <div id="annulationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Annuler le ticket</h3>
            <p class="text-sm text-gray-600 mb-4">
                Vous êtes sur le point d'annuler le ticket <strong id="ticketNumero"></strong>.
            </p>
            <form method="POST" action="/vente/annuler-ticket-en-attente">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="vente_id" id="venteId">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars((string)($returnTo ?? '/vente/tickets-en-attente')) ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif de l'annulation</label>
                    <textarea name="motif" rows="3" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Expliquez pourquoi vous annulez ce ticket..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="hideAnnulationModal()" 
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">
                        Confirmer l'annulation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAnnulationModal(venteId, ticketNumero) {
            document.getElementById('venteId').value = venteId;
            document.getElementById('ticketNumero').textContent = ticketNumero;
            document.getElementById('annulationModal').classList.remove('hidden');
            document.getElementById('annulationModal').classList.add('flex');
        }

        function hideAnnulationModal() {
            document.getElementById('annulationModal').classList.add('hidden');
            document.getElementById('annulationModal').classList.remove('flex');
        }
    </script>
</body>
</html>
