<?php

namespace App\Repositories;

use PDO;

/** Accès SQL du module Suivi Client et de ses brouillons de règlement. */
final class SuiviClientRepository
{
    public function __construct(private PDO $db) {}

    public function clients(string $search = '', string $status = '', int $page = 1, int $perPage = 25, string $sort = 'nom', string $direction = 'asc', string $typeClient = '', string $ville = ''): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($search !== '') { $where[] = '(c.code LIKE :q OR c.matricule LIKE :q OR c.nom LIKE :q OR c.prenom LIKE :q OR c.telephone LIKE :q OR c.email LIKE :q OR c.adresse LIKE :q OR c.ville LIKE :q)'; $params['q'] = "%{$search}%"; }
        if ($status === 'debiteurs') $where[] = 'c.solde_credit > 0';
        if ($status === 'crediteurs') $where[] = 'c.solde_credit < 0';
        if ($status === 'actif') $where[] = 'c.is_actif = 1';
        if ($status === 'inactif') $where[] = 'c.is_actif = 0';
        if ($typeClient !== '') { $where[] = 'c.type_client = :type_client'; $params['type_client'] = $typeClient; }
        if ($ville !== '') { $where[] = 'c.ville LIKE :ville'; $params['ville'] = "%{$ville}%"; }
        $filter = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM clients c WHERE {$filter}");
        $count->execute($params);
        $sorts = ['id'=>'c.id','code_client'=>'c.code','matricule'=>'c.matricule','nom'=>'c.nom','prenom'=>'c.prenom','telephone'=>'c.telephone','email'=>'c.email','ville'=>'c.ville','type_client'=>'c.type_client','plafond'=>'c.plafond_credit','solde'=>'c.solde_credit','created_at'=>'c.created_at','updated_at'=>'c.updated_at'];
        $order = $sorts[$sort] ?? $sorts['nom'];
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT c.id,c.code,c.code AS code_client,c.matricule,c.nom,c.prenom,c.date_naissance,c.telephone,c.telephone_secondaire,c.email,c.adresse,c.ville,c.type_client,c.is_actif,c.numero_ifu,c.numero_rccm,c.numero_assurance,c.compagnie_assurance,c.plafond_credit AS plafond,c.solde_initial,c.solde_credit,c.solde_credit AS solde,c.notes,c.created_at,c.updated_at FROM clients c WHERE {$filter} ORDER BY {$order} {$direction} LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue(':'.$key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows'=>$stmt->fetchAll(), 'total'=>(int)$count->fetchColumn(), 'page'=>$page, 'per_page'=>$perPage];
    }

    public function client(int $id): ?array { $s=$this->db->prepare('SELECT * FROM clients WHERE id=? AND deleted_at IS NULL'); $s->execute([$id]); return $s->fetch() ?: null; }
    public function users(): array { return $this->db->query('SELECT id, nom, prenom, username FROM utilisateurs WHERE is_active=1 AND deleted_at IS NULL ORDER BY nom, prenom')->fetchAll(); }

    public function drafts(array $filters = []): array
    {
        $where = ["cr.statut = 'BROUILLON'", 'cr.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = '(cr.numero_brouillon LIKE :search_number OR c.code LIKE :search_code OR c.nom LIKE :search_nom OR c.prenom LIKE :search_prenom OR c.telephone LIKE :search_phone OR u.username LIKE :search_user)';
            foreach (['search_number','search_code','search_nom','search_prenom','search_phone','search_user'] as $key) $params[$key] = '%'.$filters['search'].'%';
        }
        if (!empty($filters['client_id'])) { $where[] = 'cr.client_id = :client_id'; $params['client_id'] = (int)$filters['client_id']; }
        if (!empty($filters['utilisateur_id'])) { $where[] = 'cr.utilisateur_id = :utilisateur_id'; $params['utilisateur_id'] = (int)$filters['utilisateur_id']; }
        if (!empty($filters['from'])) { $where[] = 'cr.date_mouvement >= :from'; $params['from'] = $filters['from'].' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'cr.date_mouvement < DATE_ADD(:to, INTERVAL 1 DAY)'; $params['to'] = $filters['to']; }
        $period = $filters['period'] ?? '';
        if ($period === 'today') $where[] = 'DATE(cr.date_mouvement) = CURDATE()';
        if ($period === 'week') $where[] = 'YEARWEEK(cr.date_mouvement, 1) = YEARWEEK(CURDATE(), 1)';
        if ($period === 'month') $where[] = 'DATE_FORMAT(cr.date_mouvement, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")';
        $sql = 'SELECT cr.*, c.code, c.nom, c.prenom, c.telephone, u.username, u.nom AS utilisateur_nom, u.prenom AS utilisateur_prenom FROM client_reglements cr JOIN clients c ON c.id=cr.client_id LEFT JOIN utilisateurs u ON u.id=cr.utilisateur_id WHERE '.implode(' AND ', $where).' ORDER BY cr.updated_at DESC, cr.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function draft(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT cr.*, c.code, c.nom, c.prenom, c.telephone, u.username, u.nom AS utilisateur_nom, u.prenom AS utilisateur_prenom FROM client_reglements cr JOIN clients c ON c.id=cr.client_id LEFT JOIN utilisateurs u ON u.id=cr.utilisateur_id WHERE cr.id=:id AND cr.statut='BROUILLON' AND cr.deleted_at IS NULL");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public function saveDraft(array $data, int $userId, ?int $id = null): int
    {
        $params = ['client'=>$data['client_id'],'amount'=>$data['montant'],'mode'=>$data['mode_paiement'],'reference'=>$data['reference'] ?: null,'notes'=>$data['notes'] ?: null,'user'=>$userId,'date'=>$data['date_reglement']];
        if ($id) {
            $params['id'] = $id;
            $stmt = $this->db->prepare("UPDATE client_reglements SET client_id=:client,montant=:amount,mode_paiement=:mode,reference=:reference,notes=:notes,utilisateur_id=:user,date_mouvement=:date,updated_at=NOW() WHERE id=:id AND statut='BROUILLON' AND deleted_at IS NULL");
            $stmt->execute($params);
            if ($stmt->rowCount() === 0 && !$this->draft($id)) throw new \RuntimeException('Brouillon introuvable ou déjà traité.');
            return $id;
        }
        $stmt = $this->db->prepare("INSERT INTO client_reglements (client_id,type_mouvement,montant,mode_paiement,reference,notes,utilisateur_id,date_mouvement,statut) VALUES (:client,'CREDIT',:amount,:mode,:reference,:notes,:user,:date,'BROUILLON')");
        $stmt->execute($params);
        $id = (int)$this->db->lastInsertId();
        $this->db->prepare("UPDATE client_reglements SET numero_brouillon=CONCAT('BR-',DATE_FORMAT(NOW(),'%Y%m%d'),'-',LPAD(id,6,'0')) WHERE id=?")->execute([$id]);
        return $id;
    }

    public function validateDraft(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE client_reglements SET statut='VALIDE',validated_at=NOW(),updated_at=NOW() WHERE id=? AND statut='BROUILLON' AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('Brouillon introuvable ou déjà validé.');
    }

    public function deleteDraft(int $id): ?array
    {
        $draft = $this->draft($id);
        if (!$draft) return null;
        $stmt = $this->db->prepare("UPDATE client_reglements SET statut='SUPPRIME',deleted_at=NOW(),updated_at=NOW() WHERE id=? AND statut='BROUILLON'");
        $stmt->execute([$id]);
        return $draft;
    }

    public function balances(?string $at = null, ?int $clientId = null): array
    {
        $saleDate = $at ? ' AND v.date_vente < DATE_ADD(:sale_at, INTERVAL 1 DAY)' : '';
        $payDate = $at ? ' AND cr.date_mouvement < DATE_ADD(:pay_at, INTERVAL 1 DAY)' : '';
        $invoicePayDate = $at ? ' AND pf.date_paiement < DATE_ADD(:invoice_pay_at, INTERVAL 1 DAY)' : '';
        $avoirDate = $at ? ' AND b.date_emission < DATE_ADD(:avoir_at, INTERVAL 1 DAY)' : '';
        $clientWhere=$clientId?' AND c.id=:client_id':'';
        $sql = "SELECT c.id,c.code,c.nom,c.prenom,c.telephone,c.solde_credit AS solde,c.plafond_credit AS plafond, COALESCE((SELECT SUM(v.montant_net) FROM ventes v WHERE v.client_id=c.id AND v.is_credit=1 AND v.statut_vente <> 'ANNULEE' {$saleDate}),0) total_ventes, COALESCE((SELECT SUM(CASE WHEN cr.type_mouvement IN ('CREDIT','RISTOURNE','ESCOMPTE') THEN cr.montant WHEN cr.type_mouvement='DEBIT' THEN -cr.montant ELSE 0 END) FROM client_reglements cr WHERE cr.client_id=c.id AND cr.statut='VALIDE' AND cr.deleted_at IS NULL {$payDate}),0) + COALESCE((SELECT SUM(pf.montant_paiement) FROM paiements_factures pf JOIN factures f ON f.id=pf.facture_id JOIN ventes fv ON fv.id=f.vente_id WHERE f.client_id=c.id AND fv.is_credit=1 AND fv.statut_vente <> 'ANNULEE' {$invoicePayDate}),0) total_reglements, COALESCE((SELECT SUM(b.montant_total) FROM bons b WHERE b.client_id=c.id AND b.type_bon='avoir' AND b.statut_bon <> 'annule' AND b.deleted_at IS NULL {$avoirDate}),0) total_avoirs FROM clients c WHERE c.deleted_at IS NULL {$clientWhere} ORDER BY c.nom,c.prenom";
        $s=$this->db->prepare($sql); if ($at) foreach (['sale_at','pay_at','invoice_pay_at','avoir_at'] as $key) $s->bindValue(':'.$key,$at); if($clientId)$s->bindValue(':client_id',$clientId,PDO::PARAM_INT); $s->execute(); $rows=$s->fetchAll(); foreach($rows as &$r) $r['solde_calcule']=(float)$r['total_ventes']-(float)$r['total_reglements']-(float)$r['total_avoirs']; unset($r); return $rows;
    }

    public function payments(array $filters = []): array
    {
        $where=["cr.statut='VALIDE'",'cr.deleted_at IS NULL']; $p=[];
        if (!empty($filters['client_id'])) {$where[]='cr.client_id=:client';$p['client']=(int)$filters['client_id'];}
        if (!empty($filters['utilisateur_id'])) {$where[]='cr.utilisateur_id=:utilisateur';$p['utilisateur']=(int)$filters['utilisateur_id'];}
        if (!empty($filters['id'])) {$where[]='cr.id=:id';$p['id']=(int)$filters['id'];}
        if (!empty($filters['search'])) {$where[]='(c.nom LIKE :search OR c.prenom LIKE :search OR c.code LIKE :search OR cr.reference LIKE :search OR CAST(cr.id AS CHAR) LIKE :search)';$p['search']='%'.$filters['search'].'%';}
        if (!empty($filters['from'])) {$where[]='cr.date_mouvement >= :from';$p['from']=$filters['from'].' 00:00:00';}
        if (!empty($filters['to'])) {$where[]='cr.date_mouvement < DATE_ADD(:to, INTERVAL 1 DAY)';$p['to']=$filters['to'];}
        $sql="SELECT cr.*,c.code,c.nom,c.prenom,u.username,u.nom utilisateur_nom,u.prenom utilisateur_prenom FROM client_reglements cr JOIN clients c ON c.id=cr.client_id LEFT JOIN utilisateurs u ON u.id=cr.utilisateur_id WHERE ".implode(' AND ',$where)." AND cr.type_mouvement IN ('CREDIT','RISTOURNE','ESCOMPTE') ORDER BY cr.date_mouvement DESC,cr.id DESC";
        $s=$this->db->prepare($sql);$s->execute($p);return $s->fetchAll();
    }

    public function payment(int $id): ?array { $r=$this->payments(['id'=>$id]); return $r[0]??null; }
    public function statement(int $clientId, ?string $from = null, ?string $to = null): array
    {
        $p=['client_sale'=>$clientId,'client_bon'=>$clientId,'client_credit'=>$clientId,'client_debit'=>$clientId,'client_invoice'=>$clientId]; $date=''; if($from){$date.=' AND operation_date >= :from';$p['from']=$from.' 00:00:00';} if($to){$date.=' AND operation_date < DATE_ADD(:to, INTERVAL 1 DAY)';$p['to']=$to;}
        $sql="SELECT * FROM ( SELECT v.date_vente operation_date,'FACTURE' type_operation,v.numero_facture reference,v.montant_net debit,0 credit,v.id operation_id FROM ventes v WHERE v.client_id=:client_sale AND v.is_credit=1 AND v.statut_vente <> 'ANNULEE' UNION ALL SELECT b.date_emission,'AVOIR',b.numero_bon,0,b.montant_total,b.id FROM bons b WHERE b.client_id=:client_bon AND LOWER(b.type_bon)='avoir' AND LOWER(b.statut_bon)<>'annule' UNION ALL SELECT cr.date_mouvement,'REGLEMENT',COALESCE(cr.reference,CONCAT('RC-',cr.id)),0,cr.montant,cr.id FROM client_reglements cr WHERE cr.client_id=:client_credit AND cr.statut='VALIDE' AND cr.deleted_at IS NULL AND cr.type_mouvement IN ('CREDIT','RISTOURNE','ESCOMPTE') UNION ALL SELECT cr.date_mouvement,'AJUSTEMENT',COALESCE(cr.reference,CONCAT('RC-',cr.id)),cr.montant,0,cr.id FROM client_reglements cr WHERE cr.client_id=:client_debit AND cr.statut='VALIDE' AND cr.deleted_at IS NULL AND cr.type_mouvement='DEBIT' UNION ALL SELECT pf.date_paiement,'REGLEMENT FACTURE',COALESCE(pf.reference_paiement,CONCAT('PF-',pf.id)),0,pf.montant_paiement,pf.id FROM paiements_factures pf JOIN factures f ON f.id=pf.facture_id JOIN ventes fv ON fv.id=f.vente_id WHERE f.client_id=:client_invoice AND fv.is_credit=1 AND fv.statut_vente <> 'ANNULEE' ) ledger WHERE 1=1 {$date} ORDER BY operation_date,operation_id";
        $s=$this->db->prepare($sql);$s->execute($p);return $s->fetchAll();
    }

    public function insertPayment(array $data, int $userId): int
    {
        $s=$this->db->prepare("INSERT INTO client_reglements (client_id,type_mouvement,montant,mode_paiement,reference,notes,utilisateur_id,date_mouvement,statut,validated_at) VALUES (:client,'CREDIT',:amount,:mode,:reference,:notes,:user,:date,'VALIDE',NOW())");
        $s->execute(['client'=>$data['client_id'],'amount'=>$data['montant'],'mode'=>$data['mode_paiement'],'reference'=>$data['reference']?:null,'notes'=>$data['notes']?:null,'user'=>$userId,'date'=>$data['date_reglement']]);
        return (int)$this->db->lastInsertId();
    }

    public function syncBalance(int $clientId): void
    {
        $s=$this->db->prepare("UPDATE clients c SET solde_credit=GREATEST(0,(SELECT COALESCE(SUM(v.montant_net),0) FROM ventes v WHERE v.client_id=c.id AND v.is_credit=1 AND v.statut_vente<>'ANNULEE')-(SELECT COALESCE(SUM(CASE WHEN cr.type_mouvement IN ('CREDIT','RISTOURNE','ESCOMPTE') THEN cr.montant WHEN cr.type_mouvement='DEBIT' THEN -cr.montant ELSE 0 END),0) FROM client_reglements cr WHERE cr.client_id=c.id AND cr.statut='VALIDE' AND cr.deleted_at IS NULL)-(SELECT COALESCE(SUM(pf.montant_paiement),0) FROM paiements_factures pf JOIN factures f ON f.id=pf.facture_id JOIN ventes fv ON fv.id=f.vente_id WHERE f.client_id=c.id AND fv.is_credit=1 AND fv.statut_vente<>'ANNULEE')-(SELECT COALESCE(SUM(b.montant_total),0) FROM bons b WHERE b.client_id=c.id AND b.type_bon='avoir' AND b.statut_bon<>'annule' AND b.deleted_at IS NULL)), solde=solde_credit,updated_at=NOW() WHERE c.id=?");$s->execute([$clientId]);
    }
}
