<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Détail fournisseur')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<?php $f = $data['fournisseur'] ?? []; $receptions = $data['receptions'] ?? []; $reglements = $data['reglements'] ?? []; ?>
<div class="max-w-7xl mx-auto">
    <a href="/finance/fournisseurs" class="text-blue-600 mb-4 inline-block">← Retour</a>
    <h1 class="text-2xl font-bold mb-6"><?= htmlspecialchars((string)($f['nom'] ?? '')) ?></h1>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="font-bold mb-4">Enregistrer un paiement / acompte / ristourne / escompte</h2>
        <form method="POST" action="/finance/fournisseurs/reglement" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="hidden" name="fournisseur_id" value="<?= (int)($f['id'] ?? 0) ?>">
            <div><label class="text-sm">Type</label><select name="type_mouvement" class="w-full border rounded px-3 py-2"><option value="CREDIT">Paiement / acompte</option><option value="DEBIT">Facture (débit)</option><option value="RISTOURNE">Ristourne</option><option value="ESCOMPTE">Escompte</option></select></div>
            <div><label class="text-sm">Montant</label><input type="number" name="montant" min="0.01" step="0.01" required class="w-full border rounded px-3 py-2"></div>
            <div><label class="text-sm">Réception liée</label><select name="reception_id" class="w-full border rounded px-3 py-2"><option value="">—</option><?php foreach ($receptions as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars((string)($r['numero_facture'] ?? $r['numero_reception'])) ?> — <?= number_format((float)($r['montant_facture'] ?? 0), 0, ',', ' ') ?></option><?php endforeach; ?></select></div>
            <div class="md:col-span-3"><button class="bg-green-600 text-white px-6 py-2 rounded">Enregistrer</button></div>
        </form>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6"><h2 class="font-bold mb-3">Réceptions / factures</h2>
            <table class="w-full text-sm"><?php foreach ($receptions as $r): ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)($r['numero_facture'] ?? $r['numero_reception'])) ?></td><td class="py-2 text-right"><?= number_format((float)($r['montant_facture'] ?? 0), 0, ',', ' ') ?></td></tr><?php endforeach; ?></table>
        </div>
        <div class="bg-white rounded-lg shadow p-6"><h2 class="font-bold mb-3">Historique paiements</h2>
            <table class="w-full text-sm"><?php foreach ($reglements as $r): ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)($r['type_mouvement'] ?? '')) ?></td><td class="py-2 text-right"><?= number_format((float)($r['montant'] ?? 0), 0, ',', ' ') ?></td></tr><?php endforeach; ?></table>
        </div>
    </div>
</div></body></html>
