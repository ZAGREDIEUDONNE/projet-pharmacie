<?php
    $adminActive = (string)($adminActive ?? '');
    $adminUser = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
    $adminName = (string)($adminUser['username'] ?? $adminUser['name'] ?? $adminUser['nom'] ?? 'Admin');
    $adminItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'fa-chart-line'],
        ['key' => 'vente', 'label' => 'Vente', 'url' => '/vente/create?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-cart-shopping'],
        ['key' => 'users', 'label' => 'Utilisateurs', 'url' => '/admin/users', 'icon' => 'fa-users'],
        ['key' => 'roles', 'label' => 'Roles', 'url' => '/admin/roles', 'icon' => 'fa-user-shield'],
        ['key' => 'stats', 'label' => 'Statistiques', 'url' => '/admin/statistiques', 'icon' => 'fa-chart-column'],
        ['key' => 'stock', 'label' => 'Stock', 'url' => '/stock?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-boxes-stacked'],
        ['key' => 'caisse', 'label' => 'Caisse', 'url' => '/caisse/etat?return_to=' . urlencode('/admin/dashboard'), 'icon' => 'fa-cash-register'],
        ['key' => 'suivi-client', 'label' => 'Suivi Client', 'url' => '/suivi-client', 'icon' => 'fa-users-viewfinder'],
        ['key' => 'syscohada', 'label' => 'SYSCOHADA', 'url' => '/comptabilite', 'icon' => 'fa-scale-balanced'],
        ['key' => 'audit', 'label' => 'Traçabilité', 'url' => '/admin/audit', 'icon' => 'fa-clock-rotate-left'],
        ['key' => 'system', 'label' => 'Systeme', 'url' => '/admin/system', 'icon' => 'fa-gear'],
    ];
?>
<style>
    :root {
        --surface: #f1f6f3;
        --panel: #ffffff;
        --line: #d8e4de;
        --text: #1d2939;
        --muted: #667085;
        --green-pharmacy: #256b57;
        --green-dark: #1f5949;
        --action-hover: #e9f4ee;
        --action-hover-border: #b9d5c7;
    }

    * { box-sizing: border-box; }

    .admin-body,
    body {
        background: var(--surface);
        color: var(--text);
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        margin: 0;
        padding: 0;
    }

    .admin-sidebar {
        background: #ffffff;
        border-right: 1px solid var(--line);
        height: 100vh;
        left: 0;
        padding: 20px 16px;
        position: fixed;
        top: 0;
        width: 250px;
        z-index: 30;
        overflow-y: auto;
    }

    .admin-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--line);
    }

    .admin-brand-icon {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        display: grid;
        place-items: center;
        background: var(--green-pharmacy);
        color: #fff;
        flex: none;
    }

    .admin-nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 40px;
        padding: 10px 12px;
        border-radius: 6px;
        color: var(--text);
        text-decoration: none;
        transition: background .16s ease, border-color .16s ease;
        border: 1px solid transparent;
    }

    .admin-nav-link:hover {
        background: var(--action-hover);
        border-color: var(--action-hover-border);
    }

    .admin-nav-link.active {
        background: var(--action-hover);
        border-color: var(--action-hover-border);
        color: var(--green-pharmacy);
        font-weight: 600;
    }

    .admin-nav-link svg {
        width: 18px;
        height: 18px;
        flex: none;
        color: var(--green-pharmacy);
    }

    .admin-container,
    .layout {
        margin-left: 250px;
    }

    .admin-container {
        padding: 24px 32px;
    }

    .admin-page-head {
        align-items: center;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
    }

    .admin-card {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 6px;
    }

    .admin-table-wrap {
        overflow-x: auto;
    }

    .admin-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: 720px;
    }

    .admin-table th {
        background: #f1f6f3;
        border-bottom: 1px solid var(--line);
        color: var(--text);
        font-size: 12px;
        font-weight: 600;
        padding: 10px 12px;
        text-align: left;
        white-space: nowrap;
    }

    .admin-table td {
        border-bottom: 1px solid var(--line);
        font-size: 14px;
        padding: 10px 12px;
        vertical-align: middle;
    }

    .admin-table tr:hover td { background: #f8faf9; }

    .admin-button {
        align-items: center;
        border-radius: 6px;
        display: inline-flex;
        font-size: 14px;
        font-weight: 600;
        min-height: 38px;
        padding: 8px 14px;
        transition: background .16s ease;
    }

    .admin-button-primary {
        background: var(--green-pharmacy);
        color: #fff;
        border: none;
    }

    .admin-button-primary:hover {
        background: var(--green-dark);
    }

    .admin-button-muted {
        background: #fff;
        border: 1px solid var(--line);
        color: var(--text);
    }

    .admin-button-muted:hover {
        background: var(--action-hover);
        border-color: var(--action-hover-border);
    }

    .admin-top-meta {
        border-top: 1px solid var(--line);
        bottom: 16px;
        left: 16px;
        padding-top: 14px;
        position: absolute;
        right: 16px;
        color: var(--muted);
    }

    @media (max-width: 1024px) {
        .admin-sidebar {
            height: auto;
            position: relative;
            width: 100%;
            border-right: none;
            border-bottom: 1px solid var(--line);
        }

        .admin-nav {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }

        .admin-nav-link { justify-content: center; }
        .admin-nav-link span { display: none; }
        .admin-top-meta { display: none; }

        .admin-container,
        .layout {
            margin-left: 0;
        }
    }

    @media (max-width: 640px) {
        .admin-nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .admin-container { padding: 24px 16px; }
        .admin-page-head {
            align-items: flex-start;
            flex-direction: column;
        }
        .admin-page-actions { width: 100%; justify-content: center; }
    }
</style>

<aside class="admin-sidebar">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 12px; background: var(--action-hover); border-radius: 6px; border: 1px solid var(--action-hover-border);">
        <div style="width: 32px; height: 32px; background: var(--green-pharmacy); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                <line x1="8" y1="8" x2="16" y2="8"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
                <line x1="8" y1="16" x2="12" y2="16"></line>
            </svg>
        </div>
        <div>
            <div style="font-size: 13px; font-weight: 600; color: var(--text);">Médicaments</div>
            <div style="font-size: 11px; color: var(--muted);">Gestion pharmacie</div>
        </div>
    </div>

    <div class="admin-brand">
        <div class="admin-brand-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
        </div>
        <div>
            <div style="font-weight: 600; color: var(--text);">Admin</div>
            <div style="font-size: 12px; color: var(--muted);">ERP Pharmacie</div>
        </div>
    </div>

    <nav class="admin-nav space-y-2">
        <?php foreach ($adminItems as $item): ?>
            <a href="<?= htmlspecialchars((string)$item['url']) ?>" class="admin-nav-link <?= $adminActive === $item['key'] ? 'active' : '' ?>">
                <?php
                $iconSvg = match($item['key']) {
                    'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
                    'vente' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
                    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                    'roles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
                    'stats' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
                    'stock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                    'caisse' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>',
                    'suivi-client' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
                    'syscohada' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path></svg>',
                    'audit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
                    'system' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
                    default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
                };
                ?>
                <?= $iconSvg ?>
                <span><?= htmlspecialchars((string)$item['label']) ?></span>
            </a>
        <?php endforeach; ?>
        
        <a href="/logout" class="admin-nav-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span style="color: #dc2626;">Déconnexion</span>
        </a>
    </nav>

    <div class="admin-top-meta text-sm">
        <div style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($adminName) ?></div>
        <div style="font-size: 12px; color: var(--muted);"><?= date('d/m/Y H:i') ?></div>
    </div>
</aside>
