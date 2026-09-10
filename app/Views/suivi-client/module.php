<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $fcfa=fn($v)=>number_format((float)$v,0,',',' ').' FCFA';
$payments=$payments??[];$clients=$clients??[];$balances=$balances??[];$movements=$movements??[];$users=$users??[];$payment=$payment??null;$listing=$listing??['rows'=>[],'total'=>0,'page'=>1];$print=$print??false;
$exportExcelQuery=$h(http_build_query(array_merge($_GET,['format'=>'excel'])));
$exportPdfQuery=$h(http_build_query(array_merge($_GET,['format'=>'pdf'])));
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=$h($title??'Suivi Client')?></title><link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"><style>@media print{.no-print{display:none}.thermal{width:78mm;font-size:11px;margin:0}}</style></head><body class="bg-gray-100"><main class="max-w-7xl mx-auto p-6 <?=($screen==='recu-reglement'?'thermal bg-white':'')?>"><header class="flex justify-between mb-5 no-print"><h1 class="text-2xl font-bold"><?=$h($title??'Suivi Client')?></h1><a class="bg-gray-600 text-white px-3 py-2 rounded" href="/suivi-client">Retour</a></header>
<?php if(!empty($_SESSION['success'])):?><p class="p-3 bg-green-100 mb-4"><?=$h($_SESSION['success']);unset($_SESSION['success'])?></p><?php endif;?><?php if(!empty($_SESSION['errors'])):?><p class="p-3 bg-red-100 mb-4"><?=$h(implode(' ',$_SESSION['errors']));unset($_SESSION['errors'])?></p><?php endif;?>
<?php if($screen==='saisie-reglement'):$old=$_SESSION['old_data']??[];unset($_SESSION['old_data']);?><form method="post" action="/suivi-client/store-reglement" class="grid md:grid-cols-2 gap-4 bg-white p-6 shadow rounded"><label>Date et heure<input class="border p-2 w-full" type="datetime-local" name="date_reglement" value="<?=$h($old['date_reglement']??date('Y-m-d\TH:i'))?>" required></label><label>Client / solde<select class="border p-2 w-full" name="client_id" required><?php foreach($clients as $c):?><option value="<?=$c['id']?>" <?=($old['client_id']??0)==$c['id']?'selected':''?>><?=$h($c['code'].' — '.$c['nom'].' '.$c['prenom'].' | '.$fcfa($c['solde_credit']))?></option><?php endforeach;?></select></label><label>Montant<input class="border p-2 w-full" type="number" step="0.01" min="0.01" name="montant" value="<?=$h($old['montant']??'')?>" required></label><label>Mode<select class="border p-2 w-full" name="mode_paiement"><?php foreach(['ESPECE','CARTE','CHEQUE','VIREMENT','MOBILE_MONEY','CARNET','DEPOT','BON'] as $m):?><option <?=$m===($old['mode_paiement']??'ESPECE')?'selected':''?>><?=$m?></option><?php endforeach;?></select></label><label>Référence<input class="border p-2 w-full" name="reference" value="<?=$h($old['reference']??'')?>"></label><label>Observations<textarea class="border p-2 w-full" name="notes"><?=$h($old['notes']??'')?></textarea></label><button class="md:col-span-2 bg-green-700 text-white rounded p-2">Enregistrer</button></form>
<?php elseif(in_array($screen,['ouvrir-saisie','releve-reglements'],true)):?><form class="bg-white p-4 mb-4 grid md:grid-cols-5 gap-2 no-print"><input class="border p-2" name="search" value="<?=$h($_GET['search']??'')?>" placeholder="N°, client ou référence"><select class="border p-2" name="client_id"><option value="">Tous clients</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>" <?=($c['id']==($_GET['client_id']??0))?'selected':''?>><?=$h($c['nom'].' '.$c['prenom'])?></option><?php endforeach;?></select><select class="border p-2" name="utilisateur_id"><option value="">Tous utilisateurs</option><?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=($u['id']==($_GET['utilisateur_id']??0))?'selected':''?>><?=$h($u['nom'].' '.$u['prenom'])?></option><?php endforeach;?></select><input class="border p-2" type="date" name="from" value="<?=$h($_GET['from']??'')?>"><input class="border p-2" type="date" name="to" value="<?=$h($_GET['to']??'')?>"><button class="bg-blue-700 text-white p-2">Filtrer</button></form><p class="mb-3 no-print"><a class="bg-gray-700 text-white px-3 py-2" href="/suivi-client/export?type=reglements&amp;format=pdf">PDF / Imprimer</a> <a class="bg-green-700 text-white px-3 py-2" href="/suivi-client/export?type=reglements&amp;format=excel">Excel</a></p><table class="w-full bg-white text-sm"><tr class="bg-gray-200"><th>Date</th><th>N°</th><th>Client</th><th>Référence</th><th>Montant</th><th>Mode</th><th>Utilisateur</th><th class="no-print"></th></tr><?php $total=0;foreach($payments as $p):$total+=$p['montant'];?><tr class="border-t"><td><?=$h($p['date_mouvement'])?></td><td>RC-<?=$p['id']?></td><td><?=$h($p['nom'].' '.$p['prenom'])?></td><td><?=$h($p['reference'])?></td><td class="text-right"><?=$fcfa($p['montant'])?></td><td><?=$h($p['mode_paiement'])?></td><td><?=$h(trim(($p['utilisateur_nom']??'').' '.($p['utilisateur_prenom']??'')))?></td><td class="no-print"><a href="/suivi-client/recu-reglement?id=<?=$p['id']?>">Détail / reçu</a></td></tr><?php endforeach;?><tr class="font-bold"><td colspan="4">Nombre : <?=count($payments)?></td><td class="text-right"><?=$fcfa($total)?></td><td colspan="3"></td></tr></table>
<?php elseif($screen==='liste-clients'):?>
<form id="clients-filter" class="mb-4 bg-white p-4 rounded shadow no-print">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <input id="client-search" class="border p-2" name="q" value="<?=$h($_GET['q']??'')?>" placeholder="Recherche instantanée (matricule, nom, téléphone, adresse)">
        <select class="border p-2" name="type_client">
            <option value="">Tous types</option>
            <option value="ordinaire" <?=($_GET['type_client']??'')==='ordinaire'?'selected':''?>>Ordinaire</option>
            <option value="assure" <?=($_GET['type_client']??'')==='assure'?'selected':''?>>Assuré</option>
        </select>
        <input class="border p-2" name="ville" value="<?=$h($_GET['ville']??'')?>" placeholder="Ville (dans adresse)">
        <select class="border p-2" name="statut">
            <option value="">Tous statuts</option>
            <option value="debiteurs" <?=($_GET['statut']??'')==='debiteurs'?'selected':''?>>Débiteurs</option>
            <option value="crediteurs" <?=($_GET['statut']??'')==='crediteurs'?'selected':''?>>Créditeurs</option>
        </select>
    </div>
    <div class="flex flex-wrap gap-2 items-center">
        <select class="border p-2" name="sort">
            <option value="id" <?=($_GET['sort']??'')==='id'?'selected':''?>>ID</option>
            <option value="matricule" <?=($_GET['sort']??'')==='matricule'?'selected':''?>>Matricule</option>
            <option value="nom" <?=($_GET['sort']??'nom')==='nom'?'selected':''?>>Nom</option>
            <option value="telephone" <?=($_GET['sort']??'')==='telephone'?'selected':''?>>Téléphone</option>
            <option value="type_client" <?=($_GET['sort']??'')==='type_client'?'selected':''?>>Type</option>
            <option value="plafond" <?=($_GET['sort']??'')==='plafond'?'selected':''?>>Plafond</option>
            <option value="solde" <?=($_GET['sort']??'')==='solde'?'selected':''?>>Solde</option>
            <option value="created_at" <?=($_GET['sort']??'')==='created_at'?'selected':''?>>Date création</option>
        </select>
        <select class="border p-2" name="direction">
            <option value="asc" <?=($_GET['direction']??'asc')==='asc'?'selected':''?>>Croissant</option>
            <option value="desc" <?=($_GET['direction']??'asc')==='desc'?'selected':''?>>Décroissant</option>
        </select>
        <button class="bg-blue-700 text-white p-2">Filtrer</button>
        <a href="/suivi-client/liste-clients" class="bg-gray-600 text-white p-2">Réinitialiser</a>
    </div>
</form>
<div class="mb-4 flex flex-wrap gap-2 no-print">
    <a class="bg-green-700 text-white px-3 py-2" href="/suivi-client/export-clients?<?=$exportExcelQuery?>"><i class="fas fa-file-excel mr-1"></i>Excel</a>
    <a class="bg-red-700 text-white px-3 py-2" href="/suivi-client/export-clients?<?=$exportPdfQuery?>"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
    <button class="bg-gray-700 text-white px-3 py-2" onclick="window.print()"><i class="fas fa-print mr-1"></i>Imprimer</button>
</div>
<div class="overflow-x-auto">
    <table class="w-full bg-white text-sm min-w-[1800px]">
        <tr class="bg-gray-200">
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('id')">ID <i class="fas fa-sort"></i></th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('matricule')">Matricule <i class="fas fa-sort"></i></th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('nom')">Nom <i class="fas fa-sort"></i></th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('type_client')">Type <i class="fas fa-sort"></i></th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('telephone')">Téléphone <i class="fas fa-sort"></i></th>
            <th class="p-2">Adresse</th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('plafond')">Plafond <i class="fas fa-sort"></i></th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('solde')">Solde <i class="fas fa-sort"></i></th>
            <th class="p-2 cursor-pointer hover:bg-gray-300" onclick="sortTable('created_at')">Date création <i class="fas fa-sort"></i></th>
            <th class="p-2 no-print">Actions</th>
        </tr>
        <?php foreach($listing['rows'] as $c):?>
        <tr class="border-t hover:bg-gray-50">
            <td class="p-2"><?=$h($c['id'])?></td>
            <td class="p-2"><?=$h($c['matricule'])?></td>
            <td class="p-2 font-medium"><?=$h($c['nom'])?></td>
            <td class="p-2">
                <span class="px-2 py-1 rounded text-xs <?=$c['type_client']==='assure'?'bg-blue-100 text-blue-800':'bg-gray-100 text-gray-800'?>">
                    <?=$h($c['type_client'])?>
                </span>
            </td>
            <td class="p-2"><?=$h($c['telephone'])?></td>
            <td class="p-2 max-w-xs truncate" title="<?=$h($c['adresse'])?>"><?=$h($c['adresse'])?></td>
            <td class="p-2 text-right"><?=$fcfa($c['plafond'])?></td>
            <td class="p-2 text-right font-medium <?=($c['solde']>0)?'text-red-600':(($c['solde']<0)?'text-green-600':'text-gray-900')?>"><?=$fcfa($c['solde'])?></td>
            <td class="p-2 text-xs"><?=$h(date('d/m/Y H:i',strtotime($c['created_at'])))?></td>
            <td class="p-2 no-print">
                <div class="flex flex-wrap gap-1">
                    <a href="/suivi-client/releve-courant?client_id=<?=$c['id']?>" class="text-blue-600 hover:text-blue-800" title="Consulter"><i class="fas fa-eye"></i></a>
                    <a href="/clients/edit?id=<?=$c['id']?>" class="text-green-600 hover:text-green-800" title="Modifier"><i class="fas fa-edit"></i></a>
                    <a href="/suivi-client/solde-courant?client_id=<?=$c['id']?>" class="text-purple-600 hover:text-purple-800" title="Compte"><i class="fas fa-money-bill"></i></a>
                    <a href="/suivi-client/ouvrir-saisie?client_id=<?=$c['id']?>" class="text-orange-600 hover:text-orange-800" title="Règlements"><i class="fas fa-file-invoice"></i></a>
                    <a href="/ventes?client_id=<?=$c['id']?>" class="text-indigo-600 hover:text-indigo-800" title="Ventes"><i class="fas fa-shopping-cart"></i></a>
                    <button onclick="printClient(<?=$c['id']?>)" class="text-gray-600 hover:text-gray-800" title="Imprimer"><i class="fas fa-print"></i></button>
                </div>
            </td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($listing['rows'])):?>
        <tr>
            <td colspan="10" class="p-4 text-center text-gray-500">Aucun client trouvé</td>
        </tr>
        <?php endif;?>
    </table>
</div>
<p class="mt-3"><?=$listing['total']?> clients — page <?=$listing['page']?> sur <?=ceil($listing['total']/$listing['per_page'])?>
    <?php if($listing['page']>1):?> <a class="underline text-blue-600" href="?<?=http_build_query(array_merge($_GET,['page'=>$listing['page']-1]))?>">Précédent</a><?php endif;?>
    <?php if($listing['page']*$listing['per_page']<$listing['total']):?> <a class="underline text-blue-600" href="?<?=http_build_query(array_merge($_GET,['page'=>$listing['page']+1]))?>">Suivant</a><?php endif;?>
</p>
<script>
function sortTable(column) {
    const url = new URL(window.location);
    const currentSort = url.searchParams.get('sort');
    const currentDir = url.searchParams.get('direction') || 'asc';
    url.searchParams.set('sort', column);
    url.searchParams.set('direction', currentSort === column && currentDir === 'asc' ? 'desc' : 'asc');
    window.location = url.toString();
}
function printClient(id) {
    window.open('/suivi-client/print-client?id=' + id, '_blank');
}
</script>
<?php elseif(in_array($screen,['solde-courant','solde-arrete'],true)):?><form class="no-print mb-4"><select class="border p-2" name="client_id"><option value="">Tous clients</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>" <?=($c['id']==($_GET['client_id']??0))?'selected':''?>><?=$h($c['nom'].' '.$c['prenom'])?></option><?php endforeach;?></select><?php if($screen==='solde-arrete'):?><input class="border p-2" type="date" name="date_fin" value="<?=$h($date_fin??date('Y-m-d'))?>"><?php endif;?><button class="bg-blue-700 text-white p-2">Calculer</button></form><table class="w-full bg-white"><tr class="bg-gray-200"><th>Client</th><th>Ventes crédit</th><th>Règlements</th><th>Avoirs</th><th>Solde restant</th></tr><?php foreach($balances as $b):?><tr class="border-t"><td><?=$h($b['code'].' '.$b['nom'].' '.$b['prenom'])?></td><td><?=$fcfa($b['total_ventes'])?></td><td><?=$fcfa($b['total_reglements'])?></td><td><?=$fcfa($b['total_avoirs'])?></td><td><?=$fcfa($b['solde_calcule'])?></td></tr><?php endforeach;?></table>
<?php elseif(in_array($screen,['releve-courant','releve-arrete'],true)):?><form class="no-print mb-4 grid md:grid-cols-4 gap-2"><select class="border p-2" name="client_id"><option value="">Client</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>" <?=($c['id']==($client['id']??0))?'selected':''?>><?=$h($c['nom'].' '.$c['prenom'])?></option><?php endforeach;?></select><?php if($screen==='releve-arrete'):?><input class="border p-2" type="date" name="date_debut" value="<?=$h($_GET['date_debut']??'')?>"><input class="border p-2" type="date" name="date_fin" value="<?=$h($_GET['date_fin']??'')?>"><?php endif;?><button class="bg-blue-700 text-white p-2">Afficher</button></form><?php if($client):?><p class="mb-3 no-print"><a class="bg-gray-700 text-white px-3 py-2" href="/suivi-client/export?type=releve&amp;client_id=<?=$client['id']?>&amp;format=pdf">PDF / Imprimer</a> <a class="bg-green-700 text-white px-3 py-2" href="/suivi-client/export?type=releve&amp;client_id=<?=$client['id']?>&amp;format=excel">Excel</a></p><h2 class="font-bold mb-2"><?=$h($client['nom'].' '.$client['prenom'])?></h2><table class="w-full bg-white"><tr class="bg-gray-200"><th>Date</th><th>Type</th><th>Référence</th><th>Débit</th><th>Crédit</th><th>Solde progressif</th></tr><?php $solde=(float)($opening_balance??0);$debit=0;$credit=0;foreach($movements as $m):$solde+=$m['debit']-$m['credit'];$debit+=$m['debit'];$credit+=$m['credit'];?><tr class="border-t"><td><?=$h($m['operation_date'])?></td><td><?=$h($m['type_operation'])?></td><td><?=$h($m['reference'])?></td><td><?=$fcfa($m['debit'])?></td><td><?=$fcfa($m['credit'])?></td><td><?=$fcfa($solde)?></td></tr><?php endforeach;?><tr class="font-bold"><td colspan="3">Totaux</td><td><?=$fcfa($debit)?></td><td><?=$fcfa($credit)?></td><td><?=$fcfa($solde)?></td></tr></table><?php endif;?>
<?php elseif($screen==='recu-reglement' && $payment):?><div class="text-center"><h1 class="font-bold">Pharmacie ERP</h1><p>REÇU DE RÈGLEMENT RC-<?=$payment['id']?></p></div><hr><p>Date : <?=$h($payment['date_mouvement'])?></p><p>Client : <?=$h($payment['nom'].' '.$payment['prenom'])?></p><p>Montant : <b><?=$fcfa($payment['montant'])?></b></p><p>Mode : <?=$h($payment['mode_paiement'])?></p><p>Référence : <?=$h($payment['reference'])?></p><p>Utilisateur : <?=$h(trim(($payment['utilisateur_nom']??'').' '.($payment['utilisateur_prenom']??'')))?></p><button class="no-print bg-gray-700 text-white p-2 mt-4" onclick="window.print()">Imprimer / Enregistrer au format PDF</button><?php else:?><p class="bg-yellow-100 p-3">Sélectionnez un règlement depuis « Ouvrir une saisie ».</p><?php endif;?></main><?php if($screen==='liste-clients'):?><script>let t;document.getElementById('client-search').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>document.getElementById('clients-filter').submit(),300)});</script><?php endif;?><?php if($print):?><script>window.print()</script><?php endif;?></body></html>
