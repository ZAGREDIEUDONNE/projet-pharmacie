<?php
require_once __DIR__ . '/_helpers.php';

$planComptable = $planComptable ?? [];
$grandLivre = $grandLivre ?? null;
$compte = $compte ?? ($_GET['compte'] ?? '');
$dateDebut = $dateDebut ?? ($_GET['date_debut'] ?? date('Y-m-01'));
$dateFin = $dateFin ?? ($_GET['date_fin'] ?? date('Y-m-d'));
$lignes = $grandLivre['lignes'] ?? [];

c_header('Grand livre', 'Historique des mouvements par compte', 'fa-book-open');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="/comptabilite/grand-livre" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="compte">Compte</label>
            <select id="compte" name="compte" class="w-full border border-gray-300 rounded px-3 py-2">
                <option value="">Selectionner un compte</option>
                <?php foreach ($planComptable as $item): ?>
                    <?php $numeroCompte = $item['numero_compte'] ?? ''; ?>
                    <option value="<?= c_h($numeroCompte) ?>" <?= $compte === $numeroCompte ? 'selected' : '' ?>>
                        <?= c_h($numeroCompte . ' - ' . ($item['libelle'] ?? '')) ?>
                    </option>
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
            <i class="fas fa-search mr-2"></i>Afficher
        </button>
    </form>
</section>

<section class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <?php c_stat_card('Solde precedent', c_money($grandLivre['solde_precedent'] ?? 0), 'fa-history', 'text-gray-700'); ?>
    <?php c_stat_card('Total debit', c_money($grandLivre['total_debit'] ?? 0), 'fa-arrow-down', 'text-green-600'); ?>
    <?php c_stat_card('Total credit', c_money($grandLivre['total_credit'] ?? 0), 'fa-arrow-up', 'text-red-600'); ?>
    <?php c_stat_card('Solde final', c_money($grandLivre['solde_final'] ?? 0), 'fa-scale-balanced', 'text-blue-600'); ?>
</section>

<section class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold mb-4">Mouvements du compte <?= c_h($compte ?: '-') ?></h2>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-4 py-2 text-left">Date</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Piece</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Journal</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Libelle</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Debit</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Credit</th>
                    <th class="border border-gray-300 px-4 py-2 text-right">Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                    <?php c_empty_row(7, $compte ? 'Aucun mouvement pour ce compte.' : 'Choisissez un compte pour afficher le grand livre.'); ?>
                <?php else: ?>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_date($ligne['date_ecriture'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($ligne['numero_piece'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($ligne['journal_code'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm"><?= c_h($ligne['ligne_libelle'] ?? $ligne['ecriture_libelle'] ?? '') ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($ligne['debit'] ?? 0) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($ligne['credit'] ?? 0) ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-right"><?= c_money($ligne['solde_cumule'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php c_footer(); ?>
