<?php
require_once __DIR__ . '/_helpers.php';

$type = $type ?? ($_GET['type'] ?? 'clients');
$dateFin = $dateFin ?? ($_GET['date_fin'] ?? date('Y-m-d'));
$suivi = $suivi ?? [];
$rows = [];
$stats = [];

if ($type === 'clients') {
    $rows = $suivi['clients'] ?? [];
    $stats = $suivi['statistiques'] ?? [];
} elseif ($type === 'fournisseurs') {
    $rows = $suivi['fournisseurs'] ?? [];
    $stats = $suivi['statistiques'] ?? [];
} elseif ($type === 'age_creances' || $type === 'age_dettes') {
    $rows = is_array($suivi) ? $suivi : [];
} elseif ($type === 'rapport') {
    $stats = $suivi['synthese'] ?? [];
    $rows = $suivi['age_creances'] ?? [];
}

c_header('Suivi tiers', 'Clients, creances, fournisseurs et dettes', 'fa-users');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="/comptabilite/suivi-tiers" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="type">Vue</label>
            <select id="type" name="type" class="w-full border border-gray-300 rounded px-3 py-2">
                <?php foreach (['clients' => 'Clients', 'fournisseurs' => 'Fournisseurs', 'age_creances' => 'Age creances', 'age_dettes' => 'Age dettes', 'rapport' => 'Rapport'] as $key => $label): ?>
                    <option value="<?= c_h($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= c_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="date_fin">Date fin</label>
            <input id="date_fin" type="date" name="date_fin" value="<?= c_h($dateFin) ?>" class="w-full border border-gray-300 rounded px-3 py-2">
        </div>
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded" type="submit"><i class="fas fa-search mr-2"></i>Afficher</button>
        <a href="/comptabilite/exporter?type=<?= $type === 'fournisseurs' ? 'fournisseurs' : 'clients' ?>&date_fin=<?= urlencode($dateFin) ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-center"><i class="fas fa-file-export mr-2"></i>Exporter</a>
    </form>
</section>

<section class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <?php c_stat_card('Clients', (string)($stats['total_clients'] ?? count($rows)), 'fa-user', 'text-blue-600'); ?>
    <?php c_stat_card('Fournisseurs', (string)($stats['total_fournisseurs'] ?? 0), 'fa-truck', 'text-purple-600'); ?>
    <?php c_stat_card('Creances', c_money($stats['total_creances'] ?? 0), 'fa-hand-holding-dollar', 'text-green-600'); ?>
    <?php c_stat_card('Dettes', c_money($stats['total_dettes'] ?? 0), 'fa-file-invoice-dollar', 'text-red-600'); ?>
</section>

<section class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold mb-4">Details</h2>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-4 py-2 text-left">Nom</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Contact</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Montant</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Statut</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Activite / age</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <?php c_empty_row(5); ?>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $nom = trim(($row['nom'] ?? '') . ' ' . ($row['prenom'] ?? ''));
                        $montant = $row['solde_credit'] ?? $row['total_dettes'] ?? $row['total_credit'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($nom ?: ($row['code'] ?? '-')) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($row['telephone'] ?? $row['email'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($montant) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($row['statut_client'] ?? $row['statut_fournisseur'] ?? $row['niveau_risque'] ?? $row['niveau_dette'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($row['statut_activite'] ?? $row['tranche_age'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php c_footer(); ?>
