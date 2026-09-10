<?php
$user = $user ?? $_SESSION['user'] ?? [];
$sellerName = $user['username'] ?? $user['name'] ?? 'Comptable';
$exercice = date('Y');
$periode = date('F Y', strtotime('first day of this month'));
$periodeFr = [
    'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
    'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
    'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
    'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
];
$periode = str_replace(array_keys($periodeFr), array_values($periodeFr), $periode);
$csrfToken = \App\Services\CsrfService::token();

// Définition des valeurs par défaut pour $stats
if (!isset($stats)) {
    $stats = [
        'equilibre' => 'Non équilibrée',
        'ecart' => 0,
        'total_actif' => 0,
        'total_passif' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Comptable - ERP Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
            padding: 20px;
        }

        .kpi {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 20px;
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

        .badge-success { background: #e9f4ee; color: #256b57; }
        .badge-warning { background: #fff4e9; color: #b54708; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #edf3f6; color: #1d2939; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 600px; }
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

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background .16s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--green-pharmacy);
            color: #fff;
        }
        .btn-primary:hover { background: var(--green-dark); }

        .btn-purple { background: #7c3aed; color: #fff; }
        .btn-purple:hover { background: #6d28d9; }

        .btn-blue { background: #2563eb; color: #fff; }
        .btn-blue:hover { background: #1d4ed8; }

        .btn-orange { background: #ea580c; color: #fff; }
        .btn-orange:hover { background: #c2410c; }

        .action-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 20px;
            text-decoration: none;
            transition: background .16s ease, border-color .16s ease;
            display: block;
        }

        .action-card:hover {
            background: var(--action-hover);
            border-color: var(--action-hover-border);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .action-icon-purple { background: #edf3f6; }
        .action-icon-blue { background: #e9f4ee; }
        .action-icon-green { background: #f5f4eb; }
        .action-icon-yellow { background: #fff4e9; }
        .action-icon-red { background: #fee2e2; }
        .action-icon-indigo { background: #edf3f6; }
        .action-icon-teal { background: #e9f4ee; }

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

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .modal-content {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 12px;
        }

        .modal-message {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .modal-btn {
            flex: 1;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .modal-btn-cancel {
            background: #64748b;
            color: #fff;
        }

        .modal-btn-confirm {
            background: var(--green-pharmacy);
            color: #fff;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
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
                        <div class="section-title">Dashboard Comptable</div>
                        <div class="section-subtitle">ERP Pharmacie - SYSCOHADA</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <span style="color: var(--muted);">Exercice:</span>
                        <span class="user-name" style="margin-left: 4px;"><?= $exercice ?></span>
                    </div>
                    <div class="user-info">
                        <span style="color: var(--muted);">Période:</span>
                        <span class="user-name" style="margin-left: 4px;"><?= $periode ?></span>
                    </div>
                    <div class="user-info">
                        <span style="color: var(--muted);">Utilisateur:</span>
                        <span class="user-name" style="margin-left: 4px;"><?= htmlspecialchars($sellerName) ?></span>
                    </div>
                    <div class="user-info">
                        <span style="color: var(--muted);">Date:</span>
                        <span class="user-name" style="margin-left: 4px;"><?= date('d/m/Y H:i') ?></span>
                    </div>
                    <a href="/logout" class="logout-link">Déconnexion</a>
                </div>
            </div>
        </header>

        <main>
            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="kpi kpi-blue">
                    <div>
                        <p style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">Plan Comptable</p>
                        <p style="font-size: 20px; font-weight: 600; color: var(--text);"><?= $stats['plan_comptable']['total_comptes'] ?? 0 ?></p>
                        <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">Comptes actifs</p>
                    </div>
                </div>

                <div class="kpi kpi-green">
                    <div>
                        <p style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">Balance</p>
                        <p style="font-size: 20px; font-weight: 600; color: var(--text);">
                            <?= ($stats['balance']['equilibre'] ?? false) ? 'Équilibrée' : 'Non équilibrée' ?>
                        </p>
                        <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                            Écart: <?= number_format($stats['balance']['ecart'] ?? 0, 2, ',', ' ') ?> FCFA
                        </p>
                    </div>
                </div>

                <div class="kpi kpi-beige">
                    <div>
                        <p style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">Chiffre d'affaires comptabilisé</p>
                        <p style="font-size: 20px; font-weight: 600; color: var(--text);">
                            <?= number_format((float)($stats['kpis']['chiffre_affaires_ttc'] ?? 0), 0, ',', ' ') ?> FCFA
                        </p>
                        <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                            Écritures du mois : <?= (int)($stats['kpis']['nombre_ecritures'] ?? 0) ?>
                        </p>
                    </div>
                </div>

                <div class="kpi kpi-orange">
                    <div>
                        <p style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">Exercice comptable</p>
                        <p style="font-size: 20px; font-weight: 600; color: var(--text);">
                            <?= htmlspecialchars((string)($stats['exercices']['exercice_ouvert'] ?? 'Non disponible')) ?>
                        </p>
                        <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                            <?= (int)($stats['exercices']['ouverts'] ?? 0) ?> ouvert(s) / <?= (int)($stats['exercices']['total_exercices'] ?? 0) ?> exercice(s)
                        </p>
                    </div>
                </div>
            </div>

        <!-- Navigation principale -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <a href="/comptabilite/plan-comptable" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">Plan Comptable</h3>
                        <p class="section-subtitle">Gestion des comptes SYSCOA/OHADA</p>
                    </div>
                </div>
            </a>

            <a href="/comptabilite/journaux" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#256b57" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">Journaux</h3>
                        <p class="section-subtitle">Journal des ventes, achats, caisse</p>
                    </div>
                </div>
            </a>

            <a href="/comptabilite/grand-livre" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#256b57" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">Grand Livre</h3>
                        <p class="section-subtitle">Historique par compte</p>
                    </div>
                </div>
            </a>

            <a href="/comptabilite/balance" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-yellow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#b54708" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                            <path d="M2 12h20"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">Balance</h3>
                        <p class="section-subtitle">Balance générale équilibrée</p>
                    </div>
                </div>
            </a>

            <a href="/comptabilite/etats-financiers" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-red">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                            <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                            <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">États Financiers</h3>
                        <p class="section-subtitle">Compte résultat, bilan, trésorerie</p>
                    </div>
                </div>
            </a>

            <a href="/comptabilite/suivi-tiers" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-indigo">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">Suivi Tiers</h3>
                        <p class="section-subtitle">Clients créances, fournisseurs dettes</p>
                    </div>
                </div>
            </a>

            <a href="/comptabilite/tva" class="action-card">
                <div class="flex items-center">
                    <div class="action-icon action-icon-teal">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#256b57" stroke-width="2">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                    <div>
                        <h3 class="section-title">TVA</h3>
                        <p class="section-subtitle">Déclaration et gestion TVA</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Actions rapides -->
        <div class="card mb-6">
            <h2 class="section-title mb-4">Actions Rapides</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="initialiserPlanComptable()" class="btn btn-purple">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Initialiser Plan
                </button>
                <button onclick="verifierEquilibre()" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Vérifier Équilibre
                </button>
                <button onclick="integrerEcritures()" class="btn btn-blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    Intégrer Écritures
                </button>
                <button onclick="genererRapport()" class="btn btn-orange">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Générer Rapport
                </button>
            </div>
        </div>

        <!-- Dernières activités -->
        <div class="card">
            <h2 class="section-title mb-4">Activités Récentes</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $activitesRecentes = $activitesRecentes ?? []; ?>
                        <?php if (empty($activitesRecentes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-8" style="color: var(--muted);">
                                    Aucune ecriture comptable recente.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activitesRecentes as $activite): ?>
                            <tr>
                                <td style="font-size: 14px;">
                                    <?= date('d/m/Y H:i', strtotime($activite['date_ecriture'] ?? 'now')) ?>
                                </td>
                                <td style="font-size: 14px;">
                                    <span class="badge badge-success">
                                        <?= htmlspecialchars($activite['reference_type'] ?? $activite['journal_code'] ?? 'ECRITURE') ?>
                                    </span>
                                </td>
                                <td style="font-size: 14px;">
                                    <?= htmlspecialchars($activite['libelle'] ?? '') ?>
                                </td>
                                <td style="font-size: 14px; text-align: right;">
                                    <?= number_format((float)($activite['total_debit'] ?? 0), 0, ',', ' ') ?> FCFA
                                </td>
                                <td style="font-size: 14px;">
                                    <span class="badge-<?= !empty($activite['is_equilibree']) ? 'success' : 'warning' ?>">
                                        <?= !empty($activite['is_equilibree']) ? 'Equilibre' : 'A verifier' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal de confirmation -->
    <div id="modalConfirmation" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 class="modal-title" id="modalTitle">Confirmation</h3>
            <p class="modal-message" id="modalMessage">Êtes-vous sûr de vouloir continuer?</p>
            <div class="modal-actions">
                <button onclick="closeModal()" class="modal-btn modal-btn-cancel">Annuler</button>
                <button onclick="confirmAction()" class="modal-btn modal-btn-confirm" id="modalConfirm">Confirmer</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        let currentAction = null;

        // Initialiser le plan comptable
        function initialiserPlanComptable() {
            currentAction = 'initialiser_plan_comptable';
            showModal('Initialiser le Plan Comptable', 'Cette action va créer tous les comptes SYSCOA/OHADA. Continuer?', 'Oui, Initialiser');
        }

        // Vérifier l'équilibre
        function verifierEquilibre() {
            currentAction = 'verifier_equilibre';
            showModal('Vérifier l\'Équilibre', 'Vérifier l\'équilibre de la balance générale?', 'Oui, Vérifier');
        }

        // Intégrer les écritures
        function integrerEcritures() {
            currentAction = 'integrer_ecritures';
            showModal('Intégrer les Écritures', 'Intégrer toutes les écritures comptables en attente?', 'Oui, Intégrer');
        }

        // Générer un rapport
        function genererRapport() {
            currentAction = 'generer_rapport';
            showModal('Générer un Rapport', 'Générer le rapport complet de la période?', 'Oui, Générer');
        }

        // Afficher la modale
        function showModal(title, message, confirmText) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('modalConfirm').textContent = confirmText;
            document.getElementById('modalConfirmation').classList.remove('hidden');
        }

        // Fermer la modale
        function closeModal() {
            document.getElementById('modalConfirmation').classList.add('hidden');
            currentAction = null;
        }

        // Confirmer l'action
        function confirmAction() {
            if (!currentAction) return;

            closeModal();

            switch (currentAction) {
                case 'initialiser_plan_comptable':
                    axios.post('/comptabilite/api', {action: 'initialiser_plan_comptable', csrf_token: <?= json_encode($csrfToken) ?>})
                    .then(response => {
                        if (response.data.success) {
                            alert('Plan comptable initialisé avec succès!');
                            location.reload();
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors de l\'initialisation');
                    });
                    break;

                case 'verifier_equilibre':
                    axios.get('/comptabilite/api?action=verifier_equilibre')
                    .then(response => {
                        if (response.data.success) {
                            alert(response.data.message);
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors de la vérification');
                    });
                    break;

                case 'integrer_ecritures':
                    axios.post('/comptabilite/api', {action: 'integrer_ventes', csrf_token: <?= json_encode($csrfToken) ?>})
                    .then(response => {
                        if (response.data.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors de l\'intégration');
                    });
                    break;

                case 'generer_rapport':
                    window.open('/comptabilite/exporter?type=balance', '_blank');
                    break;
            }
        }

        // Rafraîchissement automatique des statistiques
        setInterval(() => {
            axios.get('/comptabilite/api?action=verifier_equilibre')
            .then(response => {
                if (response.data.success) {
                    // Mettre à jour les statistiques si nécessaire
                    console.log('Balance vérifiée automatiquement');
                }
            })
            .catch(error => {
                console.error('Erreur vérification automatique:', error);
            });
        }, 60000); // Toutes les minutes
    </script>
</body>
</html>
