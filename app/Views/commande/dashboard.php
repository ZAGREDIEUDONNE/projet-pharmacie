<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Dashboard Charge de commande')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --surface: #f1f6f3;
            --panel: #ffffff;
            --line: #d8e4de;
            --text: #1d2939;
            --muted: #667085;
            --green-pharmacy: #256b57;
            --green-dark: #1f5949;
            --alert-orange: #b54708;
            --alert-bg: #fffaf5;
            --alert-border: #efd9bf;
            --kpi-green: #e9f4ee;
            --kpi-blue: #edf3f6;
            --kpi-beige: #f5f4eb;
            --kpi-orange: #fff4e9;
            --action-hover: #e9f4ee;
            --action-hover-border: #b9d5c7;
        }

        * { box-sizing: border-box; }
        body {
            background: var(--surface);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }

        @media (max-width: 640px) {
            .container { padding: 16px; }
        }

        .layout {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: #ffffff;
            border-right: 1px solid var(--line);
            color: var(--text);
            padding: 16px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--line);
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: var(--green-pharmacy);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .nav-section {
            margin-bottom: 20px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
            padding-left: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 6px;
            color: var(--text);
            text-decoration: none;
            transition: background .16s ease, border-color .16s ease;
            border: 1px solid transparent;
            font-size: 14px;
        }

        .nav-link:hover {
            background: var(--action-hover);
            border-color: var(--action-hover-border);
        }

        .nav-link.active {
            background: var(--action-hover);
            border-color: var(--action-hover-border);
            color: var(--green-pharmacy);
            font-weight: 600;
        }

        .nav-link svg {
            width: 18px;
            height: 18px;
            flex: none;
            color: var(--green-pharmacy);
        }

        .header {
            background: #e7f1eb;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 16px 24px;
            margin-bottom: 24px;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        @media (max-width: 640px) {
            .header-content { flex-direction: column; align-items: flex-start; }
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        @media (max-width: 640px) {
            .header-right { flex-direction: column; align-items: flex-start; gap: 8px; }
        }

        .logo-medical {
            width: 32px;
            height: 32px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
        }

        .logo-pills {
            width: 32px;
            height: 32px;
            background: var(--green-pharmacy);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .section-subtitle {
            font-size: 13px;
            color: var(--muted);
        }

        .user-info {
            font-size: 13px;
            color: var(--text);
        }

        .user-name {
            font-weight: 600;
        }

        .logout-link {
            color: #dc2626;
            text-decoration: none;
            font-size: 13px;
        }

        .logout-link:hover {
            text-decoration: underline;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 6px;
        }

        .kpi {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 16px;
        }

        .kpi-green { background: var(--kpi-green); }
        .kpi-blue { background: var(--kpi-blue); }
        .kpi-beige { background: var(--kpi-beige); }
        .kpi-orange { background: var(--kpi-orange); }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 12px;
            border-radius: 4px;
            font-weight: 500;
        }

        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-amber { background: #fff4e9; color: #b54708; }
        .badge-green { background: #e9f4ee; color: #256b57; }
        .badge-blue { background: #edf3f6; color: #1d2939; }
        .badge-gray { background: #f1f6f3; color: #667085; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 600px; }
        th {
            background: #f1f6f3;
            border-bottom: 1px solid var(--line);
            color: var(--text);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }
        td {
            border-bottom: 1px solid var(--line);
            font-size: 14px;
            padding: 10px 12px;
            vertical-align: middle;
        }
        tr:hover td { background: #f8faf9; }

        .search {
            height: 34px;
            width: 100%;
            max-width: 240px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 0 10px;
            font-size: 13px;
        }

        .search:focus {
            border-color: var(--green-pharmacy);
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background .16s ease;
        }

        .btn-primary {
            background: var(--green-pharmacy);
            color: #fff;
            border: none;
        }
        .btn-primary:hover { background: var(--green-dark); }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        .skeleton {
            background: #e2e8f0;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        .alert-block {
            background: var(--alert-bg);
            border: 1px solid var(--alert-border);
            border-radius: 6px;
            padding: 12px 16px;
        }

        .success-message {
            background: #e9f4ee;
            border: 1px solid #b9d5c7;
            color: #256b57;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
        }

        .error-message {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
        }

        @media (max-width: 1024px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
<?php $returnTo = '/commande/dashboard'; ?>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
            </div>
            <div>
                <div style="font-weight: 600; color: var(--text);">Approvisionnement</div>
                <div style="font-size: 12px; color: var(--muted);">ERP Pharmacie</div>
            </div>
        </div>

        <nav>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="/commande/dashboard" class="nav-link active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Approvisionnement</div>
                <a href="/commande/saisie" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Besoins d'approvisionnement</span>
                </a>
                <a href="/commande/historique" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Commandes fournisseurs</span>
                </a>
                <a href="/commande/reception" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    <span>Réceptions</span>
                </a>
                <a href="/stock/fournisseurs?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Fournisseurs</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Stock</div>
                <a href="/stock/index?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span>État du stock</span>
                </a>
                <a href="/stock/ajouter?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Entrées</span>
                </a>
                <a href="/produits/sortie-stock?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Sorties</span>
                </a>
                <a href="/commande/mouvements?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                    <span>Mouvements</span>
                </a>
                <a href="/stock/ajustement?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>Ajustements</span>
                </a>
                <a href="/inventaire?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Inventaire</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Produits</div>
                <a href="/produits/catalogue-vendeur?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span>Catalogue</span>
                </a>
                <a href="/stock/historique-prix?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Prix</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Alertes</div>
                <a href="/stock/alerts?type=RUPTURE&amp;return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    <span>Ruptures</span>
                </a>
                <a href="/stock/alerts?type=STOCK_MINIMUM&amp;return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span>Stock faible</span>
                </a>
                <a href="/stock/peremptions?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Péremptions</span>
                </a>
                <a href="/commande/historique?retard=1&amp;return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>Commandes en retard</span>
                </a>
            </div>

            <div class="nav-section">
                <a href="/logout" class="nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span style="color: #dc2626;">Déconnexion</span>
                </a>
            </div>
        </nav>
    </aside>

    <div class="min-w-0">
        <div class="container">
            <header class="header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="logo-medical">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#256b57" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <div class="logo-pills">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                <line x1="8" y1="8" x2="16" y2="8"></line>
                                <line x1="8" y1="12" x2="16" y2="12"></line>
                                <line x1="8" y1="16" x2="12" y2="16"></line>
                            </svg>
                        </div>
                        <div>
                            <div class="section-title">Dashboard Charge de commande</div>
                            <div class="section-subtitle">Session active</div>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars((string)($user['username'] ?? '')) ?></span>
                            <span style="color: var(--muted); margin-left: 8px;"><?= date('d/m/Y H:i') ?></span>
                        </div>
                        <a href="/logout" class="logout-link">Déconnexion</a>
                    </div>
                </div>
            </header>

            <main>
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="success-message"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="error-message"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

            <div id="loader" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="kpi">
                        <div class="skeleton h-3 w-20 rounded mb-2"></div>
                        <div class="skeleton h-6 w-16 rounded"></div>
                    </div>
                <?php endfor; ?>
            </div>

            <div id="content" class="hidden">
                <!-- KPIs -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="kpis"></section>

                <!-- Produits à commander -->
                <section class="card p-5 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="section-title">Produits à commander</h2>
                        <input class="search" data-search="productsToOrderTable" placeholder="Rechercher">
                    </div>
                    <div id="productsToOrderTable"></div>
                </section>

                <!-- Commandes fournisseurs -->
                <section class="card p-5 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="section-title">Commandes fournisseurs</h2>
                        <div class="flex gap-2">
                            <input class="search" data-search="ordersTable" placeholder="Rechercher">
                            <a href="/commande/historique?return_to=<?= urlencode($returnTo) ?>" class="btn btn-sm btn-primary">Voir toutes</a>
                        </div>
                    </div>
                    <div id="ordersTable"></div>
                </section>

                <!-- Réceptions -->
                <section class="card p-5 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="section-title">Réceptions récentes</h2>
                        <input class="search" data-search="receptionsTable" placeholder="Rechercher">
                    </div>
                    <div id="receptionsTable"></div>
                </section>

                <!-- Alertes -->
                <section class="card p-5 mb-6">
                    <h2 class="section-title mb-4">Alertes stock</h2>
                    <div id="alertsList"></div>
                </section>

                <!-- Graphiques -->
                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="card p-5">
                        <h2 class="section-title mb-4">Entrées de stock par mois</h2>
                        <canvas id="chartEntries" class="h-56"></canvas>
                    </div>
                    <div class="card p-5">
                        <h2 class="section-title mb-4">Sorties de stock par mois</h2>
                        <canvas id="chartOutputs" class="h-56"></canvas>
                    </div>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="card p-5">
                        <h2 class="section-title mb-4">Répartition du stock par forme pharmaceutique</h2>
                        <canvas id="chartStockByForm" class="h-56"></canvas>
                    </div>
                    <div class="card p-5">
                        <h2 class="section-title mb-4">Top 10 produits sortis (90 jours)</h2>
                        <canvas id="chartTopProducts" class="h-56"></canvas>
                    </div>
                </section>

                <!-- Derniers mouvements -->
                <section class="card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="section-title">Derniers mouvements</h2>
                        <input class="search" data-search="movementsTable" placeholder="Rechercher">
                    </div>
                    <div id="movementsTable"></div>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
const tables = {};
const nf = new Intl.NumberFormat('fr-FR');
const moneyFormat = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function badge(value) {
    const status = String(value || '').toUpperCase();
    const cls = {
        RUPTURE: 'badge-red',
        FAIBLE: 'badge-amber',
        BROUILLON: 'badge-gray',
        ENVOYEE: 'badge-blue',
        VALIDEE: 'badge-green',
        RECEPTION_PARTIELLE: 'badge-amber',
        RECEPTION_COMPLETE: 'badge-green',
        EN_ATTENTE: 'badge-amber',
        PARTIELLEMENT_RECU: 'badge-amber',
        RECU_COMPLET: 'badge-green',
        ENTREE: 'badge-green',
        SORTIE: 'badge-red',
        AJUSTEMENT: 'badge-blue',
        TRANSFERT: 'badge-blue',
        PERTE: 'badge-red'
    }[status] || 'badge-gray';
    return `<span class="badge ${cls}">${esc(status.replaceAll('_', ' '))}</span>`;
}

function renderKpis(widgets) {
    const defs = [
        { label: 'Stock total', value: widgets.stock_total, class: 'kpi-blue', link: '/stock/index?return_to=/commande/dashboard' },
        { label: 'Ruptures', value: widgets.ruptures, class: 'kpi-orange', link: '/stock/alerts?type=RUPTURE&return_to=/commande/dashboard' },
        { label: 'Stock sous seuil', value: widgets.stock_sous_seuil, class: 'kpi-beige', link: '/stock/alerts?type=STOCK_MINIMUM&return_to=/commande/dashboard' },
        { label: 'Commandes en cours', value: widgets.commandes_en_cours, class: 'kpi-green', link: '/commande/historique?return_to=/commande/dashboard' }
    ];

    document.getElementById('kpis').innerHTML = defs.map(def => `
        <a href="${esc(def.link)}" class="kpi ${def.class}" style="text-decoration: none; display: block;">
            <div>
                <p style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">${esc(def.label)}</p>
                <p style="font-size: 20px; font-weight: 600; color: var(--text);">${nf.format(def.value || 0)}</p>
            </div>
        </a>
    `).join('');
}

function createTable(id, rows, columns, emptyText) {
    tables[id] = { rows: rows || [], columns, search: '', sort: columns[0].key, dir: 'asc', emptyText };
    drawTable(id);
}

function drawTable(id) {
    const table = tables[id];
    const host = document.getElementById(id);
    let rows = [...table.rows];

    if (table.search) {
        const q = table.search.toLowerCase();
        rows = rows.filter(row => Object.values(row).some(value => String(value ?? '').toLowerCase().includes(q)));
    }

    rows.sort((a, b) => {
        const av = a[table.sort] ?? '';
        const bv = b[table.sort] ?? '';
        const numeric = !Number.isNaN(Number(av)) && !Number.isNaN(Number(bv));
        const result = numeric ? Number(av) - Number(bv) : String(av).localeCompare(String(bv), 'fr');
        return table.dir === 'asc' ? result : -result;
    });

    const visible = rows.slice(0, 8);
    host.innerHTML = `
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>${table.columns.map(col => `<th data-sort="${esc(col.key)}">${esc(col.label)} ${table.sort === col.key ? (table.dir === 'asc' ? '↑' : '↓') : ''}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${visible.length ? visible.map(row => `<tr>${table.columns.map(col => `<td>${col.render ? col.render(row) : esc(row[col.key])}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${table.columns.length}" class="text-center py-6" style="color: var(--muted);">${esc(table.emptyText)}</td></tr>`}
                </tbody>
            </table>
        </div>
        ${rows.length > 8 ? `<div style="font-size: 12px; color: var(--muted); margin-top: 8px;">Affichage des 8 premiers sur ${nf.format(rows.length)}.</div>` : ''}
    `;
}

document.addEventListener('input', event => {
    const id = event.target.dataset.search;
    if (!id || !tables[id]) return;
    tables[id].search = event.target.value;
    drawTable(id);
});

document.addEventListener('click', event => {
    const th = event.target.closest('[data-sort]');
    if (!th) return;
    const host = event.target.closest('[id]');
    const table = tables[host?.id];
    if (!table) return;
    const key = th.dataset.sort;
    table.dir = table.sort === key && table.dir === 'asc' ? 'desc' : 'asc';
    table.sort = key;
    drawTable(host.id);
});

function renderAlerts(alertes) {
    const items = [
        { label: 'Ruptures', count: alertes.ruptures, link: '/stock/alerts?type=RUPTURE&return_to=/commande/dashboard' },
        { label: 'Stock sous le seuil', count: alertes.stock_sous_seuil, link: '/stock/alerts?type=STOCK_MINIMUM&return_to=/commande/dashboard' },
        { label: 'Péremptions < 30 jours', count: alertes.peremptions_30j, link: '/stock/peremptions?horizon=30&include_expired=0&return_to=/commande/dashboard' },
        { label: 'Péremptions < 60 jours', count: alertes.peremptions_60j, link: '/stock/peremptions?horizon=60&include_expired=0&return_to=/commande/dashboard' },
        { label: 'Commandes en retard', count: alertes.commandes_retard, link: '/commande/historique?retard=1&return_to=/commande/dashboard' }
    ];

    document.getElementById('alertsList').innerHTML = `
        <div class="space-y-2">
            ${items.map(item => `
                <div class="flex items-center justify-between py-2" style="border-bottom: 1px solid var(--line);">
                    <span style="font-size: 14px; color: var(--text);">${esc(item.label)}</span>
                    <div class="flex items-center gap-3">
                        <span style="font-weight: 600; color: var(--alert-orange);">${nf.format(item.count)}</span>
                        <a href="${esc(item.link)}" style="font-size: 12px; color: var(--green-pharmacy); text-decoration: none;">Voir</a>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderTables(data) {
    // Produits à commander
    createTable('productsToOrderTable', data.produits_a_commander || [], [
        { key: 'nom', label: 'Produit', render: row => `<strong>${esc(row.nom)}</strong><div style="font-size: 12px; color: var(--muted);">${esc(row.code_cip || '')}</div>` },
        { key: 'stock_actuel', label: 'Stock actuel', render: row => nf.format(Number(row.stock_actuel || 0)) },
        { key: 'stock_min', label: 'Stock min', render: row => nf.format(Number(row.stock_min || 0)) },
        { key: 'quantite_commandee', label: 'Commandé', render: row => nf.format(Number(row.quantite_commandee || 0)) },
        { key: 'besoin_estime', label: 'Besoin', render: row => `<strong style="color: var(--alert-orange);">${nf.format(row.besoin_estime)}</strong>` },
        { key: 'fournisseur_nom', label: 'Fournisseur', render: row => esc(row.fournisseur_nom || '-') },
        { key: 'action', label: 'Action', render: row => `<a href="/commande/saisie?produit_id=${encodeURIComponent(row.id || '')}&return_to=/commande/dashboard" class="btn btn-sm btn-primary">Commander</a>` }
    ], 'Aucun produit à commander.');

    // Commandes fournisseurs
    createTable('ordersTable', data.commandes_attente || [], [
        { key: 'numero_commande', label: 'Commande', render: row => `<strong>${esc(row.numero_commande)}</strong><div style="font-size: 12px; color: var(--muted);">${esc(row.fournisseur_nom || '')}</div>` },
        { key: 'date_commande', label: 'Date' },
        { key: 'montant_total', label: 'Montant', render: row => moneyFormat.format(Number(row.montant_total || 0)) + ' FCFA' },
        { key: 'quantite_attendue', label: 'Produits', render: row => nf.format(Number(row.quantite_attendue || 0)) },
        { key: 'statut', label: 'Statut', render: row => badge(row.statut) }
    ], 'Aucune commande en attente.');

    // Réceptions
    createTable('receptionsTable', data.receptions_recentes || [], [
        { key: 'numero_reception', label: 'Réception', render: row => `<strong>${esc(row.numero_reception)}</strong><div style="font-size: 12px; color: var(--muted);">${esc(row.fournisseur_nom || '')}</div>` },
        { key: 'date_reception', label: 'Date' },
        { key: 'numero_facture', label: 'Facture', render: row => esc(row.numero_facture || '-') },
        { key: 'montant_facture', label: 'Montant', render: row => row.montant_facture ? moneyFormat.format(Number(row.montant_facture)) + ' FCFA' : '-' },
        { key: 'statut', label: 'Statut', render: row => row.statut === 'PARTIELLEMENT_RECU' ? '<span class="badge badge-amber">Partielle</span>' : badge(row.statut) }
    ], 'Aucune réception récente.');

    // Mouvements
    createTable('movementsTable', data.mouvements_recents || [], [
        { key: 'produit_nom', label: 'Produit', render: row => `<strong>${esc(row.produit_nom)}</strong><div style="font-size: 12px; color: var(--muted);">${esc(row.code_cip || '')}</div>` },
        { key: 'type_mouvement', label: 'Type', render: row => badge(row.type_mouvement) },
        { key: 'quantite', label: 'Qté', render: row => `<strong>${nf.format(Number(row.quantite || 0))}</strong>` },
        { key: 'utilisateur_nom', label: 'Utilisateur', render: row => esc(row.utilisateur_nom || '-') },
        { key: 'date_mouvement', label: 'Date' },
        { key: 'motif', label: 'Observation', render: row => esc(row.motif || '-') }
    ], 'Aucun mouvement récent.');
}

function renderCharts(data) {
    const charts = data.charts || {};

    if (charts.stock_entries_monthly && charts.stock_entries_monthly.length > 0) {
        new Chart(document.getElementById('chartEntries'), {
            type: 'line',
            data: {
                labels: charts.stock_entries_monthly.map(d => d.mois),
                datasets: [{
                    label: 'Entrées',
                    data: charts.stock_entries_monthly.map(d => d.total),
                    borderColor: '#256b57',
                    backgroundColor: 'rgba(37, 107, 87, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    if (charts.stock_outputs_monthly && charts.stock_outputs_monthly.length > 0) {
        new Chart(document.getElementById('chartOutputs'), {
            type: 'line',
            data: {
                labels: charts.stock_outputs_monthly.map(d => d.mois),
                datasets: [{
                    label: 'Sorties',
                    data: charts.stock_outputs_monthly.map(d => d.total),
                    borderColor: '#b54708',
                    backgroundColor: 'rgba(181, 71, 8, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    const stockByForm = data.stock_by_forme || [];
    if (stockByForm.length > 0) {
        new Chart(document.getElementById('chartStockByForm'), {
            type: 'doughnut',
            data: {
                labels: stockByForm.map(d => d.forme),
                datasets: [{ data: stockByForm.map(d => d.total), backgroundColor: ['#256b57', '#4f8a73', '#8bb8a5', '#b54708', '#d9a441', '#6b7280'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    const topProducts = data.top_used_products || [];
    if (topProducts.length > 0) {
        new Chart(document.getElementById('chartTopProducts'), {
            type: 'bar',
            data: {
                labels: topProducts.map(d => d.nom),
                datasets: [{ label: 'Sorties', data: topProducts.map(d => d.total), backgroundColor: '#256b57' }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }
}

fetch('/commande/api/dashboard', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(response => {
        if (!response.ok) throw new Error('Erreur API');
        return response.json();
    })
    .then(payload => {
        const data = payload.data || {};
        renderKpis(data.widgets || {});
        renderTables(data);
        renderAlerts(data.alertes || {});
        renderCharts(data);
        document.getElementById('loader').classList.add('hidden');
        document.getElementById('content').classList.remove('hidden');
    })
    .catch(() => {
        document.getElementById('loader').innerHTML = `
            <div class="card p-4 col-span-full bg-red-50 border-red-200 text-red-800 text-sm">
                Impossible de charger le dashboard.
            </div>
        `;
    });
</script>
</body>
</html>
