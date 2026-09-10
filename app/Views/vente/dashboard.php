<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Point de Vente')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --surface: #f1f6f3;
            --panel: #ffffff;
            --line: #d8e4de;
            --text: #1d2939;
            --muted: #667085;
            --primary: #256b57;
            --primary-dark: #1f5949;
            --alert: #b54708;
        }

        * { box-sizing: border-box; }
        body {
            background: var(--surface);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .layout {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: var(--primary-dark);
            color: #e7f1eb;
            padding: 20px 16px;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #d6e7de;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: none;
        }

        .kpi-card {
            box-shadow: none;
        }

        .kpi-card:hover {
            transform: none;
            box-shadow: none;
        }

        .action-card {
            transition: none;
        }

        .action-card:hover {
            transform: none;
            box-shadow: none;
            border-color: #b9d5c7;
            background: #e9f4ee;
        }

        .dashboard-header { background: #e7f1eb; border: 1px solid var(--line); border-radius: 8px; margin: 16px 24px 0; padding: 16px 18px; }
        .dashboard-header-inner { max-width: 1180px; margin: 0 auto; }
        .dashboard-title { display: flex; align-items: center; gap: 12px; }
        .pharmacy-mark { width: 42px; height: 42px; padding: 8px; color: #fff; background: var(--primary); border: 1px solid var(--primary-dark); border-radius: 6px; }
        .pharmacy-mark svg { width: 100%; height: 100%; stroke: currentColor; stroke-width: 1.7; fill: none; }
        .health-mark { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; color: var(--primary); background: #fff; border: 1px solid #c5ddd1; border-radius: 50%; }
        .health-mark svg { width: 13px; height: 13px; fill: currentColor; }
        main { max-width: 1180px; margin: 0 auto; }
        main > .grid .kpi-card:nth-child(4n + 1) { background: #e9f4ee; }
        main > .grid .kpi-card:nth-child(4n + 2) { background: #edf3f6; }
        main > .grid .kpi-card:nth-child(4n + 3) { background: #f5f4eb; }
        main > .grid .kpi-card:nth-child(4n + 4) { background: #fff4e9; }
        .action-card { background: #fff !important; border-color: var(--line) !important; border-radius: 8px !important; }
        .action-card > div { width: 40px !important; height: 40px !important; margin-bottom: 10px !important; background: #e9f4ee !important; }
        .action-card > div i { color: var(--primary) !important; }
        .notification-badge { animation: none; }
        .sidebar .bg-blue-600 { background: var(--primary) !important; }
        .sidebar .text-gray-400 { color: #c8ddd2 !important; }
        .rounded-xl { border-radius: 8px !important; }
        .rounded-lg { border-radius: 6px !important; }
        main .bg-blue-50, main .bg-purple-50, main .bg-cyan-50, main .bg-indigo-50, main .bg-teal-50 { background: #edf3f6 !important; }
        main .bg-green-50 { background: #e9f4ee !important; }
        main .bg-amber-50, main .bg-orange-50 { background: #fff4e9 !important; }
        main .border-blue-200, main .border-purple-200, main .border-cyan-200, main .border-indigo-200, main .border-teal-200, main .border-green-200 { border-color: #c7ddd2 !important; }
        main .border-amber-200, main .border-orange-200 { border-color: #efd9bf !important; }
        main .text-blue-600, main .text-blue-700, main .text-purple-600, main .text-purple-700, main .text-cyan-600, main .text-cyan-700, main .text-indigo-600, main .text-indigo-700, main .text-teal-600, main .text-teal-700, main .text-green-600, main .text-green-700 { color: var(--primary) !important; }
        main .focus\:ring-blue-500:focus { --tw-ring-color: #256b57 !important; }
        main .hover\:bg-blue-700:hover, main .hover\:bg-green-700:hover { background: var(--primary-dark) !important; }

        @media (max-width: 1024px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar {
                position: relative;
                height: auto;
                padding: 14px;
            }
            .nav {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 8px;
            }
            .nav-link { justify-content: center; }
            .nav-link span { display: none; }
            .dashboard-header { margin: 14px 16px 0; }
        }

        @media (max-width: 640px) {
            .nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
<?php
    $user = is_array($user ?? null) ? $user : [];
    $roleId = (int)($_SESSION['user']['role_id'] ?? $user['role_id'] ?? 0);
    $roleCode = strtoupper((string)($_SESSION['user']['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));
    $isAssistant = $roleId === 3 || $roleCode === 'ASSISTANT';
    $returnTo = (string)($returnTo ?? ($isAssistant ? '/assistant/dashboard' : '/vente'));
    $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : ($isAssistant ? '/assistant/dashboard' : '/vente');
    $sellerName = (string)($user['username'] ?? $user['name'] ?? $_SESSION['username'] ?? 'Vendeur');
    $stats = is_array($stats ?? null) ? $stats : [];
    $dernieresVentes = is_array($dernieresVentes ?? null) ? $dernieresVentes : [];
    $notifications = is_array($notifications ?? null) ? $notifications : [];
    $caisseSession = is_array($caisseSession ?? null) ? $caisseSession : null;
    $caisseStats = is_array($caisseStats ?? null) ? $caisseStats : [];
    
    $money = static fn($value): string => number_format((float)$value, 0, ',', ' ') . ' FCFA';
    $formatDate = static fn($date): string => date('d/m/Y H:i', strtotime($date ?? 'now'));
    $formatTime = static fn($date): string => date('H:i', strtotime($date ?? 'now'));
?>
<div class="layout">
    <aside class="sidebar">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center">
                <i class="fas fa-cash-register text-white text-lg"></i>
            </div>
            <div>
                <div class="text-white font-bold">Vente</div>
                <div class="text-xs text-gray-400">ERP Pharmacie</div>
            </div>
        </div>

        <nav class="nav space-y-1">
            <a href="/vente" class="nav-link active">
                <i class="fas fa-chart-line w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="/vente/create?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                <i class="fas fa-cart-plus w-5 text-center"></i>
                <span>Nouvelle vente</span>
            </a>
            <a href="/clients?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                <i class="fas fa-users w-5 text-center"></i>
                <span>Clients</span>
            </a>
            <a href="/suivi-client" class="nav-link">
                <i class="fas fa-users-viewfinder w-5 text-center"></i>
                <span>Suivi client</span>
            </a>
            <a href="/produits/catalogue-vendeur?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                <i class="fas fa-boxes-stacked w-5 text-center"></i>
                <span>Produits</span>
            </a>
            <a href="/caisse/etat?return_to=<?= urlencode($returnTo) ?>" class="nav-link">
                <i class="fas fa-cash-register w-5 text-center"></i>
                <span>Caisse</span>
            </a>
            <?php if ($isAssistant): ?>
                <a href="<?= htmlspecialchars($returnTo) ?>" class="nav-link">
                    <i class="fas fa-arrow-left w-5 text-center"></i>
                    <span>Retour</span>
                </a>
            <?php endif; ?>
            <a href="/logout" class="nav-link">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Déconnexion</span>
            </a>
        </nav>
    </aside>

    <div class="min-w-0">
        <header class="dashboard-header sticky top-0 z-10">
            <div class="dashboard-header-inner flex items-center justify-between">
                <div class="dashboard-title">
                    <span class="pharmacy-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32"><g transform="rotate(-42 11 11)"><rect x="4" y="8" width="14" height="6" rx="3"/><path d="M11 8v6"/></g><g transform="rotate(38 21 21)"><rect x="14" y="18" width="14" height="6" rx="3"/><path d="M21 18v6"/></g></svg>
                    </span>
                    <div>
                    <h1 class="text-2xl font-bold text-gray-900">Bonjour, <?= htmlspecialchars($sellerName) ?></h1>
                    <p class="text-sm text-gray-500"><?= date('d/m/Y H:i') ?></p>
                    </div>
                    <span class="health-mark" aria-label="Santé"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/></svg></span>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Barre de recherche globale -->
                    <div class="relative hidden md:block">
                        <input type="text" 
                               id="globalSearch" 
                               placeholder="Rechercher produit, client, facture..." 
                               class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               autocomplete="off">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <div id="searchResults" class="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-50 max-h-96 overflow-y-auto"></div>
                    </div>
                    <?php if ($caisseSession): ?>
                        <div class="flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span class="text-sm font-medium text-green-700">Session ouverte</span>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Fond de caisse</div>
                            <div class="font-semibold text-gray-900"><?= $money($caisseSession['montant_ouverture'] ?? 0) ?></div>
                        </div>
                    <?php else: ?>
                        <a href="/caisse/session" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-plus mr-2"></i>Ouvrir session
                        </a>
                    <?php endif; ?>
                    <a href="/logout" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <main class="p-6">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
                <section class="mb-6">
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($notifications as $notif): ?>
                            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border notification-badge
                                <?= $notif['type'] === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 
                                   ($notif['type'] === 'info' ? 'bg-blue-50 border-blue-200 text-blue-800' : 
                                   'bg-gray-50 border-gray-200 text-gray-800') ?>">
                                <i class="fas <?= htmlspecialchars($notif['icon']) ?>"></i>
                                <span class="text-sm font-medium"><?= htmlspecialchars($notif['message']) ?></span>
                                <span class="bg-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $notif['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- KPIs -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">CA du jour</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= $money($stats['chiffre_affaires'] ?? 0) ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                            <i class="fas fa-coins text-green-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Tickets</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format((int)($stats['total_ventes'] ?? 0), 0, ',', ' ') ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-receipt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Encaissé</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= $money($stats['montant_encaisse'] ?? 0) ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Clients servis</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format((int)($stats['clients_servis'] ?? 0), 0, ',', ' ') ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center">
                            <i class="fas fa-users text-amber-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Ventes à crédit</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format((int)($stats['ventes_credit'] ?? 0), 0, ',', ' ') ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                            <i class="fas fa-credit-card text-red-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Panier moyen</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= $money($stats['panier_moyen'] ?? 0) ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-cyan-100 flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-cyan-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Articles vendus</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format((int)($stats['articles_vendus'] ?? 0), 0, ',', ' ') ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center">
                            <i class="fas fa-boxes text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                </article>
                <article class="card kpi-card p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Reste à encaisser</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= $money($stats['montant_restant'] ?? 0) ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center">
                            <i class="fas fa-hourglass-half text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </article>
            </section>

            <!-- Actions principales -->
            <section class="card p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Actions principales</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <a href="/vente/create?return_to=<?= urlencode($returnTo) ?>" class="action-card p-5 border-2 border-blue-200 rounded-xl bg-blue-50">
                        <div class="w-12 h-12 rounded-lg bg-blue-600 flex items-center justify-center mb-3">
                            <i class="fas fa-cart-plus text-white text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900">Nouvelle vente</h3>
                        <p class="text-sm text-gray-500 mt-1">Créer une nouvelle vente</p>
                    </a>
                    
                    <a href="/vente/tickets-en-attente?return_to=<?= urlencode($returnTo) ?>" class="action-card p-5 border rounded-xl">
                        <div class="w-12 h-12 rounded-lg bg-amber-500 flex items-center justify-center mb-3">
                            <i class="fas fa-pause text-white text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900">Tickets en attente</h3>
                        <p class="text-sm text-gray-500 mt-1">Reprendre une vente</p>
                    </a>
                    
                    <a href="/produits/scanner?return_to=<?= urlencode($returnTo) ?>" class="action-card p-5 border rounded-xl">
                        <div class="w-12 h-12 rounded-lg bg-teal-500 flex items-center justify-center mb-3">
                            <i class="fas fa-barcode text-white text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900">Scanner produit</h3>
                        <p class="text-sm text-gray-500 mt-1">Code-barres</p>
                    </a>
                    
                    <a href="/clients?return_to=<?= urlencode($returnTo) ?>" class="action-card p-5 border rounded-xl">
                        <div class="w-12 h-12 rounded-lg bg-purple-500 flex items-center justify-center mb-3">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900">Clients</h3>
                        <p class="text-sm text-gray-500 mt-1">Rechercher / Créer</p>
                    </a>
                    
                    <a href="/vente/impression?return_to=<?= urlencode($returnTo) ?>" class="action-card p-5 border rounded-xl">
                        <div class="w-12 h-12 rounded-lg bg-green-500 flex items-center justify-center mb-3">
                            <i class="fas fa-print text-white text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900">Impression</h3>
                        <p class="text-sm text-gray-500 mt-1">Réimprimer un ticket</p>
                    </a>
                </div>
            </section>

            <!-- Section Caisse -->
            <section class="card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold text-gray-900">Caisse</h2>
                        <span class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium
                            <?= $caisseSession ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <span class="w-2 h-2 rounded-full <?= $caisseSession ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                            <?= $caisseSession ? 'Session ouverte' : 'Session fermée' ?>
                        </span>
                    </div>
                    <a href="/caisse/etat?return_to=<?= urlencode($returnTo) ?>" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                        Voir détails <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">État session</p>
                        <p class="mt-1 font-semibold <?= $caisseSession ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $caisseSession ? 'Ouverte' : 'Fermée' ?>
                        </p>
                        <?php if ($caisseSession): ?>
                            <p class="text-xs text-gray-400 mt-1">
                                <?= date('H:i', strtotime($caisseSession['date_ouverture'] ?? 'now')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Fond initial</p>
                        <p class="mt-1 font-semibold text-gray-900"><?= $money($caisseSession ? ($caisseSession['montant_ouverture'] ?? 0) : 0) ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Solde actuel</p>
                        <p class="mt-1 font-semibold text-gray-900"><?= $money($caisseSession ? ($caisseSession['montant_ouverture'] + ($caisseStats['encaissements'] ?? 0) - ($caisseStats['decaissements'] ?? 0)) : 0) ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Écart</p>
                        <p class="mt-1 font-semibold text-gray-900">
                            <?= $money($caisseSession ? ($caisseStats['encaissements'] ?? 0) - ($caisseStats['decaissements'] ?? 0) : 0) ?>
                        </p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                        <p class="text-sm text-green-600">Encaissements</p>
                        <p class="mt-1 font-semibold text-green-700"><?= $money($caisseStats['encaissements'] ?? 0) ?></p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                        <p class="text-sm text-red-600">Décaissements</p>
                        <p class="mt-1 font-semibold text-red-700"><?= $money($caisseStats['decaissements'] ?? 0) ?></p>
                    </div>
                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-600">Ventes du jour</p>
                        <p class="mt-1 font-semibold text-blue-700"><?= number_format((int)($stats['total_ventes'] ?? 0), 0, ',', ' ') ?></p>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
                        <p class="text-sm text-purple-600">CA du jour</p>
                        <p class="mt-1 font-semibold text-purple-700"><?= $money($stats['chiffre_affaires'] ?? 0) ?></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <?php if (!$caisseSession): ?>
                        <a href="/caisse/session" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>Ouvrir session
                        </a>
                    <?php else: ?>
                        <a href="/caisse/fermer" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">
                            <i class="fas fa-times mr-2"></i>Clôturer session
                        </a>
                    <?php endif; ?>
                    <a href="/caisse/encaissements" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                        <i class="fas fa-arrow-down mr-2"></i>Encaissements
                    </a>
                    <a href="/caisse/decaissements" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                        <i class="fas fa-arrow-up mr-2"></i>Décaissements
                    </a>
                </div>
            </section>

            <!-- Section Produits (consultation uniquement) -->
            <section class="card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Produits</h2>
                    <span class="text-sm text-gray-500">Consultation uniquement</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <a href="/produits/recherche-vendeur?return_to=<?= urlencode($returnTo) ?>" class="p-4 border rounded-lg hover:border-blue-300 transition">
                        <i class="fas fa-search text-blue-600 text-xl mb-2"></i>
                        <h3 class="font-semibold text-gray-900">Recherche rapide</h3>
                        <p class="text-sm text-gray-500">Trouver un produit</p>
                    </a>
                    <a href="/produits/disponibilite-vendeur?return_to=<?= urlencode($returnTo) ?>" class="p-4 border rounded-lg hover:border-blue-300 transition">
                        <i class="fas fa-boxes-stacked text-green-600 text-xl mb-2"></i>
                        <h3 class="font-semibold text-gray-900">Disponibilité</h3>
                        <p class="text-sm text-gray-500">Vérifier le stock</p>
                    </a>
                    <a href="/produits/prix-vendeur?return_to=<?= urlencode($returnTo) ?>" class="p-4 border rounded-lg hover:border-blue-300 transition">
                        <i class="fas fa-tag text-purple-600 text-xl mb-2"></i>
                        <h3 class="font-semibold text-gray-900">Prix</h3>
                        <p class="text-sm text-gray-500">Consulter les tarifs</p>
                    </a>
                </div>
            </section>

            <!-- Section Ordonnances -->
            <section class="card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Ordonnances</h2>
                    <span class="text-sm text-gray-500">Consultation et traitement des ordonnances</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <a href="/ordonnances?return_to=<?= urlencode($returnTo) ?>" class="p-4 border rounded-lg hover:border-blue-300 transition">
                        <i class="fas fa-file-medical text-yellow-600 text-xl mb-2"></i>
                        <h3 class="font-semibold text-gray-900">Rechercher</h3>
                        <p class="text-sm text-gray-500">Trouver une ordonnance</p>
                    </a>
                    <a href="/ordonnances/liste?return_to=<?= urlencode($returnTo) ?>" class="p-4 border rounded-lg hover:border-blue-300 transition">
                        <i class="fas fa-list text-blue-600 text-xl mb-2"></i>
                        <h3 class="font-semibold text-gray-900">Historique</h3>
                        <p class="text-sm text-gray-500">Ordonnances enregistrées</p>
                    </a>
                    <a href="/vente/create?return_to=<?= urlencode($returnTo) ?>" class="p-4 border rounded-lg hover:border-blue-300 transition">
                        <i class="fas fa-plus-circle text-green-600 text-xl mb-2"></i>
                        <h3 class="font-semibold text-gray-900">Nouvelle vente</h3>
                        <p class="text-sm text-gray-500">Avec ordonnance</p>
                    </a>
                </div>
            </section>

            <!-- Historique personnel -->
            <section class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Mes dernières ventes</h2>
                    <a href="/vente/historique?return_to=<?= urlencode($returnTo) ?>" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Heure</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Facture</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Client</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Mode paiement</th>
                                <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Montant</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Statut</th>
                                <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dernieresVentes)): ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-500">Aucune vente récente</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dernieresVentes as $vente): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 px-4 text-sm"><?= $formatTime($vente['date_vente']) ?></td>
                                        <td class="py-3 px-4 text-sm font-medium"><?= htmlspecialchars($vente['numero_facture'] ?? '') ?></td>
                                        <td class="py-3 px-4 text-sm"><?= htmlspecialchars(($vente['client_nom'] ?? '') . ' ' . ($vente['client_prenom'] ?? '')) ?: '-' ?></td>
                                        <td class="py-3 px-4 text-sm">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?= ($vente['type_paiement'] ?? 'ESPECE') === 'ESPECE' ? 'bg-green-100 text-green-700' : 
                                                   (($vente['type_paiement'] ?? '') === 'CREDIT' ? 'bg-purple-100 text-purple-700' : 
                                                   (($vente['type_paiement'] ?? '') === 'MOBILE_MONEY' ? 'bg-blue-100 text-blue-700' : 
                                                   (($vente['type_paiement'] ?? '') === 'CARTE' ? 'bg-amber-100 text-amber-700' : 
                                                   'bg-gray-100 text-gray-700'))) ?>">
                                                <?= htmlspecialchars($vente['type_paiement'] ?? 'ESPECE') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-right"><?= $money($vente['montant_net'] ?? 0) ?></td>
                                        <td class="py-3 px-4 text-sm">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?= $vente['statut'] === 'PAYEE' ? 'bg-green-100 text-green-700' : 
                                                   ($vente['statut'] === 'EN_COURS' ? 'bg-amber-100 text-amber-700' : 
                                                   'bg-red-100 text-red-700') ?>">
                                                <?= htmlspecialchars($vente['statut'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-center">
                                            <a href="/vente/show/<?= $vente['id'] ?>" class="text-blue-600 hover:text-blue-700 mr-2">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/vente/impression?id=<?= $vente['id'] ?>&amp;return_to=<?= urlencode($returnTo) ?>" class="text-gray-600 hover:text-gray-700">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
<script>
    // Recherche globale
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('/recherche-globale?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.results && data.results.length > 0) {
                            searchResults.innerHTML = data.results.map(result => `
                                <a href="${result.url}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                    <div class="flex items-center gap-3">
                                        <i class="fas ${result.icon} text-gray-400"></i>
                                        <div>
                                            <div class="font-medium text-gray-900">${result.title}</div>
                                            <div class="text-sm text-gray-500">${result.subtitle}</div>
                                        </div>
                                    </div>
                                </a>
                            `).join('');
                            searchResults.classList.remove('hidden');
                        } else {
                            searchResults.innerHTML = '<div class="px-4 py-3 text-gray-500">Aucun résultat</div>';
                            searchResults.classList.remove('hidden');
                        }
                    })
                    .catch(() => {
                        searchResults.classList.add('hidden');
                    });
            }, 300);
        });

        // Fermer les résultats au clic extérieur
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    }
</script>
</html>
