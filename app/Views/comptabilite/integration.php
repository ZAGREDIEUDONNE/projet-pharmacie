<?php
require_once __DIR__ . '/_helpers.php';

$action = $action ?? ($_GET['action'] ?? 'etat');
$dateDebut = $dateDebut ?? ($_GET['date_debut'] ?? date('Y-m-01'));
$dateFin = $dateFin ?? ($_GET['date_fin'] ?? date('Y-m-d'));
$resultat = $resultat ?? [];

c_header('Integration comptable', 'Synchronisation des ventes, commandes, caisse et stock', 'fa-sync');
?>
<section class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="POST" action="/comptabilite/integration" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="csrf_token" value="<?= c_h(\App\Services\CsrfService::token()) ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="action">Action</label>
            <select id="action" name="action" class="w-full border border-gray-300 rounded px-3 py-2">
                <?php foreach (['etat' => 'Etat', 'ventes' => 'Integrer ventes', 'commandes' => 'Integrer commandes', 'caisse' => 'Integrer caisse', 'stock' => 'Integrer stock', 'complet' => 'Integration complete', 'forcer' => 'Forcer synchro'] as $key => $label): ?>
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
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded" type="submit"><i class="fas fa-play mr-2"></i>Executer</button>
        <a href="/comptabilite/journaux" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-center"><i class="fas fa-journal-whills mr-2"></i>Journaux</a>
    </form>
</section>

<section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <?php c_stat_card('Statut', !empty($resultat['success']) ? 'OK' : 'A verifier', 'fa-circle-check', !empty($resultat['success']) ? 'text-green-600' : 'text-yellow-600'); ?>
    <?php c_stat_card('Ecritures', (string)($resultat['nombre_ecritures'] ?? $resultat['total_ecritures'] ?? 0), 'fa-list', 'text-blue-600'); ?>
    <?php c_stat_card('Periode', c_date($dateDebut) . ' - ' . c_date($dateFin), 'fa-calendar', 'text-purple-600'); ?>
</section>

<section class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold mb-4">Resultat</h2>
    <?php if (!empty($resultat['message'])): ?>
        <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded"><?= c_h($resultat['message']) ?></div>
    <?php endif; ?>
    <pre class="bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto text-sm"><?= c_h(json_encode($resultat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</section>
<?php c_footer(); ?>
