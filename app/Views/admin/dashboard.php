<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Dashboard Admin')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .action {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            text-decoration: none;
            color: var(--text);
            transition: background .16s ease, border-color .16s ease;
        }

        .action:hover {
            background: var(--action-hover);
            border-color: var(--action-hover-border);
        }

        .action svg {
            width: 20px;
            height: 20px;
            flex: none;
            color: var(--green-pharmacy);
        }

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

        .audit-action {
            font-weight: 600;
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 560px; }
        th {
            background: #f1f6f3;
            border-bottom: 1px solid var(--line);
            color: var(--text);
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

        .alert-block {
            background: var(--alert-bg);
            border: 1px solid var(--alert-border);
            border-radius: 6px;
            padding: 12px 16px;
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
    </style>
</head>
<body>
<?php
    $adminName = (string)($user['username'] ?? $user['name'] ?? $user['nom'] ?? 'Admin');
    $stats = is_array($stats ?? null) ? $stats : [];
    $adminData = is_array($adminData ?? null) ? $adminData : [];
    $metier = is_array($metier ?? null) ? $metier : [];
    $usersByRole = is_array($usersByRole ?? null) ? $usersByRole : [];
    $number = static fn($value): string => number_format((float)$value, 0, ',', ' ');
    $money = static fn($value): string => number_format((float)$value, 0, ',', ' ') . ' FCFA';
    $dateShort = static function ($value): string {
        if (empty($value)) {
            return '-';
        }

        $timestamp = strtotime((string)$value);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$value;
    };
    $badgeClass = static function ($value): string {
        $status = strtoupper((string)$value);
        return [
            'OUVERTE' => 'badge-green',
            'PAYEE' => 'badge-green',
            'VALIDEE' => 'badge-green',
            'RECEPTION_COMPLETE' => 'badge-green',
            'LIVREE' => 'badge-green',
            'ANNULEE' => 'badge-red',
            'RUPTURE' => 'badge-red',
            'BROUILLON' => 'badge-gray',
            'ENVOYEE' => 'badge-blue',
            'PARTIELLEMENT_PAYEE' => 'badge-amber',
            'PARTIELLEMENT_LIVREE' => 'badge-amber',
            'RECEPTION_PARTIELLE' => 'badge-amber',
            'ALERTE' => 'badge-amber',
            'FAIBLE' => 'badge-amber',
        ][$status] ?? 'badge-gray';
    };
    $roleMax = max(1, ...array_map(static fn($row): int => (int)($row['user_count'] ?? 0), $usersByRole ?: [['user_count' => 0]]));
    $actions = [
        ['title' => 'Faire une vente', 'desc' => 'Point de vente admin', 'url' => '/vente/create?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-cart-shopping', 'color' => 'var(--green)'],
        ['title' => 'Utilisateurs', 'desc' => 'Creer, modifier, desactiver', 'url' => '/admin/users', 'icon' => 'fa-users', 'color' => 'var(--blue)'],
        ['title' => 'Roles', 'desc' => 'Creer, modifier, desactiver', 'url' => '/admin/roles', 'icon' => 'fa-user-shield', 'color' => 'var(--violet)'],
        ['title' => 'Permissions', 'desc' => 'Droits et attributions par role', 'url' => '/admin/roles#permissions', 'icon' => 'fa-key', 'color' => '#4f46e5'],
        ['title' => 'Fournisseurs', 'desc' => 'Liste et création', 'url' => '/fournisseurs?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-truck-field', 'color' => '#4f46e5'],
        ['title' => 'Flux de stock', 'desc' => 'Entrées, sorties, valorisation', 'url' => '/stock/flux', 'icon' => 'fa-arrows-rotate', 'color' => 'var(--cyan)'],
        ['title' => 'Inventaire', 'desc' => 'Comptage physique et écarts', 'url' => '/inventaire', 'icon' => 'fa-clipboard-check', 'color' => 'var(--amber)'],
        ['title' => 'Créances clients', 'desc' => 'Règlements et soldes', 'url' => '/finance/clients', 'icon' => 'fa-hand-holding-dollar', 'color' => 'var(--green)'],
        ['title' => 'Dettes fournisseurs', 'desc' => 'Factures et paiements', 'url' => '/finance/fournisseurs?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-file-invoice-dollar', 'color' => 'var(--red)'],
        ['title' => 'Plafonds remise', 'desc' => 'Limites par rôle', 'url' => '/finance/remises-limites', 'icon' => 'fa-percent', 'color' => 'var(--violet)'],
        ['title' => 'Clients', 'desc' => 'Gestion et comptes credit', 'url' => '/clients?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-address-book', 'color' => 'var(--blue)'],
        ['title' => 'Créer un client', 'desc' => 'Ajout nouveau client', 'url' => '/clients/creer?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-user-plus', 'color' => 'var(--green)'],
        ['title' => 'Suivi Client', 'desc' => 'Reglements et relevés', 'url' => '/suivi-client', 'icon' => 'fa-users-viewfinder', 'color' => 'var(--violet)'],
        ['title' => 'Correction stock', 'desc' => 'Ajustement manuel trace', 'url' => '/stock/ajustement?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-sliders', 'color' => 'var(--red)'],
        ['title' => 'Commandes fournisseurs', 'desc' => 'Suivi et creation', 'url' => '/commande/historique?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-clipboard-list', 'color' => 'var(--violet)'],
        ['title' => 'Reception produits', 'desc' => 'Entrer les livraisons', 'url' => '/commande/reception', 'icon' => 'fa-dolly', 'color' => 'var(--green)'],
        ['title' => 'Annuler vente', 'desc' => 'Tickets et restauration stock', 'url' => '/admin/annulation-tickets', 'icon' => 'fa-ban', 'color' => 'var(--red)'],
        ['title' => 'Caisse', 'desc' => 'Etat, sessions, historique', 'url' => '/caisse/etat?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-cash-register', 'color' => 'var(--cyan)'],
        ['title' => 'Statistiques', 'desc' => 'Indicateurs operationnels', 'url' => '/admin/statistiques', 'icon' => 'fa-chart-line', 'color' => 'var(--blue)'],
        ['title' => 'Rapports', 'desc' => 'Stock et exploitation', 'url' => '/stock/rapports?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-file-lines', 'color' => '#475569'],
        ['title' => 'SYSCOHADA', 'desc' => 'Comptabilite et journaux', 'url' => '/comptabilite', 'icon' => 'fa-scale-balanced', 'color' => 'var(--violet)'],
        ['title' => 'Tracabilite', 'desc' => 'Journal audit global', 'url' => '/admin/audit', 'icon' => 'fa-clock-rotate-left', 'color' => 'var(--red)'],
        ['title' => 'Systeme', 'desc' => 'Parametres et etat serveur', 'url' => '/admin/system', 'icon' => 'fa-gear', 'color' => '#475569'],
    ];
    $adminActive = 'dashboard';
    require __DIR__ . '/../partials/admin-header.php';
?>
<div class="container">
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="logo-medical">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#256b57" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </div>
                <div class="logo-pills">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                        <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                        <line x1="8" y1="8" x2="16" y2="8"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <h1 class="section-title">Dashboard Administrateur</h1>
                    <p class="section-subtitle">Administration, stock, ventes, caisse, rapports et SYSCOHADA</p>
                </div>
            </div>
            <div class="header-right">
                <a href="/vente/create?return_to=<?= urlencode('/admin/dashboard') ?>" class="action" style="padding: 8px 12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span>Faire une vente</span>
                </a>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($adminName) ?></span>
                    <span style="color: var(--muted); margin: 0 8px;">|</span>
                    <span><?= date('d/m/Y H:i') ?></span>
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

            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <article class="kpi kpi-blue">
                    <p class="text-xs" style="color: var(--muted);">Utilisateurs actifs</p>
                    <p class="mt-1 text-xl font-bold" style="color: var(--text);"><?= $number($stats['utilisateurs_actifs'] ?? 0) ?></p>
                    <p class="text-xs" style="color: var(--muted);"><?= $number($stats['total_utilisateurs'] ?? 0) ?> compte(s) au total</p>
                </article>
                <article class="kpi kpi-green">
                    <p class="text-xs" style="color: var(--muted);">CA du jour</p>
                    <p class="mt-1 text-xl font-bold" style="color: var(--text);"><?= htmlspecialchars($money($stats['ventes_jour']['total'] ?? 0)) ?></p>
                    <p class="text-xs" style="color: var(--muted);"><?= $number($stats['ventes_jour']['count'] ?? 0) ?> vente(s)</p>
                </article>
                <article class="kpi kpi-beige">
                    <p class="text-xs" style="color: var(--muted);">Stock disponible</p>
                    <p class="mt-1 text-xl font-bold" style="color: var(--text);"><?= $number($stats['stock_total'] ?? 0) ?></p>
                    <p class="text-xs" style="color: var(--muted);"><?= htmlspecialchars($money($stats['valeur_stock'] ?? 0)) ?></p>
                </article>
                <article class="kpi kpi-orange">
                    <p class="text-xs" style="color: var(--muted);">Caisse ouverte</p>
                    <p class="mt-1 text-xl font-bold" style="color: var(--text);"><?= $number($stats['sessions_actives'] ?? 0) ?></p>
                    <p class="text-xs" style="color: var(--muted);"><?= $number($stats['tickets_annules_jour'] ?? 0) ?> annulation(s) aujourd'hui</p>
                </article>
            </section>

            <section class="card p-5 mb-6">
                <h2 class="section-title mb-4">Modules Principaux</h2>
                <p class="section-subtitle mb-4">Accès aux principaux domaines de l'ERP</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                    <a href="/produits" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span>
                            <span class="block font-semibold">Produits</span>
                            <span class="text-xs" style="color: var(--muted);">Inventaire et stocks</span>
                        </span>
                    </a>
                    <a href="/clients" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>
                            <span class="block font-semibold">Clients</span>
                            <span class="text-xs" style="color: var(--muted);">Gestion clients</span>
                        </span>
                    </a>
                    <a href="/vente/create" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span>
                            <span class="block font-semibold">Ventes</span>
                            <span class="text-xs" style="color: var(--muted);">Point de vente</span>
                        </span>
                    </a>
                    <a href="/stock" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3v18h18"></path>
                            <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
                        </svg>
                        <span>
                            <span class="block font-semibold">Stock et Approvisionnement</span>
                            <span class="text-xs" style="color: var(--muted);">Commandes et stock</span>
                        </span>
                    </a>
                    <a href="/caisse" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                        <span>
                            <span class="block font-semibold">Caisse</span>
                            <span class="text-xs" style="color: var(--muted);">Gestion caisse</span>
                        </span>
                    </a>
                    <a href="/comptabilite" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3v18h18"></path>
                            <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
                        </svg>
                        <span>
                            <span class="block font-semibold">Comptabilité</span>
                            <span class="text-xs" style="color: var(--muted);">SYSCOHADA</span>
                        </span>
                    </a>
                    <a href="/suivi-client" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>
                            <span class="block font-semibold">Suivi Client</span>
                            <span class="text-xs" style="color: var(--muted);">Règlements et relevés</span>
                        </span>
                    </a>
                    <a href="/stock/rapports" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        <span>
                            <span class="block font-semibold">Rapports</span>
                            <span class="text-xs" style="color: var(--muted);">Statistiques et rapports</span>
                        </span>
                    </a>
                    <a href="/admin" class="action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>
                            <span class="block font-semibold">Administration</span>
                            <span class="text-xs" style="color: var(--muted);">Paramètres système</span>
                        </span>
                    </a>
                </div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <div class="card p-5">
                    <h2 class="section-title mb-4">Alertes Stock</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Stock</th>
                                    <th>Seuil</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($adminData['stock_alerts'] ?? []) as $item): ?>
                                    <?php $niveau = (int)($item['stock_reel'] ?? 0) <= 0 ? 'RUPTURE' : 'FAIBLE'; ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars((string)($item['nom'] ?? '-')) ?></strong><div class="text-xs" style="color: var(--muted);"><?= htmlspecialchars((string)($item['code_cip'] ?? '')) ?></div></td>
                                        <td><?= $number($item['stock_reel'] ?? 0) ?></td>
                                        <td><?= $number($item['seuil'] ?? 0) ?></td>
                                        <td><span class="badge <?= $badgeClass($niveau) ?>"><?= htmlspecialchars($niveau) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminData['stock_alerts'])): ?>
                                    <tr><td colspan="4" class="text-center py-8" style="color: var(--muted);">Aucune alerte stock.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card p-5">
                    <h2 class="section-title mb-4">Alertes Financières</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="alert-block">
                            <p class="text-sm" style="color: var(--muted);">Clients débiteurs</p>
                            <p class="text-xl font-bold" style="color: var(--alert-orange);"><?= $number($metier['clients_debiteurs'] ?? 0) ?></p>
                        </div>
                        <div class="alert-block">
                            <p class="text-sm" style="color: var(--muted);">Fournisseurs à payer</p>
                            <p class="text-xl font-bold" style="color: var(--alert-orange);"><?= $number($metier['fournisseurs_a_payer'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="card p-5">
                    <h2 class="section-title mb-4">Commandes fournisseurs</h2>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Commande</th><th>Montant</th><th>Statut</th></tr></thead>
                            <tbody>
                                <?php foreach (($adminData['pending_orders'] ?? []) as $order): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars((string)($order['numero_commande'] ?? '-')) ?></strong><div class="text-xs" style="color: var(--muted);"><?= htmlspecialchars((string)($order['fournisseur_nom'] ?? '')) ?></div></td>
                                        <td><?= htmlspecialchars($money($order['montant_total'] ?? 0)) ?></td>
                                        <td><span class="badge <?= $badgeClass($order['statut'] ?? '') ?>"><?= htmlspecialchars(str_replace('_', ' ', (string)($order['statut'] ?? '-'))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminData['pending_orders'])): ?>
                                    <tr><td colspan="3" class="text-center py-8" style="color: var(--muted);">Aucune commande en cours.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card p-5">
                    <h2 class="section-title mb-4">Ventes récentes</h2>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Ticket</th><th>Montant</th><th>Statut</th></tr></thead>
                            <tbody>
                                <?php foreach (($adminData['recent_sales'] ?? []) as $sale): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars((string)($sale['numero_facture'] ?? '-')) ?></strong><div class="text-xs" style="color: var(--muted);"><?= htmlspecialchars((string)($sale['client_nom'] ?? 'Client comptoir')) ?></div></td>
                                        <td><?= htmlspecialchars($money($sale['montant_net'] ?? 0)) ?></td>
                                        <td><span class="badge <?= $badgeClass($sale['statut_vente'] ?? '') ?>"><?= htmlspecialchars(str_replace('_', ' ', (string)($sale['statut_vente'] ?? '-'))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminData['recent_sales'])): ?>
                                    <tr><td colspan="3" class="text-center py-8" style="color: var(--muted);">Aucune vente récente.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="section-title">Traçabilité récente</h2>
                        <span class="text-xs" style="color: var(--green-pharmacy); background: var(--kpi-green); border: 1px solid var(--action-hover-border); padding: 2px 8px; border-radius: 4px;">En direct</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Date</th><th>Action</th><th>Utilisateur</th></tr></thead>
                            <tbody id="dashboard-audit-body">
                                <?php foreach (($adminData['recent_audit'] ?? []) as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dateShort($log['date_action'] ?? null)) ?></td>
                                        <td>
                                            <span class="audit-action"><?= htmlspecialchars((string)($log['action_fr'] ?? $log['action'] ?? '—')) ?></span>
                                            <div class="text-xs" style="color: var(--muted);"><?= htmlspecialchars((string)($log['table_name_fr'] ?? $log['table_name'] ?? '')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string)($log['username'] ?? $log['utilisateur_nom'] ?? 'Système')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminData['recent_audit'])): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-8" style="color: var(--muted);">Aucune action récente.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
<script>
    (function refreshDashboardAudit() {
        const tbody = document.getElementById('dashboard-audit-body');
        if (!tbody) return;

        fetch('/admin/audit/live?limit=6', { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.auditLogs)) return;

                if (!data.auditLogs.length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-8" style="color: var(--muted);">Aucune action récente.</td></tr>';
                    return;
                }

                tbody.innerHTML = data.auditLogs.map(log => {
                    const date = log.date_action || log.created_at || '';
                    const formatted = date ? new Date(String(date).replace(' ', 'T')).toLocaleString('fr-FR', {
                        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
                    }) : '—';

                    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                    })[c]);

                    return `
                        <tr>
                            <td>${escape(formatted)}</td>
                            <td>
                                <span class="audit-action">${escape(log.action_fr || log.action || '—')}</span>
                                <div class="text-xs" style="color: var(--muted);">${escape(log.table_name_fr || log.table_name || '')}</div>
                            </td>
                            <td>${escape(log.username || 'Système')}</td>
                        </tr>
                    `;
                }).join('');
            })
            .catch(() => {});

        setTimeout(refreshDashboardAudit, 5000);
    })();
</script>
</body>
</html>
