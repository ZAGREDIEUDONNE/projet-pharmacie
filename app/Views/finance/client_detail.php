<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Détail client')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<?php $client = $data['client'] ?? []; $ventes = $data['ventes'] ?? []; $reglements = $data['reglements'] ?? []; ?>
<div class="max-w-7xl mx-auto">
    <a href="/finance/clients" class="text-blue-600 mb-4 inline-block">← Retour</a>
    <h1 class="text-2xl font-bold mb-6"><?= htmlspecialchars(trim((string)($client['nom'] ?? '') . ' ' . (string)($client['prenom'] ?? ''))) ?></h1>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-5"><p class="text-sm text-gray-500">Solde crédit</p><p class="text-xl font-bold"><?= number_format((float)($client['solde_credit'] ?? 0), 0, ',', ' ') ?> FCFA</p></div>
        <div class="bg-white rounded-lg shadow p-5"><p class="text-sm text-gray-500">Plafond</p><p class="text-xl font-bold"><?= number_format((float)($client['plafond_credit'] ?? 0), 0, ',', ' ') ?> FCFA</p></div>
    </div>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="font-bold mb-4">Enregistrer un règlement / acompte / ristourne / escompte</h2>
        <form method="POST" action="/finance/clients/reglement" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="hidden" name="client_id" value="<?= (int)($client['id'] ?? 0) ?>">
            <div><label class="text-sm">Type</label><select name="type_mouvement" class="w-full border rounded px-3 py-2"><option value="CREDIT">Paiement / acompte</option><option value="DEBIT">Débit</option><option value="RISTOURNE">Ristourne</option><option value="ESCOMPTE">Escompte</option></select></div>
            <div><label class="text-sm">Montant</label><input type="number" name="montant" min="0.01" step="0.01" required class="w-full border rounded px-3 py-2"></div>
            <div><label class="text-sm">Vente liée (optionnel)</label><select name="vente_id" class="w-full border rounded px-3 py-2"><option value="">—</option><?php foreach ($ventes as $v): ?><option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars((string)($v['numero_facture'] ?? $v['id'])) ?> — reste <?= number_format((float)($v['montant_restant'] ?? 0), 0, ',', ' ') ?></option><?php endforeach; ?></select></div>
            <div><label class="text-sm">Mode</label><select name="mode_paiement" class="w-full border rounded px-3 py-2"><option>ESPECE</option><option>MOBILE_MONEY</option><option>CHEQUE</option><option>VIREMENT</option></select></div>
            <div class="md:col-span-2"><label class="text-sm">Notes</label><input type="text" name="notes" class="w-full border rounded px-3 py-2"></div>
            <div class="md:col-span-3"><button class="bg-green-600 text-white px-6 py-2 rounded">Enregistrer</button></div>
        </form>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6"><h2 class="font-bold mb-3">Ventes crédit</h2><table class="w-full text-sm"><?php foreach ($ventes as $v): ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)($v['numero_facture'] ?? '')) ?></td><td class="py-2 text-right"><?= number_format((float)($v['montant_restant'] ?? 0), 0, ',', ' ') ?></td></tr><?php endforeach; ?></table></div>
        <div class="bg-white rounded-lg shadow p-6"><h2 class="font-bold mb-3">Historique règlements</h2><table class="w-full text-sm"><?php foreach ($reglements as $r): ?><tr class="border-t"><td class="py-2"><?= htmlspecialchars((string)($r['type_mouvement'] ?? '')) ?></td><td class="py-2 text-right"><?= number_format((float)($r['montant'] ?? 0), 0, ',', ' ') ?></td><td class="py-2 text-xs text-gray-500"><?= htmlspecialchars((string)($r['date_mouvement'] ?? '')) ?></td></tr><?php endforeach; ?></table></div>
    </div>
</div></body></html>
