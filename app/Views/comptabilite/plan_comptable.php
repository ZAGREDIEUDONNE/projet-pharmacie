<?php
require_once __DIR__ . '/_helpers.php';

$planComptable = $planComptable ?? [];
$classes = $classes ?? [];
$classeFilter = $classeFilter ?? '';
$search = $search ?? '';
$page = max(1, (int)($page ?? 1));
$perPage = max(10, min(100, (int)($perPage ?? 20)));
$totalComptes = (int)($totalComptes ?? count($planComptable));
$totalPages = max(1, (int)ceil($totalComptes / $perPage));

$queryBase = http_build_query(array_filter([
    'classe' => $classeFilter,
    'q' => $search,
    'per_page' => $perPage,
]));

c_header('Plan comptable', 'Comptes SYSCOHADA de la pharmacie', 'fa-list-alt');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="/comptabilite/plan-comptable" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1" for="q">Recherche</label>
            <input id="q" type="text" name="q" value="<?= c_h($search) ?>" placeholder="Numero ou libelle de compte"
                   class="w-full border border-gray-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="classe">Classe</label>
            <select id="classe" name="classe" class="w-full border border-gray-300 rounded px-3 py-2">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $code => $libelle): ?>
                    <option value="<?= c_h($code) ?>" <?= $classeFilter === (string)$code ? 'selected' : '' ?>>
                        <?= c_h($code . ' - ' . $libelle) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="per_page">Par page</label>
            <select id="per_page" name="per_page" class="w-full border border-gray-300 rounded px-3 py-2">
                <?php foreach ([20, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
            <i class="fas fa-search mr-2"></i>Filtrer
        </button>
    </form>
</section>

<section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <?php c_stat_card('Comptes actifs', (string)$totalComptes, 'fa-list', 'text-blue-600'); ?>
    <?php c_stat_card('Classe filtree', $classeFilter !== '' ? ('Classe ' . $classeFilter) : 'Toutes', 'fa-filter', 'text-purple-600'); ?>
    <?php c_stat_card('Page', $page . ' / ' . $totalPages, 'fa-file', 'text-gray-600'); ?>
</section>

<section class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold mb-4">Liste des comptes</h2>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-4 py-2 text-left">Numero</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Libelle</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Classe</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Type</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Etat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($planComptable)): ?>
                    <?php c_empty_row(5, 'Aucun compte trouve.'); ?>
                <?php else: ?>
                    <?php foreach ($planComptable as $compte): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2 text-sm font-medium">
                                <?= c_h($compte['numero_compte'] ?? '') ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm">
                                <?= c_h($compte['libelle'] ?? $compte['nom_compte'] ?? '') ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm">
                                <?= c_h(($compte['classe_id'] ?? $compte['classe'] ?? '') . ' - ' . ($compte['classe_libelle'] ?? '')) ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm">
                                <?= c_h($compte['type'] ?? $compte['type_compte'] ?? '') ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm">
                                <?= c_h($compte['etat_financier'] ?? 'BILAN') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center gap-2 mt-6">
            <?php if ($page > 1): ?>
                <a href="/comptabilite/plan-comptable?<?= c_h($queryBase) ?>&page=<?= $page - 1 ?>"
                   class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Precedent</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="/comptabilite/plan-comptable?<?= c_h($queryBase) ?>&page=<?= $page + 1 ?>"
                   class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Suivant</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php c_footer(); ?>
