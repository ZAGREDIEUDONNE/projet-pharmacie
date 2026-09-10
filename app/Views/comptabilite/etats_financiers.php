<?php
require_once __DIR__ . '/_helpers.php';

$type = $type ?? ($_GET['type'] ?? 'compte_resultat');
$dateDebut = $dateDebut ?? ($_GET['date_debut'] ?? date('Y-m-01'));
$dateFin = $dateFin ?? ($_GET['date_fin'] ?? date('Y-m-d'));
$etat = $etat ?? [];

$titles = [
    'compte_resultat' => 'Compte de resultat',
    'bilan' => 'Bilan',
    'tresorerie' => 'Tresorerie',
    'tva' => 'Rapport TVA',
    'analyse_financiere' => 'Analyse financiere',
];

c_header('Etats financiers', 'Synthese comptable et financiere', 'fa-chart-pie');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="/comptabilite/etats-financiers" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="type">Etat</label>
            <select id="type" name="type" class="w-full border border-gray-300 rounded px-3 py-2">
                <?php foreach ($titles as $key => $label): ?>
                    <option value="<?= c_h($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= c_h($label) ?></option>
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
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded" type="submit">
            <i class="fas fa-refresh mr-2"></i>Generer
        </button>
        <a href="/comptabilite/exporter?type=<?= urlencode($type) ?>&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-center">
            <i class="fas fa-file-export mr-2"></i>Exporter
        </a>
    </form>
</section>

<?php if ($type === 'compte_resultat'): ?>
    <?php $totaux = $etat['totaux_generaux'] ?? []; $lignes = $etat['lignes_resultat'] ?? []; ?>
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <?php c_stat_card('Charges', c_money($totaux['total_charges'] ?? 0), 'fa-arrow-down', 'text-red-600'); ?>
        <?php c_stat_card('Produits', c_money($totaux['total_produits'] ?? 0), 'fa-arrow-up', 'text-green-600'); ?>
        <?php c_stat_card('Resultat', c_money($totaux['resultat_exploitation'] ?? 0), 'fa-chart-line', (($totaux['resultat_exploitation'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600')); ?>
    </section>
    <section class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4"><?= c_h($titles[$type]) ?></h2>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead><tr class="bg-gray-50"><th class="border px-4 py-2 text-left">Compte</th><th class="border px-4 py-2 text-left">Libelle</th><th class="border px-4 py-2 text-right">Charges</th><th class="border px-4 py-2 text-right">Produits</th></tr></thead>
                <tbody>
                    <?php if (empty($lignes)): c_empty_row(4); else: foreach ($lignes as $ligne): ?>
                        <tr><td class="border px-4 py-2"><?= c_h($ligne['compte_code'] ?? '') ?></td><td class="border px-4 py-2"><?= c_h($ligne['compte_libelle'] ?? '') ?></td><td class="border px-4 py-2 text-right"><?= c_money($ligne['total_charges'] ?? 0) ?></td><td class="border px-4 py-2 text-right"><?= c_money($ligne['total_produits'] ?? 0) ?></td></tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php elseif ($type === 'bilan'): ?>
    <?php $totaux = $etat['totaux_generaux'] ?? []; $lignes = $etat['lignes_bilan'] ?? []; ?>
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <?php c_stat_card('Total actif', c_money($totaux['total_actif'] ?? 0), 'fa-building-columns', 'text-green-600'); ?>
        <?php c_stat_card('Total passif', c_money($totaux['total_passif'] ?? 0), 'fa-scale-balanced', 'text-blue-600'); ?>
        <?php c_stat_card('Ecart', c_money($totaux['ecart'] ?? 0), 'fa-triangle-exclamation', empty($totaux['equilibre']) ? 'text-red-600' : 'text-green-600'); ?>
    </section>
    <section class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">Bilan au <?= c_date($dateFin) ?></h2>
        <div class="overflow-x-auto"><table class="w-full border-collapse">
            <thead><tr class="bg-gray-50"><th class="border px-4 py-2 text-left">Compte</th><th class="border px-4 py-2 text-left">Libelle</th><th class="border px-4 py-2 text-left">Type</th><th class="border px-4 py-2 text-right">Solde</th></tr></thead>
            <tbody><?php if (empty($lignes)): c_empty_row(4); else: foreach ($lignes as $ligne): ?><tr><td class="border px-4 py-2"><?= c_h($ligne['compte_code'] ?? '') ?></td><td class="border px-4 py-2"><?= c_h($ligne['compte_libelle'] ?? '') ?></td><td class="border px-4 py-2"><?= c_h($ligne['compte_type'] ?? '') ?></td><td class="border px-4 py-2 text-right"><?= c_money($ligne['solde'] ?? 0) ?></td></tr><?php endforeach; endif; ?></tbody>
        </table></div>
    </section>
<?php elseif ($type === 'tresorerie'): ?>
    <?php $totaux = $etat['totaux_generaux'] ?? []; $lignes = $etat['lignes_tresorerie'] ?? []; ?>
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <?php c_stat_card('Entrees', c_money($totaux['total_entrees'] ?? 0), 'fa-arrow-down', 'text-green-600'); ?>
        <?php c_stat_card('Sorties', c_money($totaux['total_sorties'] ?? 0), 'fa-arrow-up', 'text-red-600'); ?>
        <?php c_stat_card('Solde final', c_money($totaux['solde_final'] ?? 0), 'fa-wallet', 'text-blue-600'); ?>
    </section>
    <section class="bg-white rounded-lg shadow-md p-6"><h2 class="text-lg font-semibold mb-4">Tresorerie</h2><div class="overflow-x-auto"><table class="w-full border-collapse">
        <thead><tr class="bg-gray-50"><th class="border px-4 py-2 text-left">Compte</th><th class="border px-4 py-2 text-left">Libelle</th><th class="border px-4 py-2 text-right">Entrees</th><th class="border px-4 py-2 text-right">Sorties</th><th class="border px-4 py-2 text-right">Solde</th></tr></thead>
        <tbody><?php if (empty($lignes)): c_empty_row(5); else: foreach ($lignes as $ligne): ?><tr><td class="border px-4 py-2"><?= c_h($ligne['compte_code'] ?? '') ?></td><td class="border px-4 py-2"><?= c_h($ligne['compte_libelle'] ?? '') ?></td><td class="border px-4 py-2 text-right"><?= c_money($ligne['total_entrees'] ?? 0) ?></td><td class="border px-4 py-2 text-right"><?= c_money($ligne['total_sorties'] ?? 0) ?></td><td class="border px-4 py-2 text-right"><?= c_money($ligne['solde'] ?? 0) ?></td></tr><?php endforeach; endif; ?></tbody>
    </table></div></section>
<?php else: ?>
    <section class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4"><?= c_h($titles[$type] ?? 'Etat') ?></h2>
        <pre class="bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto text-sm"><?= c_h(json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </section>
<?php endif; ?>
<?php c_footer(); ?>
