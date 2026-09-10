<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Statistiques Administrateur')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <?php
        $stats = $stats ?? [];
        $resume = $stats['resume'] ?? [];
        $money = static fn($value): string => number_format((float)$value, 0, ',', ' ') . ' FCFA';
        $number = static fn($value): string => number_format((float)$value, 0, ',', ' ');
        $dateFormat = static function ($value): string {
            if (empty($value)) {
                return '-';
            }

            $timestamp = strtotime((string)$value);
            return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$value;
        };
        $adminActive = 'stats';
        require __DIR__ . '/../partials/admin-header.php';
    ?>

    <main class="admin-container">
        <div class="admin-page-head">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Statistiques Administrateur</h1>
                <p class="text-sm text-gray-500">Ventes, activite, sessions et journal recent</p>
            </div>
            <span id="last-refresh" class="admin-button admin-button-muted admin-page-actions">Mis a jour: <?= date('H:i:s') ?></span>
        </div>

        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">CA aujourd'hui</div>
                <div id="sales-day-total" class="text-2xl font-bold text-green-700"><?= $money($resume['ventes_jour']['total'] ?? 0) ?></div>
                <div id="sales-day-count" class="text-sm text-gray-500"><?= $number($resume['ventes_jour']['count'] ?? 0) ?> vente(s)</div>
            </div>
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">CA du mois</div>
                <div id="sales-month-total" class="text-2xl font-bold text-blue-700"><?= $money($resume['ventes_mois']['total'] ?? 0) ?></div>
                <div id="sales-month-count" class="text-sm text-gray-500"><?= $number($resume['ventes_mois']['count'] ?? 0) ?> vente(s)</div>
            </div>
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">Clients actifs</div>
                <div id="active-clients" class="text-2xl font-bold text-gray-800"><?= $number($resume['clients_actifs'] ?? 0) ?></div>
                <div id="active-products" class="text-sm text-gray-500"><?= $number($resume['produits_actifs'] ?? 0) ?> produit(s)</div>
            </div>
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">Alertes operationnelles</div>
                <div id="low-stock" class="text-2xl font-bold text-red-700"><?= $number($resume['produits_alerte'] ?? 0) ?></div>
                <div id="open-sessions" class="text-sm text-gray-500"><?= $number($resume['sessions_ouvertes'] ?? 0) ?> session(s) ouverte(s)</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="admin-card p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-calendar-day text-blue-600 mr-2"></i>Ventes des 7 derniers jours
                </h2>
                <div id="sales-bars" class="space-y-3"></div>
            </div>

            <div class="admin-card p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-pills text-green-600 mr-2"></i>Produits les plus vendus
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-3 py-2 text-left">Produit</th>
                                <th class="border border-gray-200 px-3 py-2 text-right">Quantite</th>
                                <th class="border border-gray-200 px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody id="top-products-body"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="admin-card p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-cash-register text-purple-600 mr-2"></i>Sessions recentes
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-3 py-2 text-left">Session</th>
                                <th class="border border-gray-200 px-3 py-2 text-left">Caissier</th>
                                <th class="border border-gray-200 px-3 py-2 text-left">Statut</th>
                                <th class="border border-gray-200 px-3 py-2 text-left">Ouverture</th>
                            </tr>
                        </thead>
                        <tbody id="sessions-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="admin-card p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-history text-red-600 mr-2"></i>Dernieres actions
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-3 py-2 text-left">Date</th>
                                <th class="border border-gray-200 px-3 py-2 text-left">Utilisateur</th>
                                <th class="border border-gray-200 px-3 py-2 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody id="audit-body"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        let statsData = <?= json_encode($stats) ?>;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function money(value) {
            return new Intl.NumberFormat('fr-FR').format(Number(value || 0)) + ' FCFA';
        }

        function number(value) {
            return new Intl.NumberFormat('fr-FR').format(Number(value || 0));
        }

        function formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(date.getTime()) ? value : date.toLocaleString('fr-FR');
        }

        function renderSummary(stats) {
            const resume = stats.resume || {};
            document.getElementById('sales-day-total').textContent = money(resume.ventes_jour?.total);
            document.getElementById('sales-day-count').textContent = number(resume.ventes_jour?.count) + ' vente(s)';
            document.getElementById('sales-month-total').textContent = money(resume.ventes_mois?.total);
            document.getElementById('sales-month-count').textContent = number(resume.ventes_mois?.count) + ' vente(s)';
            document.getElementById('active-clients').textContent = number(resume.clients_actifs);
            document.getElementById('active-products').textContent = number(resume.produits_actifs) + ' produit(s)';
            document.getElementById('low-stock').textContent = number(resume.produits_alerte);
            document.getElementById('open-sessions').textContent = number(resume.sessions_ouvertes) + ' session(s) ouverte(s)';
        }

        function renderSalesBars(rows) {
            const container = document.getElementById('sales-bars');
            const max = Math.max(1, ...rows.map(row => Number(row.total || 0)));

            if (!rows.length) {
                container.innerHTML = '<div class="text-gray-500 text-center py-6">Aucune vente recente</div>';
                return;
            }

            container.innerHTML = rows.map(row => {
                const percent = Math.max(4, Math.round((Number(row.total || 0) / max) * 100));
                return `
                    <div>
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>${escapeHtml(row.date)}</span>
                            <span>${number(row.nombre)} vente(s) - ${money(row.total)}</span>
                        </div>
                        <div class="h-3 bg-gray-100 rounded">
                            <div class="h-3 bg-blue-600 rounded" style="width:${percent}%"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderTopProducts(rows) {
            const tbody = document.getElementById('top-products-body');
            tbody.innerHTML = rows.length ? rows.map(row => `
                <tr>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(row.nom)}</td>
                    <td class="border border-gray-200 px-3 py-2 text-right">${number(row.quantite)}</td>
                    <td class="border border-gray-200 px-3 py-2 text-right">${money(row.total)}</td>
                </tr>
            `).join('') : '<tr><td colspan="3" class="border border-gray-200 px-3 py-6 text-center text-gray-500">Aucun produit vendu</td></tr>';
        }

        function renderSessions(rows) {
            const tbody = document.getElementById('sessions-body');
            tbody.innerHTML = rows.length ? rows.map(row => `
                <tr>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(row.numero_session || row.id)}</td>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(row.caissier_nom || '-')}</td>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(row.statut_session || '-')}</td>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(formatDate(row.date_ouverture))}</td>
                </tr>
            `).join('') : '<tr><td colspan="4" class="border border-gray-200 px-3 py-6 text-center text-gray-500">Aucune session</td></tr>';
        }

        function renderAudit(rows) {
            const tbody = document.getElementById('audit-body');
            tbody.innerHTML = rows.length ? rows.map(row => `
                <tr>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(formatDate(row.date_action))}</td>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(row.username || 'Systeme')}</td>
                    <td class="border border-gray-200 px-3 py-2">${escapeHtml(row.action_fr || row.action || '-')}</td>
                </tr>
            `).join('') : '<tr><td colspan="3" class="border border-gray-200 px-3 py-6 text-center text-gray-500">Aucune action recente</td></tr>';
        }

        function renderStats(stats) {
            renderSummary(stats);
            renderSalesBars(stats.ventes_7_jours || []);
            renderTopProducts(stats.top_produits || []);
            renderSessions(stats.sessions_recentes || []);
            renderAudit(stats.audit_recent || []);
        }

        let statsRefreshTimer = null;

        function stopStatsRefresh(message) {
            if (statsRefreshTimer) {
                clearInterval(statsRefreshTimer);
                statsRefreshTimer = null;
            }
            document.getElementById('last-refresh').textContent = message;
        }

        function refreshStats() {
            fetch('/admin/statistiques/live')
                .then(response => {
                    if (response.status === 401 || response.status === 403) {
                        stopStatsRefresh('Session expirée — reconnectez-vous.');
                        return null;
                    }
                    if (!response.ok) {
                        throw new Error('Erreur serveur (' + response.status + ')');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return;
                    if (!data.success) {
                        return;
                    }

                    statsData = data.stats || {};
                    renderStats(statsData);
                    document.getElementById('last-refresh').textContent = 'Mis a jour: ' + new Date().toLocaleTimeString('fr-FR');
                })
                .catch(error => {
                    console.error('Erreur statistiques:', error);
                    stopStatsRefresh('Rafraîchissement indisponible.');
                });
        }

        renderStats(statsData);
        statsRefreshTimer = setInterval(refreshStats, 10000);
    </script>
</body>
</html>
