<?php
$user = $user ?? ($_SESSION['user'] ?? []);
$journaux = $journaux ?? [];
$ecritures = $ecritures ?? [];

$selectedJournal = $_GET['journal'] ?? ($journaux[0]['code'] ?? '');
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');

$totalDebit = 0;
$totalCredit = 0;
foreach ($ecritures as $ecriture) {
    $totalDebit += (float)($ecriture['total_debit'] ?? 0);
    $totalCredit += (float)($ecriture['total_credit'] ?? 0);
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return number_format((float)$value, 0, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journaux comptables - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .journal-card { transition: all 0.2s ease; }
        .journal-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="bg-gray-100">
    <header class="bg-purple-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-journal-whills text-2xl"></i>
                    <div>
                        <h1 class="text-xl font-bold">Journaux comptables</h1>
                        <p class="text-sm text-purple-100">Consultation des ecritures par journal</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span><strong>Utilisateur:</strong> <?= h($user['name'] ?? $user['username'] ?? 'Invite') ?></span>
                    <span><strong>Date:</strong> <?= date('d/m/Y H:i') ?></span>
                    <a href="/comptabilite" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded">
                        <i class="fas fa-arrow-left mr-1"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm text-gray-600">Journaux actifs</p>
                <p class="text-3xl font-bold text-gray-800"><?= count($journaux) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm text-gray-600">Ecritures affichees</p>
                <p class="text-3xl font-bold text-blue-600"><?= count($ecritures) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm text-gray-600">Ecart debit / credit</p>
                <p class="text-3xl font-bold <?= abs($totalDebit - $totalCredit) < 0.01 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= money($totalDebit - $totalCredit) ?>
                </p>
            </div>
        </section>

        <section class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" action="/comptabilite/journaux" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="journal" class="block text-sm font-medium text-gray-700 mb-1">Journal</label>
                    <select id="journal" name="journal" class="w-full border border-gray-300 rounded px-3 py-2">
                        <?php foreach ($journaux as $journal): ?>
                            <option value="<?= h($journal['code'] ?? '') ?>" <?= ($selectedJournal === ($journal['code'] ?? '')) ? 'selected' : '' ?>>
                                <?= h(($journal['code'] ?? '') . ' - ' . ($journal['libelle'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-1">Date debut</label>
                    <input id="date_debut" type="date" name="date_debut" value="<?= h($dateDebut) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label for="date_fin" class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                    <input id="date_fin" type="date" name="date_fin" value="<?= h($dateFin) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-search mr-2"></i>Afficher
                </button>
            </form>

            <?php if (empty($journaux)): ?>
                <div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                    Aucun journal comptable n'est disponible. Utilisez l'action "Creer journaux" depuis le tableau de bord comptable.
                </div>
            <?php endif; ?>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <?php foreach ($journaux as $journal): ?>
                <?php $color = $journal['couleur'] ?? '#6b7280'; ?>
                <a href="/comptabilite/journaux?journal=<?= urlencode($journal['code'] ?? '') ?>&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>"
                   class="journal-card bg-white rounded-lg shadow-md p-4 border-l-4"
                   style="border-left-color: <?= h($color) ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-lg font-bold text-gray-800"><?= h($journal['code'] ?? '') ?></span>
                        <span class="w-3 h-3 rounded-full" style="background-color: <?= h($color) ?>"></span>
                    </div>
                    <p class="text-sm font-semibold text-gray-700"><?= h($journal['libelle'] ?? '') ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= h($journal['type'] ?? '') ?></p>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-list mr-2 text-blue-500"></i>
                    Ecritures comptables
                </h2>
                <?php if ($selectedJournal): ?>
                    <a href="/comptabilite/exporter?type=journal&journal=<?= urlencode($selectedJournal) ?>&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>"
                       class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-file-export mr-2"></i>Exporter
                    </a>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Date</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Piece</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Libelle</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Reference</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Debit</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Credit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ecritures)): ?>
                            <tr>
                                <td colspan="7" class="border border-gray-300 px-4 py-8 text-center text-gray-500">
                                    Aucune ecriture trouvee pour cette selection.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ecritures as $ecriture): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-4 py-2 text-sm">
                                        <?= h(date('d/m/Y', strtotime($ecriture['date_ecriture'] ?? 'now'))) ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-sm font-medium">
                                        <?= h($ecriture['numero_piece'] ?? '') ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-sm">
                                        <?= h($ecriture['libelle'] ?? '') ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-sm">
                                        <?= h(($ecriture['reference_type'] ?? '') . ' #' . ($ecriture['reference_id'] ?? '')) ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-sm text-right">
                                        <?= money($ecriture['total_debit'] ?? 0) ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-sm text-right">
                                        <?= money($ecriture['total_credit'] ?? 0) ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-sm">
                                        <?php if (!empty($ecriture['is_equilibree'])): ?>
                                            <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded">Equilibree</span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-700 text-xs px-2 py-1 rounded">A verifier</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="4" class="border border-gray-300 px-4 py-2 text-right">Totaux</td>
                            <td class="border border-gray-300 px-4 py-2 text-right"><?= money($totalDebit) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-right"><?= money($totalCredit) ?></td>
                            <td class="border border-gray-300 px-4 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
