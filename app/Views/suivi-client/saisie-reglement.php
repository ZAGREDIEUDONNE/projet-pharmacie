<?php
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$draft = $draft ?? null;
$old = $_SESSION['old_data'] ?? [];
unset($_SESSION['old_data']);
$value = fn(string $key, $default = '') => $old[$key] ?? $draft[$key] ?? $default;
$draftId = (int)($draft['id'] ?? $old['draft_id'] ?? 0);
$draftAction = $draftId ? '/suivi-client/brouillons/'.$draftId : '/suivi-client/brouillons';
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $h($title) ?></title><link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100"><main class="max-w-3xl mx-auto p-6"><header class="flex justify-between mb-5"><div><h1 class="text-2xl font-bold"><?= $draftId ? 'Reprendre le brouillon '. $h($draft['numero_brouillon'] ?? '') : 'Saisie de règlement' ?></h1><p class="text-sm text-gray-600">Un brouillon n’a aucun impact financier avant validation.</p></div><a class="bg-gray-600 text-white px-3 py-2 rounded" href="/suivi-client">Retour</a></header>
<?php if(!empty($_SESSION['success'])): ?><p class="p-3 bg-green-100 mb-4"><?= $h($_SESSION['success']); unset($_SESSION['success']) ?></p><?php endif; ?><?php if(!empty($_SESSION['errors'])): ?><p class="p-3 bg-red-100 mb-4"><?= $h(implode(' ',$_SESSION['errors'])); unset($_SESSION['errors']) ?></p><?php endif; ?>
<form id="payment-form" method="post" action="/suivi-client/store-reglement" class="grid md:grid-cols-2 gap-4 bg-white p-6 shadow rounded"><input type="hidden" name="draft_id" id="draft_id" value="<?= $draftId ?>">
<label>Date et heure<input class="border p-2 w-full" type="datetime-local" name="date_reglement" value="<?= $h(str_replace(' ', 'T', (string)$value('date_reglement', date('Y-m-d H:i')))) ?>" required></label>
<label>Client / solde<select class="border p-2 w-full" name="client_id" required><option value="">Sélectionnez un client</option><?php foreach($clients as $client): ?><option value="<?= (int)$client['id'] ?>" <?= (int)$value('client_id') === (int)$client['id'] ? 'selected' : '' ?>><?= $h($client['code'].' — '.$client['nom'].' '.$client['prenom'].' | '.number_format((float)$client['solde_credit'],0,',',' ').' FCFA') ?></option><?php endforeach; ?></select></label>
<label>Montant prévu<input class="border p-2 w-full" type="number" step="0.01" min="0.01" name="montant" value="<?= $h($value('montant')) ?>" required></label>
<label>Mode de règlement<select class="border p-2 w-full" name="mode_paiement" required><?php foreach(['ESPECE','CARTE','CHEQUE','VIREMENT','MOBILE_MONEY','CARNET','DEPOT','BON'] as $mode): ?><option value="<?= $mode ?>" <?= $mode === $value('mode_paiement','ESPECE') ? 'selected' : '' ?>><?= $mode ?></option><?php endforeach; ?></select></label>
<label>Référence<input class="border p-2 w-full" name="reference" value="<?= $h($value('reference')) ?>"></label><label>Observations<textarea class="border p-2 w-full" name="notes"><?= $h($value('notes')) ?></textarea></label>
<p id="draft-status" class="md:col-span-2 text-sm text-gray-500"><?= $draftId ? 'Brouillon enregistré — dernière modification : '. $h($draft['updated_at'] ?? '') : 'La saisie est enregistrée automatiquement en brouillon dès que les champs obligatoires sont renseignés.' ?></p>
<div class="md:col-span-2 flex flex-wrap gap-2"><button class="bg-green-700 text-white rounded p-2" type="submit">Valider le règlement</button><button class="bg-gray-700 text-white rounded p-2" type="submit" formaction="<?= $h($draftAction) ?>">Enregistrer le brouillon</button><?php if($draftId): ?><a class="bg-blue-700 text-white rounded p-2" href="/suivi-client/brouillons/<?= $draftId ?>/consulter">Consulter</a><?php endif; ?></div></form></main>
<script>
const form=document.getElementById('payment-form'), status=document.getElementById('draft-status'), draftInput=document.getElementById('draft_id'); let timer;
function autoSave(){ clearTimeout(timer); timer=setTimeout(async()=>{ if(!form.client_id.value || !form.montant.value || Number(form.montant.value)<=0) return; const id=draftInput.value, url=id?'/suivi-client/brouillons/'+id:'/suivi-client/brouillons'; const body=new FormData(form); try { const response=await fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body}); const json=await response.json(); if(!json.success) throw new Error(json.message); draftInput.value=json.id; status.textContent='Brouillon enregistré automatiquement.'; } catch(e) { status.textContent='Enregistrement automatique impossible : '+e.message; status.className='md:col-span-2 text-sm text-red-600'; } },1200); }
form.querySelectorAll('input,select,textarea').forEach(el=>el.addEventListener('change',autoSave));
</script></body></html>
