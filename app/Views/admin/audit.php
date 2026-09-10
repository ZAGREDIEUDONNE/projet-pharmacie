<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Journal de traçabilité')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .audit-action {
            font-family: 'Plus Jakarta Sans', Inter, ui-sans-serif, system-ui, sans-serif;
            font-weight: 600;
            letter-spacing: -0.01em;
            line-height: 1.35;
        }
        .audit-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.82rem;
        }
        .audit-badge-creation { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .audit-badge-modification { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .audit-badge-suppression { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .audit-badge-securite { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
        .audit-badge-autre { background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; }
        .audit-row-new { animation: auditFlash 2.4s ease-out; }
        @keyframes auditFlash {
            0% { background-color: #fef9c3; }
            100% { background-color: transparent; }
        }
        .live-dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 9999px;
            background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.65);
            animation: livePulse 1.8s infinite;
        }
        @keyframes livePulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
    </style>
</head>
<body class="admin-body">
    <?php
        $auditLogs = $auditLogs ?? [];
        $stats = $stats ?? [];
        $number = static function ($value): string {
            return number_format((float)$value, 0, ',', ' ');
        };
        $formatDate = static function ($value): string {
            if (empty($value)) {
                return '—';
            }
            $timestamp = strtotime((string)$value);
            return $timestamp ? date('d/m/Y H:i:s', $timestamp) : (string)$value;
        };
        $badgeClass = static function (string $category): string {
            return 'audit-badge audit-badge-' . ($category ?: 'autre');
        };
        $adminActive = 'audit';
        require __DIR__ . '/../partials/admin-header.php';
    ?>

    <main class="admin-container">
        <div class="admin-page-head">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Journal de traçabilité</h1>
                <p class="text-sm text-gray-500">Suivi en temps réel des actions utilisateurs et des modifications système</p>
            </div>
            <div class="flex items-center gap-3 text-sm text-gray-600">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-green-50 border border-green-200">
                    <span class="live-dot"></span>
                    <strong>En direct</strong>
                </span>
                <span id="audit-last-update" class="text-gray-500">Mise à jour : —</span>
            </div>
        </div>

        <section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">Logs (30 jours)</div>
                <div id="stat-total-logs" class="text-3xl font-bold text-gray-800"><?= $number($stats['total_logs'] ?? 0) ?></div>
            </div>
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">Créations</div>
                <div id="stat-creations" class="text-3xl font-bold text-green-600"><?= $number($stats['creations'] ?? 0) ?></div>
            </div>
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">Modifications</div>
                <div id="stat-modifications" class="text-3xl font-bold text-blue-600"><?= $number($stats['modifications'] ?? 0) ?></div>
            </div>
            <div class="admin-card p-5">
                <div class="text-sm text-gray-500">Suppressions / annulations</div>
                <div id="stat-suppressions" class="text-3xl font-bold text-red-600"><?= $number($stats['suppressions'] ?? 0) ?></div>
            </div>
        </section>

        <section class="admin-card p-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2 mb-4">
                <h2 class="text-lg font-semibold">
                    <i class="fas fa-list mr-2 text-red-600"></i>Dernières actions
                </h2>
                <span id="audit-count" class="text-sm text-gray-500"><?= count($auditLogs) ?> entrée(s)</span>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Référence</th>
                            <th>Adresse IP</th>
                        </tr>
                    </thead>
                    <tbody id="audit-table-body">
                        <?php foreach ($auditLogs as $log): ?>
                            <tr data-log-id="<?= (int)($log['id'] ?? 0) ?>">
                                <td class="whitespace-nowrap"><?= htmlspecialchars($formatDate($log['date_action'] ?? null)) ?></td>
                                <td><?= htmlspecialchars((string)($log['username'] ?? 'Système')) ?></td>
                                <td>
                                    <span class="<?= $badgeClass((string)($log['action_category'] ?? 'autre')) ?>">
                                        <span class="audit-action"><?= htmlspecialchars((string)($log['action_fr'] ?? $log['action'] ?? '')) ?></span>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string)($log['table_name_fr'] ?? $log['table_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($log['record_id'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string)($log['ip_address'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($auditLogs)): ?>
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500">Aucune action enregistrée pour le moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script>
        const categoryBadgeClass = {
            creation: 'audit-badge audit-badge-creation',
            modification: 'audit-badge audit-badge-modification',
            suppression: 'audit-badge audit-badge-suppression',
            securite: 'audit-badge audit-badge-securite',
            autre: 'audit-badge audit-badge-autre'
        };

        let knownLogIds = new Set(
            Array.from(document.querySelectorAll('#audit-table-body tr[data-log-id]'))
                .map(row => Number(row.dataset.logId || 0))
                .filter(Boolean)
        );
        let latestLogId = Math.max(0, ...Array.from(knownLogIds));
        let auditRefreshTimer = null;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('fr-FR').format(Number(value || 0));
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(date.getTime()) ? value : date.toLocaleString('fr-FR');
        }

        function renderActionCell(log) {
            const category = log.action_category || 'autre';
            const badge = categoryBadgeClass[category] || categoryBadgeClass.autre;
            const label = log.action_fr || log.action || '';
            return `<span class="${badge}"><span class="audit-action">${escapeHtml(label)}</span></span>`;
        }

        function updateAuditTable(logs, highlightNew = false) {
            const tbody = document.getElementById('audit-table-body');
            document.getElementById('audit-count').textContent = `${logs.length} entrée(s)`;

            if (!logs.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-gray-500">Aucune action enregistrée pour le moment.</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(log => {
                const id = Number(log.id || 0);
                const isNew = highlightNew && id > 0 && !knownLogIds.has(id);
                if (id > 0) knownLogIds.add(id);
                latestLogId = Math.max(latestLogId, id);

                return `
                    <tr data-log-id="${id}" class="${isNew ? 'audit-row-new' : ''}">
                        <td class="whitespace-nowrap">${escapeHtml(formatDate(log.date_action || log.created_at))}</td>
                        <td>${escapeHtml(log.username || 'Système')}</td>
                        <td>${renderActionCell(log)}</td>
                        <td>${escapeHtml(log.table_name_fr || log.table_name || log.module || '')}</td>
                        <td>${escapeHtml(log.record_id || '—')}</td>
                        <td>${escapeHtml(log.ip_address || '—')}</td>
                    </tr>
                `;
            }).join('');
        }

        function updateAuditStats(stats) {
            document.getElementById('stat-total-logs').textContent = formatNumber(stats.total_logs);
            document.getElementById('stat-creations').textContent = formatNumber(stats.creations);
            document.getElementById('stat-modifications').textContent = formatNumber(stats.modifications);
            document.getElementById('stat-suppressions').textContent = formatNumber(stats.suppressions);
        }

        function stopAuditRefresh(message) {
            if (auditRefreshTimer) {
                clearInterval(auditRefreshTimer);
                auditRefreshTimer = null;
            }
            document.getElementById('audit-last-update').textContent = message;
        }

        function refreshAudit() {
            const query = latestLogId > 0 ? '?limit=100&since_id=' + latestLogId : '?limit=100';
            fetch('/admin/audit/live' + query, { credentials: 'same-origin' })
                .then(response => {
                    if (response.status === 401 || response.status === 403) {
                        stopAuditRefresh('Session expirée — reconnectez-vous.');
                        return null;
                    }
                    if (!response.ok) {
                        throw new Error('Erreur serveur (' + response.status + ')');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return;
                    if (!data.success) return;

                    const newLogs = data.auditLogs || [];
                    const previousSize = knownLogIds.size;
                    updateAuditStats(data.stats || {});
                    if (newLogs.length) {
                        const existingLogs = Array.from(document.querySelectorAll('#audit-table-body tr[data-log-id]'))
                            .map(row => ({ id: Number(row.dataset.logId || 0), date_action: row.cells[0]?.textContent || '', username: row.cells[1]?.textContent || '', action: row.cells[2]?.textContent || '', table_name: row.cells[3]?.textContent || '', record_id: row.cells[4]?.textContent || '', ip_address: row.cells[5]?.textContent || '' }))
                            .filter(log => log.id > 0);
                        updateAuditTable([...newLogs, ...existingLogs].slice(0, 100), true);
                    }

                    if (newLogs.length > 0 || knownLogIds.size > previousSize) {
                        document.getElementById('audit-last-update').textContent =
                            'Mise à jour : ' + new Date().toLocaleTimeString('fr-FR');
                    } else if (data.generated_at) {
                        document.getElementById('audit-last-update').textContent =
                            'Mise à jour : ' + new Date(data.generated_at).toLocaleTimeString('fr-FR');
                    }
                })
                .catch(error => {
                    console.error('Erreur rafraîchissement traçabilité:', error);
                    stopAuditRefresh('Rafraîchissement indisponible.');
                });
        }

        document.getElementById('audit-last-update').textContent =
            'Mise à jour : ' + new Date().toLocaleTimeString('fr-FR');

        refreshAudit();
        auditRefreshTimer = setInterval(refreshAudit, 3000);
    </script>
</body>
</html>
