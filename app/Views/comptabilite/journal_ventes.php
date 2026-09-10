<?php
$user = $user ?? ($_SESSION['user'] ?? []);
$ecritures = $ecritures ?? [];
$dateDebut = $dateDebut ?? date('Y-m-01');
$dateFin = $dateFin ?? date('Y-m-d');
$search = $search ?? '';

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
    <title>Journal des Ventes - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <header class="bg-green-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-shopping-cart text-2xl"></i>
                    <div>
                        <h1 class="text-xl font-bold">Journal des Ventes</h1>
                        <p class="text-sm text-green-100">Écritures comptables des ventes</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span><strong>Utilisateur:</strong> <?= h($user['name'] ?? $user['username'] ?? 'Invité') ?></span>
                    <span><strong>Date:</strong> <?= date('d/m/Y H:i') ?></span>
                    <a href="/comptabilite/journaux" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded">
                        <i class="fas fa-arrow-left mr-1"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm text-gray-600">Écritures affichées</p>
                <p class="text-3xl font-bold text-blue-600"><?= count($ecritures) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm text-gray-600">Total Débit</p>
                <p class="text-3xl font-bold text-green-600"><?= money($totalDebit) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm text-gray-600">Total Crédit</p>
                <p class="text-3xl font-bold text-red-600"><?= money($totalCredit) ?></p>
            </div>
        </section>

        <section class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" action="/comptabilite/journaux/ventes" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                    <input id="search" type="text" name="search" value="<?= h($search) ?>" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Référence, libellé...">
                </div>
                <div>
                    <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                    <input id="date_debut" type="date" name="date_debut" value="<?= h($dateDebut) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div>
                    <label for="date_fin" class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                    <input id="date_fin" type="date" name="date_fin" value="<?= h($dateFin) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-search mr-2"></i>Filtrer
                </button>
            </form>
        </section>

        <section class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-list mr-2 text-green-500"></i>
                    Écritures des ventes
                </h2>
                <div class="space-x-2">
                    <a href="/comptabilite/exporter?type=journal&journal=VT&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>"
                       class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-file-excel mr-2"></i>Excel
                    </a>
                    <button onclick="window.print()" class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Date</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Pièce</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Référence</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Compte Débit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Compte Crédit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Libellé</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Débit</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Crédit</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ecritures)): ?>
                            <tr>
                                <td colspan="9" class="border border-gray-300 px-4 py-8 text-center text-gray-500">
                                    Aucune écriture trouvée pour cette période.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ecritures as $ecriture): ?>
                                <?php foreach ($ecriture['lignes'] ?? [] as $ligne): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-4 py-2 text-sm">
                                            <?= h(date('d/m/Y', strtotime($ecriture['date_ecriture'] ?? 'now'))) ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm font-medium">
                                            <?= h($ecriture['numero_piece'] ?? '') ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm">
                                            <?= h(($ecriture['reference_type'] ?? '') . ' #' . ($ecriture['reference_id'] ?? '')) ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm">
                                            <?= h($ligne['compte_code'] ?? '') ?> - <?= h($ligne['compte_libelle'] ?? '') ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm">
                                            <?php if ($ligne['credit'] > 0): ?>
                                                <?= h($ligne['compte_code'] ?? '') ?> - <?= h($ligne['compte_libelle'] ?? '') ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm">
                                            <?= h($ligne['libelle'] ?? '') ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm text-right">
                                            <?= money($ligne['debit'] ?? 0) ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm text-right">
                                            <?= money($ligne['credit'] ?? 0) ?>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm">
                                            <?= h($ecriture['utilisateur_nom'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="6" class="border border-gray-300 px-4 py-2 text-right">Totaux</td>
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
