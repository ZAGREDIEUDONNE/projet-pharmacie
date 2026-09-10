<?php
require_once __DIR__ . '/_helpers.php';

$action = $action ?? ($_GET['action'] ?? 'declaration');
$dateDebut = $dateDebut ?? ($_GET['date_debut'] ?? date('Y-m-01'));
$dateFin = $dateFin ?? ($_GET['date_fin'] ?? date('Y-m-d'));
$donnees = $donnees ?? [];

$rows = $donnees['lignes_declaration'] ?? $donnees['declaration'] ?? $donnees;
$totaux = $donnees['totaux'] ?? [];
if (!is_array($rows) || isset($rows['success'])) {
    $rows = [];
}

c_header('TVA', 'Declaration, rapport et taux actifs', 'fa-receipt');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="/comptabilite/tva" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="action">Vue</label>
            <select id="action" name="action" class="w-full border border-gray-300 rounded px-3 py-2">
                <?php foreach (['declaration' => 'Declaration', 'rapport' => 'Rapport', 'taux' => 'Taux TVA'] as $key => $label): ?>
                    <option value="<?= c_h($key) ?>" <?= $action === $key ? 'selected' : '' ?>><?= c_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="date_debut">Date debut</label>
            <input id="date_debut" type="date" name="date_debut" value="<?= c_h($dateDebut) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="date_fin">Date fin</label>
            <input id="date_fin" type="date" name="date_fin" value="<?= c_h($dateFin) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
        </div>
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded" type="submit"><i class="fas fa-refresh mr-2"></i>Afficher</button>
        <a href="/comptabilite/exporter?type=tva&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-center"><i class="fas fa-file-export mr-2"></i>Exporter</a>
    </form>
</section>

<section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <?php c_stat_card('TVA collectee', c_money($totaux['tva_collectee'] ?? 0), 'fa-arrow-up', 'text-green-600'); ?>
    <?php c_stat_card('TVA deductible', c_money($totaux['tva_deductible'] ?? 0), 'fa-arrow-down', 'text-blue-600'); ?>
    <?php c_stat_card('TVA due', c_money($totaux['tva_due'] ?? 0), 'fa-file-invoice-dollar', (($totaux['tva_due'] ?? 0) >= 0 ? 'text-red-600' : 'text-green-600')); ?>
</section>

<section class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold mb-4"><?= $action === 'taux' ? 'Taux actifs' : 'Details TVA' ?></h2>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-4 py-2 text-left">Code</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Libelle</th>
                    <th class="border border-gray-300 px-4 py-2 text-right"><?= $action === 'taux' ? 'Taux' : 'Collectee' ?></th>
                    <th class="border border-gray-300 px-4 py-2 text-right"><?= $action === 'taux' ? 'Statut' : 'Deductible' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <?php c_empty_row(4); ?>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($row['compte_code'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($row['compte_libelle'] ?? $row['libelle'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= $action === 'taux' ? c_h(((float)($row['taux'] ?? 0) * 100) . ' %') : c_money($row['tva_collectee'] ?? 0) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= $action === 'taux' ? (!empty($row['is_actif']) ? 'Actif' : 'Inactif') : c_money($row['tva_deductible'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php c_footer(); ?>
