<?php
require_once __DIR__ . '/_helpers.php';

$dateFin = $dateFin ?? ($_GET['date_fin'] ?? date('Y-m-d'));
$balance = $balance ?? [];
$lignes = $balance['lignes_balance'] ?? [];
$totaux = $balance['totaux_generaux'] ?? [];
$equilibre = $equilibre ?? ['equilibre' => $totaux['equilibree'] ?? false, 'ecart' => $totaux['ecart'] ?? 0];

c_header('Balance generale', 'Controle des soldes par compte', 'fa-balance-scale');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="/comptabilite/balance" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="date_fin">Date fin</label>
            <input id="date_fin" type="date" name="date_fin" value="<?= c_h($dateFin) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
        </div>
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded" type="submit">
            <i class="fas fa-refresh mr-2"></i>Generer
        </button>
        <a href="/comptabilite/exporter?type=balance&date_debut=<?= urlencode(date('Y-m-01')) ?>&date_fin=<?= urlencode($dateFin) ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-center">
            <i class="fas fa-file-export mr-2"></i>Exporter
        </a>
    </form>
</section>

<section class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <?php c_stat_card('Total debit', c_money($totaux['total_debit'] ?? 0), 'fa-arrow-down', 'text-green-600'); ?>
    <?php c_stat_card('Total credit', c_money($totaux['total_credit'] ?? 0), 'fa-arrow-up', 'text-red-600'); ?>
    <?php c_stat_card('Comptes mouvementes', (string)($totaux['nombre_comptes'] ?? 0), 'fa-list', 'text-blue-600'); ?>
    <?php c_stat_card('Equilibre', !empty($equilibre['equilibre']) ? 'Oui' : 'Non', 'fa-check-circle', !empty($equilibre['equilibre']) ? 'text-green-600' : 'text-red-600'); ?>
</section>

<section class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold mb-4">Balance au <?= c_date($dateFin) ?></h2>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-4 py-2 text-left">Compte</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Libelle</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Classe</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Debit</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Credit</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                    <?php c_empty_row(6, 'Aucune ecriture comptable disponible pour cette date.'); ?>
                <?php else: ?>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2 text-sm font-medium"><?= c_h($ligne['compte_code'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($ligne['compte_libelle'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($ligne['classe_libelle'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($ligne['total_debit'] ?? 0) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($ligne['total_credit'] ?? 0) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($ligne['solde'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php c_footer(); ?>
