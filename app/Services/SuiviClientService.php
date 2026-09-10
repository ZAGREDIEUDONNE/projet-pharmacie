<?php

namespace App\Services;

use App\Repositories\SuiviClientRepository;
use PDO;

final class SuiviClientService
{
    public function __construct(private PDO $db, private SuiviClientRepository $repository, private AuditService $audit, private CaisseService $caisse) {}

    public function saveDraft(array $data, int $userId, ?int $draftId = null): int
    {
        $data = $this->normalize($data);
        $this->validate($data);
        $this->db->beginTransaction();
        try {
            $id = $this->repository->saveDraft($data, $userId, $draftId);
            $this->audit->logAction($userId, $draftId ? 'MODIFICATION_BROUILLON_REGLEMENT_CLIENT' : 'CREATION_BROUILLON_REGLEMENT_CLIENT', 'client_reglements', $id, null, $this->auditPayload($data));
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function validatePayment(array $data, int $userId): int
    {
        $data = $this->normalize($data);
        $this->validate($data);
        $draftId = (int)($data['draft_id'] ?? 0);
        $this->db->beginTransaction();
        try {
            if ($draftId > 0) {
                $draft = $this->repository->draft($draftId);
                if (!$draft) throw new \InvalidArgumentException('Brouillon introuvable ou déjà traité.');
                $this->repository->saveDraft($data, $userId, $draftId);
                $this->repository->validateDraft($draftId);
                $id = $draftId;
            } else {
                $id = $this->repository->insertPayment($data, $userId);
            }
            $this->repository->syncBalance((int)$data['client_id']);
            $this->recordCashMovement($id, $data, $userId);
            $this->audit->logAction($userId, $draftId ? 'VALIDATION_BROUILLON_REGLEMENT_CLIENT' : 'SAISIE_REGLEMENT_CLIENT', 'client_reglements', $id, null, $this->auditPayload($data));
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function deleteDraft(int $draftId, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $draft = $this->repository->deleteDraft($draftId);
            if (!$draft) throw new \InvalidArgumentException('Brouillon introuvable ou déjà traité.');
            $this->audit->logAction($userId, 'SUPPRESSION_BROUILLON_REGLEMENT_CLIENT', 'client_reglements', $draftId, $draft, null);
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function normalize(array $data): array
    {
        $data['client_id'] = (int)($data['client_id'] ?? 0);
        $data['montant'] = (float)($data['montant'] ?? 0);
        $data['mode_paiement'] = strtoupper(trim((string)($data['mode_paiement'] ?? '')));
        $data['date_reglement'] = str_replace('T', ' ', trim((string)($data['date_reglement'] ?? date('Y-m-d H:i'))));
        $data['reference'] = trim((string)($data['reference'] ?? ''));
        $data['notes'] = trim((string)($data['notes'] ?? ''));
        return $data;
    }

    private function validate(array $data): void
    {
        $allowed = ['ESPECE','CARTE','CHEQUE','VIREMENT','MOBILE_MONEY','CARNET','DEPOT','CARTE_VISA','BON'];
        if ($data['client_id'] < 1 || $data['montant'] <= 0 || !in_array($data['mode_paiement'], $allowed, true)) throw new \InvalidArgumentException('Client, montant positif et mode de paiement valide sont obligatoires.');
        if (!strtotime($data['date_reglement'])) throw new \InvalidArgumentException('Date de règlement invalide.');
        if (!$this->repository->client($data['client_id'])) throw new \InvalidArgumentException('Client introuvable.');
    }

    private function recordCashMovement(int $paymentId, array $data, int $userId): void
    {
        if (!in_array($data['mode_paiement'], ['ESPECE','CARTE','CHEQUE','MOBILE_MONEY'], true)) return;
        $session = $this->caisse->getSessionOuverte($userId);
        if (!$session) throw new \LogicException('Une session de caisse ouverte est requise pour valider ce mode de règlement.');
        $this->caisse->enregistrerMouvement(['caisse_session_id'=>(int)$session['id'],'type_mouvement'=>'ENCAISSEMENT_CLIENT','montant'=>$data['montant'],'moyen_paiement'=>$data['mode_paiement']==='CARTE_VISA'?'CARTE':$data['mode_paiement'],'reference'=>'REGLEMENT_CLIENT_'.$paymentId,'description'=>'Règlement client validé #'.$paymentId,'utilisateur_id'=>$userId,'client_id'=>$data['client_id']]);
    }

    private function auditPayload(array $data): array { return ['client_id'=>$data['client_id'],'montant'=>$data['montant'],'mode_paiement'=>$data['mode_paiement'],'reference'=>$data['reference'] ?: null,'date_reglement'=>$data['date_reglement']]; }
}
