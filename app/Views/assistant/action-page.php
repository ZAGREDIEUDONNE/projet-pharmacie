<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $sectionLabel = $sectionLabel ?? 'Assistant';
        $sectionIcon = $sectionIcon ?? 'fa-user-tie';
        $sectionColor = $sectionColor ?? 'text-orange-600';
        $returnUrl = $returnUrl ?? '/assistant/dashboard';
    ?>
    <title><?= htmlspecialchars($title ?? $sectionLabel) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas <?= htmlspecialchars((string)$sectionIcon) ?> <?= htmlspecialchars((string)$sectionColor) ?> text-2xl mr-3"></i>
                <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($title ?? $sectionLabel) ?></h1>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-600"><?= htmlspecialchars((string)($user['username'] ?? $user['name'] ?? $sectionLabel)) ?></span>
                <a href="<?= htmlspecialchars((string)$returnUrl) ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <section class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($title ?? 'Assistant') ?></h2>
            <p class="text-gray-600 mt-2">Choisissez une action disponible pour continuer.</p>
            <?php if (!empty($accessNote)): ?>
                <div class="mt-4 bg-orange-50 border border-orange-200 text-orange-800 rounded p-3 text-sm">
                    <i class="fas fa-key mr-2"></i><?= htmlspecialchars((string)$accessNote) ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach (($actions ?? []) as $action): ?>
                <a href="<?= htmlspecialchars((string)($action['url'] ?? '/assistant/dashboard')) ?>" class="block bg-white rounded-lg shadow p-6 hover:bg-gray-50 hover:shadow-lg transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas <?= htmlspecialchars((string)($action['icon'] ?? 'fa-arrow-circle-right')) ?> text-2xl <?= htmlspecialchars((string)($action['color'] ?? 'text-blue-600')) ?>"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars((string)($action['label'] ?? 'Action')) ?></h3>
                            <p class="text-sm text-gray-600">Ouvrir</p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>

        <?php if (stripos((string)($title ?? ''), 'Annuler') !== false): ?>
            <section class="mt-6 bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Annulation controlee</h3>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded p-3 text-sm">
                        <?= htmlspecialchars((string)$_SESSION['success']) ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['errors'])): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded p-3 text-sm">
                        <?php foreach ((array)$_SESSION['errors'] as $error): ?>
                            <p><?= htmlspecialchars((string)$error) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>

                <?php
                    $cancelReturnTo = (string)($cancelReturnTo ?? '/assistant/annulation-ticket');
                    $tickets = is_array($tickets ?? null) ? $tickets : [];
                ?>

                <?php if (empty($tickets)): ?>
                    <p class="text-gray-500 text-sm">Aucun ticket annulable pour le moment.</p>
                <?php else: ?>
                    <form method="POST" action="/vente/cancel-ticket" class="space-y-4" onsubmit="return confirm('Confirmer l annulation de ce ticket ?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($cancelReturnTo) ?>">

                        <div>
                            <label for="vente_id" class="block text-sm font-medium text-gray-700 mb-2">Ticket a annuler</label>
                            <select id="vente_id" name="vente_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                                <option value="">-- Choisir un ticket --</option>
                                <?php foreach ($tickets as $ticket): ?>
                                    <?php
                                        $ticketId = (int)($ticket['id'] ?? 0);
                                        $numero = (string)($ticket['numero_facture'] ?? $ticketId);
                                        $dateVente = !empty($ticket['date_vente']) ? date('d/m/Y H:i', strtotime((string)$ticket['date_vente'])) : '-';
                                        $montant = number_format((float)($ticket['montant_net'] ?? $ticket['montant_total'] ?? 0), 0, ',', ' ');
                                        $client = (string)($ticket['client_label'] ?? 'Client comptoir');
                                        $vendeur = (string)($ticket['vendeur_nom'] ?? '-');
                                        $statut = (string)($ticket['statut_vente'] ?? '');
                                        $label = "#{$numero} | {$dateVente} | {$montant} FCFA | {$client} | {$vendeur} | {$statut}";
                                    ?>
                                    <option value="<?= $ticketId ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1"><?= count($tickets) ?> ticket(s) disponible(s)</p>
                        </div>

                        <div id="ticket-preview" class="hidden bg-gray-50 border border-gray-200 rounded-md p-4 text-sm text-gray-700"></div>

                        <div>
                            <label for="motif" class="block text-sm font-medium text-gray-700 mb-2">Motif d annulation</label>
                            <textarea id="motif" name="motif" rows="3" required maxlength="255"
                                      placeholder="Ex: erreur de saisie, double encaissement, demande client..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded">
                                <i class="fas fa-ban mr-2"></i>Annuler le ticket
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <script>
                (function () {
                    const select = document.getElementById('vente_id');
                    const preview = document.getElementById('ticket-preview');
                    if (!select || !preview) return;

                    const tickets = <?= json_encode(array_values($tickets), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                    function formatMoney(value) {
                        const amount = Number.parseFloat(value);
                        return Number.isFinite(amount)
                            ? amount.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' FCFA'
                            : '-';
                    }

                    function updatePreview() {
                        const ticket = tickets.find(item => String(item.id) === String(select.value));
                        if (!ticket) {
                            preview.classList.add('hidden');
                            preview.innerHTML = '';
                            return;
                        }

                        preview.innerHTML = `
                            <p><strong>Numero:</strong> ${ticket.numero_facture || ticket.id}</p>
                            <p><strong>Date:</strong> ${ticket.date_vente || '-'}</p>
                            <p><strong>Client:</strong> ${ticket.client_label || 'Client comptoir'}</p>
                            <p><strong>Vendeur:</strong> ${ticket.vendeur_nom || '-'}</p>
                            <p><strong>Montant:</strong> ${formatMoney(ticket.montant_net || ticket.montant_total)}</p>
                            <p><strong>Statut:</strong> ${ticket.statut_vente || '-'}</p>
                        `;
                        preview.classList.remove('hidden');
                    }

                    select.addEventListener('change', updatePreview);
                })();
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
