<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard Assistant') ?></title>
    <style>
        :root { --primary:#256b57; --primary-dark:#1f5949; --border:#d8e4de; --muted:#667085; --bg:#f1f6f3; --alert:#b54708; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:#1d2939; font:14px/1.45 Arial,sans-serif; }
        .page { max-width:1180px; margin:0 auto; padding:24px; }
        .header { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:16px 18px; background:#e7f1eb; border:1px solid var(--border); border-radius:8px; }
        .title-group { display:flex; align-items:center; gap:12px; }
        .pharmacy-mark { width:42px; height:42px; padding:8px; color:#fff; background:var(--primary); border:1px solid var(--primary-dark); border-radius:6px; }
        .pharmacy-mark svg { width:100%; height:100%; stroke:currentColor; stroke-width:1.7; fill:none; }
        .health-mark { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; margin-left:2px; color:var(--primary); background:#fff; border:1px solid #c5ddd1; border-radius:50%; }
        .health-mark svg { width:13px; height:13px; fill:currentColor; }
        h1,h2 { margin:0; } h1 { font-size:22px; } h2 { font-size:16px; }
        .meta { display:flex; align-items:center; justify-content:flex-end; gap:16px; color:var(--muted); text-align:right; }
        .cash-open { color:var(--primary); font-weight:600; } .cash-closed { color:var(--muted); font-weight:600; }
        a { color:var(--primary); text-decoration:none; } a:hover { text-decoration:underline; }
        .logout { color:#a12b2b; }
        .kpis,.actions { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:20px; }
        .card { background:#fff; border:1px solid var(--border); border-radius:6px; }
        .kpi { padding:14px; } .kpi dt { color:var(--muted); font-size:13px; } .kpi dd { margin:5px 0 0; font-size:21px; font-weight:600; }
        .kpis .card:nth-child(1) { background:#e9f4ee; } .kpis .card:nth-child(2) { background:#edf3f6; } .kpis .card:nth-child(3) { background:#f5f4eb; } .kpis .card:nth-child(4) { background:#fff4e9; }
        .action { display:flex; align-items:center; justify-content:center; gap:9px; padding:13px 14px; font-weight:600; text-align:center; background:#fff; } .action:hover { background:#e9f4ee; border-color:#b9d5c7; text-decoration:none; }
        .action svg { width:17px; height:17px; stroke:currentColor; stroke-width:1.8; fill:none; flex:none; }
        section { margin-top:24px; } .section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .alerts { background:#fffaf5; border:1px solid #efd9bf; border-radius:6px; }
        .alert { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid var(--border); }
        .alert:last-child { border:0; } .alert-count { color:var(--alert); font-weight:700; }
        .empty { color:var(--muted); padding:14px; }
        .table-wrap { overflow-x:auto; background:#fff; border:1px solid var(--border); border-radius:6px; }
        table { border-collapse:collapse; width:100%; min-width:720px; } th,td { padding:11px 14px; text-align:left; border-bottom:1px solid var(--border); } th { color:var(--muted); font-size:12px; font-weight:600; background:#fafbfb; } tr:last-child td { border:0; }
        .status { font-size:12px; color:var(--muted); } .sale-actions { white-space:nowrap; } .sale-actions a + a { margin-left:10px; }
        @media (max-width:700px) { .page { padding:16px; } .header,.meta { align-items:flex-start; flex-direction:column; } .meta { text-align:left; gap:4px; } .kpis,.actions { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>
<?php
    $assistantName = (string)($user['username'] ?? $user['name'] ?? 'Assistant');
    $actions = is_array($actionCards ?? null) ? $actionCards : [];
    $actionIcons = [
        'Nouvelle vente' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L20 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>',
        'Clients' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>',
        'Suivi client' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
        'Caisse' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16v10H4zM6 10V5h12v5M8 14h3M15 14h2"/></svg>',
    ];
?>
<main class="page">
    <header class="header">
        <div class="title-group">
            <span class="pharmacy-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32">
                    <g transform="rotate(-42 11 11)"><rect x="4" y="8" width="14" height="6" rx="3"/><path d="M11 8v6"/></g>
                    <g transform="rotate(38 21 21)"><rect x="14" y="18" width="14" height="6" rx="3"/><path d="M21 18v6"/></g>
                </svg>
            </span>
            <h1>Dashboard Assistant</h1>
            <span class="health-mark" aria-label="Santé">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/></svg>
            </span>
        </div>
        <div class="meta">
            <span><?= htmlspecialchars($assistantName) ?></span>
            <time><?= date('d/m/Y H:i') ?></time>
            <span id="cashStatus" class="cash-closed">Caisse : —</span>
            <a class="logout" href="/logout">Déconnexion</a>
        </div>
    </header>

    <dl class="kpis" id="kpis" aria-live="polite">
        <div class="card kpi"><dt>CA du jour</dt><dd>—</dd></div>
        <div class="card kpi"><dt>Ventes du jour</dt><dd>—</dd></div>
        <div class="card kpi"><dt>Encaissements</dt><dd>—</dd></div>
        <div class="card kpi"><dt>Alertes</dt><dd>—</dd></div>
    </dl>

    <nav class="actions" aria-label="Actions principales">
        <?php foreach ($actions as $action): ?>
            <?php $actionTitle = (string)$action['title']; ?>
            <a class="card action" href="<?= htmlspecialchars((string)$action['url']) ?>">
                <?= $actionIcons[$actionTitle] ?? '' ?>
                <span><?= htmlspecialchars($actionTitle) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section>
        <div class="section-head"><h2>Alertes</h2></div>
        <div class="alerts" id="alerts"><p class="empty">Chargement…</p></div>
    </section>

    <section>
        <div class="section-head"><h2>Dernières ventes</h2><a href="/vente/historique?return_to=/assistant/dashboard">Voir tout l’historique</a></div>
        <div class="table-wrap" id="sales"></div>
    </section>
</main>

<script>
const canCancelTicket = <?= !empty($canCancelTicket) ? 'true' : 'false' ?>;
const money = value => new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 0}).format(Number(value || 0)) + ' FCFA';
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const ticketUrl = id => '/vente/impression?id=' + encodeURIComponent(id) + '&return_to=' + encodeURIComponent('/assistant/dashboard');

function render(data) {
    const widgets = data.widgets || {};
    document.getElementById('kpis').innerHTML = [
        ['CA du jour', money(widgets.ca_jour)],
        ['Ventes du jour', Number(widgets.ventes_jour || 0)],
        ['Encaissements', money(widgets.montant_encaisse)],
        ['Alertes', Number(widgets.alertes || 0)]
    ].map(([label, value]) => `<div class="card kpi"><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>`).join('');

    const cash = data.cash_register || {};
    const cashStatus = document.getElementById('cashStatus');
    cashStatus.className = cash.open ? 'cash-open' : 'cash-closed';
    cashStatus.textContent = cash.open ? 'Caisse : Ouverte' : 'Caisse : Fermée';

    const alerts = data.alerts || [];
    document.getElementById('alerts').innerHTML = alerts.length
        ? alerts.map(alert => `<a class="alert" href="${esc(alert.url)}"><span>${esc(alert.label)}</span><span class="alert-count">${Number(alert.count || 0)}</span></a>`).join('')
        : '<p class="empty">Aucune alerte</p>';

    const sales = data.recent_sales || [];
    document.getElementById('sales').innerHTML = sales.length ? `<table><thead><tr><th>Heure</th><th>Ticket</th><th>Client</th><th>Montant</th><th>Statut</th><th>Action</th></tr></thead><tbody>${sales.map(sale => {
        const url = ticketUrl(sale.id);
        const cancel = canCancelTicket && String(sale.statut_vente || '').toUpperCase() !== 'ANNULEE' ? `<a href="/assistant/annulation-ticket?ticket_id=${encodeURIComponent(sale.id)}">Annuler</a>` : '';
        return `<tr><td>${esc(String(sale.date_vente || '').slice(11,16))}</td><td>${esc(sale.numero_facture || '-')}</td><td>${esc(sale.client_nom || 'Client comptoir')}</td><td>${money(sale.montant_net)}</td><td class="status">${esc(sale.statut_vente || '-')}</td><td class="sale-actions"><a href="${url}">Voir</a><a href="${url}">Réimprimer</a>${cancel}</td></tr>`;
    }).join('')}</tbody></table>` : '<p class="empty">Aucune vente récente</p>';
}

fetch('/assistant/api/dashboard', {headers: {'X-Requested-With':'XMLHttpRequest'}})
    .then(response => {
        if (response.status === 401 || response.status === 403) {
            document.getElementById('alerts').innerHTML = '<p class="empty">Session expirée ou accès refusé.</p>';
            document.getElementById('sales').innerHTML = '<p class="empty">Reconnectez-vous pour charger les ventes.</p>';
            return null;
        }
        if (!response.ok) throw new Error('Erreur serveur (' + response.status + ')');
        return response.json();
    })
    .then(payload => { if (payload) render(payload.data || {}); })
    .catch(() => {
        document.getElementById('alerts').innerHTML = '<p class="empty">Impossible de charger les données.</p>';
        document.getElementById('sales').innerHTML = '<p class="empty">Impossible de charger les ventes.</p>';
    });
</script>
</body>
</html>
