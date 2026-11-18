<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/PgCapturedLeadProofLab.php';

use PDO;
use Throwable;

/**
 * P43: read-only, locked, CI-only guard for a P42 company->tenant mapping
 * and the single consumed transport proof that created a specific event.
 * This class never initiates a transaction, never commits, never authorizes
 * production service access, and never handles real identities.
 */
final class PgCompanyDispatchGuardLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    public function mappingCurrent(string $company,string $tenant,int $revision):bool
    {
        if(!$this->db->inTransaction() || !self::company($company)
            ||!self::ref($tenant)||$revision<1) return false;
        try{
            $q=$this->db->prepare(<<<'SQL'
SELECT tenant_ref,active,revision FROM aia_p41_lab.company_tenant_links
WHERE company_id=CAST(:company AS uuid) FOR SHARE
SQL);
            $q->execute([':company'=>$company]);
            $r=$q->fetch(PDO::FETCH_ASSOC);
            return is_array($r)
                &&is_string($r['tenant_ref'])
                &&hash_equals($tenant,$r['tenant_ref'])
                &&in_array($r['active'],[true,1,'1','t'],true)
                &&(int)$r['revision']===$revision;
        }catch(Throwable){return false;}
    }

    public function eventMatches(string $company,string $tenant,
        string $event,string $payloadHash):bool
    {
        if(!$this->db->inTransaction()
            ||!self::company($company)||!self::ref($tenant)
            ||!self::ref($event)
            ||preg_match('/\A[a-f0-9]{64}\z/D',$payloadHash)!==1){
            return false;
        }
        try{
            $job=$this->db->prepare('SELECT canonical_event,event_hash FROM public.aia_lab_n8n_outbox_p6 '
                .'WHERE tenant_ref=:tenant AND event_ref=:event FOR SHARE');
            $job->execute([':tenant'=>$tenant,':event'=>$event]);$row=$job->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||!hash_equals($row['event_hash'],$payloadHash)
                ||!hash_equals(hash('sha256',$row['canonical_event']),$payloadHash))return false;
            $task=LabAutomationTaskContract::decode($row['canonical_event']);
            if($task===null||$task['tenant_ref']!==$tenant||$task['event_ref']!==$event)return false;
            $proof=new PgCapturedLeadProofLab($this->db);
            if(LabAutomationTaskContract::isLead($row['canonical_event']))
                return $proof->stagedMatches($company,$tenant,$row['canonical_event'],$payloadHash);
            if($proof->hasCapturedProof($tenant,$event))return false;
            $q=$this->db->prepare(<<<'SQL'
SELECT payload_hash FROM aia_p42_lab.consumed_transport_proofs
WHERE company_id=CAST(:company AS uuid)
 AND tenant_ref=:tenant AND event_ref=:event FOR SHARE
SQL);
            $q->execute([':company'=>$company,':tenant'=>$tenant,':event'=>$event]);
            $r=$q->fetchColumn();
            return is_string($r) && hash_equals($r,$payloadHash);
        }catch(Throwable){return false;}
    }

    public static function validContext(?string $company,?int $revision):bool
    {
        return ($company===null && $revision===null)
            ||($company!==null && $revision!==null
                &&self::company($company)&&$revision>=1);
    }

    private static function company(string $s):bool
    {
        return preg_match(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$s
        )===1;
    }

    private static function ref(string $s):bool
    {
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }
}
